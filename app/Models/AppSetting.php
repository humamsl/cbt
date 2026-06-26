<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $guarded = ['id'];

    /** Get value with fallback */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('appset.'.$key, function () use ($key, $default) {
            $row = static::where('key', $key)->first();
            if (! $row) return $default;
            return $row->type === 'json' ? json_decode((string) $row->value, true) : $row->value;
        });
    }

    /** Set value & clear cache */
    public static function set(string $key, mixed $value, string $type = 'text', string $group = 'umum', ?string $label = null): self
    {
        if ($type === 'json' && is_array($value)) {
            $value = json_encode($value);
        }
        $row = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group, 'label' => $label]
        );
        Cache::forget('appset.'.$key);
        return $row;
    }

    /** Forget cache (panggil setelah upload file) */
    public static function flush(string $key = null): void
    {
        if ($key) {
            Cache::forget('appset.'.$key);
            return;
        }
        foreach (static::pluck('key') as $k) Cache::forget('appset.'.$k);
    }
}
