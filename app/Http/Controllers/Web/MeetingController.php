<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\RequestMeetingPermissionRequest;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingAttendanceRequest;
use App\Models\LeagueSetting;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\TournamentRegistration;
use App\Services\MeetingPdfReportService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MeetingController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('meetings.view'), 403);

        $meetings = Meeting::query()
            ->with(['company', 'creator'])
            ->withCount([
                'attendances' => fn ($query) => $query->select(DB::raw('count(distinct team_id)')),
                'attendances as present_count' => fn ($query) => $query->where('present', true)->select(DB::raw('count(distinct team_id)')),
                'attendances as permission_count' => fn ($query) => $query->where('permission_requested', true)->select(DB::raw('count(distinct team_id)')),
            ])
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('meetings.index', compact('meetings'));
    }

    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        $companyId = CompanyContext::id(auth()->user());
        abort_unless($companyId !== null && $companyId > 0, 403);

        $meeting = DB::transaction(function () use ($request, $companyId): Meeting {
            $meeting = Meeting::query()->create([
                'company_id' => $companyId,
                'title' => $request->validated('title'),
                'meeting_date' => $request->validated('meeting_date'),
                'status' => Meeting::STATUS_OPEN,
                'started_at' => now(),
                'notes' => $request->validated('notes'),
                'created_by' => auth()->id(),
            ]);

            $this->activeTournamentRegistrations($companyId)
                ->each(function (TournamentRegistration $registration) use ($meeting): void {
                    MeetingAttendance::query()->create([
                        'company_id' => $registration->company_id,
                        'meeting_id' => $meeting->id,
                        'team_id' => $registration->team_id,
                        'tournament_registration_id' => $registration->id,
                    ]);
                });

            return $meeting;
        });

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', 'Reunion iniciada correctamente.');
    }

    private function activeTournamentRegistrations(int $companyId)
    {
        return TournamentRegistration::query()
            ->with(['team', 'category', 'tournament'])
            ->where('tournament_registrations.company_id', $companyId)
            ->where('tournament_registrations.status', 'registered')
            ->whereHas('team', fn ($query) => $query->where('is_active', true))
            ->whereHas('tournament', fn ($query) => $query->where('status', 'active')->where('is_active', true))
            ->join('teams', 'teams.id', '=', 'tournament_registrations.team_id')
            ->join('division_categories', 'division_categories.id', '=', 'tournament_registrations.category_id')
            ->orderBy('teams.name')
            ->orderBy('division_categories.name')
            ->select('tournament_registrations.*')
            ->get()
            ->unique('team_id')
            ->values();
    }

    public function show(Meeting $meeting): View
    {
        $this->ensureVisible($meeting);
        $this->syncActiveRegistrations($meeting);

        $meeting->load([
            'company',
            'company.leagueSetting',
            'creator',
            'attendances' => fn ($query) => $query
                ->with(['team.tournamentTeamPlayers.tournament', 'team.tournamentTeamPlayers.tournamentRegistration.category', 'tournamentRegistration.category', 'tournamentRegistration.tournament'])
                ->join('teams', 'teams.id', '=', 'meeting_attendances.team_id')
                ->leftJoin('tournament_registrations', 'tournament_registrations.id', '=', 'meeting_attendances.tournament_registration_id')
                ->leftJoin('division_categories', 'division_categories.id', '=', 'tournament_registrations.category_id')
                ->orderBy('teams.name')
                ->orderBy('division_categories.name')
                ->select('meeting_attendances.*'),
        ]);
        $this->collapseAttendancesByTeam($meeting);

        return view('meetings.show', compact('meeting'));
    }

    /*
     * The meeting attendance list is based on active tournament registrations,
     * not every active team in the league.
     */
    private function syncActiveRegistrations(Meeting $meeting): void
    {
        if ($meeting->isFinished()) {
            return;
        }

        $registrations = $this->activeTournamentRegistrations($meeting->company_id);
        $activeTeamIds = $registrations->pluck('team_id');

        $meeting->attendances()
            ->whereNotIn('team_id', $activeTeamIds)
            ->where('present', false)
            ->where('permission_requested', false)
            ->delete();

        $registrations->each(function (TournamentRegistration $registration) use ($meeting): void {
            $attendance = MeetingAttendance::query()
                ->where('meeting_id', $meeting->id)
                ->where('team_id', $registration->team_id)
                ->first();

            if ($attendance) {
                if ($attendance->tournament_registration_id === null) {
                    $attendance->update(['tournament_registration_id' => $registration->id]);
                }

                return;
            }

            MeetingAttendance::query()->create([
                    'company_id' => $registration->company_id,
                    'meeting_id' => $meeting->id,
                    'team_id' => $registration->team_id,
                    'tournament_registration_id' => $registration->id,
            ]);
        });
    }

    public function updateAttendance(UpdateMeetingAttendanceRequest $request, Meeting $meeting, MeetingAttendance $attendance): JsonResponse
    {
        $this->ensureVisible($meeting);

        if ($meeting->isFinished()) {
            return response()->json([
                'success' => false,
                'message' => 'La reunion ya fue finalizada.',
            ], 422);
        }

        abort_unless((int) $attendance->meeting_id === (int) $meeting->id, 404);

        $present = (bool) $request->validated('present');
        $attendance->update([
            'present' => $present,
            'attended_at' => $present ? now() : null,
            'marked_by' => $present ? auth()->id() : null,
            'permission_requested' => $present ? false : $attendance->permission_requested,
            'permission_requested_at' => $present ? null : $attendance->permission_requested_at,
            'permission_requested_by' => $present ? null : $attendance->permission_requested_by,
            'permission_reason' => $present ? null : $attendance->permission_reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => $present ? 'Asistencia marcada.' : 'Asistencia retirada.',
            'data' => $this->attendancePayload($meeting, $attendance->refresh()) + [
                'present' => $present,
                'attended_at' => $attendance->attended_at?->format('H:i'),
            ],
        ]);
    }

    public function requestPermission(RequestMeetingPermissionRequest $request, Meeting $meeting, MeetingAttendance $attendance): JsonResponse
    {
        $this->ensureVisible($meeting);

        if ($meeting->isFinished()) {
            return response()->json([
                'success' => false,
                'message' => 'La reunion ya fue finalizada.',
            ], 422);
        }

        abort_unless((int) $attendance->meeting_id === (int) $meeting->id, 404);

        $limit = (int) ($meeting->company?->leagueSetting?->max_meeting_permissions_per_team
            ?? LeagueSetting::query()->where('company_id', $meeting->company_id)->value('max_meeting_permissions_per_team')
            ?? 0);

        if ($limit <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'La liga no tiene permisos permitidos configurados.',
            ], 422);
        }

        $used = MeetingAttendance::query()
            ->where('company_id', $meeting->company_id)
            ->where('team_id', $attendance->team_id)
            ->where('permission_requested', true)
            ->whereKeyNot($attendance->id)
            ->count();

        if ($used >= $limit) {
            return response()->json([
                'success' => false,
                'message' => 'El equipo ya uso la cantidad maxima de permisos permitidos.',
            ], 422);
        }

        $attendance->update([
            'present' => false,
            'attended_at' => null,
            'marked_by' => null,
            'permission_requested' => true,
            'permission_requested_at' => now(),
            'permission_requested_by' => auth()->id(),
            'permission_reason' => $request->validated('permission_reason'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permiso solicitado correctamente.',
            'data' => $this->attendancePayload($meeting, $attendance->refresh()) + [
                'present' => false,
                'attended_at' => null,
            ],
        ]);
    }

    public function finish(Meeting $meeting): RedirectResponse
    {
        abort_unless(auth()->user()?->can('meetings.update'), 403);
        $this->ensureVisible($meeting);

        if (! $meeting->isFinished()) {
            $meeting->update([
                'status' => Meeting::STATUS_FINISHED,
                'finished_at' => now(),
                'finished_by' => auth()->id(),
            ]);
        }

        return redirect()
            ->route('meetings.index')
            ->with('success', 'Reunion finalizada. Se genero el reporte de asistencia.')
            ->with('report_url', route('meetings.pdf', $meeting));
    }

    public function pdf(Meeting $meeting, MeetingPdfReportService $report): Response
    {
        $this->ensureVisible($meeting);

        $meeting->load([
            'company',
            'creator',
            'finisher',
            'attendances' => fn ($query) => $query
                ->with(['team.tournamentTeamPlayers.tournament', 'team.tournamentTeamPlayers.tournamentRegistration.category', 'tournamentRegistration.category', 'tournamentRegistration.tournament'])
                ->join('teams', 'teams.id', '=', 'meeting_attendances.team_id')
                ->leftJoin('tournament_registrations', 'tournament_registrations.id', '=', 'meeting_attendances.tournament_registration_id')
                ->leftJoin('division_categories', 'division_categories.id', '=', 'tournament_registrations.category_id')
                ->orderBy('teams.name')
                ->orderBy('division_categories.name')
                ->select('meeting_attendances.*'),
        ]);
        $this->collapseAttendancesByTeam($meeting);

        return $report->attendance($meeting);
    }

    private function ensureVisible(Meeting $meeting): void
    {
        abort_unless(auth()->user()?->can('meetings.view'), 403);
        abort_unless(CompanyContext::belongsToUser($meeting->company_id, auth()->user()), 403);
    }

    private function attendancePayload(Meeting $meeting, MeetingAttendance $attendance): array
    {
        $presentCount = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->where('present', true)
            ->distinct('team_id')
            ->count('team_id');
        $permissionCount = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->where('permission_requested', true)
            ->distinct('team_id')
            ->count('team_id');
        $totalCount = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->distinct('team_id')
            ->count('team_id');

        return [
            'permission_requested' => (bool) $attendance->permission_requested,
            'permission_requested_at' => $attendance->permission_requested_at?->format('H:i'),
            'permission_count' => $permissionCount,
            'present_count' => $presentCount,
            'absent_count' => max(0, $totalCount - $presentCount - $permissionCount),
            'total_count' => $totalCount,
        ];
    }

    private function collapseAttendancesByTeam(Meeting $meeting): void
    {
        $registrationsByTeam = $this->activeTournamentRegistrations($meeting->company_id)
            ->groupBy('team_id');

        $attendances = $meeting->attendances
            ->groupBy('team_id')
            ->map(function ($teamAttendances) use ($registrationsByTeam): MeetingAttendance {
                /** @var MeetingAttendance $attendance */
                $attendance = $teamAttendances
                    ->sortByDesc(fn (MeetingAttendance $item): int => (int) $item->present)
                    ->sortByDesc(fn (MeetingAttendance $item): int => (int) $item->permission_requested)
                    ->first();

                $categories = $registrationsByTeam
                    ->get($attendance->team_id, collect())
                    ->map(fn (TournamentRegistration $registration): ?string => $registration->category?->name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                $attendance->setAttribute('represented_categories', $categories);

                return $attendance;
            })
            ->sortBy(fn (MeetingAttendance $attendance): string => $attendance->team?->name ?? '')
            ->values();

        $meeting->setRelation('attendances', $attendances);
    }
}
