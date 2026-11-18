<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** TA/DA and other expenses of field staff, approved by the manager and paid with the salary. */
class ExpenseClaim extends Model
{
    use Concerns\PlainDates;

    protected array $plainDates = ['date'];

    public const KINDS = ['da' => 'Daily allowance (DA)', 'travel' => 'Travel (km)', 'fare' => 'Bus / train / taxi fare', 'hotel' => 'Hotel', 'food' => 'Food', 'phone' => 'Phone / internet', 'other' => 'Other'];

    public const STATUSES = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'paid' => 'Paid with salary'];

    public const DA_AREAS = ['hq' => 'Headquarter (HQ)', 'exhq' => 'Ex-HQ', 'outstation' => 'Outstation'];

    public const VEHICLES = ['bike' => 'Two-wheeler', 'car' => 'Car'];

    /** Receipts: photos and PDF only. */
    public const RECEIPTS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

    protected $fillable = ['user_id', 'date', 'kind', 'detail', 'from_place', 'to_place', 'km', 'amount', 'note', 'receipt_path', 'receipt_mime', 'status',
        'decided_by', 'decided_at', 'decision_note', 'payslip_id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'km' => 'decimal:1', 'amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
