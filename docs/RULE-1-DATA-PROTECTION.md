# Rule 1: client data must never leak, not even by mistake

> **Hinglish mein:** Client ka data (parties, rates, orders, messages, documents) client ke server se bahar nahi jaayega, aur galti se bhi kisi galat insaan tak nahi pahunchega. Har feature, har library aur har release ko pehle is rule par check kiya jaayega. Agar koi feature is rule ko todta hai, to wo feature nahi banega.

This rule beats every other requirement, including convenience, cost and speed.
No system can promise zero risk. Someone can always photograph a screen. The goal is:
nothing leaks by design, nothing leaks by accident, and every access can be traced.

---

## 1. Data stays on the client's own server

| Item | Rule |
|---|---|
| Database, files, PDFs, backups | Only on the client's server, or on backup storage the client controls. |
| Translation (22 Indian languages) | Runs **on the client's server** (AI4Bharat IndicTrans2, open source). No external translation API. |
| Suggested replies | Built from intent detection plus pre-written replies in every language, filled with that client's CRM data, all on the server. |
| External AI (Gemini, OpenAI, Anthropic, Bhashini…) | **Off by default and not built into the first release.** If ever added, it must be opt-in by the client's owner, with a written warning, and names, phone numbers and prices are stripped out before anything is sent. |
| Fonts, scripts, icons | Served from the client's server. No CDN, no Google Fonts: even these reveal who is using the app. |
| Analytics, error tracking, telemetry | None to any outside service, including to us (the vendor). Logs stay on the server. |
| Software updates | The server pulls updates. No client data is ever pushed to us. |

**The only unavoidable outside party is WhatsApp (Meta).** A WhatsApp message to a client
passes through Meta, because that is how WhatsApp works. Meta's on-premises API option has
been retired, so the Cloud API is the only route. The software sends Meta only the message
itself. Internal notes, margins and other clients' data never go into a WhatsApp message.

## 2. Network: only one small door is open

- Only the path `/webhook/whatsapp` is reachable from the internet, over HTTPS with the
  server's own certificate (Let's Encrypt). There is **no Cloudflare Tunnel or other proxy**,
  because such a proxy would decrypt and see the messages.
- The firewall accepts that path only from Meta's published IP ranges.
- Every incoming webhook must carry Meta's valid `X-Hub-Signature-256`, checked with the
  app secret. Anything else is dropped and logged.
- The webhook can only **deliver** messages into the system. It can never read data out.
- The CRM screens are **not on the public internet**. They open only from the office network,
  through the company's own VPN (WireGuard, self-hosted), or from allow-listed IP addresses.
- The database port is never exposed. It listens only on the server itself.

## 3. Access: everyone sees only what their job needs

- Every user logs in with a password plus an OTP (2-step login). Sessions time out when idle.
- A **sales rep sees only their own clients and chats.** Margins, cost prices and other
  reps' clients are hidden from them.
- **No bulk export for reps.** An Excel/PDF export of client lists needs the owner's
  approval, carries the user's name as a watermark, and is logged.
- **WhatsApp numbers belong to the company, not the person.** When a rep leaves, the owner
  disables the login in one click and the number with its full chat history passes to
  another rep. The departing rep takes nothing.
- Every view, export, download and settings change is written to an audit log that users
  cannot edit or delete.

## 4. Stopping mistakes ("galti se" leaks)

| Mistake | Guard in the software |
|---|---|
| Sending client A's quote, rate or COA to client B | Every document is tied to one client. The send button only offers that client's own number. Sending it anywhere else is blocked. |
| AI or a template mentions another client | Suggestions and auto-replies are built only from the data of the client in that chat. Tests check this on every release. |
| Campaign shows recipients to each other | Each campaign message goes out separately. The sender sees the recipient count and a preview, and must confirm. |
| Pasting the wrong text into a chat | The preview shows exactly what the client will receive, in the client's language, before it is sent. |
| Phone numbers or messages in log files | Logs mask phone numbers and never store message text. |
| Screenshots of sensitive screens | Rate lists and margin screens show the viewer's name as a watermark. |
| Test or demo data mixed with real data | Production data is never copied to test or development machines. |
| Vendor (us) looking at data | Support access only with the client owner's time-limited approval, and it is logged. Without approval we have no access. |

## 5. Encryption and secrets

- HTTPS on every connection. Old TLS versions are switched off.
- Server disks are encrypted. Phone numbers, rates and bank details are also encrypted
  inside the database, field by field.
- Passwords are stored only as strong hashes (Argon2id or bcrypt).
- Keys and passwords live in the server's environment settings, never in the code and
  never in Git.
- Backups are encrypted before they leave the database and are kept in a place the client
  controls.

## 6. How we build and release

- Every release runs automated **data-isolation tests**: rep A cannot open rep B's client;
  chat X can never show data of client Y; a quote can only be sent to its own client.
- Before each release: review of new libraries, a dependency vulnerability check, and a
  security review of the changes.
- Library versions are pinned. Nothing is pulled in at run time from the internet.

## 7. Law (India)

- Under the **Digital Personal Data Protection Act, 2023**, the client company is the
  *Data Fiduciary* and we are the *Data Processor*. We sign a data processing agreement
  with each client.
- A written plan for a data breach: who is told, within what time, and what steps follow.
  Actual legal wording should be checked by a lawyer.

---

## What this rule changes in earlier plans

| Earlier idea | Now |
|---|---|
| Cloudflare Tunnel | **Dropped.** Cloudflare would see the messages. Direct HTTPS plus firewall instead. |
| Gemini free tier | **Not used.** Under its free-tier terms, Google may use the data to improve its products. |
| Bhashini (government API) | **Not used by default**, because data would leave the client's server. |
| Live AI translation in the demo | Demo only. The real software translates on the client's own server. |

## Hosting and editions

The software is sold to many pharma companies. **Each company gets its own separate
installation on its own hosting, with its own database.** No two companies ever share a
server, a database or a login, so one company's data can never reach another company.

| | Standard edition | Pro edition |
|---|---|---|
| Hosting | Shared hosting (e.g. Hostinger: PHP + MySQL + cron) | VPS or the company's own server, about 4–8 GB RAM, root access |
| CRM, WhatsApp inbox, absence auto-reply, quotes, orders, reminders | Yes | Yes |
| Client's language detected, request type detected (order, rate, payment, sample, COA, complaint…) | Yes, on the server | Yes, on the server |
| Suggested replies in the client's language | Yes: pre-written replies in all 22 languages, filled with that client's data | Yes |
| Word-for-word translation of any free-text message | **No.** The rep sees the original message plus the detected request type. | Yes: IndicTrans2 on the company's own server |
| CRM screens closed to the public internet | IP allow-list in `.htaccess` | Firewall + self-hosted VPN |

**Extra care on shared hosting.** Other customers' websites run on the same machine and the
host's staff can reach it. So on shared hosting:
- phone numbers, rates, bank details and message text are encrypted inside the database,
  field by field;
- the encryption key sits outside the public web folder and never in Git;
- login uses an authenticator-app code (TOTP), which works offline and costs nothing;
- backups are encrypted before they are downloaded.

A VPS is still the safer choice, and the Pro edition needs one.

## Licence and updates

- A licence check sends only the licence key and a hash of the domain to the vendor's
  licence server. **Never any client data.**
- Updates are downloaded by the installation itself. Nothing is uploaded.
