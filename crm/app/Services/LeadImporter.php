<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads leads from an Excel (.xlsx) or CSV file. A Google Sheet is imported by
 * downloading it as .xlsx or .csv first: the sheet never has to be made public.
 */
class LeadImporter
{
    public const MAX_ROWS = 5000;

    /** Column names people actually use, mapped to our fields. */
    private const ALIASES = [
        'firm_name' => ['firm', 'firm name', 'company', 'company name', 'party', 'party name', 'client', 'client name', 'business', 'business name', 'name of firm'],
        'contact_name' => ['contact', 'contact name', 'contact person', 'person', 'name', 'owner name'],
        'phone' => ['phone', 'mobile', 'mobile no', 'mobile number', 'phone no', 'phone number', 'contact no', 'contact number', 'whatsapp', 'whatsapp no', 'number'],
        'email' => ['email', 'e-mail', 'email id', 'mail'],
        'city' => ['city', 'town', 'location'],
        'state' => ['state'],
        'gstin' => ['gst', 'gstin', 'gst no', 'gst number'],
        'source' => ['source', 'lead source'],
        'client_type' => ['type', 'client type', 'category', 'party type'],
        'requirement' => ['requirement', 'product', 'products', 'need', 'enquiry', 'inquiry', 'query'],
        'status' => ['status', 'lead status', 'stage'],
        'expected_value' => ['value', 'amount', 'deal value', 'expected value', 'order value'],
        'next_follow_up_on' => ['follow up', 'follow-up', 'next follow up', 'follow up date', 'next follow-up'],
        'notes' => ['notes', 'note', 'remark', 'remarks', 'comment', 'comments'],
        'owner' => ['salesperson', 'sales person', 'assigned to', 'rep', 'sales rep', 'executive'],
    ];

    public function __construct(private ClashDetector $clashes) {}

    /**
     * @return array{created:int, already_yours:int, clashes:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path, string $extension, User $by, ?User $assignTo = null): array
    {
        $result = ['created' => 0, 'already_yours' => 0, 'clashes' => 0, 'skipped' => 0, 'errors' => []];
        $rows = $this->readRows($path, $extension);
        $header = null;
        $seen = [];
        $assignable = User::active()->visibleTo($by)->get();

        foreach ($rows as $i => $cells) {
            $line = $i + 1;
            if ($header === null) {
                $header = $this->mapHeader($cells);
                if (! isset($header['firm_name']) && ! isset($header['phone'])) {
                    $result['errors'][$line] = __('Header row needs at least a "Firm name" or "Phone" column.');

                    return $result;
                }
                continue;
            }
            if ($line > self::MAX_ROWS + 1) {
                $result['errors'][$line] = __('Only the first :n rows were imported.', ['n' => self::MAX_ROWS]);
                break;
            }

            $data = [];
            foreach ($header as $field => $col) {
                $v = $cells[$col] ?? null;
                $data[$field] = is_string($v) ? trim($v) : $v;
            }
            if (blank($data['firm_name'] ?? null) && blank($data['phone'] ?? null) && blank($data['contact_name'] ?? null)) {
                $result['skipped']++;
                continue;
            }

            $owner = $this->ownerFor($by, $data['owner'] ?? null, $assignTo, $assignable);
            if (! $owner) {
                $result['errors'][$line] = __('Salesperson ":name" not found or not in your team.', ['name' => $data['owner']]);
                continue;
            }

            $key = Lead::hashPhone($data['phone'] ?? null) ?? (filled($data['gstin'] ?? null) ? 'g:'.strtoupper($data['gstin']) : null);
            if ($key && isset($seen[$key])) {
                $result['skipped']++; // same client twice in this file
                continue;
            }
            if ($key) {
                $seen[$key] = true;
            }

            $existing = $this->clashes->findExisting($data['phone'] ?? null, $data['gstin'] ?? null);
            if ($existing) {
                if ((int) $existing->owner_id === $owner->id) {
                    $result['already_yours']++;
                } elseif ($existing->owner_id === null) {
                    $existing->update(['owner_id' => $owner->id]);
                    $result['created']++;
                } else {
                    $this->clashes->report($existing, $owner, 'imported_again', __('File row :n', ['n' => $line]));
                    $result['clashes']++;
                }
                continue;
            }

            Lead::create([
                'firm_name' => ($data['firm_name'] ?? null) ?: (($data['contact_name'] ?? null) ?: (string) ($data['phone'] ?? '')),
                'contact_name' => $data['contact_name'] ?? null,
                'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
                'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'gstin' => $data['gstin'] ?? null,
                'source' => ($data['source'] ?? null) ?: 'Import',
                'client_type' => $data['client_type'] ?? null,
                'requirement' => $data['requirement'] ?? null,
                'status' => $this->status($data['status'] ?? null),
                'expected_value' => is_numeric(str_replace([',', '₹'], '', (string) ($data['expected_value'] ?? ''))) ? str_replace([',', '₹'], '', (string) $data['expected_value']) : null,
                'next_follow_up_on' => $this->date($data['next_follow_up_on'] ?? null),
                'notes' => $data['notes'] ?? null,
                'owner_id' => $owner->id,
                'created_by' => $by->id,
                'created_via' => 'import',
            ]);
            $result['created']++;
        }

        return $result;
    }

    /** @return iterable<int, array<int, mixed>> */
    private function readRows(string $path, string $extension): iterable
    {
        $reader = strtolower($extension) === 'xlsx' ? new XlsxReader : new CsvReader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $row->toArray());
                if (count($rows) > self::MAX_ROWS + 2) {
                    break;
                }
            }
            break; // first sheet only
        }
        $reader->close();

        return $rows;
    }

    /** @return array<string,int> field => column index */
    private function mapHeader(array $cells): array
    {
        $map = [];
        foreach ($cells as $i => $label) {
            $l = strtolower(trim(preg_replace('/[^a-z0-9\- ]+/i', ' ', (string) $label)));
            $l = preg_replace('/\s+/', ' ', $l);
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($l, $aliases, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    private function ownerFor(User $by, ?string $named, ?User $assignTo, $assignable): ?User
    {
        if ($by->isRep()) {
            return $by; // a rep's import always belongs to that rep
        }
        if (filled($named)) {
            $n = strtolower(trim($named));

            return $assignable->first(fn (User $u) => strtolower($u->email) === $n || strtolower($u->name) === $n);
        }

        return $assignTo ?? $by;
    }

    private function status(?string $value): string
    {
        $v = strtolower(trim((string) $value));
        foreach (Lead::STATUSES as $key => $label) {
            if ($v === $key || $v === strtolower($label)) {
                return $key;
            }
        }

        return 'new';
    }

    private function date($value): ?string
    {
        if (blank($value)) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y'] as $f) {
            $d = \DateTime::createFromFormat('!'.$f, (string) $value);
            if ($d) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
