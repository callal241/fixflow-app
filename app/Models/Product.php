<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /**
     * @use HasFactory<\Database\Factories\ProductFactory>
     */
    use BelongsToBusiness, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'sku',
        'barcode',
        'description',
        'condition',
        'price',
        'cost',
        'stock',
        'reorder_level',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'float',
        'cost' => 'float',
        'stock' => 'integer',
        'reorder_level' => 'integer',
        'is_active' => 'boolean',
    ];

    // RELATIONS ///////////////////////////////////////////////////////////////////////////////////

    /**
     * Get the category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // SCOPES //////////////////////////////////////////////////////////////////////////////////////

    /**
     * Scope to active products only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to products at or below their reorder level (low stock).
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'reorder_level');
    }

    /**
     * Scope to out-of-stock products.
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', 0);
    }

    /**
     * Scope to in-stock products.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Scope to products sold by this category (optionally including children).
     */
    public function scopeInCategory(Builder $query, Category $category, bool $includeChildren = true): Builder
    {
        if ($includeChildren) {
            $ids = Category::where('parent_id', $category->id)
                ->pluck('id')
                ->prepend($category->id);
        } else {
            $ids = collect([$category->id]);
        }

        return $query->whereIn('category_id', $ids);
    }

    // METHODS /////////////////////////////////////////////////////////////////////////////////////

    /**
     * Determine if the product is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Determine if the product is at or below its reorder level.
     */
    public function isLowStock(): bool
    {
        return $this->stock <= $this->reorder_level;
    }

    /**
     * Add stock (a positive or negative delta, e.g. a restock or a sale).
     */
    public function adjustStock(int $delta): self
    {
        return $this->forceFill([
            'stock' => max(0, $this->stock + $delta),
        ]);
    }

    /**
     * The product margin (price - cost).
     */
    public function getMarginAttribute(): float
    {
        return ($this->price ?? 0) - ($this->cost ?? 0);
    }
}
