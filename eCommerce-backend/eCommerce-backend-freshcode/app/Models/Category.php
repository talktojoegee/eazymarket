<?php

namespace App\Models;

use App\Traits\HasSeoMetadata;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Category extends Model implements HasMedia
{
    use HasSeoMetadata, InteractsWithMedia, SoftDeletes;

    protected $appends = [
        'image',
        'banner',
    ];

    protected $fillable = [
        'uuid',
        'parent_id',
        'title',
        'slug',
        'description',
        'status',
        'requires_approval',
        'commission',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'commission' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = generateUniqueSlug(self::class, $value, 'slug', $this->id);
        $this->attributes['uuid'] = $this->attributes['uuid'] ?? (string) Str::uuid();
    }

    public function setCommissionAttribute($value): void
    {
        $this->attributes['commission'] = empty($value) ? 0 : $value;
    }

    public function getImageAttribute(): ?string
    {
        return ! empty($this->getFirstMediaUrl('image')) ? $this->getFirstMediaUrl('image') : asset('assets/images/category-placeholder.png');
    }

    public function getBannerAttribute(): ?string
    {
        return $this->getFirstMediaUrl('banner');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function getCategoryHierarchy(): array
    {
        $hierarchy = [];
        $current = $this;

        while ($current) {
            array_unshift($hierarchy, [
                'id' => $current->id,
                'uuid' => $current->uuid,
                'title' => $current->title,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return $hierarchy;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('banner')->singleFile();
    }

    protected static function booted(): void
    {
        static::deleting(function ($category) {
            if ($category->products()->exists()) {
                throw new Exception(__('messages.category_cannot_be_deleted_with_products'));
            }
            if ($category->children()->whereHas('products')->exists()) {
                throw new Exception(__('messages.category_cannot_be_deleted_with_products'));
            }
            $category->clearMediaCollection('image');
            $category->clearMediaCollection('banner');
        });
    }
}
