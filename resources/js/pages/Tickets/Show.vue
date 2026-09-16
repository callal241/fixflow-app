<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectItemText, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Banknote, FileText, PackagePlus, Plus, Trash2,
    UserRound, Wrench, CheckCircle2, CreditCard, Zap,
} from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface Ticket {
    id: number;
    ticket_number: string | null;
    title: string;
    description: string | null;
    internal_notes: string | null;
    intake_type: string;
    status: string;
    priority: string;
    due_date: string | null;
    assignee: string | null;
    created_at: string | null;
}
interface Device {
    id: number | null;
    model: string | null;
    brand: string | null;
    serial_number: string | null;
    imei: string | null;
    color: string | null;
    storage: string | null;
    carrier: string | null;
    status: string | null;
}
interface Customer {
    id: number;
    name: string;
    company: string | null;
    phone: string | null;
    email: string | null;
}
interface Task {
    id: number;
    type: string;
    note: string | null;
    cost: number;
    is_billable: boolean;
    status: string;
    approved_at: string | null;
    created_at: string | null;
}
interface OrderLine {
    id: number;
    name: string;
    url: string | null;
    supplier: string | null;
    quantity: number;
    price: number;
    cost: number;
    is_billable: boolean;
    status: string;
    product_id: number | null;
    product: { id: number; name: string; sku: string | null; price: number; stock: number } | null;
}
interface Product {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    stock: number;
}
interface Invoice {
    id: number;
    status: string;
    subtotal: number;
    net_amount: number;
    total: number;
    paid_amount: number;
    refunded_amount: number;
    balance: number;
    due_date: string | null;
    transactions: { id: number; type: string; method: string; amount: number; note: string | null; created_at: string | null }[];
    adjustments: { id: number; type: string; reason: string; amount: number | null; percentage: number | null; note: string | null }[];
}
interface Totals { task_total: number; order_total: number; subtotal: number; }
interface Staff { id: number; name: string; }

