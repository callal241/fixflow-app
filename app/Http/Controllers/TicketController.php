<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Enums\ChecklistPhase;
use App\Enums\ChecklistStatus;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketStatus;
use App\Enums\TransactionMethod;
use App\Models\ChecklistItem;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Product;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Payments\PaymentProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
    ) {
    }

    /**
     * List the tickets for the acting user's business.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $tickets = Ticket::forBusiness()
            ->with(['device.customer:id,name,company', 'assignee:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('ticket_number', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhereHas('device', fn ($d) => $d
                            ->where('model', 'like', $like)
                            ->orWhere('serial_number', 'like', $like)
                            ->orWhere('imei', 'like', $like)
                            ->orWhereHas('customer', fn ($c) => $c
                                ->where('name', 'like', $like)
                                ->orWhere('phone', 'like', $like)))
                        ->orWhereHas('assignee', fn ($a) => $a->where('name', 'like', $like));
                });
            })
            ->latest()
            ->get();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'device' => $ticket->device?->model,
                'customer' => $ticket->device?->customer?->name,
                'company' => $ticket->device?->customer?->company,
                'assignee' => $ticket->assignee?->name,
                'due_date' => $ticket->due_date?->toDateString(),
                'created_at' => $ticket->created_at?->toDateTimeString(),
            ]),
            'search' => $search,
        ]);
    }

    /**
     * Fast intake form: customer (existing or new), device (existing or new),
     * and the repair details — one screen, one submit.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->user()->business_id;

        // Preselects when arriving from a customer or device page.
        $customerId = $request->integer('customer') ?: null;
        $deviceId = $request->integer('device') ?: null;

        $customers = Customer::forBusiness($businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'company']);

        $preselectedCustomer = $customerId ? Customer::forBusiness($businessId)->find($customerId) : null;
        $preselectedDevice = $deviceId ? Device::forBusiness($businessId)->find($deviceId) : null;
        if ($preselectedDevice && !$preselectedCustomer) {
            $preselectedCustomer = $preselectedDevice->customer;
        }

        $devices = $preselectedCustomer
            ? $preselectedCustomer->devices()->get(['id', 'model', 'brand', 'serial_number', 'type', 'status'])
            : [];

        return Inertia::render('Tickets/Create', [
            'customers' => $customers,
            'devices' => $devices,
            'selected_customer_id' => $preselectedCustomer?->id,
            'selected_device_id' => $preselectedDevice?->id,
            'device_types' => DeviceType::values(),
            'priorities' => Priority::values(),
            'staff' => User::where('business_id', $businessId)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Create a repair ticket with its customer/device context in one shot.
     */
    public function store(Request $request): RedirectResponse
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'new_customer' => ['nullable', 'array'],
            'new_customer.name' => ['required_if:customer_id,null', 'required_without:customer_id', 'string', 'max:255'],
            'new_customer.company' => ['nullable', 'string', 'max:255'],
            'new_customer.email' => ['nullable', 'email', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:255'],
            'new_customer.address' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'new_device' => ['nullable', 'array'],
            'new_device.model' => ['required_if:device_id,null', 'required_without:device_id', 'string', 'max:255'],
            'new_device.model_number' => ['nullable', 'string', 'max:255'],
            'new_device.brand' => ['nullable', 'string', 'max:255'],
            'new_device.serial_number' => ['nullable', 'string', 'max:255'],
            'new_device.imei' => ['nullable', 'string', 'max:255'],
            'new_device.color' => ['nullable', 'string', 'max:255'],
            'new_device.storage' => ['nullable', 'string', 'max:255'],
            'new_device.carrier' => ['nullable', 'string', 'max:255'],
            'new_device.type' => ['required_if:device_id,null', 'string', 'in:' . implode(',', DeviceType::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'intake_type' => ['required', 'string', 'in:walk_in,appointment,mail_in,warranty,refurbishment,b2b'],
            'priority' => ['required', 'string', 'in:' . implode(',', Priority::values())],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket = DB::transaction(function () use ($validated, $businessId) {
            // 1. Customer (existing or newly typed).
            $customerId = $validated['customer_id'] ?? null;
            if ($customerId === null && ! empty($validated['new_customer']['name'])) {
                $customer = Customer::create(collect($validated['new_customer'])
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->all());
                $customerId = $customer->id;
            }
            $customer = $customerId ? Customer::forBusiness($businessId)->find($customerId) : null;
            abort_if($customer === null, 422, 'A customer is required for a repair ticket.');

            // 2. Device (existing or newly typed).
            $deviceId = $validated['device_id'] ?? null;
            if ($deviceId === null && ! empty($validated['new_device']['model'])) {
                $device = Device::create(collect($validated['new_device'])
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->all() + ['customer_id' => $customer->id]);
                $deviceId = $device->id;
            } else {
                $device = $deviceId ? Device::forBusiness($businessId)->find($deviceId) : null;
                abort_if($device === null, 422, 'A device is required for a repair ticket.');
            }

            // 3. The ticket itself (number assigned by the observer).
            return Ticket::create([
                'device_id' => $device->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'intake_type' => $validated['intake_type'],
                'priority' => $validated['priority'],
                'due_date' => $validated['due_date'] ?? null,
                'assignee_id' => $validated['assignee_id'] ?? null,
            ]);
        });

        return to_route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} created.");
    }

    /**
     * Show a single ticket: device, customer, tasks, parts, invoice panel.
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $ticket->load(['device.customer:id,name,company,phone,email', 'assignee:id,name']);

        $tasks = $ticket->tasks()->latest()->get()->map(fn (Task $task) => [
            'id' => $task->id,
            'type' => $task->type->value,
            'note' => $task->note,
            'cost' => (float) $task->cost,
            'is_billable' => $task->is_billable,
            'status' => $task->status->value,
            'approved_at' => $task->approved_at?->toDateTimeString(),
            'created_at' => $task->created_at?->toDateString(),
        ]);

        // Pre/post-repair checklist (QC documentation, not billable work).
        // Order pre_repair first (workflow order), not alphabetically.
        $checklistItems = $ticket->checklistItems()
            ->with('checkedBy:id,name')
            ->orderByRaw("case phase when 'pre_repair' then 0 else 1 end")
            ->orderBy('id')
            ->get()
            ->map(fn (ChecklistItem $item) => [
                'id' => $item->id,
                'label' => $item->label,
                'note' => $item->note,
                'phase' => $item->phase->value,
                'status' => $item->status->value,
                'checked_by' => $item->checkedBy?->name,
                'checked_at' => $item->checked_at?->toDateTimeString(),
            ]);

        $orders = $ticket->orders()
            ->with('product:id,name,sku,price,stock')
            ->latest()
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'name' => $order->name,
                'url' => $order->url,
                'supplier' => $order->supplier,
                'quantity' => $order->quantity,
                'price' => (float) $order->price,
                'cost' => (float) $order->cost,
                'is_billable' => $order->is_billable,
                'status' => $order->status->value,
                'product_id' => $order->product_id,
                'product' => $order->product ? [
                    'id' => $order->product->id,
                    'name' => $order->product->name,
                    'sku' => $order->product->sku,
                    'price' => (float) $order->product->price,
                    'stock' => $order->product->stock,
                ] : null,
            ]);

        // Catalog the shop can sell against this ticket.
        $products = Product::forBusiness($request->user()->business_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'stock']);

        $invoice = $ticket->invoice()->first();
        $taskTotal = (float) $ticket->tasks()->billable()->sum('cost');
        $orderTotal = (float) $ticket->orders()->billable()->sum('cost');

        $invoicePayload = null;
        if ($invoice !== null) {
            $invoice->load(['transactions', 'adjustments']);
            $invoicePayload = [
                'id' => $invoice->id,
                'status' => $invoice->status->value,
                'subtotal' => (float) $invoice->subtotal,
                'net_amount' => (float) $invoice->net_amount,
                'total' => (float) $invoice->total,
                'paid_amount' => (float) $invoice->paid_amount,
                'refunded_amount' => (float) $invoice->refunded_amount,
                'balance' => (float) $invoice->balance,
                'due_date' => $invoice->due_date?->toDateString(),
                'transactions' => $invoice->transactions->latest()->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type->value,
                    'method' => $t->method->value,
                    'amount' => (float) $t->amount,
                    'note' => $t->note,
                    'created_at' => $t->created_at?->toDateTimeString(),
                ]),
                'adjustments' => $invoice->adjustments->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type->value,
                    'reason' => $a->reason->value,
                    'amount' => $a->amount !== null ? (float) $a->amount : null,
                    'percentage' => $a->percentage !== null ? (float) $a->percentage : null,
                    'note' => $a->note,
                ]),
            ];
        }

        return Inertia::render('Tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'internal_notes' => $ticket->internal_notes,
                'intake_type' => $ticket->intake_type,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'due_date' => $ticket->due_date?->toDateString(),
                'assignee' => $ticket->assignee?->name,
                'created_at' => $ticket->created_at?->toDateTimeString(),
            ],
            'device' => [
                'id' => $ticket->device?->id,
                'model' => $ticket->device?->model,
                'brand' => $ticket->device?->brand,
                'serial_number' => $ticket->device?->serial_number,
                'imei' => $ticket->device?->imei,
                'color' => $ticket->device?->color,
                'storage' => $ticket->device?->storage,
                'carrier' => $ticket->device?->carrier,
                'status' => $ticket->device?->status->value,
            ],
            'customer' => $ticket->device?->customer ? [
                'id' => $ticket->device->customer->id,
                'name' => $ticket->device->customer->name,
                'company' => $ticket->device->customer->company,
                'phone' => $ticket->device->customer->phone,
                'email' => $ticket->device->customer->email,
            ] : null,
            'tasks' => $tasks,
            'checklist_items' => $checklistItems,
            'orders' => $orders,
            'products' => $products,
            'invoice' => $invoicePayload,
            'payment_provider' => $this->providerPayload($ticket),
            'totals' => [
                'task_total' => $taskTotal,
                'order_total' => $orderTotal,
                'subtotal' => $taskTotal + $orderTotal,
            ],
            'statuses' => TicketStatus::values(),
            'priorities' => Priority::values(),
            'task_types' => TaskType::values(),
            'task_statuses' => TaskStatus::values(),
            'checklist_phases' => ChecklistPhase::values(),
            'checklist_statuses' => ChecklistStatus::values(),
            'adjustment_types' => AdjustmentType::values(),
            'adjustment_reasons' => AdjustmentReason::values(),
            'transaction_methods' => TransactionMethod::values(),
            'staff' => User::where('business_id', $request->user()->business_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Describe the active payment provider for the ticket's business so the
     * checkout UI can show how payments are taken (and what is configured).
     */
    private function providerPayload(Ticket $ticket): array
    {
        $business = $ticket->business;
        $provider = $this->providers->for($business);

        return [
            'id' => $provider->id(),
            'name' => $provider->name(),
            'configured' => $provider->isConfigured(),
        ];
    }

    /**
     * Update the workflow fields of a ticket (status, priority, assignee, due date, notes).
     */
    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(TicketStatus::values())],
            'priority' => ['required', 'string', Rule::in(Priority::values())],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket->update($validated);

        // Keep the device's status in sync with the repair it carries.
        $this->syncDeviceStatus($ticket);

        return back()->with('success', "Ticket {$ticket->ticket_number} updated.");
    }

    /**
     * Mirror the ticket status onto its device so the bench view stays honest.
     */
    private function syncDeviceStatus(Ticket $ticket): void
    {
        $device = $ticket->device;
        if ($device === null) {
            return;
        }

        $device->status = match ($ticket->status) {
            TicketStatus::InProgress => DeviceStatus::UnderRepair,
            TicketStatus::OnHold => DeviceStatus::OnHold,
            TicketStatus::Resolved => DeviceStatus::Ready,
            TicketStatus::Closed => DeviceStatus::Delivered,
            default => $device->status,
        };
        $device->save();
    }
}
