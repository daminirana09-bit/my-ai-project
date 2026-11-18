<?php

namespace App\Models\Concerns;

/**
 * Date-only columns are stored as "2026-11-20" in every database (SQLite would otherwise keep
 * "2026-11-20 00:00:00"), so comparisons and unique checks behave the same everywhere.
 */
trait PlainDates
{
    public function setAttribute($key, $value)
    {
        parent::setAttribute($key, $value);
        if (in_array($key, $this->plainDates, true) && isset($this->attributes[$key]) && is_string($this->attributes[$key])) {
            $this->attributes[$key] = substr($this->attributes[$key], 0, 10);
        }

        return $this;
    }
}
