<?php

namespace App\Services;

use App\Models\Player;
use App\Models\TournamentTeamPlayer;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

class PlayerQrCodeService
{
    private const TARGET_BYTES = 30720;

    public function generateForHabilitation(TournamentTeamPlayer $habilitation): TournamentTeamPlayer
    {
        $habilitation->loadMissing(['player']);

        $player = $this->ensureForPlayer($habilitation->player);

        $habilitation->forceFill([
            'qr_code_path' => $player->qr_code_path,
            'qr_code_size' => $player->qr_code_size,
        ])->saveQuietly();

        return $habilitation->refresh();
    }

    public function ensureForPlayer(Player $player): Player
    {
        if (filled($player->qr_code_path) && Storage::disk('public')->exists($player->qr_code_path)) {
            return $player;
        }

        $path = sprintf('player-qrcodes/player-%s.svg', $player->id);
        $svg = $this->padToTargetSize($this->writer()->writeString(
            (string) $player->internal_code,
            'UTF-8',
            ErrorCorrectionLevel::M()
        ));

        Storage::disk('public')->put($path, $svg);

        $player->forceFill([
            'qr_code_path' => $path,
            'qr_code_size' => strlen($svg),
        ])->saveQuietly();

        return $player->refresh();
    }

    public function dataUriFor(Player $player): ?string
    {
        if (blank($player->qr_code_path) || ! Storage::disk('public')->exists($player->qr_code_path)) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode(Storage::disk('public')->get($player->qr_code_path));
    }

    private function writer(): Writer
    {
        return new Writer(new ImageRenderer(
            new RendererStyle(420, 2),
            new SvgImageBackEnd
        ));
    }

    private function padToTargetSize(string $svg): string
    {
        $missingBytes = self::TARGET_BYTES - strlen($svg);

        if ($missingBytes <= 0) {
            return $svg;
        }

        $comment = '<!-- '.str_repeat('N', max(0, $missingBytes - 9)).' -->';

        return str_replace('</svg>', $comment.'</svg>', $svg);
    }
}
