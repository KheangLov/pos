<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Resizes/compresses uploaded images to WebP and generates a companion
 * thumbnail, so product/category/company images stay small over the wire
 * (POS, KDS and eMenu all render many of these per screen).
 */
class ImageOptimizer
{
    private const MAX_DIMENSION = 1600;

    private const THUMB_SIZE = 400;

    /**
     * Resize/compress the upload and store it plus a "-thumb" companion,
     * returning the main image's disk-relative path (what Filament's
     * FileUpload field stores).
     */
    public static function store(UploadedFile $file, string $disk, string $directory): string
    {
        $manager = new ImageManager(new Driver());
        $hash = Str::random(20);

        $main = $manager->decodePath($file->getRealPath());
        $main->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION);
        $mainPath = "{$directory}/{$hash}.webp";
        Storage::disk($disk)->put($mainPath, (string) $main->encode(new WebpEncoder(quality: 82)));

        $thumb = $manager->decodePath($file->getRealPath());
        $thumb->cover(self::THUMB_SIZE, self::THUMB_SIZE);
        $thumbPath = self::thumbPath($mainPath);
        Storage::disk($disk)->put($thumbPath, (string) $thumb->encode(new WebpEncoder(quality: 75)));

        return $mainPath;
    }

    public static function thumbPath(string $mainPath): string
    {
        return preg_replace('/\.webp$/', '-thumb.webp', $mainPath);
    }

    public static function thumbUrl(string $disk, ?string $mainPath): ?string
    {
        if (! $mainPath) {
            return null;
        }

        return Storage::disk($disk)->url(self::thumbPath($mainPath));
    }
}
