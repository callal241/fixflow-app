<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Models\Customer;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    /**
     * List the devices for the acting user's business, with optional search.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $devices = Device::forBusiness()
            ->with('customer:id,name,company')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('model', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('serial_number', 'like', $like)
                        ->orWhere('imei', 'like', $like);
                });
            })
            ->latest()
            ->get();

        return Inertia::render('Devices/Index', [
            'devices' => $devices->map(fn (Device $d) => [
                'id' => $d->id,
                'model' => $d->model,
                'brand' => $d->brand,
                'serial_number' => $d->serial_number,
                'imei' => $d->imei,
                'type' => $d->type->value,
                'status' => $d->status->value,
                'customer' => $d->customer?->name,
                'company' => $d->customer?->company,
                'tickets_count' => (int) $d->total_tickets_count,
            ]),
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new device.
     */
    public function create(Request $request, ?Customer $customer = null): Response
    {
        $customers = Customer::forBusiness()
            ->orderBy('name')
            ->get(['id', 'name', 'company']);

        return Inertia::render('Devices/Create', [
            'customers' => $customers,
            'default_customer_id' => $customer?->id,
        ]);
    }

    /**
     * Store a new device for the acting user's business.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        // The business is stamped automatically by the BelongsToBusiness trait.
        $device = Device::create($validated);

        return to_route('devices.show', $device)
            ->with('success', "Device \"{$device->model}\" added.");
    }

    /**
     * Show a single device with its tickets.
     */
    public function show(Request $request, Device $device): Response
    {
        abort_unless($device->business_id === $request->user()->business_id, 403);

        $device->load(['customer:id,name,company,phone', 'tickets']);

        return Inertia::render('Devices/Show', [
            'device' => [
                'id' => $device->id,
                'model' => $device->model,
                'model_number' => $device->model_number,
                'brand' => $device->brand,
                'serial_number' => $device->serial_number,
                'imei' => $device->imei,
                'color' => $device->color,
                'storage' => $device->storage,
                'carrier' => $device->carrier,
                'type' => $device->type->value,
                'status' => $device->status->value,
                'purchase_date' => $device->purchase_date?->toDateString(),
                'warranty_expire_date' => $device->warranty_expire_date?->toDateString(),
                'has_warranty' => $device->hasWarranty(),
                'customer' => $device->customer ? [
                    'id' => $device->customer->id,
                    'name' => $device->customer->name,
                    'company' => $device->customer->company,
                    'phone' => $device->customer->phone,
                ] : null,
            ],
            'tickets' => $device->tickets()->latest()->get()->map(fn ($t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'title' => $t->title,
                'status' => $t->status->value,
                'priority' => $t->priority->value,
                'created_at' => $t->created_at?->toDateString(),
            ]),
        ]);
    }

    /**
     * Show the form for editing a device.
     */
    public function edit(Request $request, Device $device): Response
    {
        abort_unless($device->business_id === $request->user()->business_id, 403);

        $customers = Customer::forBusiness()
            ->orderBy('name')
            ->get(['id', 'name', 'company']);

        return Inertia::render('Devices/Edit', [
            'device' => [
                'id' => $device->id,
                'customer_id' => $device->customer_id,
                'model' => $device->model,
                'model_number' => $device->model_number,
                'brand' => $device->brand,
                'serial_number' => $device->serial_number,
                'imei' => $device->imei,
                'color' => $device->color,
                'storage' => $device->storage,
                'carrier' => $device->carrier,
                'type' => $device->type->value,
                'status' => $device->status->value,
                'purchase_date' => $device->purchase_date?->toDateString(),
                'warranty_expire_date' => $device->warranty_expire_date?->toDateString(),
            ],
            'customers' => $customers,
        ]);
    }

    /**
     * Update an existing device.
     */
    public function update(Request $request, Device $device): RedirectResponse
    {
        abort_unless($device->business_id === $request->user()->business_id, 403);

        $device->update($this->validated($request));

        return to_route('devices.show', $device)
            ->with('success', "Device \"{$device->model}\" updated.");
    }

    /**
     * Validate and return the device payload.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'model' => ['required', 'string', 'max:255'],
            'model_number' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'imei' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', DeviceType::values())],
            'status' => ['required', 'string', 'in:' . implode(',', DeviceStatus::values())],
            'purchase_date' => ['nullable', 'date'],
            'warranty_expire_date' => ['nullable', 'date'],
        ]);
    }
}
