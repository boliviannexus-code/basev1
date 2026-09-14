<?php

namespace App\Services;

use App\Models\FixtureMatch;
use App\Models\MeetingAttendance;
use App\Models\StandingAdjustment;
use App\Models\Team;
use App\Models\TeamAccreditation;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamPlayer;
use App\Models\TournamentTeamSubstitution;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentModificationService
{
    public function substitute(TournamentRegistration $registration, Team $incomingTeam, string $reason): TournamentTeamSubstitution
    {
        abort_unless(CompanyContext::belongsToUser($registration->company_id, auth()->user()), 403);
        abort_unless((int) $incomingTeam->company_id === (int) $registration->company_id, 403);

        return DB::transaction(function () use ($registration, $incomingTeam, $reason): TournamentTeamSubstitution {
            $registration = TournamentRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $incomingTeam = Team::query()->lockForUpdate()->findOrFail($incomingTeam->id);
            $outgoingTeamId = (int) $registration->team_id;

            if ($registration->status !== 'registered') {
                throw ValidationException::withMessages(['tournament_registration_id' => 'Solo se puede sustituir una inscripcion activa.']);
            }

            if (! $incomingTeam->is_active || (int) $incomingTeam->company_id !== (int) $registration->company_id) {
                throw ValidationException::withMessages(['incoming_team_id' => 'El equipo entrante debe estar activo y pertenecer a la misma liga.']);
            }

            if ((int) $incomingTeam->id === $outgoingTeamId) {
                throw ValidationException::withMessages(['incoming_team_id' => 'Selecciona un equipo diferente al saliente.']);
            }

            $alreadyRegistered = TournamentRegistration::query()
                ->whereKeyNot($registration->id)
                ->whereNull('deleted_at')
                ->where('tournament_id', $registration->tournament_id)
                ->where('team_id', $incomingTeam->id)
                ->exists();

            if ($alreadyRegistered) {
                throw ValidationException::withMessages(['incoming_team_id' => 'El equipo entrante ya tiene una inscripcion activa en este torneo.']);
            }

            $homeMatches = FixtureMatch::query()
                ->where('tournament_id', $registration->tournament_id)
                ->where('category_id', $registration->category_id)
                ->where(fn ($query) => $query
                    ->where('home_registration_id', $registration->id)
                    ->orWhere('home_team_id', $outgoingTeamId))
                ->update(['home_team_id' => $incomingTeam->id]);
            $awayMatches = FixtureMatch::query()
                ->where('tournament_id', $registration->tournament_id)
                ->where('category_id', $registration->category_id)
                ->where(fn ($query) => $query
                    ->where('away_registration_id', $registration->id)
                    ->orWhere('away_team_id', $outgoingTeamId))
                ->update(['away_team_id' => $incomingTeam->id]);

            $adjustments = StandingAdjustment::query()
                ->where('tournament_id', $registration->tournament_id)
                ->where('category_id', $registration->category_id)
                ->where('series', $registration->series)
                ->where('team_id', $outgoingTeamId)
                ->update(['team_id' => $incomingTeam->id]);

            MeetingAttendance::query()
                ->where('tournament_registration_id', $registration->id)
                ->where('team_id', $outgoingTeamId)
                ->update(['team_id' => $incomingTeam->id]);

            $habilitations = TournamentTeamPlayer::query()
                ->where('tournament_registration_id', $registration->id)
                ->where('team_id', $outgoingTeamId)
                ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
                ->update(['status' => TournamentTeamPlayer::STATUS_DISABLED]);

            $accreditations = TeamAccreditation::query()
                ->where('tournament_registration_id', $registration->id)
                ->where('team_id', $outgoingTeamId)
                ->delete();

            $registration->update(['team_id' => $incomingTeam->id]);

            return TournamentTeamSubstitution::query()->create([
                'company_id' => $registration->company_id,
                'tournament_id' => $registration->tournament_id,
                'tournament_registration_id' => $registration->id,
                'outgoing_team_id' => $outgoingTeamId,
                'incoming_team_id' => $incomingTeam->id,
                'created_by' => auth()->id(),
                'reason' => $reason,
                'fixture_matches_updated' => $homeMatches + $awayMatches,
                'standing_adjustments_updated' => $adjustments,
                'habilitations_disabled' => $habilitations,
                'accreditations_removed' => $accreditations,
                'substituted_at' => now(),
            ]);
        });
    }
}
