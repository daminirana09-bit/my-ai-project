<?php

namespace App\Services\WhatsApp;

use App\Models\CompanySetting;
use App\Models\ReplyTemplate;
use App\Models\User;
use App\Models\WaConversation;
use Illuminate\Support\Collection;

/** Ready replies in the client's language, with the meaning in the salesperson's own language. */
class ReplySuggester
{
    /** Language to fall back to when no reply exists in the client's own language. */
    private function chain(string $lang): array
    {
        return array_values(array_unique([$lang, in_array($lang, ['mr', 'ne', 'hing'], true) ? 'hi' : $lang, 'hi', 'en']));
    }

    public function template(string $intent, string $lang): ?ReplyTemplate
    {
        foreach ($this->chain($lang) as $l) {
            $t = ReplyTemplate::where('intent', $intent)->where('language', $l)->where('active', true)->first();
            if ($t) {
                return $t;
            }
        }

        return null;
    }

    public function vars(WaConversation $c, ?User $rep = null, ?string $back = null): array
    {
        $rep ??= $c->number?->user;

        return [
            'name' => $c->lead?->contact_name ?: ($c->contact_name ?: 'Sir/Madam'),
            'company' => CompanySetting::current()?->company_name,
            'rep' => $rep?->calledAs() ?? CompanySetting::current()?->company_name,
            'back' => $back ?? '',
        ];
    }

    /**
     * Suggestions for the latest client message: the matching reply first, then a few useful others.
     * @return Collection<int, object{intent:string, language:string, text:string, meaning:string}>
     */
    public function suggest(WaConversation $c, string $viewerLocale): Collection
    {
        $lang = $c->language ?: 'en';
        $intents = collect([$c->last_intent ?: 'other', 'greeting', 'price', 'order_status', 'new_order'])->unique()->take(4);
        $vars = $this->vars($c);
        $meaningLang = in_array($viewerLocale, ['en', 'hing', 'hi'], true) ? $viewerLocale : 'en';

        return $intents->map(function ($intent) use ($lang, $vars, $meaningLang) {
            $t = $this->template($intent, $lang);
            if (! $t) {
                return null;
            }
            $m = $this->template($intent, $meaningLang);

            return (object) [
                'intent' => $intent,
                'language' => $t->language,
                'text' => ReplyTemplate::render($t->body, $vars),
                'meaning' => $m && $m->language !== $t->language ? ReplyTemplate::render($m->body, $vars) : null,
            ];
        })->filter()->values();
    }
}
