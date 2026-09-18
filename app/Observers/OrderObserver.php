<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Keeps ticket counters, invoice totals, and product stock consistent with an
 * order's lifecycle.
 *
 * Stock model:
 *  - A line for a catalog product ALLOCATES (reserves) its quantity the moment
 *    it is added to a ticket: products.reserved_stock rises, so other tickets
 *    see less available stock while the part is committed to this one.
 *  - Receiving such a line consumes the reservation (on-hand drops).
 *  - Cancelling or removing a live line releases the reservation.
 *  - A purchase line (is_purchase) that points at a catalog product instead
 *    RESTOCKS it when received: it is incoming stock, not stock on a shelf.
 *  - Free-form lines (no product_id) and pure sales never touch stock.
 *
 * Every stock effect is guarded by product_id (checked first, so mock/VO
 * contexts short-circuit safely) and by status, so a line that already reached
 * a terminal state (received/cancelled) is never moved twice.
 */
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

        $this->reserveProductStock($order, (int) $order->quantity);
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

        // Quantity changed on a still-live line: reserve/release the
        // difference.
        if ($order->wasChanged(['quantity']) && !$this->isStockSettled($order)) {
            $diff = (int) $order->quantity - (int) $order->getOriginal('quantity');
            $this->reserveProductStock($order, $diff);
        }

        // Receiving settles the stock effect; cancelling releases the
        // reservation; reopening a cancelled line re-reserves it.
        if ($statusChanged) {
            $from = $this->asStatus($order->getOriginal('status'));
            $to = $this->asStatus($order->status);

            if ($to === OrderStatus::Received) {
                $this->settleReceivedStock($order);
            } elseif ($to === OrderStatus::Cancelled) {
                $this->releaseProductStock($order, (int) $order->quantity);
            } elseif ($from === OrderStatus::Cancelled && $to !== null && $to !== OrderStatus::Received) {
                $this->reserveProductStock($order, (int) $order->quantity);
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

        // Release the reservation for a still-live product line. A line that
        // was already received or cancelled had its stock settled, so it must
        // not move again (no double release).
        if (!$this->isStockSettled($order)) {
            $this->releaseProductStock($order, (int) $order->quantity);
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
     * True when the line's stock effects are already fully settled and no
     * further event should touch stock. A cancelled line had its reservation
     * released; a received line has either consumed its reservation (own
     * stock) or restocked (purchase) -- in both cases exactly its own
     * quantity, so concurrent reservations are unaffected.
     */
    private function isStockSettled(Order $order): bool
    {
        $status = $this->asStatus($order->status);

        return $status === OrderStatus::Cancelled || $status === OrderStatus::Received;
    }

    /**
     * Reserve stock (release with a negative delta) for a product-backed,
     * non-purchase line. product_id is checked first so contexts without a
     * catalog product short-circuit before is_purchase is ever read.
     */
    private function reserveProductStock(Order $order, int $delta): void
    {
        if ($order->product_id === null) {
            return;
        }

        if ($delta === 0 || (bool) $order->is_purchase) {
            return;
        }

        $order->product?->reserveStock($delta)->save();
    }

    /**
     * Release a reservation back to available stock (funnels through
     * reserveProductStock so the product_id guard runs first).
     */
    private function releaseProductStock(Order $order, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->reserveProductStock($order, -$quantity);
    }

    /**
     * Settle a received line: own-stock lines consume exactly their reserved
     * quantity (concurrent reservations on the same product are untouched),
     * purchase lines restock the linked catalog product.
     */
    private function settleReceivedStock(Order $order): void
    {
        if ($order->product_id === null) {
            return;
        }

        $product = $order->product;
        if ($product === null) {
            return;
        }

        $quantity = (int) $order->quantity;
        if ($quantity <= 0) {
            return;
        }

        if ((bool) $order->is_purchase) {
            $product->adjustStock($quantity)->save();

            return;
        }

        $product
            ->forceFill(['reserved_stock' => max(0, (int) $product->reserved_stock - $quantity)])
            ->adjustStock(-$quantity)
            ->save();
    }
}
