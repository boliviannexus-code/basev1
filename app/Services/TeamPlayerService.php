<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Support\CompanyContext;
use Illuminate\Validation\ValidationException;

class TeamPlayerService
{
    public function affiliate(array $data): TeamPlayer
    {
        $companyId = CompanyContext::id();
        $team = Team::query()->whereKey($data['team_id'] ?? null)->first();
        $division = Division::query()->whereKey($data['division_id'] ?? null)->first();
        $player = Player::query()->whereKey($data['player_id'] ?? null)->first();

        if (! $team || ! CompanyContext::belongsToUser($team->company_id, auth()->user())) {
            throw ValidationException::withMessages(['team_id' => 'Selecciona un equipo de la liga activa.']);
        }

        if (! $division || ! CompanyContext::belongsToUser($division->company_id, auth()->user())) {
            throw ValidationException::withMessages(['division_id' => 'Selecciona una division de la liga activa.']);
        }

        if (! $player || ! $player->is_active) {
            throw ValidationException::withMessages(['player_id' => 'Selecciona un jugador activo.']);
        }

        if ($player->company_id === null) {
            $player->forceFill(['company_id' => $team->company_id])->save();
        }

        if ((int) $player->company_id !== (int) $team->company_id) {
            throw ValidationException::withMessages(['player_id' => 'Selecciona un jugador de la liga activa.']);
        }

        if ((int) $team->company_id !== (int) $division->company_id) {
            throw ValidationException::withMessages(['team_id' => 'El equipo y la division deben pertenecer a la misma liga.']);
        }

        if ($companyId !== null && $companyId > 0 && (int) $team->company_id !== $companyId) {
            throw ValidationException::withMessages(['team_id' => 'El equipo no pertenece a la liga activa.']);
        }

        $existing = TeamPlayer::query()
            ->with('team')
            ->where('company_id', $team->company_id)
            ->where('division_id', $division->id)
            ->where('player_id', $player->id)
            ->where('status', TeamPlayer::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if ($existing && (int) $existing->team_id === (int) $team->id) {
            return $existing;
        }

        if ($existing) {
            throw ValidationException::withMessages([
                'player_id' => 'El jugador ya pertenece a otro equipo. Para cambiarlo de equipo se debera usar el modulo de pases y transferencias.',
            ]);
        }

        return TeamPlayer::query()->create([
            'company_id' => $team->company_id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'status' => TeamPlayer::STATUS_ACTIVE,
            'joined_at' => $data['joined_at'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
