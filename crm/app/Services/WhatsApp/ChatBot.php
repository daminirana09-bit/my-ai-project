<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\ProductController;
use App\Models\BotAnswer;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Task;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Notifications\ChatAssigned;
use App\Services\Translation\TranslationFailed;
use App\Services\Translation\Translator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The WhatsApp chatbot: answers instantly, in the client's language, only from the company's own
 * data (questions & answers written by the admin, the product list, the price list). It never
 * invents a rate or a promise. Orders, samples, documents and anything it cannot answer go to
 * the salesperson as a task, and "talk to a person" hands the chat over at once.
 */
class ChatBot
{
    public const DEFAULTS = [
        'enabled' => false,
        'mode' => 'always',          // always | away (only when the salesperson is away / after hours)
        'welcome' => 'Welcome to {company}. How can we help you today?',
        'share_rates' => true,       // product answers include the rate
        'send_price_list' => true,   // "price" gets the PDF price list
        'ask_details' => true,       // a new client is asked for firm name and city
        'human_pause_hours' => 12,   // after a person replies, the bot stays quiet this long
    ];

    public const MODES = ['always' => 'Always, at once', 'away' => 'Only when the salesperson is away or after office hours'];

    /** Reply buttons. Titles are translated; WhatsApp allows 20 characters. */
    private const MENU = ['bot_price' => 'Price list', 'bot_order' => 'Place an order', 'bot_human' => 'Talk to a person'];

    private const HUMAN_WORDS = ['talk to', 'speak to', 'call me', 'call back', 'callback', 'salesperson', 'sales person', 'executive', 'agent', 'human', 'real person',
        'baat karni', 'baat karna', 'baat karao', 'call karo', 'call kijiye', 'call karna', 'phone karo', 'फोन करो', 'कॉल करो', 'कॉल कीजिए', 'बात करनी', 'बात करवाइए'];

    private const GENERIC = ['tablet', 'tablets', 'tab', 'syrup', 'capsule', 'capsules', 'cap', 'injection', 'inj', 'cream', 'gel', 'drop', 'drops', 'ointment',
        'suspension', 'powder', 'sachet', 'lotion', 'soap', 'kit', 'plus', 'forte', 'with', 'and', 'for'];

    private const MAX_PER_HOUR = 8;

    private const QUANTITY = '/(?<![\pL\pN])\d+\s*(strips?|box(es)?|pcs|pieces?|bottles?|tabs?|vials?|packs?|cartons?|cases?|units?|kg|ltrs?|litres?|nos)(?![\pL])/iu';

    /** @var array<int, array{int,int,?string}> chats waiting for an answer after the webhook has replied */
    private static array $pending = [];

    private static ?int $hooked = null;

    private CompanySetting $co;

    private WaConversation $c;

    private ?User $rep = null;

    private ?string $back = null;

    private bool $away = false;

    public function __construct(private Client $client, private IntentDetector $intents, private LanguageDetector $langs) {}

    public static function config(string $key): mixed
    {
        $cfg = CompanySetting::current()?->bot_config ?? [];

        return array_key_exists($key, $cfg) && $cfg[$key] !== null && $cfg[$key] !== '' ? $cfg[$key] : self::DEFAULTS[$key];
    }

    public static function humanPauseHours(): int
    {
        return max(1, min(72, (int) self::config('human_pause_hours')));
    }

    /** Will the bot answer this chat now? */
    public static function willAnswer(WaConversation $c, bool $away): bool
    {
        return (bool) self::config('enabled') && $c->botMayReply() && (self::config('mode') === 'always' || $away);
    }

    /** Answer after WhatsApp has got its "200 OK", so Meta never waits for us. */
    public static function defer(int $conversationId, int $messageId, ?string $replyId): void
    {
        self::$pending[] = [$conversationId, $messageId, $replyId];
        $app = app();
        if (self::$hooked !== spl_object_id($app)) {
            self::$hooked = spl_object_id($app);
            $app->terminating(function () {
                while ($job = array_shift(self::$pending)) {
                    try {
                        app(self::class)->answer(...$job);
                    } catch (Throwable $e) {
                        Log::error('Chatbot failed', ['conversation' => $job[0], 'error' => $e->getMessage()]);
                    }
                }
            });
        }
    }

