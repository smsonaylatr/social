<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'key',
        'value',
        'type',
        'group',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        $type = 'string';
        if (is_bool($value)) {
            $type = 'boolean';
            $value = $value ? '1' : '0';
        } elseif (is_int($value)) {
            $type = 'integer';
            $value = (string) $value;
        } elseif (is_array($value) || is_object($value)) {
            $type = 'json';
            $value = json_encode($value);
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'type' => $type, 'group' => $group]
        );
    }
}
