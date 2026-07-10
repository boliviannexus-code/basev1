<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PlayerRosterImportService
{
    public function __construct(
        private readonly PlayerService $players,
        private readonly TeamService $teams,
        private readonly TeamPlayerService $teamPlayers
    ) {}

    public function import(UploadedFile $file, bool $dryRun = false): array
    {
        $companyId = CompanyContext::id();

        if ($companyId === null || $companyId <= 0) {
            throw ValidationException::withMessages([
                'file' => 'Selecciona una liga activa para importar jugadores.',
            ]);
        }

        $rows = $this->readCsv($file);
        $summary = [
            'mode' => $dryRun ? 'dry_run' : 'import',
            'total_rows' => count($rows),
            'imported_rows' => 0,
            'failed_rows' => 0,
            'players_created' => 0,
            'players_existing' => 0,
            'teams_created' => 0,
            'teams_existing' => 0,
            'affiliations_created' => 0,
            'affiliations_existing' => 0,
            'duplicate_rows_ignored' => 0,
        ];
        $results = [];
        $state = [
            'teams' => [],
            'players' => [],
            'affiliations' => [],
        ];
        $lastRowsByCi = $this->lastRowsByCi($rows);

        foreach ($rows as $row) {
            $rowCi = Player::normalizeCi($this->clean($row['ci'] ?? ''));

            if ($rowCi !== '' && ($lastRowsByCi[$rowCi] ?? null) !== $row['_row']) {
                $summary['duplicate_rows_ignored']++;
                $results[] = [
                    'row' => $row['_row'],
                    'status' => 'ignored',
                    'messages' => ['Fila ignorada porque existe un registro posterior con el mismo CI.'],
                ];

                continue;
            }

            $result = [
                'row' => $row['_row'],
                'status' => $dryRun ? 'would_import' : 'imported',
                'messages' => [],
            ];

            DB::beginTransaction();

            try {
                $prepared = $this->prepareRow($row, $companyId);
                $result['player'] = $prepared['full_name'];
                $result['team'] = $prepared['team_name'];
                $result['division'] = $prepared['division']->name;

                $ciKey = Player::normalizeCi($prepared['ci']);

                $rowStats = $this->processPreparedRow($prepared, $companyId, $dryRun, $state);
                $summary = $this->mergeSummary($summary, $rowStats);
                $summary['imported_rows']++;
                $result['messages'] = $rowStats['messages'];

                if ($dryRun) {
                    DB::rollBack();
                } else {
                    DB::commit();
                }
            } catch (Throwable $exception) {
                DB::rollBack();

                $summary['failed_rows']++;
                $result['status'] = 'skipped';
                $result['messages'] = [$this->messageFor($exception)];
            }

            $results[] = $result;
        }

        return [
            'summary' => $summary,
            'rows' => $results,
        ];
    }

    private function processPreparedRow(array $row, int $companyId, bool $dryRun, array &$state): array
    {
        $messages = [];
        $teamKey = $this->teamKey($row['team_name']);
        $ciKey = Player::normalizeCi($row['ci']);
        $team = $this->teamFromState($state, $teamKey) ?? $this->findTeam($row['team_name'], $companyId);
        $player = $this->playerFromState($state, $ciKey) ?? $this->players->findByCi($row['ci']);
        $existingAffiliation = $player instanceof Player
            ? ($this->affiliationFromState($state, $player, $row['division']) ?? $this->activeAffiliation($player, $row['division']))
            : null;

        if ($existingAffiliation && (! $team instanceof Team || (int) $existingAffiliation->team_id !== (int) $team->id)) {
            throw ValidationException::withMessages([
                'player_id' => 'El jugador ya pertenece a '.$existingAffiliation->team?->name.' en la division '.$row['division']->name.'.',
            ]);
        }

        if ($dryRun) {
            if (! $team) {
                $messages[] = 'Se crearia el equipo.';
            }

            if (! $player) {
                $messages[] = 'Se crearia el jugador.';
            }

            if (! $existingAffiliation) {
                $messages[] = 'Se afiliaria el jugador al equipo en la division.';
            }

            if (! $team) {
                $state['teams'][$teamKey] = true;
            }

            if (! $player) {
                $state['players'][$ciKey] = true;
            }

            if (! $existingAffiliation) {
                $state['affiliations'][$ciKey.'|'.$row['division']->id] = true;
            }

            return [
                'players_created' => $player ? 0 : 1,
                'players_existing' => $player ? 1 : 0,
                'teams_created' => $team ? 0 : 1,
                'teams_existing' => $team ? 1 : 0,
                'affiliations_created' => $existingAffiliation ? 0 : 1,
                'affiliations_existing' => $existingAffiliation ? 1 : 0,
                'messages' => $messages ?: ['Sin cambios nuevos.'],
            ];
        }

        $teamCreated = false;
        $playerCreated = false;

        if (! $team) {
            $team = $this->teams->create([
                'company_id' => $companyId,
                'name' => $row['team_name'],
                'founded_at' => now()->toDateString(),
                'notes' => 'Creado por importacion CSV.',
                'is_active' => true,
            ]);
            $teamCreated = true;
            $state['teams'][$teamKey] = $team;
            $messages[] = 'Equipo creado.';
        } elseif ($team instanceof Team) {
            $state['teams'][$teamKey] = $team;
        }

        if (! $player) {
            $player = $this->players->create([
                'company_id' => $companyId,
                'ci' => $row['ci'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'maternal_name' => $row['maternal_name'],
                'birth_date' => $row['birth_date']->toDateString(),
                'is_active' => true,
            ]);
            $playerCreated = true;
            $state['players'][$ciKey] = $player;
            $messages[] = 'Jugador creado.';
        } elseif ($player instanceof Player) {
            $state['players'][$ciKey] = $player;
        }

        $existingAffiliation = $this->affiliationFromState($state, $player, $row['division']) ?? $this->activeAffiliation($player, $row['division']);

        if ($existingAffiliation && (int) $existingAffiliation->team_id !== (int) $team->id) {
            throw ValidationException::withMessages([
                'player_id' => 'El jugador ya pertenece a '.$existingAffiliation->team?->name.' en la division '.$row['division']->name.'.',
            ]);
        }

        $teamPlayer = $this->teamPlayers->affiliate([
            'team_id' => $team->id,
            'division_id' => $row['division']->id,
            'player_id' => $player->id,
            'joined_at' => now()->toDateString(),
            'notes' => 'Afiliado por importacion CSV.',
        ]);
        $affiliationCreated = $teamPlayer->wasRecentlyCreated;
        $state['affiliations'][$ciKey.'|'.$row['division']->id] = $teamPlayer;

        if ($affiliationCreated) {
            $messages[] = 'Afiliacion creada.';
        }

        return [
            'players_created' => $playerCreated ? 1 : 0,
            'players_existing' => $playerCreated ? 0 : 1,
            'teams_created' => $teamCreated ? 1 : 0,
            'teams_existing' => $teamCreated ? 0 : 1,
            'affiliations_created' => $affiliationCreated ? 1 : 0,
            'affiliations_existing' => $affiliationCreated ? 0 : 1,
            'messages' => $messages ?: ['Registro ya existente.'],
        ];
    }

    private function prepareRow(array $row, int $companyId): array
    {
        $firstName = $this->clean($row['nombre'] ?? '');
        $paternalName = $this->clean($row['paterno'] ?? '');
        $maternalName = $this->clean($row['materno'] ?? '');
        $ci = str($this->clean($row['ci'] ?? ''))->upper()->toString();
        $teamName = Team::formatName($this->clean($row['equipo'] ?? ''));
        $divisionName = $this->clean($row['division'] ?? '');
        $birthDate = $this->parseDate($this->clean($row['fecha_nacimiento'] ?? ''));

        if ($firstName === '') {
            throw new RuntimeException('El nombre es obligatorio.');
        }

        if ($paternalName === '' && $maternalName === '') {
            throw new RuntimeException('Registra al menos apellido paterno o materno.');
        }

        if (Player::normalizeCi($ci) === '') {
            throw new RuntimeException('El CI es obligatorio.');
        }

        if ($teamName === '') {
            throw new RuntimeException('El equipo es obligatorio.');
        }

        if ($divisionName === '') {
            throw new RuntimeException('La division es obligatoria.');
        }

        if (! $birthDate || $birthDate->isToday() || $birthDate->isFuture()) {
            throw new RuntimeException('La fecha de nacimiento debe ser anterior a hoy.');
        }

        $division = $this->findDivision($divisionName, $companyId);

        if (! $division) {
            throw new RuntimeException('No existe la division '.$divisionName.'.');
        }

        return [
            'ci' => $ci,
            'first_name' => $firstName,
            'last_name' => $paternalName,
            'maternal_name' => $maternalName,
            'full_name' => trim($firstName.' '.$paternalName.' '.$maternalName),
            'birth_date' => $birthDate,
            'team_name' => $teamName,
            'division' => $division,
        ];
    }

    private function readCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages(['file' => 'No se pudo leer el archivo.']);
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'No se pudo abrir el archivo.']);
        }

        $firstLine = fgets($handle) ?: '';
        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);

        if (! is_array($header)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'El archivo no tiene cabecera.']);
        }

        $columns = $this->mapHeader($header);
        $rows = [];
        $rowNumber = 1;

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($this->isEmptyLine($line)) {
                continue;
            }

            $row = ['_row' => $rowNumber];

            foreach ($columns as $index => $key) {
                if ($key) {
                    $row[$key] = $line[$index] ?? '';
                }
            }

            $rows[] = $row;
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'El archivo no contiene filas para importar.']);
        }

        return $rows;
    }

    private function lastRowsByCi(array $rows): array
    {
        $lastRows = [];

        foreach ($rows as $row) {
            $ci = Player::normalizeCi($this->clean($row['ci'] ?? ''));

            if ($ci !== '') {
                $lastRows[$ci] = $row['_row'];
            }
        }

        return $lastRows;
    }

    private function mapHeader(array $header): array
    {
        $aliases = [
            'nombre' => ['nombre', 'nombres', 'name'],
            'paterno' => ['paterno', 'apellidopaterno', 'primerapellido'],
            'materno' => ['materno', 'apellidomaterno', 'segundoapellido'],
            'ci' => ['ci', 'carnet', 'documento', 'cedula'],
            'fecha_nacimiento' => ['fechanacimiento', 'nacimiento', 'birthdate'],
            'equipo' => ['equipo', 'team', 'club'],
            'division' => ['division'],
        ];
        $mapped = [];
        $found = [];

        foreach ($header as $index => $column) {
            $normalized = $this->headerKey((string) $column);
            $key = null;

            foreach ($aliases as $target => $options) {
                if (in_array($normalized, $options, true)) {
                    $key = $target;
                    $found[$target] = true;
                    break;
                }
            }

            $mapped[$index] = $key;
        }

        $missing = collect(['nombre', 'paterno', 'materno', 'ci', 'fecha_nacimiento', 'equipo', 'division'])
            ->reject(fn (string $key): bool => isset($found[$key]))
            ->values()
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Faltan columnas obligatorias: '.implode(', ', $missing).'.',
            ]);
        }

        return $mapped;
    }

    private function detectDelimiter(string $line): string
    {
        return collect([',', ';', "\t"])
            ->sortByDesc(fn (string $delimiter): int => count(str_getcsv($line, $delimiter)))
            ->first() ?? ',';
    }

    private function parseDate(string $value): ?CarbonInterface
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (int) $value > 20000 && (int) $value < 60000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->startOfDay();
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
                $date = null;
            }

            if ($date && $date->format($format) === $value) {
                return $date->startOfDay();
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function findTeam(string $name, int $companyId): ?Team
    {
        $normalized = Team::normalizeName($name);
        $matchKey = Team::matchKey($name);

        return Team::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($normalized, $matchKey): void {
                $query->where('name_normalized', $normalized)
                    ->orWhere('name_match_key', $matchKey);
            })
            ->orderByRaw('CASE WHEN name_normalized = ? THEN 0 ELSE 1 END', [$normalized])
            ->first();
    }

    private function findDivision(string $name, int $companyId): ?Division
    {
        return Division::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [str($name)->squish()->lower()->toString()])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();
    }

    private function activeAffiliation(Player $player, Division $division): ?TeamPlayer
    {
        return TeamPlayer::query()
            ->with('team')
            ->where('company_id', $division->company_id)
            ->where('division_id', $division->id)
            ->where('player_id', $player->id)
            ->where('status', TeamPlayer::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->first();
    }

    private function teamFromState(array $state, string $teamKey): mixed
    {
        return $state['teams'][$teamKey] ?? null;
    }

    private function playerFromState(array $state, string $ciKey): mixed
    {
        return $state['players'][$ciKey] ?? null;
    }

    private function affiliationFromState(array $state, mixed $player, Division $division): mixed
    {
        if (! $player instanceof Player) {
            return null;
        }

        return $state['affiliations'][Player::normalizeCi($player->ci).'|'.$division->id] ?? null;
    }

    private function teamKey(string $name): string
    {
        return Team::matchKey($name);
    }

    private function mergeSummary(array $summary, array $rowStats): array
    {
        foreach (['players_created', 'players_existing', 'teams_created', 'teams_existing', 'affiliations_created', 'affiliations_existing'] as $key) {
            $summary[$key] += $rowStats[$key] ?? 0;
        }

        return $summary;
    }

    private function messageFor(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return collect($exception->errors())->flatten()->first() ?? 'Fila no valida.';
        }

        return $exception->getMessage() ?: 'Fila no valida.';
    }

    private function clean(string $value): string
    {
        return str($value)->replace("\xEF\xBB\xBF", '')->squish()->toString();
    }

    private function headerKey(string $value): string
    {
        return str($value)
            ->replace("\xEF\xBB\xBF", '')
            ->squish()
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }

    private function isEmptyLine(array $line): bool
    {
        return collect($line)->every(fn ($value): bool => trim((string) $value) === '');
    }
}
