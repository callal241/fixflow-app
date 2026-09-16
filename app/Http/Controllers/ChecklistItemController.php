<?php

namespace App\Http\Controllers;

use App\Enums\ChecklistPhase;
use App\Enums\ChecklistStatus;
use App\Models\ChecklistItem;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistItemController extends Controller
{
    /**
     * Add a checklist item (pre- or post-repair) to a ticket.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'phase' => ['required', 'string', Rule::in(ChecklistPhase::values())],
            'status' => ['nullable', 'string', Rule::in(ChecklistStatus::values())],
        ]);

        $status = ChecklistStatus::from($validated['status'] ?? ChecklistStatus::Pending->value);
        $item = $ticket->checklistItems()->create([
            'label' => $validated['label'],
            'note' => $validated['note'] ?? null,
            'phase' => $validated['phase'],
            'status' => $status,
            'checked_by_id' => $status === ChecklistStatus::Pending ? null : $request->user()->id,
            'checked_at' => $status === ChecklistStatus::Pending ? null : now(),
        ]);

        return to_route('tickets.show', $ticket)
            ->with('success', 'Checklist item added.');
    }

    /**
     * Update a checklist item (label, note, or its checked status).
     */
    public function update(Request $request, Ticket $ticket, ChecklistItem $item): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($item->ticket_id === $ticket->id, 404);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'phase' => ['required', 'string', Rule::in(ChecklistPhase::values())],
            'status' => ['required', 'string', Rule::in(ChecklistStatus::values())],
        ]);

        $status = ChecklistStatus::from($validated['status']);
        $item->update([
            'label' => $validated['label'],
            'note' => $validated['note'] ?? null,
            'phase' => $validated['phase'],
            'status' => $status,
            'checked_by_id' => $status === ChecklistStatus::Pending ? null : $request->user()->id,
            'checked_at' => $status === ChecklistStatus::Pending ? null : now(),
        ]);

        return to_route('tickets.show', $ticket)
            ->with('success', 'Checklist item updated.');
    }

    /**
     * Remove a checklist item.
     */
    public function destroy(Request $request, Ticket $ticket, ChecklistItem $item): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($item->ticket_id === $ticket->id, 404);

        $item->delete();

        return to_route('tickets.show', $ticket)
            ->with('success', 'Checklist item removed.');
    }
}
