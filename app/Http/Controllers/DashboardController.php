<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Product;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the business dashboard.
     *
     * Every figure is scoped to the acting user's business so each shop only
     * ever sees its own data.
     */
    public function index(Request $request): Response
    {
        $business = $request->user()->business;
        $businessId = $request->user()->business_id;

        return Inertia::render('Dashboard', [
            'business' => $business,

            'stats' => [
                'open_tickets' => Ticket::forBusiness($businessId)->pending()->count(),
                'ready_devices' => Device::forBusiness($businessId)->ofStatus(DeviceStatus::Ready)->count(),
                'customers' => Customer::forBusiness($businessId)->count(),
                'low_stock_products' => Product::forBusiness($businessId)->active()->lowStock()->count(),
            ],

            'recent_tickets' => Ticket::forBusiness($businessId)
                ->with(['device' => fn ($q) => $q->with('customer'), 'assignee'])
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Ticket $ticket) => [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'status' => $ticket->status->value,
                    'priority' => $ticket->priority->value,
                    'device' => $ticket->device?->model,
                    'customer' => $ticket->device?->customer?->name,
                    'assignee' => $ticket->assignee?->name,
                    'created_at' => $ticket->created_at?->toDateTimeString(),
                ]),

            'low_stock_products' => Product::forBusiness($businessId)
                ->active()
                ->lowStock()
                ->with('category:id,name')
                ->orderBy('stock')
                ->limit(6)
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock' => $product->stock,
                    'reorder_level' => $product->reorder_level,
                    'category' => $product->category?->name,
                ]),
        ]);
    }
}
