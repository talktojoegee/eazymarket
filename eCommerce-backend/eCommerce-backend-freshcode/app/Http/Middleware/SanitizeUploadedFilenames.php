<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class SanitizeUploadedFilenames
{
    /**
     * Handle an incoming request and sanitize any uploaded file names globally.
     * Removes single quotes (') and unsafe special characters from uploaded file names
     * to prevent WAF 403 Forbidden errors and Spatie Media Library file upload issues.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->files->count() > 0) {
            $sanitizedFiles = $this->sanitizeFiles($request->files->all());
            $request->files->replace($sanitizedFiles);
        }

        return $next($request);
    }

    /**
     * Recursively sanitize uploaded file names in request file bag.
     */
    protected function sanitizeFiles(array $files): array
    {
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $files[$key] = $this->sanitizeFiles($file);
            } elseif ($file instanceof UploadedFile) {
                $originalName = $file->getClientOriginalName();

                if (str_contains($originalName, "'")) {
                    $pathInfo = pathinfo($originalName);
                    $filename = $pathInfo['filename'] ?? '';
                    $extension = isset($pathInfo['extension']) && $pathInfo['extension'] !== ''
                        ? '.' . $pathInfo['extension']
                        : '';

                    // Strip single quotes from filename
                    $cleanFilename = str_replace("'", '', $filename);
                    $sanitizedName = $cleanFilename . $extension;

                    $files[$key] = new UploadedFile(
                        $file->getPathname(),
                        $sanitizedName,
                        $file->getClientMimeType(),
                        $file->getError(),
                        true
                    );
                }
            }
        }

        return $files;
    }
}
