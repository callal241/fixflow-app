<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * List the customers for the acting user's business, with optional search.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $customers = Customer::forBusiness()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('company', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'company' => $c->company,
                'email' => $c->email,
                'phone' => $c->phone,
                'is_business' => $c->company !== null && $c->company !== '',
                'device_count' => (int) $c->total_devices_count,
            ]),
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    /**
     * Store a new customer for the acting user's business.
     */
    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->validated($request));

        return to_route('customers.show', $customer)
            ->with('success', "Customer \"{$customer->name}\" added.");
    }

    /**
     * Show a single customer with their devices and tickets.
     */
    public function show(Request $request, Customer $customer): Response
    {
        abort_unless($customer->business_id === $request->user()->business_id, 403);

        $customer->load(['devices' => fn ($q) => $q->withCount('tickets')->orderBy('id')]);

        $tickets = $customer->tickets()
            ->with('device:id,model')
            ->latest()
            ->take(100)
            ->get();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'company' => $customer->company,
                'vat_number' => $customer->vat_number,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'note' => $customer->note,
                'is_business' => $customer->company !== null && $customer->company !== '',
                'created_at' => $customer->created_at?->toDateTimeString(),
            ],
            'devices' => $customer->devices->map(fn ($d) => [
                'id' => $d->id,
                'model' => $d->model,
                'brand' => $d->brand,
                'serial_number' => $d->serial_number,
                'imei' => $d->imei,
                'type' => $d->type->value,
                'status' => $d->status->value,
                'tickets_count' => (int) $d->tickets_count,
            ]),
            'tickets' => $tickets->map(fn ($t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'title' => $t->title,
                'status' => $t->status->value,
                'priority' => $t->priority->value,
                'device' => $t->device?->model,
                'created_at' => $t->created_at?->toDateString(),
            ]),
        ]);
    }

    /**
     * Show the form for editing a customer.
     */
    public function edit(Request $request, Customer $customer): Response
    {
        abort_unless($customer->business_id === $request->user()->business_id, 403);

        return Inertia::render('Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'company' => $customer->company,
                'vat_number' => $customer->vat_number,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'note' => $customer->note,
            ],
        ]);
    }

    /**
     * Update an existing customer.
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->business_id === $request->user()->business_id, 403);

        $customer->update($this->validated($request));

        return to_route('customers.show', $customer)
            ->with('success', "Customer \"{$customer->name}\" updated.");
    }

    /**
     * Validate and return the customer payload.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);
    }
}
