<?php

namespace App\Services\Legacy;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\Legacy\LegacyImportBatch;
use App\Models\Legacy\LegacyImportLog;
use App\Models\Legacy\LegacyReference;
use App\Models\Legacy\LegacySeries;
use App\Models\Legacy\LegacyTransferEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamPlayer;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MejillonesLegacyImportService
{
    private const SOURCE = 'mejillones';

    private array $summary = [];

    public function __construct(
        private readonly MejillonesLegacyNormalizer $normalizer,
    ) {}

    public function analyze(int $companyId, ?string $sourcePath = null): LegacyImportBatch
    {
        $batch = $this->startBatch($companyId, 'analysis', $sourcePath);
        $summary = $this->buildAnalysisSummary();
        $reportPath = $this->writeReport($batch, $summary);

        $batch->update([
            'status' => 'completed',
            'summary' => $summary,
            'report_path' => $reportPath,
            'finished_at' => now(),
        ]);

        return $batch->refresh();
    }

    public function dryRun(int $companyId, ?string $sourcePath = null): LegacyImportBatch
    {
        $batch = $this->startBatch($companyId, 'dry_run', $sourcePath);
        $summary = $this->buildAnalysisSummary();
        $summary['dry_run'] = [
            'message' => 'No se escribieron registros de dominio. Ejecuta con --commit para importar.',
            'target_company_id' => $companyId,
        ];
        $reportPath = $this->writeReport($batch, $summary);

        $batch->update([
            'status' => 'completed',
            'summary' => $summary,
            'report_path' => $reportPath,
            'finished_at' => now(),
        ]);

        return $batch->refresh();
    }

    public function import(int $companyId, ?string $sourcePath = null): LegacyImportBatch
    {
        Company::query()->findOrFail($companyId);

        $batch = $this->startBatch($companyId, 'import', $sourcePath);
        $this->summary = [];

        try {
            $this->importCatalogs($batch, $companyId);
            $this->importTeams($batch, $companyId);
            $this->importPlayers($batch, $companyId);
            $this->importTournamentRegistrations($batch, $companyId);
            $this->importHabilitations($batch, $companyId);
            $this->importTransferEvents($batch, $companyId);

            $summary = array_merge($this->buildAnalysisSummary(false), ['import' => $this->summary]);
            $reportPath = $this->writeReport($batch, $summary);

            $batch->update([
                'status' => 'completed',
                'summary' => $summary,
                'report_path' => $reportPath,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->log($batch, 'system', null, null, null, 'failed', 'error', $exception->getMessage());

            $batch->update([
                'status' => 'failed',
                'summary' => ['error' => $exception->getMessage(), 'import' => $this->summary],
                'finished_at' => now(),
            ]);

            throw $exception;
        }

        return $batch->refresh();
    }

    public function rollback(int $batchId): LegacyImportBatch
    {
        $batch = LegacyImportBatch::query()->findOrFail($batchId);

        DB::transaction(function () use ($batch): void {
            $refs = LegacyReference::query()
                ->where('batch_id', $batch->id)
                ->orderByDesc('id')
                ->get();

            $deleteOrder = [
                'legacy_transfer_events' => LegacyTransferEvent::class,
                'tournament_team_players' => TournamentTeamPlayer::class,
                'tournament_registrations' => TournamentRegistration::class,
                'team_players' => TeamPlayer::class,
                'players' => Player::class,
                'teams' => Team::class,
                'legacy_series' => LegacySeries::class,
                'tournaments' => Tournament::class,
                'division_categories' => DivisionCategory::class,
                'divisions' => Division::class,
                'seasons' => Season::class,
            ];

            foreach ($deleteOrder as $table => $modelClass) {
                $refs->where('target_table', $table)
                    ->each(function (LegacyReference $reference) use ($batch, $modelClass, $table): void {
                        $model = $this->modelQuery($modelClass)->find($reference->target_id);

                        if ($model) {
                            method_exists($model, 'forceDelete') ? $model->forceDelete() : $model->delete();
                        }

                        $this->log($batch, $reference->source_table, $reference->source_id, $table, $reference->target_id, 'rollback', 'deleted', 'Registro creado por lote revertido.');
                    });
            }

            LegacyReference::query()->where('batch_id', $batch->id)->delete();

            $batch->update([
                'status' => 'rolled_back',
                'finished_at' => now(),
            ]);
        });

        return $batch->refresh();
    }

    private function buildAnalysisSummary(bool $includeDuplicates = true): array
    {
        $tables = ['persona', 'equipo', 'torneo', 'categoria', 'serie', 'equipotorneo', 'habilitacion', 'solicitudpase', 'traspaso', 'files'];
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = $this->legacyTableExists($table) ? $this->legacy($table)->count() : 0;
        }

        $summary = ['counts' => $counts, 'missing_tables' => array_values(array_filter($tables, fn (string $table): bool => ! $this->legacyTableExists($table)))];

        if ($includeDuplicates && $this->legacyTableExists('persona')) {
            $summary['duplicates'] = [
                'players_by_ci' => $this->legacy('persona')
                    ->select('carnet', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('carnet')
                    ->where('carnet', '!=', '')
                    ->where('carnet', '!=', '0')
                    ->groupBy('carnet')
                    ->havingRaw('COUNT(*) > 1')
                    ->count(),
                'teams_by_name' => $this->legacy('equipo')
                    ->select('nombre', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('nombre')
                    ->groupBy('nombre')
                    ->havingRaw('COUNT(*) > 1')
                    ->count(),
            ];
        }

        return $summary;
    }

    private function importCatalogs(LegacyImportBatch $batch, int $companyId): void
    {
        $this->eachLegacy('gestion', function (object $row) use ($batch, $companyId): void {
            $year = (int) $row->anio;
            $season = Season::query()->firstOrCreate(
                ['company_id' => $companyId, 'name' => 'Gestion '.$year],
                ['year' => $year, 'status' => 'completed', 'is_active' => (bool) $row->activo]
            );
            $this->reference($batch, 'gestion', $row->idgestion, 'seasons', $season->id, $season->wasRecentlyCreated);
        });

        $this->eachLegacy('tipo', function (object $row) use ($batch, $companyId): void {
            $name = $this->normalizer->title($row->nombre, 'Division legacy '.$row->idtipo);
            $division = Division::query()->firstOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                [
                    'min_age' => max(0, min(120, (int) $row->edadminima)),
                    'max_age' => max(1, min(120, (int) $row->edadmaxima)),
                    'description' => $this->normalizer->text($row->descripcion),
                    'is_active' => $this->normalizer->active($row->activo),
                ]
            );
            $this->reference($batch, 'tipo', $row->idtipo, 'divisions', $division->id, $division->wasRecentlyCreated);
        });

        $this->eachLegacy('categoria', function (object $row) use ($batch, $companyId): void {
            $division = $this->target($companyId, 'tipo', $row->idtipotorneo, Division::class);

            if (! $division) {
                $this->log($batch, 'categoria', $row->idcategoria, 'division_categories', null, 'skipped', 'pending_review', 'Categoria sin division/tipo resoluble.');

                return;
            }

            $name = $this->normalizer->title($row->nombre, 'Categoria legacy '.$row->idcategoria);
            $category = DivisionCategory::query()->firstOrCreate(
                ['company_id' => $companyId, 'division_id' => $division->id, 'name' => $name],
                [
                    'description' => $this->normalizer->notes([
                        'descripcion' => $this->normalizer->text($row->descripcion),
                        'nivel' => $row->nivel,
                        'duracion' => $row->duracion,
                        'edad_min' => $row->edadmin,
                        'edad_max' => $row->edadmax,
                    ]),
                    'is_active' => $this->normalizer->active($row->activo),
                ]
            );
            $this->reference($batch, 'categoria', $row->idcategoria, 'division_categories', $category->id, $category->wasRecentlyCreated);
        });

        $this->eachLegacy('serie', function (object $row) use ($batch, $companyId): void {
            $category = $this->target($companyId, 'categoria', $row->idcategoria, DivisionCategory::class);
            $series = LegacySeries::query()->updateOrCreate(
                ['company_id' => $companyId, 'legacy_id' => $row->idserie],
                [
                    'division_category_id' => $category?->id,
                    'name' => $this->normalizer->title($row->nombre, 'Serie legacy '.$row->idserie),
                    'description' => $this->normalizer->text($row->Descripcion),
                    'is_active' => $this->normalizer->active($row->activo),
                ]
            );
            $this->reference($batch, 'serie', $row->idserie, 'legacy_series', $series->id, $series->wasRecentlyCreated);
        });

        $this->eachLegacy('torneo', function (object $row) use ($batch, $companyId): void {
            $division = $this->target($companyId, 'tipo', $row->idtipo, Division::class);

            if (! $division) {
                $this->log($batch, 'torneo', $row->idtorneo, 'tournaments', null, 'skipped', 'pending_review', 'Torneo sin division/tipo resoluble.');

                return;
            }

            $season = Season::query()->firstOrCreate(
                ['company_id' => $companyId, 'name' => 'Gestion '.(int) $row->anio],
                ['year' => (int) $row->anio, 'status' => 'completed', 'is_active' => true]
            );
            $name = $this->normalizer->title($row->nombre ?: $row->descripcion, 'Torneo legacy '.$row->idtorneo);

            $tournament = Tournament::query()->firstOrCreate(
                ['company_id' => $companyId, 'season_id' => $season->id, 'division_id' => $division->id, 'name' => $name],
                ['category_id' => null, 'status' => $this->normalizer->active($row->activo) ? 'active' : 'completed', 'is_active' => $this->normalizer->active($row->activo)]
            );
            $this->reference($batch, 'torneo', $row->idtorneo, 'tournaments', $tournament->id, $tournament->wasRecentlyCreated);
        });
    }

    private function importTeams(LegacyImportBatch $batch, int $companyId): void
    {
        $this->eachLegacy('equipo', function (object $row) use ($batch, $companyId): void {
            [$name, $normalized] = $this->normalizer->teamName($row->nombre, $row->idequipo);
            $team = Team::query()
                ->where('company_id', $companyId)
                ->where('name_normalized', $normalized)
                ->first();
            $created = false;

            if (! $team) {
                $team = Team::query()->create([
                    'company_id' => $companyId,
                    'name' => $name,
                    'founded_at' => $this->normalizer->date($row->fundacion, '1900-01-01'),
                    'notes' => $this->normalizer->notes([
                        'legacy_idequipo' => $row->idequipo,
                        'colores' => $row->colores,
                        'escudo' => $row->equi_escudo,
                        'categoria_legacy' => $row->idcategoria,
                        'serie_legacy' => $row->idserie,
                    ]),
                    'is_active' => $this->normalizer->active($row->activo),
                ]);
                $this->bump('teams.created');
                $created = true;
            } else {
                $this->bump('teams.matched');
            }

            $this->reference($batch, 'equipo', $row->idequipo, 'teams', $team->id, $created);
        });
    }

    private function importPlayers(LegacyImportBatch $batch, int $companyId): void
    {
        $this->eachLegacy('persona', function (object $row) use ($batch, $companyId): void {
            [$ci, $normalizedCi, $generatedCi] = $this->normalizer->ci($row->carnet, $row->idpersona);
            $player = Player::query()
                ->where('ci_normalized', $normalizedCi)
                ->first();
            $created = false;

            if (! $player) {
                $player = Player::query()->create([
                    'company_id' => null,
                    'ci' => $ci,
                    'ci_normalized' => $normalizedCi,
                    'first_name' => $this->normalizer->title($row->nombre, 'Sin nombre'),
                    'last_name' => $this->normalizer->title($row->paterno, 'Sin apellido'),
                    'maternal_name' => $this->normalizer->title($row->materno),
                    'internal_code' => $this->normalizer->text($row->cod),
                    'birth_date' => $this->normalizer->date($row->nacimiento, '1900-01-01'),
                    'notes' => $this->normalizer->notes([
                        'legacy_idpersona' => $row->idpersona,
                        'cod_legacy' => $row->cod,
                        'ci_generado_pending_review' => $generatedCi ? 'si' : null,
                        'expedido' => $row->expedido,
                        'email' => $row->email,
                        'direccion' => $row->direccion,
                        'celular' => $row->celular,
                        'idequipo' => $row->idequipo,
                        'idequipoprestamo' => $row->idequipoprestamo,
                        'idequiposenior' => $row->idequiposenior,
                        'idequipomaster' => $row->idequipomaster,
                        'idequipojuvenil' => $row->idequipojuvenil,
                    ]),
                    'is_active' => $this->normalizer->active($row->activo),
                ]);
                $this->bump('players.created');
                $created = true;
            } else {
                $this->bump('players.matched');
            }

            $this->reference($batch, 'persona', $row->idpersona, 'players', $player->id, $created);
        });
    }

    private function importTournamentRegistrations(LegacyImportBatch $batch, int $companyId): void
    {
        $this->eachLegacy('equipotorneo', function (object $row) use ($batch, $companyId): void {
            $team = $this->target($companyId, 'equipo', $row->idequipo, Team::class);
            $tournament = $this->target($companyId, 'torneo', $row->idtorneo, Tournament::class);

            if (! $team || ! $tournament) {
                $this->log($batch, 'equipotorneo', $row->idequipotorneo, 'tournament_registrations', null, 'skipped', 'pending_review', 'Inscripcion con equipo o torneo no resoluble.');

                return;
            }

            $registration = TournamentRegistration::query()
                ->where('division_id', $tournament->division_id)
                ->where('team_id', $team->id)
                ->whereNull('deleted_at')
                ->first();

            $historical = false;
            $created = false;

            if (! $registration) {
                $registration = TournamentRegistration::query()->create([
                    'company_id' => $companyId,
                    'tournament_id' => $tournament->id,
                    'division_id' => $tournament->division_id,
                    'team_id' => $team->id,
                    'category_id' => $tournament->category_id,
                    'team_number' => $this->nextTournamentRegistrationNumber($tournament->id, $tournament->category_id, 'unica'),
                    'series' => 'unica',
                    'status' => $this->normalizer->active($row->activo) ? 'registered' : 'inactive',
                    'notes' => $this->normalizer->notes([
                        'legacy_idequipotorneo' => $row->idequipotorneo,
                        'categoria_legacy' => $row->idcategoria,
                        'serie_legacy' => $row->idserie,
                        'tipo_legacy' => $row->tipo,
                        'llave_legacy' => $row->llave,
                    ]),
                ]);
                $this->bump('registrations.created');
                $created = true;
            } elseif ((int) $registration->tournament_id !== (int) $tournament->id) {
                $historical = true;
                $registration = $this->createHistoricalRegistration($companyId, $tournament, $team, 'Registro historico legacy soft-deleted para conservar relacion sin violar inscripcion activa por division/equipo.');
                $this->bump('registrations.historical');
                $created = true;
            } else {
                $this->bump('registrations.matched');
            }

            $this->reference($batch, 'equipotorneo', $row->idequipotorneo, 'tournament_registrations', $registration->id, $created);
            $this->log($batch, 'equipotorneo', $row->idequipotorneo, 'tournament_registrations', $registration->id, $historical ? 'historical' : 'upsert', $historical ? 'pending_review' : 'ok', $historical ? 'Registro historico creado como soft-deleted.' : 'Inscripcion resuelta.');
        });
    }

    private function importHabilitations(LegacyImportBatch $batch, int $companyId): void
    {
        $this->eachLegacy('habilitacion', function (object $row) use ($batch, $companyId): void {
            $player = $this->target($companyId, 'persona', $row->idpersona, Player::class);
            $team = $this->target($companyId, 'equipo', $row->idequipo, Team::class);
            $tournament = $this->target($companyId, 'torneo', $row->idtorneo, Tournament::class);

            if (! $player || ! $team || ! $tournament) {
                $this->log($batch, 'habilitacion', $row->idhabilitacion, 'tournament_team_players', null, 'skipped', 'pending_review', 'Habilitacion con jugador/equipo/torneo no resoluble.');

                return;
            }

            $joinedAt = $this->normalizer->date($row->fechacreacion, '1900-01-01');
            $teamPlayer = TeamPlayer::query()
                ->where('company_id', $companyId)
                ->where('division_id', $tournament->division_id)
                ->where('team_id', $team->id)
                ->where('player_id', $player->id)
                ->first();

            if (! $teamPlayer) {
                $activeConflict = TeamPlayer::query()
                    ->where('company_id', $companyId)
                    ->where('division_id', $tournament->division_id)
                    ->where('player_id', $player->id)
                    ->where('status', TeamPlayer::STATUS_ACTIVE)
                    ->whereNull('deleted_at')
                    ->exists();

                $teamPlayer = TeamPlayer::query()->create([
                    'company_id' => $companyId,
                    'division_id' => $tournament->division_id,
                    'team_id' => $team->id,
                    'player_id' => $player->id,
                    'status' => $activeConflict ? TeamPlayer::STATUS_INACTIVE : ($this->normalizer->active($row->activo) ? TeamPlayer::STATUS_ACTIVE : TeamPlayer::STATUS_INACTIVE),
                    'joined_at' => $joinedAt,
                    'notes' => $this->normalizer->notes([
                        'legacy_idhabilitacion' => $row->idhabilitacion,
                        'conflicto_activo_pending_review' => $activeConflict ? 'si' : null,
                        'equipo_texto_legacy' => $row->equipo,
                    ]),
                ]);
                $this->reference($batch, 'habilitacion', $row->idhabilitacion, 'team_players', $teamPlayer->id, true);
            }

            $registration = $this->registrationFor($tournament, $team);

            if (! $registration) {
                $registration = $this->createHistoricalRegistration($companyId, $tournament, $team, 'Inscripcion reconstruida desde habilitacion legacy.');
            }

            $existing = TournamentTeamPlayer::query()
                ->where('tournament_id', $tournament->id)
                ->where('team_id', $team->id)
                ->where('player_id', $player->id)
                ->first();

            if ($existing) {
                $this->reference($batch, 'habilitacion', $row->idhabilitacion, 'tournament_team_players', $existing->id, false);
                $this->bump('habilitations.matched');

                return;
            }

            $habilitation = TournamentTeamPlayer::query()->create([
                'company_id' => $companyId,
                'tournament_id' => $tournament->id,
                'tournament_registration_id' => $registration->id,
                'team_id' => $team->id,
                'player_id' => $player->id,
                'team_player_id' => $teamPlayer->id,
                'status' => $this->normalizer->active($row->activo) ? TournamentTeamPlayer::STATUS_ENABLED : TournamentTeamPlayer::STATUS_DISABLED,
                'enabled_at' => $this->normalizer->datetime($row->fechacreacion, $row->horacreacion),
                'notes' => $this->normalizer->notes([
                    'legacy_idhabilitacion' => $row->idhabilitacion,
                    'legacy_idequipotorneo' => $row->idequipotorneo,
                    'categoria_legacy' => $row->categoria,
                    'serie_legacy' => $row->serie,
                ]),
            ]);

            if ($habilitation->status === TournamentTeamPlayer::STATUS_DISABLED) {
                $habilitation->forceFill(['deleted_at' => now()])->save();
            }

            $this->reference($batch, 'habilitacion', $row->idhabilitacion, 'tournament_team_players', $habilitation->id, true);
            $this->bump('habilitations.created');
        });
    }

    private function importTransferEvents(LegacyImportBatch $batch, int $companyId): void
    {
        $this->eachLegacy('solicitudpase', function (object $row) use ($batch, $companyId): void {
            $event = LegacyTransferEvent::query()->updateOrCreate(
                ['company_id' => $companyId, 'source_table' => 'solicitudpase', 'legacy_id' => $row->idsolicitudpase],
                [
                    'player_id' => $this->target($companyId, 'persona', $row->idpersona, Player::class)?->id,
                    'from_team_id' => $this->target($companyId, 'equipo', $row->idequipoA, Team::class)?->id,
                    'to_team_id' => $this->target($companyId, 'equipo', $row->idequipoD, Team::class)?->id,
                    'tournament_id' => $this->target($companyId, 'torneo', $row->idtorneo, Tournament::class)?->id,
                    'event_type' => 'solicitud_pase',
                    'status' => ((int) $row->estado === 1) ? 'accepted' : 'pending_review',
                    'payload' => (array) $row,
                    'event_at' => $this->normalizer->datetime($row->fechacreacion, $row->horacreacion),
                ]
            );
            $this->reference($batch, 'solicitudpase', $row->idsolicitudpase, 'legacy_transfer_events', $event->id, $event->wasRecentlyCreated);
        });

        $this->eachLegacy('traspaso', function (object $row) use ($batch, $companyId): void {
            $event = LegacyTransferEvent::query()->updateOrCreate(
                ['company_id' => $companyId, 'source_table' => 'traspaso', 'legacy_id' => $row->idtraspaso],
                [
                    'player_id' => $this->target($companyId, 'persona', $row->idpersona, Player::class)?->id,
                    'from_team_id' => $this->target($companyId, 'equipo', $row->idequipoA, Team::class)?->id,
                    'to_team_id' => $this->target($companyId, 'equipo', $row->idequipoD, Team::class)?->id,
                    'event_type' => 'traspaso',
                    'status' => 'historical',
                    'payload' => (array) $row,
                    'event_at' => $this->normalizer->datetime($row->fechacreacion, $row->horacreacion),
                ]
            );
            $this->reference($batch, 'traspaso', $row->idtraspaso, 'legacy_transfer_events', $event->id, $event->wasRecentlyCreated);
        });
    }

    private function registrationFor(Tournament $tournament, Team $team): ?TournamentRegistration
    {
        return TournamentRegistration::query()
            ->withTrashed()
            ->where('tournament_id', $tournament->id)
            ->where('team_id', $team->id)
            ->first();
    }

    private function createHistoricalRegistration(int $companyId, Tournament $tournament, Team $team, string $notes): TournamentRegistration
    {
        $now = now();
        $id = DB::table('tournament_registrations')->insertGetId([
            'company_id' => $companyId,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'team_id' => $team->id,
            'category_id' => $tournament->category_id,
            'team_number' => $this->nextTournamentRegistrationNumber($tournament->id, $tournament->category_id, 'unica'),
            'series' => 'unica',
            'status' => 'historical',
            'notes' => $notes,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $now,
        ]);

        return TournamentRegistration::query()->withTrashed()->findOrFail($id);
    }

    private function nextTournamentRegistrationNumber(int $tournamentId, ?int $categoryId, string $series): int
    {
        return (int) (TournamentRegistration::query()
            ->where('tournament_id', $tournamentId)
            ->where('category_id', $categoryId)
            ->where('series', $series)
            ->whereNull('deleted_at')
            ->max('team_number') ?? 0) + 1;
    }

    private function target(int $companyId, string $sourceTable, mixed $sourceId, string $modelClass): mixed
    {
        $reference = LegacyReference::query()
            ->where('source_system', self::SOURCE)
            ->where('company_id', $companyId)
            ->where('source_table', $sourceTable)
            ->where('source_id', (string) $sourceId)
            ->where('target_table', (new $modelClass)->getTable())
            ->latest('id')
            ->first();

        return $reference ? $this->modelQuery($modelClass)->find($reference->target_id) : null;
    }

    private function reference(LegacyImportBatch $batch, string $sourceTable, mixed $sourceId, string $targetTable, int $targetId, bool $createdTarget): void
    {
        $existing = LegacyReference::query()
            ->where('source_system', self::SOURCE)
            ->where('company_id', $batch->company_id)
            ->where('source_table', $sourceTable)
            ->where('source_id', (string) $sourceId)
            ->where('target_table', $targetTable)
            ->first();

        LegacyReference::query()->updateOrCreate(
            ['source_system' => self::SOURCE, 'company_id' => $batch->company_id, 'source_table' => $sourceTable, 'source_id' => (string) $sourceId, 'target_table' => $targetTable],
            ['batch_id' => $existing?->batch_id ?? ($createdTarget ? $batch->id : null), 'target_id' => $targetId, 'fingerprint' => sha1($targetTable.':'.$targetId)]
        );

        $this->log($batch, $sourceTable, $sourceId, $targetTable, $targetId, $existing ? 'matched' : 'created', 'ok', $existing ? 'Referencia legacy existente reutilizada.' : 'Referencia legacy creada.');
    }

    private function log(LegacyImportBatch $batch, string $sourceTable, mixed $sourceId, ?string $targetTable, ?int $targetId, string $action, string $status, ?string $message = null, ?array $payload = null): void
    {
        LegacyImportLog::query()->create([
            'batch_id' => $batch->id,
            'source_table' => $sourceTable,
            'source_id' => $sourceId === null ? null : (string) $sourceId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'imported_at' => now(),
        ]);
    }

    private function eachLegacy(string $table, callable $callback): void
    {
        if (! $this->legacyTableExists($table)) {
            return;
        }

        $this->legacy($table)->orderBy($this->legacyPrimaryKey($table))->chunk(500, function ($rows) use ($callback): void {
            foreach ($rows as $row) {
                $callback($row);
            }
        });
    }

    private function legacy(string $table): Builder
    {
        return DB::connection('legacy')->table($table);
    }

    private function legacyTableExists(string $table): bool
    {
        return Schema::connection('legacy')->hasTable($table);
    }

    private function legacyPrimaryKey(string $table): string
    {
        return match ($table) {
            'gestion' => 'idgestion',
            'tipo' => 'idtipo',
            'categoria' => 'idcategoria',
            'serie' => 'idserie',
            'torneo' => 'idtorneo',
            'equipo' => 'idequipo',
            'equipotorneo' => 'idequipotorneo',
            'persona' => 'idpersona',
            'habilitacion' => 'idhabilitacion',
            'solicitudpase' => 'idsolicitudpase',
            'traspaso' => 'idtraspaso',
            default => 'id',
        };
    }

    private function startBatch(int $companyId, string $mode, ?string $sourcePath): LegacyImportBatch
    {
        Company::query()->findOrFail($companyId);

        return LegacyImportBatch::query()->create([
            'source_system' => self::SOURCE,
            'source_path' => $sourcePath,
            'source_hash' => $sourcePath && is_file($sourcePath) ? hash_file('sha256', $sourcePath) : null,
            'company_id' => $companyId,
            'mode' => $mode,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    private function writeReport(LegacyImportBatch $batch, array $summary): string
    {
        $path = 'legacy-imports/mejillones-batch-'.$batch->id.'-'.Str::slug($batch->mode).'.json';
        Storage::disk('local')->put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function bump(string $key): void
    {
        data_set($this->summary, $key, (int) data_get($this->summary, $key, 0) + 1);
    }

    private function modelQuery(string $modelClass): mixed
    {
        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query;
    }
}
