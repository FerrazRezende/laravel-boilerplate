<?php

declare(strict_types=1);

namespace Modules\Profile\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfilePictureService
{
    private const DISK = 'rustfs';

    public function upload(User $user, UploadedFile $photo): string
    {
        $filename = $user->id.'-'.Str::random(8).'.'.$photo->extension();

        return Storage::disk(self::DISK)->putFileAs('avatars', $photo, $filename);
    }

    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }
}
