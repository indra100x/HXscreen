<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_logs';

    protected $fillable = [
        'busniss_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'meta',
        'ip',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a dashboard action. Never throws: auditing must not break
     * the action being audited.
     */
    public static function record(
        ?object $user,
        string $action,
        ?string $businessId = null,
        object|string|null $subject = null,
        array $meta = [],
        ?string $ip = null,
    ): void {
        try {
            $type = null;
            $id = null;

            if (is_object($subject)) {
                $type = class_basename($subject);
                $id = isset($subject->id) ? (string) $subject->id : null;
            } elseif (is_string($subject)) {
                $id = $subject;
            }

            static::create([
                'busniss_id' => $businessId,
                'user_id' => $user?->id,
                'action' => $action,
                'subject_type' => $type,
                'subject_id' => $id,
                'meta' => $meta === [] ? null : $meta,
                'ip' => $ip,
            ]);
        } catch (\Throwable) {
            // Auditing is best-effort by design.
        }
    }
}
