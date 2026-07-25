<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageAttachmentStorageService
{
    private const DISK = 'r2';

    public function store(UploadedFile $file, int $conversationId): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = Str::uuid().($extension ? '.'.$extension : '');

        return $file->storeAs("arrendamientos/files_message/{$conversationId}", $filename, ['disk' => self::DISK]);
    }

    public function delete(?string $path): void
    {
        if (!$path) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public function temporaryUrl(string $path, int $minutesTtl = 30): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes($minutesTtl));
    }
}
