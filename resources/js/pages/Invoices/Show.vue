<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FileText, Printer } from 'lucide-vue-next';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface TaskLine {
    id: number;
    note: string | null;
    cost: number;
}

interface OrderLine {
    id: number;
    name: string;
    quantity: number;
    cost: number;
}

interface AdjustmentLine {
    id: number;
    type: string;
    amount: number;
    percentage: number | null;
    note: string | null;
}

interface TransactionLine {
    id: number;
    type: string;
    method: string;
    amount: number;
    note: string | null;
    created_at: string | null;
}

interface Invoice {
    id: number;
    status: string;
    business: string | null;
    total: number;
    task_total: number;
    order_total: number;
    discount_amount: number;
    fee_amount: number;
    compensation_amount: number;
    bonus_amount: number;
    paid_amount: number;
    refunded_amount: number;
    net_amount: number;
    balance: number;
    due_date: string | null;
    approved_at: string | null;
    created_at: string | null;
    customer: {
        name: string | null;
        company: string | null;
        phone: string | null;
        email: string | null;
    };
    ticket: {
        id: number;
        title: string;
    };
    tasks: TaskLine[];
    orders: OrderLine[];
    adjustments: AdjustmentLine[];
    transactions: TransactionLine[];
}

interface Props {
    invoice: Invoice;
}

const props = defineProps<Props>();

const businessName = computed(() => props.invoice.business ?? 'FixFlow');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Invoices', href: '/invoices' },
    { title: `Invoice #${props.invoice.id}`, href: '' },
];

const currency = (n: number) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n);

const statusTone: Record<string, string> = {
    draft: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    issued: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    sent: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    paid: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    refunded: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
};

const pretty = (s: string) => s.replace(/_/g, ' ');

// Adjustment rows only appear when they are non-zero.
const adjustmentRows = computed(() => {
    const rows: { label: string; amount: number }[] = [];
    const a = props.invoice;
    if (a.discount_amount) rows.push({ label: 'Discount', amount: -a.discount_amount });
    if (a.fee_amount) rows.push({ label: 'Fee', amount: a.fee_amount });
    if (a.compensation_amount) rows.push({ label: 'Compensation', amount: -a.compensation_amount });
    if (a.bonus_amount) rows.push({ label: 'Bonus', amount: -a.bonus_amount });
    return rows;
});

const hasLineItems = computed(
    () => props.invoice.tasks.length > 0 || props.invoice.orders.length > 0,
);
</script>

