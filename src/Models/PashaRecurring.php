<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The persisted template for a recurring payment. Required for
 * PashaBank::recurring()->execute($billerClientId) to work.
 *
 * @property int $id
 * @property string $merchant_key
 * @property string $biller_client_id
 * @property string $expiry
 * @property string|null $card_mask
 * @property string $status
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property array<string, mixed>|null $meta
 *
 * @method static Builder<static> query()
 */
class PashaRecurring extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_EXPIRED = 'expired';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function getTable(): string
    {
        return $this->table
            ?? config('pashabank.persistence.tables.recurring', 'pashabank_recurring');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        /** @var class-string<PashaTransaction> $model */
        $model = config('pashabank.persistence.models.transaction', PashaTransaction::class);

        return $this->hasMany($model, 'recurring_id');
    }

    public function markActive(?string $cardMask = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_ACTIVE,
            'card_mask' => $cardMask ?? $this->card_mask,
        ])->save();
    }

    public function markDeleted(): void
    {
        $this->forceFill(['status' => self::STATUS_DELETED])->save();
    }
}
