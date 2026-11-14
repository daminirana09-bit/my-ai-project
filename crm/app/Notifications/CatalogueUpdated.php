<?php

namespace App\Notifications;

use App\Models\ProductFile;
use Illuminate\Notifications\Notification;

/** A new or updated price list / catalogue for the whole team. */
class CatalogueUpdated extends Notification
{
    public function __construct(public ProductFile $file, public string $by, public bool $updated) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'catalogue', 'file_id' => $this->file->id, 'title' => $this->file->title, 'by' => $this->by, 'updated' => $this->updated, 'version' => $this->file->version];
    }
}
