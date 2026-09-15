<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Package, Plus, Trash2, ShoppingCart } from 'lucide-vue-next';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface OrderProduct {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    stock: number;
    condition: string | null;
}

interface Order {
    id: number;
    name: string;
    quantity: number;
    price: number;
    cost: number;
    is_billable: boolean;
    status: string;
    product: OrderProduct | null;
}

interface CatalogProduct {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    stock: number;
    condition: string | null;
    category: string | null;
}

interface TicketInfo {
    id: number;
    title: string;
    status: string;
    priority: string;
    device: string | null;
    brand: string | null;
    customer: string | null;
    assignee: string | null;
    created_at: string | null;
}

interface Totals {
    task_total: number;
    order_total: number;
    subtotal: number;
}

interface Props {
    ticket: TicketInfo;
    orders: Order[];
    products: CatalogProduct[];
    totals: Totals;
    flash: { success: string | null };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets', href: '/tickets' },
    { title: `#${props.ticket.id}`, href: '' },
];

const currency = (n: number) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n);

// --- Add-a-product form ---
const form = useForm({
    product_id: null as number | null,
    quantity: 1,
    is_billable: true,
});

const selected = computed(() =>
    props.products.find((p) => p.id === form.product_id) ?? null,
);

const remaining = computed(() => (selected.value ? selected.value.stock - form.quantity : 0));

const lineTotal = computed(() =>
    selected.value ? selected.value.price * form.quantity : 0,
);

const canAdd = computed(() => {
    if (!selected.value) return false;
    return form.quantity >= 1 && form.quantity <= selected.value.stock;
});

const page = usePage();
const flashMessage = computed(() => (page.props as { flash?: { success: string | null } }).flash?.success ?? props.flash.success);

function submit() {
    form.post(route('tickets.orders.store', { ticket: props.ticket.id }), {
        preserveScroll: true,
        onSuccess: () => form.reset('product_id', 'quantity'),
    });
}

function remove(order: Order) {
    if (!confirm(`Remove "${order.name}" from this ticket? Stock will be restored.`)) return;
    router.delete(route('tickets.orders.destroy', { ticket: props.ticket.id, order: order.id }), {
        preserveScroll: true,
    });
}

const statusTone: Record<string, string> = {
    new: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    on_hold: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    resolved: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    closed: 'bg-neutral-200 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-500',
};

const pretty = (s: string) => s.replace(/_/g, ' ');
</script>

<template>
    <Head :title="`Ticket #${ticket.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading
                :title="`Ticket #${ticket.id} — ${ticket.title}`"
                :description="`${ticket.device ?? 'No device'}${ticket.brand ? ' · ' + ticket.brand : ''} · ${ticket.customer ?? 'No customer'}`"
            />

            <p
                v-if="flashMessage"
                class="rounded-lg border border-green-300/60 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800/60 dark:bg-green-950/40 dark:text-green-200"
            >
                {{ flashMessage }}
            </p>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Left: sales surface -->
                <div class="flex flex-col gap-4 lg:col-span-2">
                    <!-- Add a product -->
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <ShoppingCart class="mr-2 inline h-4 w-4" />
                                Sell a product against this ticket
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-3">
                            <div class="grid gap-3 sm:grid-cols-[1fr_6rem_4rem]">
                                <div class="flex flex-col gap-1">
                                    <Label for="product_id">Product</Label>
                                    <select
                                        id="product_id"
                                        v-model="form.product_id"
                                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option :value="null" disabled>Choose a product…</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">
                                            {{ p.name }} — {{ currency(p.price) }} ({{ p.stock }} in stock)
                                        </option>
                                    </select>
                                    <span v-if="form.errors.product_id" class="text-xs text-red-600">
                                        {{ form.errors.product_id }}
                                    </span>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label for="quantity">Qty</Label>
                                    <Input
                                        id="quantity"
                                        type="number"
                                        min="1"
                                        v-model.number="form.quantity"
                                    />
                                    <span
                                        v-if="selected && form.quantity > selected.stock"
                                        class="text-xs text-red-600"
                                    >
                                        Only {{ selected.stock }} left.
                                    </span>
                                </div>

                                <div class="flex items-end">
                                    <Button type="button" :disabled="!canAdd" @click="submit()">
                                        <Plus class="h-4 w-4" />
                                        Add
                                    </Button>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                                <label class="flex items-center gap-2">
                                    <Checkbox
                                        :model-value="form.is_billable"
                                        @update:model-value="(v: boolean | 'indeterminate') => (form.is_billable = v === true)"
                                    />
                                    Billable (included in invoice)
                                </label>
                                <span v-if="selected">
                                    Line total: <span class="font-medium text-foreground">{{ currency(lineTotal) }}</span>
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Order lines -->
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Package class="mr-2 inline h-4 w-4" />
                                Parts &amp; products on this ticket ({{ orders.length }})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p v-if="orders.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                Nothing sold against this ticket yet.
                            </p>

                            <div v-else class="overflow-x-auto rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/40">
                                        <tr class="text-left text-muted-foreground">
                                            <th class="px-4 py-2 font-medium">Item</th>
                                            <th class="px-4 py-2 text-right font-medium">Unit</th>
                                            <th class="px-4 py-2 text-right font-medium">Qty</th>
                                            <th class="px-4 py-2 text-right font-medium">Line total</th>
                                            <th class="px-4 py-2 font-medium">Billable</th>
                                            <th class="px-4 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr v-for="order in orders" :key="order.id" class="hover:bg-muted/20">
                                            <td class="px-4 py-2">
                                                <div class="font-medium">{{ order.name }}</div>
                                                <div class="text-xs text-muted-foreground">
                                                    {{ order.product?.sku ?? 'Free-form part' }}
                                                    <template v-if="!order.is_billable"> · non-billable</template>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 text-right">{{ currency(order.price) }}</td>
                                            <td class="px-4 py-2 text-right">{{ order.quantity }}</td>
                                            <td class="px-4 py-2 text-right font-medium">{{ currency(order.cost) }}</td>
                                            <td class="px-4 py-2">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                    :class="order.is_billable
                                                        ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                                        : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-900'"
                                                >
                                                    {{ order.is_billable ? 'Yes' : 'No' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-8 w-8 text-muted-foreground hover:text-red-600"
                                                    @click="remove(order)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: totals + status -->
                <div class="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Totals</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Tasks</span>
                                <span>{{ currency(totals.task_total) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Parts &amp; products</span>
                                <span>{{ currency(totals.order_total) }}</span>
                            </div>
                            <div class="mt-1 flex justify-between border-t pt-2 text-base font-semibold">
                                <span>Subtotal</span>
                                <span>{{ currency(totals.subtotal) }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Status</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="statusTone[ticket.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                                >
                                    {{ pretty(ticket.status) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Priority</span>
                                <span class="capitalize">{{ pretty(ticket.priority) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Assignee</span>
                                <span>{{ ticket.assignee ?? 'Unassigned' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Customer</span>
                                <span>{{ ticket.customer ?? 'No customer' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Opened</span>
                                <span>{{ ticket.created_at ? ticket.created_at.slice(0, 10) : '-' }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
