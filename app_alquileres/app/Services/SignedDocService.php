<?php

namespace App\Services;

use App\Models\SignedDoc;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignedDocService
{
    public function storeForAgreement(int $agreementId, UploadedFile $file): SignedDoc
    {
        return $this->store(['agreement_id' => $agreementId], $file, "agreements/{$agreementId}");
    }

    public function storeForAdemdum(int $ademdumId, UploadedFile $file): SignedDoc
    {
        return $this->store(['ademdum_id' => $ademdumId], $file, "ademdums/{$ademdumId}");
    }

    public function removeFile(?SignedDoc $signedDoc): void
    {
        if (!$signedDoc) {
            return;
        }

        if (Storage::disk($signedDoc->disk)->exists($signedDoc->path)) {
            Storage::disk($signedDoc->disk)->delete($signedDoc->path);
        }
    }

    public function deleteForAgreement(int $agreementId): void
    {
        $signedDoc = SignedDoc::query()->where('agreement_id', $agreementId)->first();

        $this->deleteSignedDoc($signedDoc);
    }

    public function deleteForAdemdum(int $ademdumId): void
    {
        $signedDoc = SignedDoc::query()->where('ademdum_id', $ademdumId)->first();

        $this->deleteSignedDoc($signedDoc);
    }

    private function store(array $ownerCondition, UploadedFile $file, string $baseDirectory): SignedDoc
    {
        $existing = SignedDoc::query()->where($ownerCondition)->first();

        if ($existing) {
            $this->removeFile($existing);
        }

        $binary = file_get_contents($file->getRealPath());
        $compressed = gzencode($binary, 9);
        $filename = Str::uuid()->toString() . '.gz';
        $relativePath = 'signed_docs/' . trim($baseDirectory, '/') . '/' . $filename;
        $disk = 'local';

        Storage::disk($disk)->put($relativePath, $compressed);

        return SignedDoc::query()->updateOrCreate(
            $ownerCondition,
            [
                'disk' => $disk,
                'path' => $relativePath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: strlen($binary),
                'compressed_size_bytes' => strlen($compressed),
            ]
        );
    }

    private function deleteSignedDoc(?SignedDoc $signedDoc): void
    {
        if (!$signedDoc) {
            return;
        }

        $this->removeFile($signedDoc);
        $signedDoc->delete();
    }
}
