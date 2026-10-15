<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private document storage. Files never get public URLs; downloads pass through authorised controllers.
 */
class PrivateFiles
{
    public const RULES = 'required|file|mimes:pdf|extensions:pdf|max:5120';

    /**
     * Store a PDF privately after checking the file signature, returning [path, disk].
     *
     * @return array{0: string, 1: string}
     */
    public function storePdf(UploadedFile $file, string $directory): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $signature = $handle ? fread($handle, 5) : '';
        if ($handle) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            throw ValidationException::withMessages(['document' => 'Berkas bukan PDF yang valid.']);
        }

        $disk = config('brudel.document_disk');
        $path = $file->store($directory, ['disk' => $disk, 'visibility' => 'private']);
        if (! $path) {
            throw ValidationException::withMessages(['document' => 'Penyimpanan dokumen gagal.']);
        }

        return [$path, $disk];
    }

    public function delete(?string $path, ?string $disk): void
    {
        if ($path && $disk) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function download(string $path, string $disk, string $name): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => 'application/pdf',
        ]);
    }
}
