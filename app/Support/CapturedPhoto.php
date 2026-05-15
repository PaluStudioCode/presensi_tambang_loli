<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CapturedPhoto
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public static function storeDataUrl(string $photoDataUrl, string $directory, string $requiredMessage): string
    {
        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/', $photoDataUrl, $matches)) {
            throw ValidationException::withMessages([
                'photo' => $requiredMessage,
            ]);
        }

        $binary = base64_decode(str_replace(' ', '+', $matches[2]), true);

        if ($binary === false) {
            throw ValidationException::withMessages([
                'photo' => 'Format foto tidak valid.',
            ]);
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'photo' => 'Ukuran foto terlalu besar. Coba ambil ulang foto yang lebih ringan.',
            ]);
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $path = $directory.'/'.Carbon::now()->format('Y/m/d').'/'.Str::uuid().'.'.$extension;

        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
