<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription Audit Log Model
 *
 * Tracks all subscription state changes for compliance, debugging,
 * and audit trail purposes. Every state transition creates a log entry.
 *
 * @property int $id
 * @property int $subscription_id
 * @property int $user_id
 * @property string $event_type
 * @property string|null $old_status
 * @property string|null $new_status
 * @property array|null $metadata
 * @property string|null $triggered_by
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Subscription $subscription
 * @property-read User $user
 */
class SubscriptionAuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'subscription_audit_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'subscription_id',
        'user_id',
        'event_type',
        'old_status',
        'new_status',
        'metadata',
        'triggered_by',
        'request_id',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the subscription that was modified.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the user who triggered this change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Filter by subscription ID.
     */
    public function scopeForSubscription($query, int $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    /**
     * Scope: Filter by user ID.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by event type.
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope: Filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Order by occurrence date.
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('occurred_at');
    }

    /**
     * Create a new audit log entry.
     */
    public static function logEvent(
        Subscription $subscription,
        string $eventType,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        array $metadata = [],
        ?string $triggeredBy = null,
    ): self {
        return self::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'event_type' => $eventType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'metadata' => $metadata,
            'triggered_by' => $triggeredBy ?? 'system',
            'request_id' => request()->header('X-Request-ID'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
