<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterOption;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;

/** Product list with rates, packing and MOQ. Everyone can view; editing needs the permission. */
class ProductController extends Controller
{
    private const HEADER = ['Name', 'Composition', 'Form', 'Packing', 'Category', 'Rate', 'Unit', 'MOQ', 'HSN', 'GST %', 'Active', 'Notes'];

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

    public function export(Request $request)
    {
        $this->authorizeEdit($request);
        $path = tempnam(sys_get_temp_dir(), 'prd');
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(self::HEADER));
        Product::orderBy('name')->chunk(500, function ($chunk) use ($w) {
            foreach ($chunk as $p) {
                $w->addRow(Row::fromValues([$p->name, $p->composition, $p->form, $p->packing, $p->category, $p->rate !== null ? (float) $p->rate : null, $p->unit, $p->moq, $p->hsn, (float) $p->gst_percent, $p->active ? 'Yes' : 'No', $p->notes]));
            }
        });
        $w->close();
        AuditLog::record('product.export');

        return response()->download($path, 'products-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    /** Adds new products and updates existing ones with the same name and packing. */
    public function import(Request $request)
    {
        $this->authorizeEdit($request);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt']]);
        $file = $request->file('file');
        $reader = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? new XlsxReader : new CsvReader;
        $reader->open($file->getRealPath());
        $header = null;
        $added = $updated = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $c = $row->toArray();
                if ($header === null) {
                    $header = array_map(fn ($h) => strtolower(trim((string) $h)), $c);
                    continue;
                }
                $get = function (array $names) use ($header, $c) {
                    foreach ($names as $n) {
                        $i = array_search($n, $header, true);
                        if ($i !== false && isset($c[$i]) && trim((string) $c[$i]) !== '') {
                            return trim((string) $c[$i]);
                        }
                    }

                    return null;
                };
                $name = $get(['name', 'product', 'product name', 'brand']);
                if (! $name) {
                    continue;
                }
                $num = fn ($v) => $v !== null && is_numeric(str_replace([',', '₹'], '', $v)) ? str_replace([',', '₹'], '', $v) : null;
                $data = [
                    'composition' => $get(['composition', 'salt', 'generic']),
                    'form' => $get(['form', 'dosage form', 'type']),
                    'category' => $get(['category', 'therapy', 'segment']),
                    'rate' => $num($get(['rate', 'price', 'ptr'])),
                    'unit' => $get(['unit']) ?? 'strip',
                    'moq' => $num($get(['moq', 'minimum order'])),
                    'hsn' => $get(['hsn', 'hsn code']),
                    'gst_percent' => $num($get(['gst %', 'gst', 'gst percent'])) ?? 5,
                    'active' => ! in_array(strtolower((string) $get(['active'])), ['no', '0', 'false'], true),
                    'notes' => $get(['notes', 'remarks']),
                ];
                $packing = $get(['packing', 'pack', 'pack size']);
                $p = Product::firstOrNew(['name' => mb_substr($name, 0, 255), 'packing' => $packing]);
                $p->exists ? $updated++ : $added++;
                $p->fill($data)->save();
            }
            break;
        }
        $reader->close();
        AuditLog::record('product.import', $file->getClientOriginalName(), ['added' => $added, 'updated' => $updated]);

        return redirect()->route('products.index')->with('ok', __('Products imported: :a added, :u updated.', ['a' => $added, 'u' => $updated]));
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
