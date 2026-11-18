<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use Concerns\PlainDates;

    protected array $plainDates = ['date'];

    public const TYPES = ['national' => 'National holiday', 'festival' => 'Festival', 'optional' => 'Optional (paid if taken)'];

    protected $fillable = ['date', 'name', 'type'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    private static array $cache = [];

    /** Paid holiday for everyone (optional holidays are not days off for all). */
    public static function isHoliday(CarbonInterface|string $date): bool
    {
        $d = is_string($date) ? substr($date, 0, 10) : $date->toDateString();
        $year = substr($d, 0, 4);
        self::$cache[$year] ??= self::whereIn('type', ['national', 'festival'])->whereYear('date', (int) $year)->get()
            ->map(fn ($h) => $h->date->toDateString())->flip()->all();

        return isset(self::$cache[$year][$d]);
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forget());
        static::deleted(fn () => self::forget());
    }
}
