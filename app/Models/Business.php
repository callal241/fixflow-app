<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'trade',
        'email',
        'phone',
        'website',
        'tax_number',
        'address',
        'city',
        'state',
        'zip_code',
        'currency',
        'tax_rate',
        'payment_provider_id',
        'logo_path',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tax_rate' => 'decimal:2',
    ];

    // RELATIONS ///////////////////////////////////////////////////////////////////////////////////

    /**
     * Get the users (staff) belonging to this business.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the admin user for this business.
     */
    public function admin(): HasOne
    {
        return $this->hasOne(User::class)->where('role', \App\Enums\UserRole::Admin);
    }

    // ACCESSORS ///////////////////////////////////////////////////////////////////////////////////

    /**
     * Display name including the trade, when present.
     */
    public function getDisplayAttribute(): string
    {
        return $this->trade ? "{$this->name} · {$this->trade}" : $this->name;
    }
}
