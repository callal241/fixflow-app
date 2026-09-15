<?php

namespace App\Models\Concerns;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by a Business.
 *
 * Provides:
 *  - a business() relation,
 *  - a forBusiness() scope for read queries, and
 *  - automatic stamping of the acting user's business on create.
 *
 * The business_id column is nullable on purpose: a record without a business
 * remains valid (e.g. "shared" data or pre-scoping rows), so moving between
 * single-shop and true multi-tenant behaviour is a config/query change, not a
 * migration.
 */
trait BelongsToBusiness
{
    private const BUSINESS_ID = 'business_id';

    /**
     * Initialize the trait for an instance: stamp the owner business on create.
     */
    public function initializeBelongsToBusiness(): void
    {
        $this->creating(function ($model) {
            if ($model->{static::BUSINESS_ID} === null) {
                $businessId = auth()->id()
                    ? auth()->user()->business_id
                    : null;

                $model->{static::BUSINESS_ID} = $businessId;
            }
        });
    }

    /**
     * Determine the acting user's business id (null when unauthenticated / ownerless).
     */
    public function currentBusinessId(): ?int
    {
        return auth()->id() ? auth()->user()->business_id : null;
    }

    // RELATIONS ///////////////////////////////////////////////////////////////////////////////////

    /**
     * Get the business that owns the model.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, static::BUSINESS_ID);
    }

    // SCOPES //////////////////////////////////////////////////////////////////////////////////////

    /**
     * Scope a query to a single business.
     *
     * Passing a business id scopes to it; omitting it (or passing null) scopes
     * to the acting user's business. A user with no business is scoped to an
     * empty set (never unscoped), so multi-tenant data can never leak to an
     * ownerless account. Callers that genuinely need an unscoped query should
     * not use this scope.
     */
    public function scopeForBusiness(Builder $query, ?int $businessId = null): Builder
    {
        if ($businessId === null) {
            $businessId = $this->currentBusinessId();
        }

        return $businessId === null
            ? $query->where(static::BUSINESS_ID, -1)
            : $query->where(static::BUSINESS_ID, $businessId);
    }
}
