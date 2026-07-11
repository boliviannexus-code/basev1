<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingAttendanceRequest;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Team;
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
                'attendances',
                'attendances as present_count' => fn ($query) => $query->where('present', true),
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

            Team::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'company_id'])
                ->each(function (Team $team) use ($meeting): void {
                    MeetingAttendance::query()->create([
                        'company_id' => $team->company_id,
                        'meeting_id' => $meeting->id,
                        'team_id' => $team->id,
                    ]);
                });

            return $meeting;
        });

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', 'Reunion iniciada correctamente.');
    }

    public function show(Meeting $meeting): View
    {
        $this->ensureVisible($meeting);
        $this->syncActiveTeams($meeting);

        $meeting->load([
            'company',
            'creator',
            'attendances' => fn ($query) => $query->with('team')->join('teams', 'teams.id', '=', 'meeting_attendances.team_id')
                ->orderBy('teams.name')
                ->select('meeting_attendances.*'),
        ]);

        return view('meetings.show', compact('meeting'));
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
        ]);

        $presentCount = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->where('present', true)
            ->count();
        $totalCount = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->count();

        return response()->json([
            'success' => true,
            'message' => $present ? 'Asistencia marcada.' : 'Asistencia retirada.',
            'data' => [
                'present' => $present,
                'attended_at' => $attendance->attended_at?->format('H:i'),
                'present_count' => $presentCount,
                'absent_count' => max(0, $totalCount - $presentCount),
                'total_count' => $totalCount,
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
            'attendances' => fn ($query) => $query->with('team')->join('teams', 'teams.id', '=', 'meeting_attendances.team_id')
                ->orderBy('teams.name')
                ->select('meeting_attendances.*'),
        ]);

        return $report->attendance($meeting);
    }

    private function ensureVisible(Meeting $meeting): void
    {
        abort_unless(auth()->user()?->can('meetings.view'), 403);
        abort_unless(CompanyContext::belongsToUser($meeting->company_id, auth()->user()), 403);
    }

    private function syncActiveTeams(Meeting $meeting): void
    {
        if ($meeting->isFinished()) {
            return;
        }

        Team::query()
            ->where('company_id', $meeting->company_id)
            ->where('is_active', true)
            ->whereNotIn('id', $meeting->attendances()->pluck('team_id'))
            ->get(['id', 'company_id'])
            ->each(function (Team $team) use ($meeting): void {
                MeetingAttendance::query()->create([
                    'company_id' => $team->company_id,
                    'meeting_id' => $meeting->id,
                    'team_id' => $team->id,
                ]);
            });
    }
}
