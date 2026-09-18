<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Billable;
use App\Models\Concerns\HasApproval;
use App\Models\Concerns\HasProgress;
use App\Models\Concerns\HasStatus;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    /**
     * @use HasFactory<\Database\Factories\OrderFactory>
     * @use HasStatus<\App\Enums\OrderStatus>
     */
    use BelongsToBusiness, Billable, HasApproval, HasFactory, HasProgress, HasStatus;

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'quantity' => 1,
        'status' => OrderStatus::New,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'product_id',
        'url',
        'supplier',
        'quantity',
        'price',
        'cost',
        'is_billable',
        'is_purchase',
        'status',
        'approved_at',
        'received_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'cost' => 'float',
        'is_purchase' => 'boolean',
        'received_at' => 'datetime',
    ];

    // ACCESSORS ///////////////////////////////////////////////////////////////////////////////////

    // RELATIONS ///////////////////////////////////////////////////////////////////////////////////

    /**
     * Get the ticket that the order belongs to.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the catalog product this order line is for (null for free-form parts).
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // SCOPES //////////////////////////////////////////////////////////////////////////////////////

    // METHODS /////////////////////////////////////////////////////////////////////////////////////
}
