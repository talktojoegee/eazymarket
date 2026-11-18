<?php

namespace App\Support\MediaLibrary;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;

/**
 * Globally slugifies the original file name of every uploaded media item.
 *
 * Spatie's default sanitizer only replaces '#', '/', '\' and spaces, so names
 * like "image-Photoroom-(27).png" keep their parentheses (and any unicode).
 * When the URL generator encodes the path, those characters become %28/%29 and
 * can get double-encoded (%2528/%2529), which breaks image loading.
 *
 * By slugging the base name here, every stored file name is URL-safe
 * regardless of which code path added the media (category, product, brand,
 * variant, etc.). The extension is handled separately by the FileAdder.
 */
class SluggedFileNamer extends DefaultFileNamer
{
    public function originalFileName(string $fileName): string
    {
        $baseName = parent::originalFileName($fileName);

        $slug = Str::slug($baseName);

        if ($slug === '') {
            $slug = 'file-' . substr(md5($baseName . microtime()), 0, 8);
        }

        return $slug;
    }
}
