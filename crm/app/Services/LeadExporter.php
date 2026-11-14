<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class LeadExporter
{
    public const HEADER = ['Firm name', 'Contact name', 'Phone', 'Email', 'City', 'State', 'GSTIN', 'Source', 'Client type', 'Requirement', 'Status', 'Temperature', 'Expected value', 'Salesperson', 'Next follow-up', 'Last contact', 'Created', 'Notes', 'Tags', 'Campaign', 'Referred by'];

    /** Writes the leads to a temporary file and returns its path. */
    public function write(Builder $leads, string $format): string
    {
        $path = tempnam(sys_get_temp_dir(), 'leads');
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADER));

        $leads->with('owner:id,name')->orderBy('id')->chunk(500, function ($chunk) use ($writer) {
            foreach ($chunk as $l) {
                $writer->addRow(Row::fromValues([
                    $l->firm_name, $l->contact_name, $l->phone, $l->email, $l->city, $l->state, $l->gstin,
                    $l->source, $l->client_type, $l->requirement, Lead::STATUSES[$l->status] ?? $l->status, Lead::TEMPERATURES[$l->temperature] ?? '',
                    $l->expected_value !== null ? (float) $l->expected_value : null, $l->owner?->name,
                    $l->next_follow_up_on?->format('Y-m-d'), $l->last_contact_at?->format('Y-m-d H:i'),
                    $l->created_at?->format('Y-m-d'), $l->notes, implode(', ', $l->tags ?? []), $l->campaign, $l->referred_by,
                ]));
            }
        });
        $writer->close();

        return $path;
    }
}
