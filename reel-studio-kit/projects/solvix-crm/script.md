# solvix-crm — promo for "Pharma Sales CRM by Solvix Web"

Main feature first: no language barrier between client and sales team. Then 6 more fixes. Every feature named in
the voice is in the CRM (branch claude/zen-feynman-pkptd6, crm/README.md and the code it points to):
- WhatsApp inbox: the client's language (LanguageDetector, 17 scripts/languages) and request (IntentDetector) are
  detected; ready replies are offered in that language with their meaning in the rep's own app language
  (ReplySuggester). Ready replies exist in 14 languages: English, Hinglish, Hindi, Marathi, Gujarati, Punjabi,
  Bengali, Assamese, Odia, Tamil, Telugu, Kannada, Malayalam, Urdu.
- The app itself runs in English, Hindi or Hinglish, per user (Profile -> Language -> "Show the app in").
- Away cover replies in the client's language; urgent messages alert backup + managers.

How the screens were made (reproducible, scratchpad scripts):
- CRM run locally with SQLite and `php artisan db:seed --class=DemoSeeder` (demo company, demo users, no real data).
- Client messages in Tamil, Malayalam, Gujarati, Bengali, Telugu, English and Hinglish pushed through the CRM's own
  Inbound service; the CRM detected every language and request itself. Only the send to Meta was stubbed (no
  WhatsApp account locally). Rep Amit was set "at work" and the night-time auto-reply automations switched off
  (they are normal CRM settings) so the chats show the rep's view.
- Playwright, 430x932 mobile @2x, logged in as the rep: inbox, the Tamil chat with the app in English, Hindi and
  Hinglish (switched on the real profile page), tap "Bhejein" -> reply sent in Tamil. Other screens as before
  (leads, follow-ups, lead clash, away cover, GST quotation + PDF, targets, attendance, analytics, admin vs rep).

Voice: George (free premade), 10% faster; the "sales person ... English, Hindi ya Hinglish" line was voiced
separately and spliced in after line 3, then long pauses trimmed and everything sped up 2.5% to stay under 60 s.
Music/SFX: Mixkit free licences.
