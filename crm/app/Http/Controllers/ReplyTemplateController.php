<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ReplyTemplate;
use App\Services\WhatsApp\LanguageDetector;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ready replies per request type and language; admin and managers fix wording or add languages. */
class ReplyTemplateController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->query('lang', 'hi');
        $langs = LanguageDetector::NAMES;
        abort_unless(isset($langs[$lang]), 404);

        return view('reply-templates', [
            'lang' => $lang, 'langs' => $langs,
            'rows' => ReplyTemplate::where('language', $lang)->get()->keyBy('intent'),
            'counts' => ReplyTemplate::selectRaw('language, COUNT(*) as n')->groupBy('language')->pluck('n', 'language'),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(array_keys(LanguageDetector::NAMES))],
            'body' => ['array'],
            'body.*' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach ($data['body'] ?? [] as $intent => $text) {
            if (! isset(ReplyTemplate::INTENTS[$intent])) {
                continue;
            }
            if (blank($text)) {
                ReplyTemplate::where('intent', $intent)->where('language', $data['language'])->delete();
            } else {
                ReplyTemplate::updateOrCreate(['intent' => $intent, 'language' => $data['language']], ['body' => trim($text), 'active' => true]);
            }
        }
        AuditLog::record('reply_templates.save', $data['language']);

        return back()->with('ok', __('Ready replies saved.'));
    }
}
