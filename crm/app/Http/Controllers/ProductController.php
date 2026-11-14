<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterOption;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\CompanySetting;
use App\Services\ProductImporter;
use App\Services\Sheets;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/** Product list with rates, packing and MOQ. Everyone can view; editing needs the permission. */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::search($request->q)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when(! $request->boolean('all'), fn ($q) => $q->where('active', true))
            ->orderBy('name')->paginate(40)->withQueryString();

        return view('products.index', ['products' => $products, 'categories' => MasterOption::values('product_category'), 'canEdit' => $request->user()->allows('edit_products')]);
    }

    public function create(Request $request)
    {
        $this->authorizeEdit($request);

        return view('products.form', ['product' => new Product(['active' => true, 'unit' => 'strip', 'gst_percent' => 5])]);
    }

    public function store(Request $request)
    {
        $this->authorizeEdit($request);
        $p = Product::create($this->validated($request));
        AuditLog::record('product.create', "product:{$p->id}");

        return redirect()->route('products.index')->with('ok', __('Product saved.'));
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorizeEdit($request);

        return view('products.form', ['product' => $product]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeEdit($request);
        $product->update($this->validated($request));
        AuditLog::record('product.update', "product:{$product->id}");

        return redirect()->route('products.index')->with('ok', __('Product saved.'));
    }

    /** Product list as Excel, CSV or ODS (needs edit rights), or as a PDF price list (anyone, to share with clients). */
    public function export(Request $request)
    {
        $format = (string) $request->query('format', 'xlsx');
        if ($format === 'pdf') {
            AuditLog::record('product.export', 'pdf');

            return response($this->priceListPdf(), 200, ['Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="price-list-'.now()->format('Y-m-d').'.pdf"']);
        }
        $this->authorizeEdit($request);
        $format = Sheets::format($format);
        $rows = Product::orderBy('name')->get()->map(fn ($p) => [$p->name, $p->composition, $p->form, $p->packing, $p->category,
            $p->rate !== null ? (float) $p->rate : null, $p->unit, $p->moq, $p->hsn, (float) $p->gst_percent, $p->active ? 'Yes' : 'No', $p->notes]);
        $path = Sheets::write($format, ProductImporter::HEADER, $rows);
        AuditLog::record('product.export', $format);

        return response()->download($path, 'products-'.now()->format('Y-m-d').'.'.$format)->deleteFileAfterSend();
    }

    /** Active products with rates, on the company letterhead. */
    public function priceListPdf(): string
    {
        $c = CompanySetting::current();
        $logo = null;
        if ($c->logo_path && Storage::disk('local')->exists($c->logo_path)) {
            $file = Storage::disk('local')->path($c->logo_path);
            $logo = 'data:'.mime_content_type($file).';base64,'.base64_encode(file_get_contents($file));
        }
        $opt = new Options;
        $opt->set('isRemoteEnabled', false); // never fetch anything from the internet
        $opt->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($opt);
        $products = Product::where('active', true)->orderBy('category')->orderBy('name')->get();
        $pdf->loadHtml(view('products.pdf', ['c' => $c, 'logo' => $logo, 'groups' => $products->groupBy(fn ($p) => $p->category ?: __('Other'))])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    /** Adds new products and updates existing ones with the same name and packing. */
    public function import(Request $request, ProductImporter $importer)
    {
        $this->authorizeEdit($request);
        $request->validate(['file' => ['required', 'file', 'max:10240', 'extensions:'.implode(',', Sheets::IMPORT)]],
            ['file.extensions' => __('Use an Excel (.xlsx), CSV or ODS file. An old .xls file: open it and save as .xlsx.')]);
        $file = $request->file('file');
        $r = $importer->import($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
        AuditLog::record('product.import', $file->getClientOriginalName(), $r);

        return redirect()->route('products.index')->with('ok', __('Products imported: :a added, :u updated.', ['a' => $r['added'], 'u' => $r['updated']]));
    }

    private function authorizeEdit(Request $request): void
    {
        abort_unless($request->user()->allows('edit_products'), 403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'composition' => ['nullable', 'string', 'max:255'],
            'form' => ['nullable', 'string', 'max:40'],
            'packing' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', 'string', 'max:60'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', Rule::in(['strip', 'bottle', 'box', 'piece', 'kg', 'litre'])],
            'moq' => ['nullable', 'integer', 'min:0'],
            'hsn' => ['nullable', 'string', 'max:12'],
            'gst_percent' => ['required', 'numeric', 'min:0', 'max:28'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
