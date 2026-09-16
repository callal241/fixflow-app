<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Product;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * App-wide, business-scoped search across the core record types.
     *
     * Powers the command palette (Ctrl/Cmd+K). Each group is capped so a
     * keystroke stays fast even on a busy shop, and every row is scoped to
     * the acting user's business so tenants never see each other's data.
     */
    public function index(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $q = trim((string) $request->query('q', ''));

        // Too short to be useful — return empty groups rather than scanning.
        if (mb_strlen($q) < 2) {
            return response()->json($this->empty());
        }

        $like = '%' . $q . '%';

        $tickets = Ticket::forBusiness($businessId)
            ->with(['device.customer:id,name', 'assignee:id,name'])
            ->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)
                    ->orWhere('ticket_number', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status->value,
                'device' => $ticket->device?->model,
                'customer' => $ticket->device?->customer?->name,
                'url' => route('tickets.show', $ticket),
            ]);

        $customers = Customer::forBusiness($businessId)
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'company' => $customer->company,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'url' => route('customers.show', $customer),
            ]);

        $devices = Device::forBusiness($businessId)
            ->with('customer:id,name')
            ->where(function ($query) use ($like) {
                $query->where('model', 'like', $like)
                    ->orWhere('model_number', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('imei', 'like', $like)
                    ->orWhere('brand', 'like', $like);
            })
            ->orderBy('model')
            ->limit(8)
            ->get()
            ->map(fn (Device $device) => [
                'id' => $device->id,
                'model' => $device->model,
                'model_number' => $device->model_number,
                'serial_number' => $device->serial_number,
                'imei' => $device->imei,
                'status' => $device->status->value,
                'customer' => $device->customer?->name,
                'url' => route('devices.show', $device),
            ]);

        $products = Product::forBusiness($businessId)
            ->active()
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'url' => route('products.index'),
            ]);

        return response()->json([
            'tickets' => $tickets,
            'customers' => $customers,
            'devices' => $devices,
            'products' => $products,
        ]);
    }

    private function empty(): array
    {
        return [
            'tickets' => [],
            'customers' => [],
            'devices' => [],
            'products' => [],
        ];
    }
}
