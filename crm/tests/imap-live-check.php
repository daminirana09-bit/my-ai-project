<?php
// Local check against a real IMAP server (Dovecot). Not part of the release.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/Feature/CrmTestCase.php';
require __DIR__.'/Feature/EmailLeadsTest.php';

$save = function (string $raw) { $p = proc_open(['doveadm', 'save', '-u', 'leads@dermain.test'], [0 => ['pipe', 'r']], $pipes); fwrite($pipes[0], $raw); fclose($pipes[0]); return proc_close($p); };
exec('doveadm expunge -u leads@dermain.test mailbox INBOX all');
$save(Tests\Feature\EmailLeadsTest::indiamartMail('<live-1@indiamart.com>'));
$save("From: Neha <neha@example.in>\nSubject: Franchise\nMessage-ID: <live-2@x>\nContent-Type: text/plain\n\nInterested in PCD. 9988776655\n");
$save("From: Offers <offers@shop.example>\nList-Unsubscribe: <x>\nSubject: Sale\nContent-Type: text/plain\n\nbuy now\n");

$box = new class extends App\Services\Leads\ImapMailbox { protected string $extraFlags = '/novalidate-cert'; };
$parser = app(App\Services\Leads\EmailLeadParser::class);
$r = $box->fetchNew('127.0.0.1', 993, 'leads@dermain.test', 'box-pass-1', 0, 0);
echo "run 1: uidvalidity={$r['uidvalidity']} last_uid={$r['last_uid']} messages=".count($r['messages'])."\n";
foreach ($r['messages'] as $uid => $raw) { [$d, $why] = $parser->parse(App\Services\Leads\MimeMessage::parse($raw), 'leads@dermain.test'); echo "  uid $uid: ".($d ? "LEAD {$d->source} | {$d->name} | {$d->phone} | {$d->email}" : "skipped ($why)")."\n"; }
exec('doveadm fetch -u leads@dermain.test flags mailbox INBOX all', $flags); echo "  flags after reading (must stay unread): ".json_encode(array_values(array_filter($flags, fn ($l) => str_starts_with($l, 'flags'))))."\n";
$r2 = $box->fetchNew('127.0.0.1', 993, 'leads@dermain.test', 'box-pass-1', $r['uidvalidity'], $r['last_uid']);
echo "run 2 (nothing new): messages=".count($r2['messages'])." last_uid={$r2['last_uid']}\n";
$save("From: Sunil <sunil@example.in>\nSubject: Need rate\nMessage-ID: <live-4@x>\nContent-Type: text/plain\n\nMobile: 9811100011\n");
$r3 = $box->fetchNew('127.0.0.1', 993, 'leads@dermain.test', 'box-pass-1', $r2['uidvalidity'], $r2['last_uid']);
echo "run 3 (one new): messages=".count($r3['messages'])." uids=".implode(',', array_keys($r3['messages']))."\n";
try { $box->fetchNew('127.0.0.1', 993, 'leads@dermain.test', 'wrong', 0, 0); } catch (App\Services\Leads\PullFailed $e) { echo "wrong password: ".$e->getMessage()."\n"; }
$real = new App\Services\Leads\ImapMailbox;
try { $real->fetchNew('127.0.0.1', 993, 'leads@dermain.test', 'box-pass-1', 0, 0); echo "self-signed accepted?! BAD\n"; } catch (App\Services\Leads\PullFailed $e) { echo "real reader refuses a bad certificate: OK\n"; }
