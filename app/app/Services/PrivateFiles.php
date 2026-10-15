<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private document storage. Files never get public URLs; downloads pass through authorised controllers.
 */
class PrivateFiles
{
    public const RULES = 'required|file|mimes:pdf|extensions:pdf|max:5120';

    public const DOCUMENT_RULES = 'required|file|mimes:pdf,docx|extensions:pdf,docx|max:5120';

    public const PHOTO_RULES = 'required|file|image|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:2048|dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000';

    /**
     * Store a PDF privately after checking the file signature, returning [path, disk].
     *
     * @return array{0: string, 1: string}
     */
    public function storePdf(UploadedFile $file, string $directory): array
    {
        if ($this->signature($file, 5) !== '%PDF-') {
            throw ValidationException::withMessages(['document' => 'Berkas bukan PDF yang valid.']);
        }

        return $this->put($file, $directory);
    }

    /**
     * Store a PDF or DOCX (e.g. meeting minutes) after checking the file signature.
     *
     * @return array{0: string, 1: string}
     */
    public function storeDocument(UploadedFile $file, string $directory): array
    {
        $signature = $this->signature($file, 5);
        $docx = str_starts_with($signature, "PK\x03\x04") && strtolower($file->getClientOriginalExtension()) === 'docx';
        if ($signature !== '%PDF-' && ! $docx) {
            throw ValidationException::withMessages(['document' => 'Berkas harus PDF atau DOCX yang valid.']);
        }

        return $this->put($file, $directory);
    }

    /**
     * Re-encode a profile photo to a 320×320 JPEG (drops metadata and anything embedded in the original).
     *
     * @return array{0: string, 1: string}
     */
    public function storePhoto(UploadedFile $file, string $directory): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['photo' => 'Gambar tidak dapat dibaca.']);
        }
        $side = min(imagesx($source), imagesy($source));
        $target = imagecreatetruecolor(320, 320);
        imagecopyresampled($target, $source, 0, 0, intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2), 320, 320, $side, $side);
        ob_start();
        imagejpeg($target, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        $disk = config('brudel.document_disk');
        $path = trim($directory, '/').'/'.Str::random(32).'.jpg';
        if (! Storage::disk($disk)->put($path, $jpeg, ['visibility' => 'private'])) {
            throw ValidationException::withMessages(['photo' => 'Penyimpanan foto gagal.']);
        }

        return [$path, $disk];
    }

    private function signature(UploadedFile $file, int $length): string
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $signature = $handle ? (string) fread($handle, $length) : '';
        if ($handle) {
            fclose($handle);
        }

        return $signature;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function put(UploadedFile $file, string $directory): array
    {
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

        $types = ['pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'jpg' => 'image/jpeg'];

        return Storage::disk($disk)->download($path, $name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream',
        ]);
    }

    /**
     * Serve a private image inline (profile photos) after the caller has authorised the request.
     */
    public function inlineImage(string $path, string $disk): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
