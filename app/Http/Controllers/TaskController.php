<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * Log a unit of repair work against a ticket.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(TaskType::values())],
            'note' => ['nullable', 'string', 'max:2000'],
            'cost' => ['required', 'numeric', 'min:0'],
            'is_billable' => ['boolean'],
            'status' => ['nullable', 'string', Rule::in(TaskStatus::values())],
        ]);

        $task = $ticket->tasks()->create([
            'type' => $validated['type'],
            'note' => $validated['note'] ?? null,
            'cost' => round((float) $validated['cost'], 2),
            'is_billable' => $validated['is_billable'] ?? true,
            'status' => $validated['status'] ?? TaskStatus::New,
        ]);

        $this->resyncInvoice($ticket);

        return to_route('tickets.show', $ticket)
            ->with('success', "Logged {$task->type->value} task.");
    }

    /**
     * Update a logged task.
     */
    public function update(Request $request, Ticket $ticket, Task $task): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($task->ticket_id === $ticket->id, 404);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(TaskType::values())],
            'note' => ['nullable', 'string', 'max:2000'],
            'cost' => ['required', 'numeric', 'min:0'],
            'is_billable' => ['boolean'],
            'status' => ['required', 'string', Rule::in(TaskStatus::values())],
        ]);

        $task->update([
            'type' => $validated['type'],
            'note' => $validated['note'] ?? null,
            'cost' => round((float) $validated['cost'], 2),
            'is_billable' => $validated['is_billable'],
            'status' => $validated['status'],
        ]);

        $this->resyncInvoice($ticket);

        return to_route('tickets.show', $ticket)
            ->with('success', 'Task updated.');
    }

    /**
     * Remove a logged task.
     */
    public function destroy(Request $request, Ticket $ticket, Task $task): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($task->ticket_id === $ticket->id, 404);

        $task->delete();

        $this->resyncInvoice($ticket);

        return to_route('tickets.show', $ticket)
            ->with('success', 'Task removed.');
    }

    /**
     * Keep the ticket's invoice totals in sync after task changes.
     */
    private function resyncInvoice(Ticket $ticket): void
    {
        if (! $ticket->invoice()->exists()) {
            return;
        }

        $ticket->invoice
            ->fillTaskTotal()
            ->syncTotal()
            ->fillStatus()
            ->save();
    }
}
