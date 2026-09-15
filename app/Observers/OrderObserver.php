<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $order->load('ticket.invoice');
        $order->ticket->fillOrderCounts()->save();

        // Update invoice order total if invoice exists
        $order->ticket->invoice?->fillOrderTotal()->save();

        // Consume stock for catalog product lines.
        $this->consumeProductStock($order, (int) $order->quantity);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $order->load('ticket.invoice');

        $statusChanged = $order->wasChanged(['status']);

        // Update counts if status changed
        if ($statusChanged) {
            $order->ticket->fillOrderCounts()->save();
        }

        // Update invoice subtotal if cost or billable status changed
        if ($order->wasChanged(['cost', 'is_billable'])) {
            $order->ticket->invoice?->fillOrderTotal()->save();
        }

        // Quantity changed: consume or release the difference.
        if ($order->wasChanged(['quantity'])) {
            $diff = (int) $order->getOriginal('quantity') - (int) $order->quantity;
            $this->adjustProductStock($order, $diff);
        }

        // Cancelling releases stock; reopening a cancelled order consumes it again.
        if ($statusChanged) {
            $from = $this->asStatus($order->getOriginal('status'));
            $to = $this->asStatus($order->status);

            if ($to === OrderStatus::Cancelled) {
                $this->adjustProductStock($order, (int) $order->quantity);
            } elseif ($from === OrderStatus::Cancelled && $to !== null) {
                $this->adjustProductStock($order, -(int) $order->quantity);
            }
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $order->load('ticket.invoice');
        $order->ticket->fillOrderCounts()->save();

        // Update invoice total if invoice exists
        $order->ticket->invoice?->fillOrderTotal()->save();

        // Release stock for the removed product line — but only if it still
        // holds stock. A cancelled order already had its stock restored, so we
        // must not restore it a second time.
        if ($this->asStatus($order->status) !== OrderStatus::Cancelled) {
            $this->adjustProductStock($order, (int) $order->quantity);
        }
    }

    /**
     * Normalize a status value (an OrderStatus enum or a raw string) to an
     * OrderStatus instance, or null when it is not a known status.
     */
    private function asStatus(mixed $value): ?OrderStatus
    {
        return match (true) {
            $value instanceof OrderStatus => $value,
            is_string($value) => OrderStatus::tryFrom($value),
            default => null,
        };
    }

    /**
     * Consume stock for a product-backed order. The decrement is guarded so
     * stock can never go negative, even under concurrent sales.
     */
    private function consumeProductStock(Order $order, int $quantity): void
    {
        if ($order->product_id === null || $quantity <= 0) {
            return;
        }

        Product::whereKey($order->product_id)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);
    }

    /**
     * Adjust a product-backed order's stock by an arbitrary delta (positive to
     * restock, negative to consume). Used for quantity changes, cancellations,
     * and deletions. Clamps at zero via Product::adjustStock().
     */
    private function adjustProductStock(Order $order, int $delta): void
    {
        if ($order->product_id === null || $delta === 0) {
            return;
        }

        $order->product?->adjustStock($delta)->save();
    }
}