<template>
    <Head :title="`Invoice #${invoice.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex items-start justify-between print:hidden">
                <Heading
                    :title="`Invoice #${invoice.id}`"
                    :description="invoice.ticket.title"
                />
                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                        :class="statusTone[invoice.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                    >
                        {{ pretty(invoice.status) }}
                    </span>
                    <Button variant="outline" size="sm" @click="window.print()">
                        <Printer class="h-4 w-4" />
                        Print / PDF
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="route('tickets.show', invoice.ticket.id)">
                            View ticket
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Printable invoice document -->
            <Card class="bg-white text-neutral-900">
                <CardContent class="p-8">
                    <div class="flex items-start justify-between border-b border-neutral-200 pb-6">
                        <div>
                            <div class="text-xl font-semibold">{{ businessName }}</div>
                            <div class="text-sm text-neutral-500">Repair &amp; sales invoice</div>
                        </div>
                        <div class="text-right text-sm">
                            <div class="text-lg font-semibold">Invoice #{{ invoice.id }}</div>
                            <div class="mt-1 text-neutral-500">
                                Issued: {{ invoice.created_at ?? '-' }}
                            </div>
                            <div class="text-neutral-500">Due: {{ invoice.due_date ?? '-' }}</div>
                            <div v-if="invoice.approved_at" class="text-neutral-500">
                                Approved: {{ invoice.approved_at }}
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 py-6 sm:grid-cols-2">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-neutral-400">
                                Billed to
                            </div>
                            <div class="mt-1 font-medium">{{ invoice.customer.name ?? 'Walk-in customer' }}</div>
                            <div v-if="invoice.customer.company" class="text-sm text-neutral-500">
                                {{ invoice.customer.company }}
                            </div>
                            <div v-if="invoice.customer.phone" class="text-sm text-neutral-500">
                                {{ invoice.customer.phone }}
                            </div>
                            <div v-if="invoice.customer.email" class="text-sm text-neutral-500">
                                {{ invoice.customer.email }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-medium uppercase tracking-wide text-neutral-400">
                                Ticket
                            </div>
                            <div class="mt-1 text-sm">{{ invoice.ticket.title }}</div>
                        </div>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-neutral-300 text-left text-neutral-500">
                                <th class="py-2 font-medium">Item</th>
                                <th class="py-2 text-right font-medium">Qty</th>
                                <th class="py-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-if="!hasLineItems">
                                <td colspan="3" class="py-6 text-center text-neutral-400">
                                    No billable line items.
                                </td>
                            </tr>
                            <tr v-for="task in invoice.tasks" :key="`t-${task.id}`">
                                <td class="py-2">{{ task.note ?? 'Labour' }}</td>
                                <td class="py-2 text-right">1</td>
                                <td class="py-2 text-right">{{ currency(task.cost) }}</td>
                            </tr>
                            <tr v-for="order in invoice.orders" :key="`o-${order.id}`">
                                <td class="py-2">{{ order.name }}</td>
                                <td class="py-2 text-right">{{ order.quantity }}</td>
                                <td class="py-2 text-right">{{ currency(order.cost) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-6 flex justify-end">
                        <div class="w-full max-w-xs space-y-1 text-sm">
                            <div class="flex justify-between text-neutral-500">
                                <span>Subtotal</span>
                                <span>{{ currency(invoice.task_total + invoice.order_total) }}</span>
                            </div>
                            <div
                                v-for="row in adjustmentRows"
                                :key="row.label"
                                class="flex justify-between text-neutral-500"
                            >
                                <span>{{ row.label }}</span>
                                <span>{{ currency(row.amount) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-neutral-300 pt-2 text-base font-semibold">
                                <span>Total</span>
                                <span>{{ currency(invoice.total) }}</span>
                            </div>
                            <div class="flex justify-between text-neutral-500">
                                <span>Paid</span>
                                <span>{{ currency(invoice.paid_amount) }}</span>
                            </div>
                            <div
                                v-if="invoice.refunded_amount > 0"
                                class="flex justify-between text-neutral-500"
                            >
                                <span>Refunded</span>
                                <span>{{ currency(invoice.refunded_amount) }}</span>
                            </div>
                            <div class="flex justify-between font-medium">
                                <span>Balance due</span>
                                <span>{{ currency(invoice.balance) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="invoice.transactions.length > 0" class="mt-8 border-t border-neutral-200 pt-4">
                        <div class="text-xs font-medium uppercase tracking-wide text-neutral-400">
                            Payment history
                        </div>
                        <table class="mt-2 w-full text-sm">
                            <tbody class="divide-y divide-neutral-100">
                                <tr v-for="tx in invoice.transactions" :key="tx.id">
                                    <td class="py-1.5">{{ tx.created_at ?? '-' }}</td>
                                    <td class="py-1.5 capitalize">{{ pretty(tx.method) }}</td>
                                    <td
                                        class="py-1.5 text-right"
                                        :class="tx.type === 'refund' ? 'text-amber-600' : ''"
                                    >
                                        {{ tx.type === 'refund' ? '-' : '+' }}{{ currency(tx.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Adjustments detail (screen only) -->
            <Card v-if="invoice.adjustments.length > 0" class="print:hidden">
                <CardHeader>
                    <CardTitle>
                        <FileText class="mr-2 inline h-4 w-4" />
                        Adjustments ({{ invoice.adjustments.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr class="text-left text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Type</th>
                                <th class="px-4 py-2 font-medium">Note</th>
                                <th class="px-4 py-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="adj in invoice.adjustments" :key="adj.id">
                                <td class="px-4 py-2 capitalize">{{ pretty(adj.type) }}</td>
                                <td class="px-4 py-2">{{ adj.note ?? '-' }}</td>
                                <td class="px-4 py-2 text-right">
                                    {{ currency(adj.amount) }}
                                    <span v-if="adj.percentage" class="text-xs text-muted-foreground">
                                        ({{ adj.percentage }}%)
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
