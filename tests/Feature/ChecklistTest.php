<?php

use App\Enums\ChecklistPhase;
use App\Enums\ChecklistStatus;
use App\Models\Business;
use App\Models\ChecklistItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);
    $this->ticket = Ticket::factory()->create(['business_id' => $this->business->id]);
});

test('a pre-repair checklist item can be added to a ticket', function () {
    $this->actingAs($this->user)
        ->post(route('tickets.checklist-items.store', $this->ticket), [
            'label' => 'No cracks on screen',
            'note' => 'Checked under backlight',
            'phase' => 'pre_repair',
        ])
        ->assertRedirect(route('tickets.show', $this->ticket))
        ->assertSessionHas('success');

    $item = $this->ticket->checklistItems()->sole();
    expect($item->label)->toBe('No cracks on screen')
        ->and($item->note)->toBe('Checked under backlight')
        ->and($item->phase)->toBe(ChecklistPhase::PreRepair)
        ->and($item->status)->toBe(ChecklistStatus::Pending)
        ->and($item->business_id)->toBe($this->business->id)
        ->and($item->checked_by_id)->toBeNull()
        ->and($item->checked_at)->toBeNull();
});

test('a post-repair item checked as passed records who and when', function () {
    $this->actingAs($this->user)
        ->post(route('tickets.checklist-items.store', $this->ticket), [
            'label' => 'Screen displays correctly',
            'phase' => 'post_repair',
            'status' => 'passed',
        ]);

    $item = $this->ticket->checklistItems()->sole();
    expect($item->phase)->toBe(ChecklistPhase::PostRepair)
        ->and($item->status)->toBe(ChecklistStatus::Passed)
        ->and($item->checked_by_id)->toBe($this->user->id)
        ->and($item->checked_at)->not->toBeNull();
});

test('an item status can be updated and reset to pending', function () {
    $item = ChecklistItem::factory()->forTicket($this->ticket)
        ->create(['business_id' => $this->business->id, 'phase' => 'post_repair']);

    $this->actingAs($this->user)
        ->put(route('tickets.checklist-items.update', [$this->ticket, $item]), [
            'label' => $item->label,
            'phase' => 'post_repair',
            'status' => 'passed',
        ])
        ->assertRedirect(route('tickets.show', $this->ticket));

    $item->refresh();
    expect($item->status)->toBe(ChecklistStatus::Passed)
        ->and($item->checked_by_id)->toBe($this->user->id)
        ->and($item->checked_at)->not->toBeNull();

    // Reset back to pending clears the checker.
    $this->actingAs($this->user)
        ->put(route('tickets.checklist-items.update', [$this->ticket, $item]), [
            'label' => $item->label,
            'phase' => 'post_repair',
            'status' => 'pending',
        ]);

    $item->refresh();
    expect($item->status)->toBe(ChecklistStatus::Pending)
        ->and($item->checked_by_id)->toBeNull()
        ->and($item->checked_at)->toBeNull();
});

test('a checklist item can be removed', function () {
    $item = ChecklistItem::factory()->forTicket($this->ticket)
        ->create(['business_id' => $this->business->id]);

    $this->actingAs($this->user)
        ->delete(route('tickets.checklist-items.destroy', [$this->ticket, $item]))
        ->assertRedirect(route('tickets.show', $this->ticket));

    expect($this->ticket->checklistItems()->count())->toBe(0);
});

test('a label is required to add a checklist item', function () {
    $this->actingAs($this->user)->post(route('tickets.checklist-items.store', $this->ticket), [
        'label' => '',
        'phase' => 'pre_repair',
    ])->assertSessionHasErrors('label');

    expect($this->ticket->checklistItems()->count())->toBe(0);
});

test('phase and status must be valid values', function () {
    $this->actingAs($this->user)->post(route('tickets.checklist-items.store', $this->ticket), [
        'label' => 'Screen ok',
        'phase' => 'bogus_phase',
    ])->assertSessionHasErrors('phase');

    $this->actingAs($this->user)->post(route('tickets.checklist-items.store', $this->ticket), [
        'label' => 'Screen ok',
        'phase' => 'pre_repair',
        'status' => 'bogus_status',
    ])->assertSessionHasErrors('status');

    expect($this->ticket->checklistItems()->count())->toBe(0);
});

test('other business staff cannot touch a foreign ticket checklist', function () {
    $otherBusiness = Business::factory()->create();
    $outsider = User::factory()->admin()->create(['business_id' => $otherBusiness->id]);

    $this->actingAs($outsider)
        ->post(route('tickets.checklist-items.store', $this->ticket), [
            'label' => 'Nope',
            'phase' => 'pre_repair',
        ])
        ->assertStatus(403);

    $item = ChecklistItem::factory()->forTicket($this->ticket)
        ->create(['business_id' => $this->business->id]);

    $this->actingAs($outsider)
        ->put(route('tickets.checklist-items.update', [$this->ticket, $item]), [
            'label' => $item->label,
            'phase' => 'pre_repair',
            'status' => 'passed',
        ])
        ->assertStatus(403);

    $this->actingAs($outsider)
        ->delete(route('tickets.checklist-items.destroy', [$this->ticket, $item]))
        ->assertStatus(403);

    expect($this->ticket->checklistItems()->count())->toBe(1);
});

test('an item cannot be updated under a different ticket', function () {
    $item = ChecklistItem::factory()->forTicket($this->ticket)
        ->create(['business_id' => $this->business->id]);
    $otherTicket = Ticket::factory()->create(['business_id' => $this->business->id]);

    $this->actingAs($this->user)
        ->put(route('tickets.checklist-items.update', [$otherTicket, $item]), [
            'label' => $item->label,
            'phase' => 'pre_repair',
            'status' => 'passed',
        ])
        ->assertStatus(404);

    $this->actingAs($this->user)
        ->delete(route('tickets.checklist-items.destroy', [$otherTicket, $item]))
        ->assertStatus(404);

    expect($item->refresh()->status)->toBe(ChecklistStatus::Pending);
});

test('the ticket page exposes checklist items and option values', function () {
    ChecklistItem::factory()->forTicket($this->ticket)
        ->create(['business_id' => $this->business->id, 'phase' => 'pre_repair', 'label' => 'Battery holds charge']);
    ChecklistItem::factory()->forTicket($this->ticket)
        ->checked()
        ->create(['business_id' => $this->business->id, 'phase' => 'post_repair', 'label' => 'Screen ok']);

    $this->actingAs($this->user)
        ->get(route('tickets.show', $this->ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tickets/Show')
            ->has('checklist_items', 2)
            ->where('checklist_phases', ChecklistPhase::values())
            ->where('checklist_statuses', ChecklistStatus::values())
            ->where('checklist_items.0.label', 'Battery holds charge')
            ->where('checklist_items.0.phase', 'pre_repair')
            ->where('checklist_items.1.status', 'passed'));
});
