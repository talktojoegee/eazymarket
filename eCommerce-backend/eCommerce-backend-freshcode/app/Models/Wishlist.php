<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Wishlist extends Model
{
    /** Title of the single default wishlist backing the one-tap favorite flow. */
    public const FAVORITE_TITLE = 'Favorite';

    protected $fillable = ['user_id', 'title', 'slug'];

    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = generateUniqueSlug(self::class, $value, 'slug', $this->id);
    }

    /**
     * Get (or create) the user's default "Favorite" wishlist. Guarantees the
     * single-list favorite UX always has a target to add/remove from.
     */
    public static function favoriteFor(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'title' => self::FAVORITE_TITLE],
        );
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }
}
