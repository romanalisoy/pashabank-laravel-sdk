<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Romanalisoy\PashaBank\Support\Amount;

/**
 * Persisted snapshot of a single PashaBank transaction. The table name and
 * this model's class are both overridable via config — subclass and set
 * 'pashabank.persistence.models.transaction' to your own class if your
 * naming rules don't allow the "pasha" prefix.
 *
 * @property int $id
 * @property string $merchant_key
 * @property string $transaction_id
 * @property string $command
 * @property string $status
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $result_code
 * @property string|null $approval_code
 * @property string|null $rrn
 * @property string|null $card_mask
 * @property string|null $three_ds_status
 * @property string|null $client_ip
 * @property string|null $description
 * @property string|null $payable_type
 * @property int|string|null $payable_id
 * @property int|null $recurring_id
 * @property string|null $parent_transaction_id
 * @property array<string, mixed>|null $meta
 *
 * @method static Builder<static> query()
 */
class PashaTransaction extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_PENDING = 'pending';

    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_REFUNDED = 'refunded';

    protected $guarded = [];

    protected $casts = [
        'amount_minor' => 'integer',
        'meta' => 'array',
    ];

    public function getTable(): string
    {
        return $this->table
            ?? config('pashabank.persistence.tables.transactions', 'pashabank_transactions');
    }

    /** Decimal view of amount_minor. Read-only accessor. */
    public function getAmountAttribute(): float
    {
        return Amount::toDecimal((int) $this->amount_minor);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recurring(): BelongsTo
    {
        /** @var class-string<PashaRecurring> $model */
        $model = config('pashabank.persistence.models.recurring', PashaRecurring::class);

        return $this->belongsTo($model, 'recurring_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_transaction_id', 'transaction_id');
    }

    public function markOk(?string $approvalCode, ?string $rrn, ?string $cardMask, ?string $resultCode, ?string $threeDs): void
    {
        $this->forceFill([
            'status' => self::STATUS_OK,
            'approval_code' => $approvalCode,
            'rrn' => $rrn,
            'card_mask' => $cardMask,
            'result_code' => $resultCode,
            'three_ds_status' => $threeDs,
        ])->save();
    }

    public function markFailed(?string $resultCode): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'result_code' => $resultCode,
        ])->save();
    }
}
