<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a new audit log entry conveniently from anywhere.
     */
    public static function record(
        string $action,
        mixed $auditable = null,
        ?array $newValues = null,
        ?array $oldValues = null,
        ?int $userId = null,
        ?string $ipAddress = null
    ): self {
        $userId = $userId ?? auth()->id();
        $ipAddress = $ipAddress ?? (request()?->ip() ?? '127.0.0.1');

        $auditableType = null;
        $auditableId = null;

        if ($auditable instanceof Model) {
            $auditableType = get_class($auditable);
            $auditableId = $auditable->getKey();
        } elseif (is_string($auditable)) {
            $auditableType = $auditable;
        }

        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
        ]);
    }
}
