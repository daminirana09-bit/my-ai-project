<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Offer letter, ID proofs and the like. Kept outside the website folder; admins only. */
class HrDocument extends Model
{
    public const TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    protected $fillable = ['user_id', 'title', 'path', 'extension', 'size', 'uploaded_by'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
