<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SpaceApprovalController extends Controller
{
    public function index(): View
    {
        Gate::authorize('spaces.approve');

        $spaces = Space::query()
            ->with(['company', 'spaceMode', 'privateSpaceType', 'sharedSpaceType', 'approvedBy'])
            ->whereIn('status', ['completed', 'needs_corrections', 'in_review', 'approved', 'active', 'inactive'])
            ->latest('updated_at')
            ->paginate(15);

        return view('admin.spaces.approvals', [
            'spaces' => $spaces,
        ]);
    }

    public function show(Space $space): View
    {
        Gate::authorize('spaces.approve');

        $space->load([
            'company',
            'spaceMode',
            'privateSpaceType',
            'sharedSpaceType',
            'photos',
            'location',
            'generalServices',
            'rooms.bathroomType',
            'rooms.beds.bedType',
            'rooms.roomServices',
            'rooms.photos',
            'reviewNotes.user',
            'approvedBy',
        ]);

        return view('admin.spaces.show', [
            'space' => $space,
        ]);
    }

    public function approve(Space $space): RedirectResponse
    {
        Gate::authorize('spaces.approve');
        abort_unless($space->status === 'completed', 422);

        $space->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $space->reviewNotes()->create([
            'user_id' => auth()->id(),
            'type' => 'approval',
            'message' => 'Alojamiento aprobado.',
        ]);

        return back()->with('success', 'Alojamiento aprobado correctamente.');
    }

    public function requestCorrections(Request $request, Space $space): RedirectResponse
    {
        Gate::authorize('spaces.approve');
        abort_unless(in_array($space->status, ['completed', 'needs_corrections'], true), 422);

        $data = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $space->reviewNotes()->create([
            'user_id' => auth()->id(),
            'type' => 'correction',
            'message' => $data['message'],
        ]);

        $space->update([
            'status' => 'needs_corrections',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return back()->with('success', 'Correcciones enviadas correctamente.');
    }

    public function suspendForReview(Request $request, Space $space): RedirectResponse
    {
        Gate::authorize('spaces.approve');
        abort_unless(in_array($space->status, ['completed', 'needs_corrections', 'approved', 'active', 'inactive'], true), 422);

        $data = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $space->reviewNotes()->create([
            'user_id' => auth()->id(),
            'type' => 'suspension',
            'message' => $data['message'],
        ]);

        $space->update([
            'status' => 'in_review',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return back()->with('success', 'Alojamiento suspendido y enviado a revision correctamente.');
    }
}
