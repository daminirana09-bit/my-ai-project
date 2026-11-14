<?php

namespace App\Services;

use App\Models\Product;

/**
 * Adds and updates products from a spreadsheet (Excel, CSV, ODS). A product with the same name
 * and packing is updated; others are added. Column names are matched loosely (Rate / Price / PTR…).
 */
class ProductImporter
{
    public const HEADER = ['Name', 'Composition', 'Form', 'Packing', 'Category', 'Rate', 'Unit', 'MOQ', 'HSN', 'GST %', 'Active', 'Notes'];

    /** @return array{added: int, updated: int} */
    public function import(string $path, string $extension): array
    {
        $rows = Sheets::rows($path, $extension, 20000);
        $header = null;
        $added = $updated = 0;
        foreach ($rows as $c) {
            if ($header === null) {
                if (! array_filter($c, fn ($v) => trim((string) $v) !== '')) {
                    continue; // blank lines above the header
                }
                $header = array_map(fn ($h) => strtolower(trim(preg_replace('/\s+/', ' ', (string) $h) ?? '')), $c);

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
            $name = $get(['name', 'product', 'product name', 'brand', 'brand name', 'item', 'item name']);
            if (! $name) {
                continue;
            }
            $num = fn ($v) => $v !== null && is_numeric(str_replace([',', '₹', 'rs.', 'rs', ' '], '', strtolower($v))) ? str_replace([',', '₹', 'rs.', 'rs', ' '], '', strtolower($v)) : null;
            $has = fn (array $names) => (bool) array_intersect($names, $header);
            $columns = [
                'composition' => [['composition', 'salt', 'generic', 'generic name', 'molecule'], fn ($v) => $this->cut($v)],
                'form' => [['form', 'dosage form', 'type'], fn ($v) => $this->cut($v, 40)],
                'category' => [['category', 'therapy', 'segment', 'division'], fn ($v) => $this->cut($v, 60)],
                'rate' => [['rate', 'price', 'ptr', 'pts', 'mrp'], $num],
                'unit' => [['unit'], fn ($v) => $this->cut($v, 20) ?? 'strip'],
                'moq' => [['moq', 'minimum order', 'min order'], fn ($v) => ($m = $num($v)) !== null ? (int) round((float) $m) : null],
                'hsn' => [['hsn', 'hsn code'], fn ($v) => $this->cut($v, 12)],
                'gst_percent' => [['gst %', 'gst', 'gst percent', 'gst%'], fn ($v) => $num($v) ?? 5],
                'active' => [['active'], fn ($v) => ! in_array(strtolower((string) $v), ['no', '0', 'false'], true)],
                'notes' => [['notes', 'remarks', 'remark'], fn ($v) => $this->cut($v, 1000)],
            ];
            $packing = $this->cut($get(['packing', 'pack', 'pack size', 'packing size']), 60);
            $p = Product::firstOrNew(['name' => mb_substr($name, 0, 255), 'packing' => $packing]);
            $data = [];
            foreach ($columns as $field => [$names, $convert]) {
                // An existing product keeps what the file has no column for (a rate-only sheet changes only rates).
                if (! $p->exists || $has($names)) {
                    $data[$field] = $convert($get($names));
                }
            }
            $p->exists ? $updated++ : $added++;
            $p->fill($data)->save();
        }

        return ['added' => $added, 'updated' => $updated];
    }

    private function cut(?string $v, int $max = 255): ?string
    {
        return $v === null ? null : mb_substr($v, 0, $max);
    }
}
