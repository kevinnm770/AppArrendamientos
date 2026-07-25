<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyPhotoStorageService
{
    private const DISK = 'r2';

    public function store(UploadedFile $file, int $propertyId): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = Str::uuid().($extension ? '.'.$extension : '');

        return $file->storeAs("propertyphotos/{$propertyId}", $filename, ['disk' => self::DISK]);
    }

    public function delete(?string $path): void
    {
        if (!$path) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public function temporaryUrl(string $path, int $minutesTtl = 15): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes($minutesTtl));
    }
}
