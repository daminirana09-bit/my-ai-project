<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file for the whole team: price list, catalogue, visualiser, COA, product photo. */
class ProductFile extends Model
{
    /** Allowed types: extension => what it is. HTML, SVG and programs are never accepted. */
    public const TYPES = [
        'pdf' => 'PDF', 'xlsx' => 'Excel', 'xls' => 'Excel', 'csv' => 'CSV', 'ods' => 'Spreadsheet', 'doc' => 'Word', 'docx' => 'Word',
        'ppt' => 'PowerPoint', 'pptx' => 'PowerPoint', 'txt' => 'Text', 'jpg' => 'Photo', 'jpeg' => 'Photo', 'png' => 'Photo', 'webp' => 'Photo',
    ];

    /** Types WhatsApp accepts, and how it shows them. */
    public const WHATSAPP = [
        'pdf' => ['document', 'application/pdf'], 'doc' => ['document', 'application/msword'],
        'docx' => ['document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['document', 'application/vnd.ms-excel'], 'xlsx' => ['document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['document', 'application/vnd.ms-powerpoint'], 'pptx' => ['document', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'txt' => ['document', 'text/plain'], 'csv' => ['document', 'text/plain'], 'jpg' => ['image', 'image/jpeg'], 'jpeg' => ['image', 'image/jpeg'], 'png' => ['image', 'image/png'],
    ];

    /** Shown in the browser instead of downloaded. */
    public const INLINE = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public const SPREADSHEETS = ['xlsx', 'csv', 'ods'];

    public const MAX_KB = 25600; // 25 MB

    protected $fillable = ['title', 'category', 'path', 'original_name', 'extension', 'mime', 'size', 'version', 'uploaded_by', 'share_token', 'share_expires_at', 'downloads'];

    protected function casts(): array
    {
        return ['share_expires_at' => 'datetime'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function kind(): string
    {
        return self::TYPES[$this->extension] ?? strtoupper($this->extension);
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }

    public function shareActive(): bool
    {
        return $this->share_token !== null && $this->share_expires_at?->isFuture();
    }

    /** File name for downloads: the title plus the real extension, nothing from outside. */
    public function downloadName(): string
    {
        $base = trim(preg_replace('/[^\pL\pN ._\-()]+/u', '', $this->title) ?? '') ?: 'file';

        return mb_substr($base, 0, 100).'.'.$this->extension;
    }
}
