<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One enquiry received from a platform; the unique (platform, external_id) stops duplicates. */
class LeadInbound extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['platform', 'external_id', 'lead_id', 'result'];
}
