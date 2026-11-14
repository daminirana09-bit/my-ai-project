<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ProductFile;
use App\Models\User;
use App\Notifications\CatalogueUpdated;
use App\Services\ProductImporter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Price lists, catalogues, visualisers, COAs: uploaded once, seen by the whole team at once.
 * Files live outside the public folder and are only given out through these routes.
 */
class CatalogueController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        return view('catalogue', [
            'files' => ProductFile::with('uploader:id,name')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%')->orWhere('category', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%')))
                ->latest('updated_at')->get(),
            'canEdit' => $request->user()->allows('edit_products'),
            'categories' => ProductFile::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeEdit($request);
        $data = $request->validate($this->rules() + [
            'title' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:60'],
            'tell_team' => ['nullable', 'boolean'],
        ], $this->messages());
        $file = $request->file('file');
        $pf = new ProductFile([
            'title' => trim((string) ($data['title'] ?? '')) ?: mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 150),
            'category' => trim((string) ($data['category'] ?? '')) ?: null,
            'uploaded_by' => $request->user()->id,
        ]);
        $this->keep($pf, $file);
        $pf->save();
        AuditLog::record('catalogue.upload', "file:{$pf->id}", ['title' => $pf->title]);
        if ($request->boolean('tell_team', true)) {
            $this->tellTeam($pf, $request->user(), false);
        }

        return back()->with('ok', __(':title uploaded. The whole team can see it now.', ['title' => $pf->title]));
    }

    /** New version of the same file: the share link and place in the list stay. */
    public function replace(Request $request, ProductFile $file)
    {
        $this->authorizeEdit($request);
        $request->validate($this->rules(), $this->messages());
        $old = $file->path;
        $this->keep($file, $request->file('file'));
        $file->version++;
        $file->uploaded_by = $request->user()->id;
        $file->save();
        Storage::disk('local')->delete($old);
        AuditLog::record('catalogue.replace', "file:{$file->id}", ['version' => $file->version]);
        $this->tellTeam($file, $request->user(), true);

        return back()->with('ok', __(':title updated to version :v for the whole team.', ['title' => $file->title, 'v' => $file->version]));
    }

    public function destroy(Request $request, ProductFile $file)
    {
        $this->authorizeEdit($request);
        Storage::disk('local')->delete($file->path);
        $file->delete();
        AuditLog::record('catalogue.delete', "file:{$file->id}", ['title' => $file->title]);

        return back()->with('ok', __('File deleted.'));
    }

    /** Download, or open in the browser for PDFs and photos. */
    public function download(Request $request, ProductFile $file)
    {
        return $this->send($file, $request->boolean('open'));
    }

    /** A link that works without logging in (for email or the salesperson's own WhatsApp), ending after some days. */
    public function share(Request $request, ProductFile $file)
    {
        $data = $request->validate(['days' => ['required', 'integer', 'in:1,7,30,90']]);
        if (! $file->shareActive()) {
            $file->share_token = Str::random(40);
        }
        $file->share_expires_at = now()->addDays((int) $data['days']);
        $file->save();
        AuditLog::record('catalogue.share', "file:{$file->id}", ['days' => (int) $data['days']]);

        return back()->with('ok', __('Share link ready, valid until :d.', ['d' => $file->share_expires_at->format('d M Y')]))->with('share_file', $file->id);
    }

    public function unshare(Request $request, ProductFile $file)
    {
        $this->authorizeEdit($request);
        $file->forceFill(['share_token' => null, 'share_expires_at' => null])->save();
        AuditLog::record('catalogue.unshare', "file:{$file->id}");

        return back()->with('ok', __('Share link stopped.'));
    }

    /** Public download by share link. */
    public function shared(string $token)
    {
        $file = ProductFile::where('share_token', $token)->first();
        if (! $file || ! $file->shareActive() || ! hash_equals((string) $file->share_token, $token)) {
            abort(404);
        }

        return $this->send($file, isset(ProductFile::INLINE[$file->extension]));
    }

    /** A spreadsheet in the library also fills the product list. */
    public function importProducts(Request $request, ProductFile $file, ProductImporter $importer)
    {
        $this->authorizeEdit($request);
        abort_unless(in_array($file->extension, ProductFile::SPREADSHEETS, true), 404);
        $r = $importer->import(Storage::disk('local')->path($file->path), $file->extension);
        AuditLog::record('product.import', "file:{$file->id}", $r);

        return redirect()->route('products.index')->with('ok', __('Products imported: :a added, :u updated.', ['a' => $r['added'], 'u' => $r['updated']]));
    }

    /** Sends a catalogue file to the client on WhatsApp (PDF, Office files, text and photos). */
    public function sendToLead(Request $request, \App\Models\Lead $lead, \App\Services\WhatsApp\LeadMessenger $messenger)
    {
        $me = $request->user();
        abort_unless($lead->isVisibleTo($me), 404);
        $file = ProductFile::findOrFail((int) $request->input('file_id'));
        [$kind, $mime] = ProductFile::WHATSAPP[$file->extension] ?? [null, null];
        if (! $kind) {
            return back()->withErrors(['share' => __('WhatsApp does not accept :type files. Use the share link instead.', ['type' => strtoupper($file->extension)])]);
        }
        if ($file->size > 16 * 1048576) {
            return back()->withErrors(['share' => __('WhatsApp accepts files up to 16 MB. Use the share link instead.')]);
        }
        $problem = $messenger->file($lead, Storage::disk('local')->path($file->path), $file->downloadName(), $mime, $kind, $file->title, $me);
        if ($problem) {
            return back()->withErrors(['share' => $problem]);
        }
        \App\Models\LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $me->id, 'type' => 'whatsapp', 'note' => 'Sent: '.$file->title, 'occurred_at' => now()]);

        return back()->with('ok', __(':title sent on WhatsApp.', ['title' => $file->title]));
    }

    private function send(ProductFile $file, bool $inline)
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->path), 404);
        $file->increment('downloads');
        $headers = ['Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff'];
        if ($inline && isset(ProductFile::INLINE[$file->extension])) {
            return response()->file($disk->path($file->path), $headers + ['Content-Type' => ProductFile::INLINE[$file->extension],
                'Content-Disposition' => 'inline; filename="'.Str::ascii($file->downloadName()).'"']);
        }

        return response()->download($disk->path($file->path), $file->downloadName(), $headers);
    }

    private function keep(ProductFile $pf, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $name = 'catalogue/'.Str::random(32).'.'.$ext;
        Storage::disk('local')->putFileAs('catalogue', $file, basename($name));
        $pf->fill([
            'path' => $name,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'extension' => $ext,
            'mime' => mb_substr((string) ($file->getMimeType() ?: 'application/octet-stream'), 0, 120),
            'size' => (int) $file->getSize(),
        ]);
    }

    private function rules(): array
    {
        $types = implode(',', array_keys(ProductFile::TYPES));

        return ['file' => ['required', 'file', 'max:'.ProductFile::MAX_KB, 'extensions:'.$types, 'mimes:'.$types]];
    }

    private function messages(): array
    {
        return ['file.extensions' => __('This type of file is not allowed. Use PDF, Excel, CSV, ODS, Word, PowerPoint, text or a photo.'),
            'file.mimes' => __('This type of file is not allowed. Use PDF, Excel, CSV, ODS, Word, PowerPoint, text or a photo.')];
    }

    private function tellTeam(ProductFile $pf, User $by, bool $updated): void
    {
        Notification::send(User::active()->where('id', '!=', $by->id)->get(), new CatalogueUpdated($pf, $by->name, $updated));
    }

    private function authorizeEdit(Request $request): void
    {
        abort_unless($request->user()->allows('edit_products'), 403);
    }
}
