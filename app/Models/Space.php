<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'space_mode_id',
        'private_space_type_id',
        'shared_space_type_id',
        'title',
        'name',
        'slug',
        'short_description',
        'full_description',
        'max_capacity',
        'bedrooms_count',
        'beds_count',
        'private_bathrooms_count',
        'shared_bathrooms_count',
        'photos_skipped',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'max_capacity' => 'integer',
            'bedrooms_count' => 'integer',
            'beds_count' => 'integer',
            'private_bathrooms_count' => 'integer',
            'shared_bathrooms_count' => 'integer',
            'photos_skipped' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function spaceMode(): BelongsTo
    {
        return $this->belongsTo(SpaceMode::class);
    }

    public function privateSpaceType(): BelongsTo
    {
        return $this->belongsTo(PrivateSpaceType::class);
    }

    public function sharedSpaceType(): BelongsTo
    {
        return $this->belongsTo(SharedSpaceType::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(SpaceRoom::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SpacePhoto::class);
    }

    public function location(): HasOne
    {
        return $this->hasOne(SpaceLocation::class);
    }

    public function generalServices(): BelongsToMany
    {
        return $this->belongsToMany(GeneralService::class, 'space_general_service')
            ->withPivot(['id', 'company_id'])
            ->withTimestamps();
    }

    public function reviewNotes(): HasMany
    {
        return $this->hasMany(SpaceReviewNote::class)->latest();
    }

    public function occupancyBlocks(): HasMany
    {
        return $this->hasMany(OccupancyBlock::class);
    }

    public function availabilityDays(): HasMany
    {
        return $this->hasMany(AvailabilityDay::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApprovedLocked(): bool
    {
        return $this->approved_at !== null || in_array($this->status, ['approved', 'active', 'inactive'], true);
    }
}