interface Props {
    ticket: Ticket;
    device: Device;
    customer: Customer | null;
    tasks: Task[];
    orders: OrderLine[];
    products: Product[];
    invoice: Invoice | null;
    payment_provider: { id: string; name: string; configured: boolean } | null;
    totals: Totals;
    statuses: string[];
    priorities: string[];
    task_types: string[];
    task_statuses: string[];
    adjustment_types: string[];
    adjustment_reasons: string[];
    transaction_methods: string[];
    staff: Staff[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets', href: '/tickets' },
    { title: props.ticket.ticket_number ?? `#${props.ticket.id}`, href: '' },
];

const page = usePage();
const flashMessage = computed(() => (page.props as { flash?: { success: string | null } }).flash?.success ?? null);

const pretty = (s: string) => s.replace(/_/g, ' ');
const money = (n: number) => `$${(n ?? 0).toFixed(2)}`;

const statusTone: Record<string, string> = {
    new: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    on_hold: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    resolved: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    closed: 'bg-neutral-200 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-500',
};
const invoiceTone: Record<string, string> = {
    draft: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    issued: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    sent: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    paid: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    refunded: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    cancelled: 'bg-neutral-200 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-500',
};

const ticketForm = useForm({
    title: props.ticket.title,
    description: props.ticket.description ?? '',
    internal_notes: props.ticket.internal_notes ?? '',
    status: props.ticket.status,
    priority: props.ticket.priority,
    due_date: props.ticket.due_date ?? '',
    assignee_id: null as number | null,
});
const saveTicket = () => ticketForm.put(route('tickets.update', props.ticket.id));

const taskForm = useForm({
    type: 'repair',
    note: '',
    cost: 0,
    is_billable: true,
    status: 'new',
});
const addTask = () => taskForm.post(route('tickets.tasks.store', props.ticket.id), {
    onSuccess: () => { taskForm.reset(); },
});
const removeTask = (id: number) => {
    if (confirm('Remove this task?')) {
        taskForm.delete(route('tickets.tasks.destroy', { ticket: props.ticket.id, task: id }));
    }
};

const partForm = useForm({
    product_id: null as number | null,
    quantity: 1,
    is_billable: true,
});
const addPart = () => partForm.post(route('tickets.orders.store', props.ticket.id), {
    onSuccess: () => { partForm.data.quantity = 1; partForm.data.is_billable = true; partForm.data.product_id = null; },
});
const removePart = (id: number) => {
    if (confirm('Remove this part?')) {
        partForm.delete(route('tickets.orders.destroy', { ticket: props.ticket.id, order: id }));
    }
};

const invoiceForm = useForm({ due_date: '' });
const generateInvoice = () => invoiceForm.post(route('tickets.invoice.store', props.ticket.id));

const adjustmentForm = useForm({
    type: 'discount',
    reason: '',
    amount: null as number | null,
    percentage: null as number | null,
    note: '',
});
const addAdjustment = () => adjustmentForm.post(route('tickets.invoice.adjustments.store', props.ticket.id), {
    onSuccess: () => { adjustmentForm.reset(); },
});
const removeAdjustment = (id: number) => {
    if (confirm('Remove this adjustment?')) {
        adjustmentForm.delete(route('tickets.invoice.adjustments.destroy', { ticket: props.ticket.id, adjustment: id }));
    }
};

const paymentForm = useForm({
    amount: null as number | null,
    method: 'card',
    type: 'payment',
    note: '',
});

// Prefill the outstanding balance so "charge" is one click in the common case.
const chargeBalance = () => {
    if (props.invoice) {
        paymentForm.data.amount = Math.round((Number(props.invoice.balance) || 0) * 100) / 100;
    }
};

const addPayment = () => paymentForm.post(route('tickets.invoice.transactions.store', props.ticket.id), {
    onSuccess: () => { paymentForm.reset(); paymentForm.data.type = 'payment'; },
});
const removePayment = (id: number) => {
    if (confirm('Remove this transaction?')) {
        paymentForm.delete(route('tickets.invoice.transactions.destroy', { ticket: props.ticket.id, transaction: id }));
    }
};

const reasonForType = (type: string): string[] => {
    const map: Record<string, string[]> = {
        discount: ['bulk_service_discount', 'promotional_discount', 'price_match_discount'],
        fee: ['rush_service_fee', 'service_fee', 'late_payment_fee'],
        compensation: ['service_delay_compensation', 'damage_incident_compensation', 'rework_compensation'],
        bonus: ['welcome_bonus', 'loyalty_bonus', 'referral_bonus'],
    };
    return map[type] ?? [];
};
</script>

<template>
    <Head :title="ticket.ticket_number ?? `Ticket #${ticket.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <!-- Header + workflow controls -->
            <Card>
                <CardHeader class="flex-row items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold">{{ ticket.ticket_number ?? `#${ticket.id}` }}</h2>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                :class="statusTone[ticket.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                            >
                                {{ pretty(ticket.status) }}
                            </span>
                            <span class="text-xs text-muted-foreground capitalize">· {{ ticket.intake_type.replace(/_/g, ' ') }}</span>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ ticket.title }}</p>
                    </div>
                    <Button :disabled="ticketForm.processing" @click="saveTicket">
                        Save ticket
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <Label>Status</Label>
                            <Select v-model="ticketForm.status">
                                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in statuses" :key="s" :value="s">
                                        <SelectItemText>{{ pretty(s) }}</SelectItemText>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Priority</Label>
                            <Select v-model="ticketForm.priority">
                                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="p in priorities" :key="p" :value="p">
                                        <SelectItemText>{{ p }}</SelectItemText>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Assigned to</Label>
                            <Select v-model="ticketForm.assignee_id">
                                <SelectTrigger class="w-full"><SelectValue placeholder="Unassigned" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">Unassigned</SelectItem>
                                    <SelectItem v-for="s in staff" :key="s.id" :value="s.id">
                                        <SelectItemText>{{ s.name }}</SelectItemText>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Due date</Label>
                            <Input v-model="ticketForm.due_date" type="date" />
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Reported problem</Label>
                            <textarea
                                v-model="ticketForm.description"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Internal notes</Label>
                            <textarea
                                v-model="ticketForm.internal_notes"
                                rows="2"
                                placeholder="Team-only notes…"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <p
                v-if="flashMessage"
                class="rounded-lg border border-green-300/60 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800/60 dark:bg-green-950/40 dark:text-green-200"
            >
                {{ flashMessage }}
            </p>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Device + customer -->
                <div class="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <PackagePlus class="mr-2 inline h-4 w-4" />
                                Device
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-1.5 text-sm">
                            <div class="flex justify-between"><span class="text-muted-foreground">Model</span><span>{{ device.brand ? `${device.brand} ` : '' }}{{ device.model }}</span></div>
                            <div class="flex justify-between"><span class="text-muted-foreground">Serial</span><span class="font-mono text-xs">{{ device.serial_number ?? '—' }}</span></div>
                            <div v-if="device.imei" class="flex justify-between"><span class="text-muted-foreground">IMEI</span><span class="font-mono text-xs">{{ device.imei }}</span></div>
                            <div v-if="device.color" class="flex justify-between"><span class="text-muted-foreground">Color</span><span>{{ device.color }}</span></div>
                            <div v-if="device.storage" class="flex justify-between"><span class="text-muted-foreground">Storage</span><span>{{ device.storage }}</span></div>
                            <div v-if="device.carrier" class="flex justify-between"><span class="text-muted-foreground">Carrier</span><span>{{ device.carrier }}</span></div>
                        </CardContent>
                    </Card>

                    <Card v-if="customer">
                        <CardHeader>
                            <CardTitle>
                                <UserRound class="mr-2 inline h-4 w-4" />
                                Customer
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-1.5 text-sm">
                            <Link :href="route('customers.show', customer.id)" class="font-medium hover:underline">{{ customer.name }}</Link>
                            <div v-if="customer.company" class="text-xs text-muted-foreground">{{ customer.company }}</div>
                            <div v-if="customer.phone" class="text-muted-foreground">{{ customer.phone }}</div>
                            <div v-if="customer.email" class="text-xs text-muted-foreground">{{ customer.email }}</div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Tasks + Parts -->
                <div class="flex flex-col gap-4 lg:col-span-2">
                    <!-- Repair tasks -->
                    <Card>
                        <CardHeader class="flex-row items-center justify-between">
                            <CardTitle><Wrench class="mr-2 inline h-4 w-4" />Repair work</CardTitle>
                            <div class="text-sm font-medium">Total {{ money(totals.task_total) }}</div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="tasks.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                                No repair work logged yet.
                            </div>

                            <div v-for="t in tasks" :key="t.id" class="flex items-center gap-3 rounded-lg border p-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium capitalize">{{ t.type }}</span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="t.is_billable
                                                ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                                : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-900'"
                                        >
                                            {{ t.is_billable ? 'billable' : 'not billable' }}
                                        </span>
                                        <span class="text-xs capitalize text-muted-foreground">· {{ t.status }}</span>
                                    </div>
                                    <div v-if="t.note" class="mt-0.5 text-xs text-muted-foreground">{{ t.note }}</div>
                                </div>
                                <div class="text-sm font-medium">{{ money(t.cost) }}</div>
                                <Button variant="ghost" size="icon" @click="removeTask(t.id)">
                                    <Trash2 class="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </div>

                            <Separator />

                            <form @submit.prevent="addTask" class="grid gap-3 sm:grid-cols-12">
                                <div class="grid gap-1 sm:col-span-3">
                                    <Label>Work type</Label>
                                    <Select v-model="taskForm.type">
                                        <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="tt in task_types" :key="tt" :value="tt">
                                                <SelectItemText>{{ tt }}</SelectItemText>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-1 sm:col-span-4">
                                    <Label>Note</Label>
                                    <Input v-model="taskForm.note" placeholder="What was done / found" />
                                </div>
                                <div class="grid gap-1 sm:col-span-2">
                                    <Label>Cost</Label>
                                    <Input v-model.number="taskForm.cost" type="number" step="0.01" min="0" required />
                                </div>
                                <div class="grid gap-1 sm:col-span-2">
                                    <Label>Status</Label>
                                    <Select v-model="taskForm.status">
                                        <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="st in task_statuses" :key="st" :value="st">
                                                <SelectItemText>{{ pretty(st) }}</SelectItemText>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex items-end gap-2 sm:col-span-1">
                                    <Button type="submit" size="sm" class="w-full" :disabled="taskForm.processing">
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Parts / products -->
                    <Card>
                        <CardHeader class="flex-row items-center justify-between">
                            <CardTitle><PackagePlus class="mr-2 inline h-4 w-4" />Parts &amp; products</CardTitle>
                            <div class="text-sm font-medium">Total {{ money(totals.order_total) }}</div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="orders.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                                No parts added yet.
                            </div>

                            <div v-for="o in orders" :key="o.id" class="flex items-center gap-3 rounded-lg border p-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium">{{ o.name }}</span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="o.is_billable
                                                ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                                : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-900'"
                                        >
                                            {{ o.is_billable ? 'billable' : 'not billable' }}
                                        </span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-muted-foreground">
                                        {{ o.quantity }} × {{ money(o.price) }}
                                        <template v-if="o.supplier"> · {{ o.supplier }}</template>
                                    </div>
                                </div>
                                <div class="text-sm font-medium">{{ money(o.cost) }}</div>
                                <Button variant="ghost" size="icon" @click="removePart(o.id)">
                                    <Trash2 class="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </div>

                            <Separator />

                            <form @submit.prevent="addPart" class="grid gap-3 sm:grid-cols-12">
                                <div class="grid gap-1 sm:col-span-6">
                                    <Label>Product</Label>
                                    <Select v-model="partForm.product_id">
                                        <SelectTrigger class="w-full" :class="{ 'border-destructive': partForm.errors.product_id }">
                                            <SelectValue placeholder="Choose from catalog…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="p in products" :key="p.id" :value="p.id">
                                                <SelectItemText>
                                                    {{ p.name }}{{ p.sku ? ` (${p.sku})` : '' }} — {{ money(p.price) }} · {{ p.stock }} in stock
                                                </SelectItemText>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="partForm.errors.product_id" />
                                </div>
                                <div class="grid gap-1 sm:col-span-2">
                                    <Label>Qty</Label>
                                    <Input v-model.number="partForm.quantity" type="number" min="1" />
                                </div>
                                <div class="flex items-end gap-2 sm:col-span-4">
                                    <Button type="submit" size="sm" :disabled="partForm.processing">
                                        <Plus class="h-4 w-4" />
                                        Add part
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Invoice & payments -->
            <Card>
                <CardHeader class="flex-row items-center justify-between">
                    <CardTitle><FileText class="mr-2 inline h-4 w-4" />Invoice &amp; payment</CardTitle>
                    <div class="flex items-center gap-2">
                        <span
                            v-if="invoice"
                            class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                            :class="invoiceTone[invoice.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                        >
                            {{ invoice.status }}
                        </span>
                        <Button v-if="!invoice" variant="outline" size="sm" :disabled="invoiceForm.processing" @click="generateInvoice">
                            <FileText class="mr-1 h-4 w-4" />
                            Generate invoice
                        </Button>
                        <Button v-else variant="outline" size="sm" :disabled="invoiceForm.processing" @click="generateInvoice">
                            <FileText class="mr-1 h-4 w-4" />
                            Refresh totals
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border p-3">
                            <div class="text-xs text-muted-foreground">Subtotal</div>
                            <div class="text-lg font-semibold">{{ money(totals.subtotal) }}</div>
                        </div>
                        <div class="rounded-lg border p-3">
                            <div class="text-xs text-muted-foreground">Total due</div>
                            <div class="text-lg font-semibold">{{ invoice ? money(invoice.total) : money(totals.subtotal) }}</div>
                        </div>
                        <div class="rounded-lg border p-3">
                            <div class="text-xs text-muted-foreground">Paid</div>
                            <div class="text-lg font-semibold text-green-600 dark:text-green-400">{{ invoice ? money(invoice.paid_amount) : money(0) }}</div>
                        </div>
                        <div class="rounded-lg border p-3">
                            <div class="text-xs text-muted-foreground">Balance</div>
                            <div class="text-lg font-semibold">{{ invoice ? money(invoice.balance) : money(totals.subtotal) }}</div>
                        </div>
                    </div>

                    <template v-if="invoice">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <!-- Adjustments -->
                            <div class="space-y-3">
                                <h3 class="text-sm font-medium">Adjustments</h3>
                                <p v-if="invoice.adjustments.length === 0" class="text-xs text-muted-foreground">No adjustments.</p>
                                <div v-for="a in invoice.adjustments" :key="a.id" class="flex items-center gap-2 rounded-lg border p-2 text-sm">
                                    <span class="flex-1 capitalize">{{ a.type }} · {{ pretty(a.reason) }}</span>
                                    <span>{{ a.percentage !== null ? `${a.percentage}%` : money(a.amount ?? 0) }}</span>
                                    <Button variant="ghost" size="icon" @click="removeAdjustment(a.id)">
                                        <Trash2 class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                </div>

                                <form @submit.prevent="addAdjustment" class="grid gap-2 sm:grid-cols-12">
                                    <div class="grid gap-1 sm:col-span-3">
                                        <Label>Type</Label>
                                        <Select v-model="adjustmentForm.type">
                                            <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="t in adjustment_types" :key="t" :value="t">
                                                    <SelectItemText>{{ t }}</SelectItemText>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1 sm:col-span-3">
                                        <Label>Reason</Label>
                                        <Select v-model="adjustmentForm.reason">
                                            <SelectTrigger class="w-full"><SelectValue placeholder="Reason" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="r in reasonForType(adjustmentForm.type)" :key="r" :value="r">
                                                    <SelectItemText>{{ pretty(r) }}</SelectItemText>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1 sm:col-span-3">
                                        <Label>Amount / %</Label>
                                        <Input v-model.number="adjustmentForm.amount" type="number" step="0.01" min="0" placeholder="Amount" />
                                    </div>
                                    <div class="flex items-end gap-2 sm:col-span-3">
                                        <Button type="submit" size="sm" :disabled="adjustmentForm.processing">
                                            <Plus class="h-4 w-4" />
                                            Add
                                        </Button>
                                    </div>
                                </form>
                            </div>

                            <!-- Payments -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-medium">Payments &amp; refunds</h3>
                                    <span
                                        v-if="payment_provider"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                        :title="payment_provider.configured ? 'Provider is configured' : 'Provider is not configured; falling back to counter'"
                                    >
                                        <CreditCard class="h-3 w-3" />
                                        {{ payment_provider.name }}
                                    </span>
                                </div>
                                <p v-if="invoice.transactions.length === 0" class="text-xs text-muted-foreground">No transactions yet.</p>
                                <div v-for="tx in invoice.transactions" :key="tx.id" class="flex items-center gap-2 rounded-lg border p-2 text-sm">
                                    <Banknote class="h-4 w-4 text-muted-foreground" />
                                    <span class="flex-1 capitalize">
                                        <span :class="tx.type === 'refund' ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400'">{{ tx.type }}</span>
                                        · {{ tx.method }}
                                        <span v-if="tx.note" class="text-muted-foreground"> · {{ tx.note }}</span>
                                    </span>
                                    <span class="font-medium">{{ money(tx.amount) }}</span>
                                    <Button variant="ghost" size="icon" @click="removePayment(tx.id)">
                                        <Trash2 class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                </div>

                                <form @submit.prevent="addPayment" class="grid gap-2 rounded-lg border p-3 sm:grid-cols-12">
                                    <div class="grid gap-1 sm:col-span-3">
                                        <Label>Amount</Label>
                                        <Input v-model.number="paymentForm.amount" type="number" step="0.01" min="0.01" required :class="{ 'border-destructive': paymentForm.errors.amount }" />
                                        <InputError :message="paymentForm.errors.amount" />
                                    </div>
                                    <div class="grid gap-1 sm:col-span-3">
                                        <Label>Method</Label>
                                        <Select v-model="paymentForm.method">
                                            <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="m in transaction_methods" :key="m" :value="m">
                                                    <SelectItemText>{{ pretty(m) }}</SelectItemText>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1 sm:col-span-3">
                                        <Label>Type</Label>
                                        <Select v-model="paymentForm.type">
                                            <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="payment">Payment</SelectItem>
                                                <SelectItem value="refund">Refund</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex items-end gap-2 sm:col-span-3">
                                        <Button
                                            v-if="paymentForm.type === 'payment' && Number(invoice.balance) > 0"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="chargeBalance"
                                        >
                                            <Zap class="h-4 w-4" />
                                            Balance
                                        </Button>
                                        <Button type="submit" size="sm" :disabled="paymentForm.processing">
                                            <Banknote class="h-4 w-4" />
                                            Record
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>

                    <div v-else class="flex items-center gap-2 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        <CheckCircle2 class="h-4 w-4" />
                        Log repair work and add parts, then generate the invoice to bill the customer.
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
