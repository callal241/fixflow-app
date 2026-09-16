<?php

namespace App\Models;

use App\Enums\ChecklistPhase;
use App\Enums\ChecklistStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line on a ticket's repair checklist.
 *
 * Pre-repair items document the device's condition before work begins;
 * post-repair items are the quality-control sign-off before the ticket can
 * be resolved. Checklist items are documentation, not billable work, so they
 * never affect invoice totals.
 */
class ChecklistItem extends Model
{
    /**
     * @use HasFactory<\Database\Factories\ChecklistItemFactory>
     * @use HasStatus<\App\Enums\ChecklistStatus>
     */
    use BelongsToBusiness, HasFactory, HasStatus;

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'phase' => ChecklistPhase::PreRepair,
        'status' => ChecklistStatus::Pending,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'label',
        'note',
        'phase',
        'status',
        'checked_by_id',
        'checked_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'phase' => ChecklistPhase::class,
        'checked_at' => 'datetime',
    ];

    // ACCESSORS ///////////////////////////////////////////////////////////////////////////////////

    // RELATIONS ///////////////////////////////////////////////////////////////////////////////////

    /**
     * Get the ticket the checklist item belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who last checked the item.
     */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    // SCOPES //////////////////////////////////////////////////////////////////////////////////////

    // METHODS /////////////////////////////////////////////////////////////////////////////////////
}
