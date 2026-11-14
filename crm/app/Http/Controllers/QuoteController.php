<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Services\WhatsApp\Client;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Quotations: product lines with discount and GST, a PDF, and sending it to the client on WhatsApp. */
class QuoteController extends Controller
{
    public const LINES = 10;

    private function visible(User $me)
    {
        return Quote::whereIn('lead_id', Lead::visibleTo($me)->select('id'));
    }

    public function index(Request $request)
    {
        return view('quotes.index', ['quotes' => $this->visible($request->user())->with(['lead:id,firm_name,city', 'user:id,name'])->latest('id')->paginate(30)]);
    }

    public function create(Request $request)
    {
        $me = $request->user();
        $lead = $request->filled('lead') ? Lead::visibleTo($me)->findOrFail((int) $request->lead) : null;

        return view('quotes.form', [
            'lead' => $lead,
            'leads' => Lead::visibleTo($me)->whereNotIn('status', ['lost'])->orderBy('firm_name')->limit(1000)->get(['id', 'firm_name', 'city']),
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'terms' => "50% advance, balance before dispatch.\nDelivery: 21–28 days after artwork approval.\nCOA with every batch. Prices are ex-factory unless stated.",
        ]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'lead_id' => ['required', 'integer'],
            'valid_days' => ['required', 'integer', 'min:1', 'max:180'],
            'freight' => ['nullable', 'numeric', 'min:0'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'max:'.self::LINES],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0', 'max:28'],
        ]);
        $lead = Lead::visibleTo($me)->findOrFail((int) $data['lead_id']);
        $lines = [];
        foreach ($data['items'] as $i => $row) {
            if (empty($row['qty']) || (empty($row['product_id']) && blank($row['description'] ?? null))) {
                continue; // empty line
            }
            $p = ! empty($row['product_id']) ? Product::find((int) $row['product_id']) : null;
            $rate = isset($row['rate']) && $row['rate'] !== '' ? (float) $row['rate'] : (float) ($p?->rate ?? 0);
            $disc = (float) ($row['discount_percent'] ?? 0);
            $gst = isset($row['gst_percent']) && $row['gst_percent'] !== '' ? (float) $row['gst_percent'] : (float) ($p?->gst_percent ?? 5);
            $amount = round($row['qty'] * $rate * (1 - $disc / 100), 2);
            $lines[] = ['product_id' => $p?->id, 'description' => trim(($row['description'] ?? '') ?: ($p ? $p->name.($p->composition ? ' ('.$p->composition.')' : '') : '')),
                'packing' => $p?->packing, 'hsn' => $p?->hsn, 'qty' => (int) $row['qty'], 'rate' => $rate, 'discount_percent' => $disc, 'gst_percent' => $gst, 'amount' => $amount];
        }
        if (! $lines) {
            return back()->withInput()->withErrors(['items' => __('Add at least one product line with a quantity.')]);
        }
        $subtotal = round(array_sum(array_column($lines, 'amount')), 2);
        $gstTotal = round(array_sum(array_map(fn ($l) => $l['amount'] * $l['gst_percent'] / 100, $lines)), 2);
        $freight = round((float) ($data['freight'] ?? 0), 2);

        $quote = DB::transaction(function () use ($lead, $me, $data, $lines, $subtotal, $gstTotal, $freight) {
            $q = Quote::create(['number' => Quote::nextNumber(), 'lead_id' => $lead->id, 'user_id' => $me->id, 'quote_date' => today(),
                'valid_days' => $data['valid_days'], 'freight' => $freight, 'terms' => $data['terms'] ?? null, 'status' => 'draft',
                'subtotal' => $subtotal, 'gst_total' => $gstTotal, 'grand_total' => round($subtotal + $gstTotal + $freight, 2)]);
            $q->items()->createMany($lines);

            return $q;
        });
        if ($lead->status === 'new' || $lead->status === 'contacted' || $lead->status === 'interested') {
            $lead->update(['status' => 'quote_sent']);
        }
        AuditLog::record('quote.create', $quote->number);

