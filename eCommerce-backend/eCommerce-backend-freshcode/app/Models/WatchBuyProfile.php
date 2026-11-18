<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WatchBuyProfile extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'profile_type',
        'seller_id',
        'username',
        'bio',
        'status',
        'suspended_by',
        'suspension_reason',
        'suspended_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['suspended_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO())->singleFile();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function reels(): HasMany
    {
        return $this->hasMany(WatchBuyReel::class, 'profile_id');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(WatchBuyStatus::class, 'profile_id');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO()) ?: null;
    }
}
