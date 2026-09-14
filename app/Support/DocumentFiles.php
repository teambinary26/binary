<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFiles
{
    public const DISK = 'public';

    public static function store(UploadedFile $file, int|string $applicationId): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.strtolower($extension);

        return $file->storeAs('documents/'.$applicationId, $filename, self::DISK);
    }

    public static function url(?string $path): ?string
    {
        $disk = self::adapter(self::DISK);

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        return $disk->url($path);
    }

    public static function download(string $path, string $name): StreamedResponse
    {
        $disk = self::adapter(self::resolveDisk($path));

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $name);
    }

    public static function inline(string $path, string $name): StreamedResponse
    {
        $disk = self::adapter(self::resolveDisk($path));

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $name);
    }

    public static function isImage(?string $mime, ?string $name = null): bool
    {
        if (str_starts_with((string) $mime, 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', (string) $name);
    }

    public static function isPdf(?string $mime, ?string $name = null): bool
    {
        if ($mime === 'application/pdf') {
            return true;
        }

        return (bool) preg_match('/\.pdf$/i', (string) $name);
    }

    public static function isText(?string $mime, ?string $name = null): bool
    {
        if (str_starts_with((string) $mime, 'text/')) {
            return true;
        }

        return (bool) preg_match('/\.(txt|csv|log)$/i', (string) $name);
    }

    public static function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        foreach ([self::DISK, 'local'] as $disk) {
            $adapter = self::adapter($disk);
            if ($adapter->exists($path)) {
                $adapter->delete($path);
            }
        }
    }

    public static function deleteDirectory(string $directory): void
    {
        foreach ([self::DISK, 'local'] as $disk) {
            self::adapter($disk)->deleteDirectory($directory);
        }
    }

    public static function put(string $path, string $contents): string
    {
        self::adapter(self::DISK)->put($path, $contents);

        return $path;
    }

    public static function exists(string $path): bool
    {
        return self::adapter(self::DISK)->exists($path)
            || self::adapter('local')->exists($path);
    }

    public static function contents(string $path): ?string
    {
        foreach ([self::DISK, 'local'] as $disk) {
            $adapter = self::adapter($disk);
            if ($adapter->exists($path)) {
                return $adapter->get($path);
            }
        }

        return null;
    }

    private static function resolveDisk(string $path): string
    {
        return self::adapter(self::DISK)->exists($path) ? self::DISK : 'local';
    }

    private static function adapter(string $disk): FilesystemAdapter
    {
        $adapter = Storage::disk($disk);

        abort_unless($adapter instanceof FilesystemAdapter, 500);

        return $adapter;
    }
}