    public function answer(int $conversationId, int $messageId, ?string $replyId = null): void
    {
        $this->co = CompanySetting::current();
        $c = WaConversation::with(['number.user', 'lead.owner', 'assignee'])->find($conversationId);
        $msg = WaMessage::find($messageId);
        if (! $c || ! $msg || ! $this->client->configured()) {
            return;
        }
        $this->c = $c;
        $this->rep = $c->assignee ?? $c->number?->user ?? $c->lead?->owner;
        [$this->away, $this->back] = app(Inbound::class)->awayState($this->rep, $this->co);
        if (! self::willAnswer($c, $this->away) || $c->lead?->whatsapp_opt_out || ! $c->canReplyFreely()) {
            return;
        }
        if (WaMessage::where('wa_conversation_id', $c->id)->where('type', 'bot')->where('sent_at', '>=', now()->subHour())->count() >= self::MAX_PER_HOUR) {
            return; // never get into a loop with another bot
        }
        $text = trim((string) $msg->body);
        if ($text === '' || (str_starts_with($text, '[') && ! $replyId)) {
            return; // photos, voice notes…: a person looks at them
        }
        $english = $this->inEnglish($text, $msg->language ?: $c->language);
        $intent = $this->intents->detect($text);
        if ($intent === 'other' && $english !== $text) {
            $intent = $this->intents->detect($english);
        }
        // "100 strips …", "50 box …": a quantity means an order, even if it also says "delivery" or "rate".
        if (in_array($intent, ['other', 'order_status', 'price', 'greeting'], true) && preg_match(self::QUANTITY, $text.' '.$english)) {
            $intent = 'new_order';
        }

        // 1. A question the bot asked a new client
        if ($c->bot_step && ! $replyId && in_array($intent, ['other', 'greeting'], true) && $this->step($text)) {
            return;
        }
        $c->forceFill(['bot_step' => null])->save();

        // 2. Wants a person, or is unhappy: hand over at once
        if ($replyId === 'bot_human' || $this->hasAny(mb_strtolower($text.' '.$english), self::HUMAN_WORDS) || $intent === 'complaint') {
            $this->handOver($intent === 'complaint' ? 'complaint' : 'person', $text);

            return;
        }
        // 3. The company's own questions & answers
        if (! $replyId && ($a = $this->knowledge($text, $english))) {
            $a->increment('hits');
            $this->send($this->say($a->answer, true));

            return;
        }
        // 4. An order with details ("need 100 strips of …"); then a product by name
        if (! $replyId && $intent === 'new_order' && mb_strlen($text) >= 12) {
            $this->order($text);

            return;
        }
        if (! $replyId && ($products = $this->products($text.' '.$english))->isNotEmpty()) {
            $this->send($this->productCard($products));

            return;
        }
        if ($replyId === 'bot_price' || $intent === 'price') {
            $this->priceList();

            return;
        }
        if ($replyId === 'bot_order' || $intent === 'new_order') {
            $this->order($replyId ? null : $text);

            return;
        }
        if (in_array($intent, ['sample', 'documents', 'order_status', 'payment'], true)) {
            $this->task($intent, $text);
            $this->send($this->t('Thank you. :rep will get back to you on this:when.', $this->repVars()));

            return;
        }
        $firstTime = ! WaMessage::where('wa_conversation_id', $c->id)->where('type', 'bot')->exists();
        if (! $firstTime && $this->isThanks($text)) {
            return; // "ok", "thanks", 👍: nothing to answer
        }
        if ($intent === 'greeting' || $firstTime) {
            $this->welcome();

            return;
        }
        // 5. Not understood: say a person will reply (once in 12 hours), with the menu
        if (! WaMessage::where('wa_conversation_id', $c->id)->where('type', 'bot')->where('intent', 'fallback')->where('sent_at', '>=', now()->subHours(12))->exists()) {
            $c->forceFill(['waiting_callback' => true])->save();
            $this->send($this->t('Thank you for your message. :rep will reply to you shortly:when. Meanwhile you can choose:', $this->repVars()), self::MENU, 'fallback');
        }
    }

