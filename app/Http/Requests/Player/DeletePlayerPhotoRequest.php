<?php

namespace App\Http\Requests\Player;

use App\Models\Player;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class DeletePlayerPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $player = $this->route('player');
        $user = $this->user();

        if (! $player instanceof Player || ! $user?->can('players.update') || ! CompanyContext::canOperate($user)) {
            return false;
        }

        if (CompanyContext::isGlobalAdmin($user)) {
            return true;
        }

        return (int) $player->company_id === (int) CompanyContext::id($user);
    }
}
