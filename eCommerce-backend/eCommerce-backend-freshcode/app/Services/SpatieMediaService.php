<?php

namespace App\Services;

use App\Support\OutboundHost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SpatieMediaService
{
    public static function upload(object $model, string $media): mixed
    {
        $file = request()->file($media);
        if ($file) {
            $original = $file->getClientOriginalName();
            $basename = pathinfo($original, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $slug = Str::slug($basename);
            $sluggedName = $extension ? ($slug.'.'.$extension) : $slug;

            return $model
                ->addMediaFromRequest($media)
                ->usingFileName($sluggedName)
                ->toMediaCollection($media);
        }

        return $model->addMediaFromRequest($media)->toMediaCollection($media);
    }

    public static function uploadFromRequest($model, $file, $collectionName)
    {
        $original = $file->getClientOriginalName();
        $basename = pathinfo($original, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $slug = Str::slug($basename);
        $sluggedName = $extension ? ($slug.'.'.$extension) : $slug;

        return $model
            ->addMedia($file)
            ->usingFileName($sluggedName)
            ->toMediaCollection($collectionName);
    }

    public static function update($request, $model, $media)
    {
        if ($request->hasFile($media)) {
            $newImageFile = $request->file($media);
            $existingImage = $model->getFirstMedia($media);

            $original = $newImageFile->getClientOriginalName();
            $basename = pathinfo($original, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $slug = Str::slug($basename);
            $newImageName = $extension ? ($slug.'.'.$extension) : $slug;

            if (! $existingImage || $existingImage->file_name !== $newImageName) {
                return $model
                    ->addMedia($newImageFile)
                    ->usingFileName($newImageName)
                    ->toMediaCollection($media);
            }
        }

        return null;
    }

    public static function uploadFromUrl(object $model, string $url, string $collectionName): mixed
    {
        $urlPath = (string) parse_url($url, PHP_URL_PATH);
        $basename = pathinfo($urlPath, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
        $slug = Str::slug($basename);
        $sluggedName = $extension ? ($slug.'.'.$extension) : $slug;

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $maxBytes = 2 * 1024 * 1024;

        if ($extension && ! in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('Invalid image extension. Allowed: jpg, jpeg, png, webp');
        }

        $localPath = self::publicDiskPathForUrl($url);
        if ($localPath !== null) {
            self::assertLocalImage($localPath, $maxBytes);

            return $model
                ->addMedia($localPath)
                ->preservingOriginal()
                ->usingFileName($sluggedName)
                ->toMediaCollection($collectionName);
        }

        OutboundHost::assertPublicHttpUrl($url, 'Image URL');

        try {
            $response = Http::withoutVerifying()
                ->withOptions(['allow_redirects' => false])
                ->timeout(15)
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('uploadFromUrl fetch failed: '.$e->getMessage());
            throw new \InvalidArgumentException('Image URL could not be fetched');
        }

        if ($response->redirect()) {
            throw new \InvalidArgumentException('Image URL must not redirect');
        }

        if (! $response->successful()) {
            throw new \InvalidArgumentException(__('labels.image_url_http_error', ['status' => $response->status()]));
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $contentType = trim(explode(';', $contentType)[0]);
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (! isset($map[$contentType])) {
            throw new \InvalidArgumentException('URL does not point to a jpg, png or webp image');
        }

        if (! $extension) {
            $extension = $map[$contentType];
            $sluggedName = $slug.'.'.$extension;
        }

        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            throw new \InvalidArgumentException('Image exceeds maximum allowed size of 2 MB');
        }

        return $model
            ->addMediaFromString($body)
            ->usingFileName($sluggedName)
            ->toMediaCollection($collectionName);
    }

    private static function publicDiskPathForUrl(string $url): ?string
    {
        $publicDiskUrl = (string) config('filesystems.disks.public.url', rtrim((string) config('app.url'), '/').'/storage');
        $source = parse_url($url);
        $public = parse_url($publicDiskUrl);

        if (! is_array($source) || ! is_array($public)) {
            return null;
        }

        $sameOrigin = strtolower((string) ($source['scheme'] ?? '')) === strtolower((string) ($public['scheme'] ?? ''))
            && strtolower((string) ($source['host'] ?? '')) === strtolower((string) ($public['host'] ?? ''))
            && ($source['port'] ?? null) === ($public['port'] ?? null);
        $publicPath = '/'.trim((string) ($public['path'] ?? '/storage'), '/');
        $sourcePath = (string) ($source['path'] ?? '');

        if (! $sameOrigin || ! str_starts_with($sourcePath, $publicPath.'/')) {
            return null;
        }

        $relativePath = rawurldecode(substr($sourcePath, strlen($publicPath) + 1));
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            $root = realpath($disk->path(''));
            $path = realpath($disk->path($relativePath));
        } catch (\Throwable) {
            return null;
        }

        if ($root === false || $path === false || ! str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    private static function assertLocalImage(string $path, int $maxBytes): void
    {
        $size = filesize($path);
        if ($size === false || $size > $maxBytes) {
            throw new \InvalidArgumentException('Image exceeds maximum allowed size of 2 MB');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $mimeType = mime_content_type($path);
        if ($mimeType === false || ! in_array(strtolower($mimeType), $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('URL does not point to a jpg, png or webp image');
        }
    }
}