    private function welcome(): void
    {
        $lead = $this->c->lead;
        $name = $lead?->contact_name ?: $this->c->contact_name;
        $hello = $this->t('Namaste :name!', ['name' => $name ? ' '.$name : '']);
        $custom = trim((string) ($this->co->bot_config['welcome'] ?? ''));
        $welcome = $custom !== '' && $custom !== self::DEFAULTS['welcome']
            ? $this->say(strtr($custom, ['{company}' => (string) $this->co->company_name, '{name}' => (string) $name]), true)
            : $this->t('Welcome to :company. How can we help you today?', ['company' => (string) $this->co->company_name]);
        if ($this->away && $this->back) {
            $welcome .= ' '.$this->t(':rep is available from :time; the chatbot helps you till then.', ['rep' => $this->rep?->name ?: __('Our team', [], $this->uiLang()), 'time' => $this->back]);
        }
        $needsDetails = self::config('ask_details') && $lead && $lead->created_via === 'whatsapp' && (! $lead->city || $this->looksLikePhone($lead->firm_name));
        $this->send(str_replace('  ', ' ', $hello).' '.$welcome, self::MENU, 'welcome');
        if ($needsDetails) {
            $this->c->forceFill(['bot_step' => 'ask_firm'])->save();
            $this->send($this->t('To serve you better, please tell us your firm (shop / company) name.'), [], 'ask');
        }
    }

    /** Firm name, then city, for a new client. Returns false when the reply is not an answer. */
    private function step(string $text): bool
    {
        $lead = $this->c->lead;
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if (! $lead || mb_strlen($value) < 2 || mb_strlen($value) > 120 || str_word_count($value) > 12) {
            return false;
        }
        if ($this->c->bot_step === 'ask_firm') {
            $lead->forceFill(['firm_name' => mb_substr($value, 0, 120)])->save();
            $this->c->forceFill(['bot_step' => $lead->city ? null : 'ask_city'])->save();
            $this->send($lead->city ? $this->t('Thank you! How can we help you today?') : $this->t('Thank you! Which city are you in?'), $lead->city ? self::MENU : [], 'ask');

            return true;
        }
        if ($this->c->bot_step === 'ask_city') {
            $lead->forceFill(['city' => mb_substr($value, 0, 80)])->save();
            $this->c->forceFill(['bot_step' => null])->save();
            $this->send($this->t('Thank you! How can we help you today?'), self::MENU, 'ask');

            return true;
        }

        return false;
    }

    private function knowledge(string $text, string $english): ?BotAnswer
    {
        $hay = [mb_strtolower($text), mb_strtolower($english)];
        $best = null;
        $bestScore = 0;
        foreach (BotAnswer::where('active', true)->get() as $a) {
            $score = 0;
            foreach ($a->keywordList() as $k) {
                foreach ($hay as $h) {
                    if ($this->contains($h, $k)) {
                        $score += 10 + mb_strlen($k); // more and longer matching words win
                        break;
                    }
                }
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$a, $score];
            }
        }

