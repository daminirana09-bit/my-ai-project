<?php

namespace App\Services\Leads;

/**
 * Reads new mail from the lead mailbox (Hostinger email, Gmail with an app password…).
 * Read-only: nothing is marked read, moved or deleted, so people can keep using the mailbox.
 */
class ImapMailbox
{
    public const MAX_PER_RUN = 50;

    /** Only for a local test server with a self-made certificate; real mailboxes are always fully checked. */
    protected string $extraFlags = '';

    /**
     * @return array{uidvalidity: int, last_uid: int, messages: array<int, string>} raw messages by UID, oldest first
     *
     * @throws PullFailed
     */
    public function fetchNew(string $host, int $port, string $user, string $password, int $uidValidity, int $lastUid): array
    {
        if (! function_exists('imap_open')) {
            throw new PullFailed('This hosting has no PHP IMAP extension, so the mailbox cannot be read. Ask the hosting to enable "imap" for PHP 8.3.');
        }
        $flags = $port === 143 ? '/imap/tls' : '/imap/ssl';
        $box = '{'.$host.':'.$port.$flags.$this->extraFlags.'}INBOX';
        imap_timeout(IMAP_OPENTIMEOUT, 20);
        imap_timeout(IMAP_READTIMEOUT, 30);
        $m = @imap_open($box, $user, $password, OP_READONLY, 1);
        if ($m === false) {
            $err = rtrim((string) (imap_last_error() ?: 'unknown error'), '. ');
            imap_errors();
            throw new PullFailed('Could not open the mailbox: '.mb_substr($err, 0, 200).'. Check the server, email address and password.');
        }
        try {
            $status = imap_status($m, $box, SA_UIDVALIDITY | SA_UIDNEXT);
            $validity = (int) ($status->uidvalidity ?? 0);
            if ($validity !== $uidValidity) {
                // First time (or the mailbox was rebuilt): start with the mail of the last 3 days.
                $uids = imap_search($m, 'SINCE "'.date('d-M-Y', strtotime('-3 days')).'"', SE_UID) ?: [];
            } else {
                $uids = $lastUid > 0 ? (imap_fetch_overview($m, ($lastUid + 1).':*', FT_UID) ?: []) : [];
                $uids = array_map(fn ($o) => (int) $o->uid, $uids);
            }
            $uids = array_values(array_filter(array_map('intval', $uids), fn ($u) => $validity !== $uidValidity || $u > $lastUid));
            sort($uids);
            $uids = array_slice($uids, 0, self::MAX_PER_RUN);
            $messages = [];
            foreach ($uids as $uid) {
                $messages[$uid] = imap_fetchheader($m, $uid, FT_UID).imap_body($m, $uid, FT_UID | FT_PEEK);
            }
            $last = $uids ? max($uids) : ($validity === $uidValidity ? $lastUid : max(0, (int) ($status->uidnext ?? 1) - 1));

            return ['uidvalidity' => $validity, 'last_uid' => $last, 'messages' => $messages];
        } finally {
            @imap_close($m);
            imap_errors();
            imap_alerts();
        }
    }
}