        return redirect()->route('quotes.show', $quote)->with('ok', __('Quotation :n created.', ['n' => $quote->number]));
    }

    public function show(Request $request, Quote $quote)
    {
        abort_unless($this->visible($request->user())->whereKey($quote->id)->exists(), 404);

        return view('quotes.show', ['quote' => $quote->load(['items', 'lead', 'user'])]);
    }

    public function pdf(Request $request, Quote $quote)
    {
        abort_unless($this->visible($request->user())->whereKey($quote->id)->exists(), 404);
        AuditLog::record('quote.pdf', $quote->number);

        return response($this->renderPdf($quote), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$quote->number.'.pdf"']);
    }

    public function status(Request $request, Quote $quote)
    {
        abort_unless($this->visible($request->user())->whereKey($quote->id)->exists(), 404);
        $quote->update($request->validate(['status' => ['required', Rule::in(array_keys(Quote::STATUSES))]]));

        return back()->with('ok', __('Saved.'));
    }

    /** Sends the PDF from the salesperson's own WhatsApp number (or the company number). */
    public function send(Request $request, Quote $quote, Client $client)
    {
        $me = $request->user();
        abort_unless($this->visible($me)->whereKey($quote->id)->exists(), 404);
        $quote->load('lead');
        $to = Client::toWaId($quote->lead->phone);
        if (! $to) {
            return back()->withErrors(['send' => __('This client has no phone number.')]);
        }
        $number = WhatsappNumber::where('active', true)->where('user_id', $quote->lead->owner_id)->first() ?? WhatsappNumber::where('active', true)->whereNull('user_id')->first();
        if (! $number) {
            return back()->withErrors(['send' => __('No WhatsApp number is set up for this salesperson or the company.')]);
        }
        $conv = WaConversation::where('whatsapp_number_id', $number->id)->where('wa_id_hash', Lead::hashPhone($to))->first();
        if (! $conv?->canReplyFreely()) {
            return back()->withErrors(['send' => __('WhatsApp allows a document only within 24 hours of the client\'s last message. Download the PDF and share it, or ask the client to message you first.')]);
        }
        $path = tempnam(sys_get_temp_dir(), 'qt');
        file_put_contents($path, $this->renderPdf($quote));
        $res = $client->sendDocument($number, $to, $path, $quote->number.'.pdf', __('Quotation :n', ['n' => $quote->number]));
        @unlink($path);
        WaMessage::create(['wa_conversation_id' => $conv->id, 'direction' => 'out', 'body' => 'PDF: '.$quote->number, 'type' => 'document', 'wamid' => $res['id'],
            'status' => $res['ok'] ? 'sent' : 'failed', 'error' => $res['error'], 'user_id' => $me->id, 'sent_at' => now()]);
        if ($res['ok']) {
            $quote->update(['status' => 'sent']);
            $conv->forceFill(['last_message_at' => now()])->save();
        }
        AuditLog::record('quote.send', $quote->number, ['ok' => $res['ok']]);

        return $res['ok'] ? back()->with('ok', __('Quotation sent on WhatsApp.')) : back()->withErrors(['send' => __('Not sent: :e', ['e' => $res['error']])]);
    }

    public function renderPdf(Quote $quote): string
    {
        $quote->loadMissing(['items', 'lead', 'user']);
        $c = CompanySetting::current();
        $logo = null;
        if ($c->logo_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($c->logo_path)) {
            $file = \Illuminate\Support\Facades\Storage::disk('local')->path($c->logo_path);
            $logo = 'data:'.mime_content_type($file).';base64,'.base64_encode(file_get_contents($file));
        }
        $opt = new Options;
        $opt->set('isRemoteEnabled', false); // never fetch anything from the internet
        $opt->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($opt);
        $pdf->loadHtml(view('quotes.pdf', ['q' => $quote, 'c' => $c, 'logo' => $logo])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
