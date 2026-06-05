<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Laravel\Facades\Image;

class PlayerPhotoService
{
    public function update(Player $player, UploadedFile $photo): Player
    {
        $this->ensurePlayerCode($player);

        $path = $this->photoPath($player);
        $disk = $this->disk();
        $oldPath = $player->photo_path;
        $contents = $this->optimizedContents($photo);

        Storage::disk($disk)->put($path, $contents);

        if (filled($oldPath) && $oldPath !== $path) {
            Storage::disk($disk)->delete($oldPath);
        }

        $player->forceFill(['photo_path' => $path])->saveQuietly();

        return $player->refresh();
    }

    public function delete(Player $player): Player
    {
        if (filled($player->photo_path)) {
            Storage::disk($this->disk())->delete($player->photo_path);
        }

        $player->forceFill(['photo_path' => null])->saveQuietly();

        return $player->refresh();
    }

    public function url(?Player $player): ?string
    {
        if (! $player || blank($player->photo_path)) {
            return null;
        }

        return Storage::disk($this->disk())->url($player->photo_path);
    }

    public function dataUriFor(?Player $player): ?string
    {
        if (! $player || blank($player->photo_path) || ! Storage::disk($this->disk())->exists($player->photo_path)) {
            return null;
        }

        return 'data:image/webp;base64,'.base64_encode(Storage::disk($this->disk())->get($player->photo_path));
    }

    public function photoPath(Player $player): string
    {
        return trim((string) config('player_media.photos.directory'), '/').'/'.$this->filename($player);
    }

    private function optimizedContents(UploadedFile $photo): string
    {
        try {
            $image = Image::decode($photo)->cover($this->size(), $this->size());
        } catch (DecoderException $exception) {
            throw ValidationException::withMessages([
                'photo' => 'No se pudo leer la imagen. El archivo puede estar corrupto.',
            ]);
        }

        return (string) $image->encode(new WebpEncoder(
            quality: $this->quality(),
            strip: true
        ));
    }

    private function ensurePlayerCode(Player $player): void
    {
        if (blank($player->internal_code)) {
            $player->forceFill([
                'internal_code' => Player::internalCodeForId((int) $player->id),
            ])->saveQuietly();

            $player->refresh();
        }
    }

    private function filename(Player $player): string
    {
        $code = str((string) $player->internal_code)
            ->replaceMatches('/[^A-Za-z0-9_-]/', '')
            ->toString();

        return $code.'.webp';
    }

    private function disk(): string
    {
        return (string) config('player_media.photos.disk', 'public');
    }

    private function size(): int
    {
        return (int) config('player_media.photos.size', 600);
    }

    private function quality(): int
    {
        return (int) config('player_media.photos.quality', 78);
    }
}
