<?php

namespace App\Observers;

use App\Models\Ticket;

class TicketObserver
{
    /**
     * Handle the Ticket "created" event.
     */
    public function created(Ticket $ticket): void
    {
        // Assign the stable, human-friendly ticket number (FF-00001) now that
        // the auto-increment id exists. saveQuietly avoids re-entering this
        // observer's updated() path.
        if ($ticket->ticket_number === null) {
            $ticket->forceFill(['ticket_number' => 'FF-' . str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT)])
                ->saveQuietly();
        }

        $ticket->load('device');
        $ticket->device->fillTicketCounts()->save();
    }

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        // Only update counts if status changed
        if ($ticket->wasChanged(['status'])) {
            $ticket->load('device');
            $ticket->device->fillTicketCounts()->save();
        }
    }

    /**
     * Handle the Ticket "deleted" event.
     */
    public function deleted(Ticket $ticket): void
    {
        $ticket->load('device');
        $ticket->device->fillTicketCounts()->save();
    }
}