        return $best;
    }

    /** Products named in the message: the full name first, else a distinctive first word. */
    private function products(string $text)
    {
        $t = ' '.mb_strtolower(preg_replace('/[^\pL\pM\pN]+/u', ' ', $text)).' ';
        $full = collect();
        $partial = collect();
        foreach (Product::where('active', true)->get(['id', 'name']) as $p) {
            $name = trim(mb_strtolower(preg_replace('/[^\pL\pM\pN]+/u', ' ', $p->name)));
            if ($name === '') {
                continue;
            }
            if (str_contains($t, ' '.$name.' ')) {
                $full->push($p->id);

                continue;
            }
            $first = explode(' ', $name)[0];
            if (mb_strlen($first) >= 4 && ! in_array($first, self::GENERIC, true) && ! is_numeric($first) && str_contains($t, ' '.$first.' ')) {
                $partial->push($p->id);
            }
        }
        $ids = ($full->isNotEmpty() ? $full : $partial)->take(3);

        return $ids->isEmpty() ? collect() : Product::whereIn('id', $ids)->orderBy('name')->get();
    }

    private function productCard($products): string
    {
        $lines = [];
        foreach ($products as $p) {
            $lines[] = '*'.$p->name.'*';
            foreach (['composition' => 'Composition', 'packing' => 'Packing', 'form' => 'Form'] as $f => $label) {
                if (filled($p->{$f})) {
                    $lines[] = $this->t($label).': '.$p->{$f};
                }
            }
            if (self::config('share_rates') && $p->rate !== null) {
                $lines[] = $this->t('Rate').': ₹'.number_format((float) $p->rate, 2).($p->unit ? ' / '.$p->unit : '').($p->gst_percent ? ' + '.rtrim(rtrim(number_format((float) $p->gst_percent, 2), '0'), '.').'% GST' : '');
            }
            if (filled($p->moq)) {
                $lines[] = $this->t('Minimum order').': '.$p->moq;
            }
            $lines[] = '';
        }
        $lines[] = $this->t('To order, send the product name and quantity. :rep will confirm.', $this->repVars());

        return implode("\n", $lines);
    }

    private function priceList(): void
    {
        if (! self::config('send_price_list') || ! Product::where('active', true)->exists()) {
            $this->task('price', $this->t('Price list asked on WhatsApp'));
            $this->send($this->t('Thank you. :rep will send you the rates shortly:when.', $this->repVars()));

            return;
        }
        $path = $this->priceListFile();
        $caption = $this->t('Our latest price list. Send the product name and quantity to order.');
        $res = $this->client->sendDocument($this->c->number, (string) $this->c->wa_id, $path, 'price-list.pdf', $caption);
        $this->record('PDF: price-list.pdf · '.$caption, $res, 'price');
        $this->c->lead && $this->c->lead->status === 'new' && $this->c->lead->forceFill(['status' => 'contacted'])->save();
    }

    /** The PDF price list, made again only when the product list changes. */
    private function priceListFile(): string
    {
        $stamp = md5(Product::where('active', true)->count().'|'.Product::max('updated_at').'|'.$this->co->updated_at);
        $rel = 'bot/price-list-'.$stamp.'.pdf';
        $disk = Storage::disk('local');
        if (! $disk->exists($rel)) {
            foreach ($disk->files('bot') as $old) {
                $disk->delete($old);
            }
            $disk->put($rel, app(ProductController::class)->priceListPdf());
        }

        return $disk->path($rel);
    }

    private function order(?string $details): void
    {
        $lead = $this->c->lead;
        if ($lead && in_array($lead->status, ['new', 'contacted'], true)) {
            $lead->forceFill(['status' => 'interested', 'temperature' => 'hot'])->save();
        }
        if ($details !== null && mb_strlen($details) >= 12) {
            $this->task('new_order', $details);
            $this->send($this->t('Thank you for your order. :rep will confirm the rates, quantity and delivery shortly:when.', $this->repVars()));

            return;
        }
        $this->c->forceFill(['waiting_callback' => true])->save();
        $this->send($this->t('Please send the product names and quantity. :rep will confirm the order:when.', $this->repVars()));
    }

    private function handOver(string $why, string $text): void
    {
        $this->c->forceFill(['bot_paused_until' => now()->addHours(self::humanPauseHours()), 'waiting_callback' => true, 'bot_step' => null])->save();
        $people = collect([$this->rep])->filter();
        if ($people->isEmpty()) {
            $people = User::active()->where('role', 'admin')->get();
        }
        Notification::send($people, new ChatAssigned($this->c, null, 'bot_handoff'));
        $this->send($why === 'complaint'
            ? $this->t('We are sorry for the trouble. :rep will call you about this as soon as possible:when.', $this->repVars())
            : $this->t('Sure. :rep will contact you shortly:when.', $this->repVars()), [], 'handoff');
    }

    private function task(string $intent, string $text): void
    {
        $owner = $this->rep ?? User::active()->where('role', 'admin')->orderBy('id')->first();
        if (! $owner) {
            return;
        }
        $labels = ['new_order' => 'Order', 'sample' => 'Sample asked', 'documents' => 'Documents asked', 'order_status' => 'Order status asked', 'payment' => 'Payment query', 'price' => 'Rates asked'];
        $firm = $this->c->lead?->firm_name ?? $this->c->contact_name ?? $this->c->maskedNumber();
        Task::create(['title' => mb_substr('WhatsApp: '.($labels[$intent] ?? 'Query').' – '.$firm, 0, 190), 'details' => mb_substr($text, 0, 2000),
            'assigned_to' => $owner->id, 'created_by' => null, 'lead_id' => $this->c->lead_id, 'due_on' => today(), 'priority' => 'high', 'status' => 'pending']);
        $this->c->forceFill(['waiting_callback' => true])->save();
    }

    private function repVars(): array
    {
        return ['rep' => $this->rep?->name ?: __('Our team', [], $this->uiLang()), 'when' => $this->away && $this->back ? ' ('.__('from :time', ['time' => $this->back], $this->uiLang()).')' : ''];
    }

    // ---------- language ----------

    private function lang(): string
    {
        return $this->c->language ?: 'en';
    }

    /** Hindi and Hinglish have the bot's sentences written by hand; English for the rest before translation. */
    private function uiLang(): string
    {
        return in_array($this->lang(), ['hi', 'hing'], true) ? $this->lang() : 'en';
    }

    /** One of the bot's own sentences, in the client's language. */
    private function t(string $key, array $replace = []): string
    {
        $text = __($key, $replace, $this->uiLang());

        return $this->uiLang() === 'en' && $this->lang() !== 'en' ? $this->say($text, false) : $text;
    }

    /** Text into the client's language. Falls back to the original when translation is off, fails, or changes a number. */
    private function say(string $text, bool $detect): string
    {
        $to = $this->lang();
        $from = $detect ? $this->langs->detect($text) : 'en';
        if ($from === $to || ($to === 'en' && $from === 'hing') || ($to === 'hing' && $from === 'en')) {
            return $text;
        }
        $tr = app(Translator::class);
        if (! $tr->enabled() && ! (in_array($from, ['hi', 'hing'], true) && in_array($to, ['hi', 'hing'], true))) {
            return $text;
        }
        try {
            $r = $tr->translate($text, $from, $to);

            return $r['warnings'] ? $text : $r['text'];
        } catch (TranslationFailed) {
            return $text;
        }
    }

    private function inEnglish(string $text, ?string $lang): string
    {
        if (in_array($lang, [null, '', 'en', 'hing'], true)) {
            return $text;
        }
        $tr = app(Translator::class);
        if (! $tr->enabled()) {
            return $text;
        }
        try {
            return $tr->translate($text, $lang, 'en')['text'];
        } catch (TranslationFailed) {
            return $text;
        }
    }

    // ---------- sending ----------

    private function send(string $text, array $buttons = [], ?string $kind = null): void
    {
        $to = (string) $this->c->wa_id;
        if ($buttons) {
            $titles = array_map(fn ($b) => $this->t($b), $buttons);
            $res = $this->client->sendButtons($this->c->number, $to, $text, $titles);
            $text .= "\n[".implode(' | ', $titles).']';
        } else {
            $res = $this->client->sendText($this->c->number, $to, $text);
        }
        $this->record($text, $res, $kind);
    }

    private function record(string $text, array $res, ?string $kind): void
    {
        WaMessage::create(['wa_conversation_id' => $this->c->id, 'direction' => 'out', 'body' => $text, 'type' => 'bot', 'wamid' => $res['id'],
            'status' => $res['ok'] ? 'sent' : 'failed', 'error' => $res['error'], 'language' => $this->lang(), 'intent' => $kind, 'sent_at' => now()]);
        $this->c->forceFill(['bot_replied_at' => now(), 'last_message_at' => now()])->save();
        if (! $res['ok']) {
            Log::warning('Chatbot reply failed', ['conversation' => $this->c->id, 'error' => $res['error']]);
        }
    }

    // ---------- helpers ----------

    private function hasAny(string $hay, array $words): bool
    {
        foreach ($words as $w) {
            if ($this->contains($hay, $w)) {
                return true;
            }
        }

        return false;
    }

    private function contains(string $hay, string $needle): bool
    {
        return preg_match('/^[a-z0-9 ]+$/', $needle)
            ? (bool) preg_match('/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/u', $hay)
            : str_contains($hay, $needle);
    }

    private function isThanks(string $text): bool
    {
        $t = trim(preg_replace('/[^\pL\pM\pN ]+/u', ' ', mb_strtolower($text)));
        if ($t === '') {
            return true; // only emoji or punctuation
        }

        return (bool) preg_match('/^((ok|okay|okk|k|thanks|thank you|thank u|thanku|thx|ty|done|fine|good|great|nice|sure|yes|ji|haan|ha|theek hai|thik hai|achha|accha|dhanyavaad|dhanyawad|shukriya|ठीक है|धन्यवाद|शुक्रिया|जी|हाँ|ओके)\s*){1,4}(sir|ji|madam|bhai)?$/u', $t);
    }

    private function looksLikePhone(?string $s): bool
    {
        return $s === null || (bool) preg_match('/^\+?[\d\s\-]{8,}$/', trim($s));
    }
}
