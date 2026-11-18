<?php

namespace App\Models;

use App\Traits\HasSeoMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Blog extends Model implements HasMedia
{
    use HasSeoMetadata, InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'content',
        'status',
        'editor_pick',
        'author_profile_id',
        'published_at',
        'tags',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'editor_pick' => 'boolean',
            'published_at' => 'datetime',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Blog $blog) {
            $blog->slug = static::uniqueSlug($blog->slug ?: $blog->title);
        });

        static::updating(function (Blog $blog) {
            if ($blog->isDirty('slug')) {
                $blog->slug = static::uniqueSlug($blog->slug ?: $blog->title, $blog->id);
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('blog_cover_image')->singleFile();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_category_blog');
    }

    public function authorProfile(): BelongsTo
    {
        return $this->belongsTo(WatchBuyProfile::class, 'author_profile_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'blog_product');
    }

    public function getImageAttribute(): string
    {
        return $this->getFirstMediaUrl('blog_cover_image');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'blog';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
