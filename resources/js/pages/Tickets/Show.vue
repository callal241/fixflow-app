<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectItemText, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Banknote, ClipboardCheck, FileText, PackagePlus, Plus, Search, Trash2,
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
    is_purchase: boolean;
    status: string;
    received_at: string | null;
    product_id: number | null;
    product: { id: number; name: string; sku: string | null; price: number; stock: number; reserved_stock: number; available: number } | null;
}
interface Product {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    stock: number;
    reserved_stock: number;
    available: number;
}
interface Offer {
    supplier: string;
    name: string;
    external_id: string | null;
    url: string | null;
    image: string | null;
    price: number | null;
    stock: number | null;
    category: string | null;
    description: string | null;
}
interface ChecklistItem {
    id: number;
    label: string;
    note: string | null;
    phase: string;
    status: string;
    checked_by: string | null;
    checked_at: string | null;
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
    approved_at: string | null;
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
    checklist_items: ChecklistItem[];
    products: Product[];
    invoice: Invoice | null;
    payment_provider: { id: string; name: string; configured: boolean } | null;
    totals: Totals;
    statuses: string[];
    priorities: string[];
    task_types: string[];
    task_statuses: string[];
    checklist_phases: string[];
    checklist_statuses: string[];
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

const checklistForm = useForm({
    label: '',
    note: '',
    phase: 'pre_repair',
    status: 'pending',
});
const addChecklistItem = () => checklistForm.post(route('tickets.checklist-items.store', props.ticket.id), {
    onSuccess: () => { checklistForm.reset(); checklistForm.data.phase = 'pre_repair'; checklistForm.data.status = 'pending'; },
});
const setChecklistStatus = (item: ChecklistItem, status: string) => {
    useForm({ label: item.label, note: item.note ?? '', phase: item.phase, status }).put(
        route('tickets.checklist-items.update', { ticket: props.ticket.id, item: item.id }),
    );
};
const removeChecklistItem = (id: number) => {
    if (confirm('Remove this checklist item?')) {
        checklistForm.delete(route('tickets.checklist-items.destroy', { ticket: props.ticket.id, item: id }));
    }
};
const preItems = computed(() => props.checklist_items.filter((i) => i.phase === 'pre_repair'));
const postItems = computed(() => props.checklist_items.filter((i) => i.phase === 'post_repair'));
const checklistGroups = computed(() => [
    { key: 'pre_repair', title: 'Pre-repair Â· condition at intake', items: preItems.value },
    { key: 'post_repair', title: 'Post-repair Â· QC sign-off', items: postItems.value },
].filter((g) => g.items.length > 0 || props.checklist_items.length > 0));
const checklistProgress = computed(() => {
    const total = props.checklist_items.length;
    const passed = props.checklist_items.filter((i) => i.status === 'passed').length;
    return { total, passed, failed: props.checklist_items.filter((i) => i.status === 'failed').length };
});
const statusToneFor = (status: string): string => ({
    pending: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300',
    passed: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
}[status] ?? '');

const partForm = useForm({
    product_id: null as number | null,
    name: '',
    supplier: null as string | null,
    url: null as string | null,
    price: null as number | null,
    quantity: 1,
    is_billable: true,
    is_purchase: false,
});
const addPart = () => partForm.post(route('tickets.orders.store', props.ticket.id), {
    onSuccess: () => {
        partForm.data.quantity = 1;
        partForm.data.is_billable = true;
        partForm.data.is_purchase = false;
        partForm.data.product_id = null;
        partForm.data.name = '';
        partForm.data.supplier = null;
        partForm.data.url = null;
        partForm.data.price = null;
    },
});
const removePart = (id: number) => {
    if (confirm('Remove this part?')) {
        partForm.delete(route('tickets.orders.destroy', { ticket: props.ticket.id, order: id }));
    }
};
const receivePart = (id: number) =>
    partForm.patch(route('tickets.orders.receive', { ticket: props.ticket.id, order: id }));
const cancelPart = (id: number) => {
    if (confirm('Cancel this part? It will become non-billable and any reserved stock is released.')) {
        partForm.patch(route('tickets.orders.cancel', { ticket: props.ticket.id, order: id }));
    }
};

const partTone: Record<string, string> = {
    new: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    shipped: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    received: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    cancelled: 'bg-neutral-200 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-500',
};

// ---- find parts: live supplier search (same endpoint as the Suppliers page) ----
const findQuery = ref('');
const findOffers = ref<Offer[]>([]);
const findSearching = ref(false);
const findFailure = ref<string | null>(null);
const findSearchedFor = ref<string | null>(null);
let findDebounce: ReturnType<typeof setTimeout> | undefined;
let findSeq = 0;

async function runFindSearch() {
    const q = findQuery.value.trim();
    const mySeq = ++findSeq;
    if (q.length < 2) {
        findOffers.value = [];
        findFailure.value = null;
        findSearchedFor.value = null;
        return;
    }
    findSearching.value = true;
    try {
        const res = await fetch(
            `${route('suppliers.search')}?q=${encodeURIComponent(q)}&limit=8`,
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
        );
        const data: { success: boolean; offers: Offer[]; failure_reason: string | null } = await res.json();
        if (mySeq === findSeq) {
            findOffers.value = data.offers ?? [];
            findFailure.value = data.success ? null : (data.failure_reason ?? 'Supplier unavailable.');
            findSearchedFor.value = q;
        }
    } catch {
        if (mySeq === findSeq) {
            findFailure.value = 'Could not reach the supplier search.';
            findOffers.value = [];
        }
    } finally {
        if (mySeq === findSeq) {
            findSearching.value = false;
        }
    }
}

function onFindInput() {
    if (findDebounce) clearTimeout(findDebounce);
    findDebounce = setTimeout(runFindSearch, 300);
}

const addOffer = (offer: Offer) => {
    partForm.data.product_id = null;
    partForm.data.name = offer.name;
    partForm.data.supplier = offer.supplier || null;
    partForm.data.url = offer.url;
    partForm.data.price = offer.price;
    partForm.data.is_purchase = true;
};

const invoiceForm = useForm({ due_date: '' });
const generateInvoice = () => invoiceForm.post(route('tickets.invoice.store', props.ticket.id));

const approveForm = useForm({});
const approveInvoice = () => approveForm.post(route('tickets.invoice.approve', props.ticket.id));
// Only a draft invoice with billable work can be approved.
const canApproveInvoice = computed(() =>
    !!props.invoice && props.invoice.status === 'draft' && Number(props.invoice.total) > 0,
);

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
                            <span class="text-xs text-muted-foreground capitalize">Â· {{ ticket.intake_type.replace(/_/g, ' ') }}</span>
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
                                placeholder="Team-only notesâ€¦"
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
                            <div class="flex justify-between"><span class="text-muted-foreground">Serial</span><span class="font-mono text-xs">{{ device.serial_number ?? 'â€”' }}</span></div>
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
                                        <span class="text-xs capitalize text-muted-foreground">Â· {{ t.status }}</span>
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

                            <div v-for="o in orders" :key="o.id" class="rounded-lg border p-3" :class="o.status === 'cancelled' ? 'opacity-60' : ''">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium">{{ o.name }}</span>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                                :class="partTone[o.status] ?? 'bg-neutral-100 text-neutral-600'"
                                            >
                                                {{ o.status }}
                                            </span>
                                            <span
                                                v-if="o.is_purchase"
                                                class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-300"
                                            >
                                                purchase
                                            </span>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="o.is_billable
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                                    : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-900'"
                                            >
                                                {{ o.is_billable ? 'billable' : 'not billable' }}
                                            </span>
                                        </div>
                                        <div class="mt-0.5 text-xs text-muted-foreground">
                                            {{ o.quantity }} x {{ money(o.price) }}
                                            <template v-if="o.supplier"> x {{ o.supplier }}</template>
                                            <template v-if="o.received_at"> x received {{ o.received_at }}</template>
                                            <template v-if="o.product">
                                                x shelf: {{ o.product.available }} available
                                                <template v-if="o.product.reserved_stock > 0"> ({{ o.product.reserved_stock }} reserved)</template>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="text-sm font-medium">{{ money(o.cost) }}</div>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <Button v-if="o.status === 'new' || o.status === 'shipped'" variant="outline" size="sm" class="h-7 text-xs" @click="receivePart(o.id)">
                                        <CheckCircle2 class="mr-1 h-3.5 w-3.5" />
                                        Receive
                                    </Button>
                                    <Button v-if="o.status === 'new' || o.status === 'shipped'" variant="ghost" size="sm" class="h-7 text-xs text-muted-foreground" @click="cancelPart(o.id)">
                                        Cancel part
                                    </Button>
                                    <span v-else-if="o.status === 'received'" class="text-xs text-green-600 dark:text-green-400">Received - stock settled.</span>
                                    <span v-else class="text-xs text-muted-foreground">Cancelled - not billable.</span>
                                    <div class="flex-1"></div>
                                    <Button variant="ghost" size="icon" class="h-7 w-7" @click="removePart(o.id)">
                                        <Trash2 class="h-4 w-4 text-muted-foreground" />
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label class="text-sm font-medium">
                                    <Search class="mr-1 inline h-3.5 w-3.5" />
                                    Find parts
                                </Label>
                                <Input
                                    v-model="findQuery"
                                    placeholder="Search supplier parts, e.g. iPhone 13 battery, SSD 1TB..."
                                    @input="onFindInput"
                                />
                                <div v-if="findSearching" class="text-xs text-muted-foreground">Searching...</div>
                                <p v-else-if="findFailure" class="text-xs text-amber-600 dark:text-amber-400">
                                    {{ findFailure }}
                                </p>
                                <div v-else-if="findSearchedFor && findOffers.length === 0" class="text-xs text-muted-foreground">
                                    No offers for "{{ findSearchedFor }}".
                                </div>
                                <ul v-else-if="findOffers.length > 0" class="space-y-2">
                                    <li v-for="(offer, i) in findOffers" :key="i" class="flex items-start gap-3 rounded-md border p-2.5">
                                        <img
                                            v-if="offer.image"
                                            :src="offer.image"
                                            :alt="offer.name"
                                            class="h-10 w-10 rounded-md object-cover"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium">{{ offer.name }}</p>
                                            <p class="truncate text-xs text-muted-foreground">
                                                <template v-if="offer.category">{{ offer.category }}</template>
                                                <span v-if="offer.price != null" class="ml-2 text-green-600 dark:text-green-400">${{ offer.price.toFixed(2) }}</span>
                                                <span v-else class="ml-2 italic">price on request</span>
                                            </p>
                                        </div>
                                        <Button variant="outline" size="sm" class="h-7 shrink-0 text-xs" @click="addOffer(offer)">
                                            <Plus class="mr-1 h-3.5 w-3.5" />
                                            Add
                                        </Button>
                                    </li>
                                </ul>
                                <p class="text-xs text-muted-foreground">
                                    Adds a purchase line to the ticket; receiving it restocks your shelf.
                                </p>
                            </div>
                            <Separator />

                            <form @submit.prevent="addPart" class="grid gap-3 sm:grid-cols-12">
                                <div class="grid gap-1 sm:col-span-5">
                                    <Label>Product (own shelf)</Label>
                                    <Select v-model="partForm.product_id">
                                        <SelectTrigger class="w-full" :class="{ 'border-destructive': partForm.errors.product_id }">
                                            <SelectValue placeholder="Choose from catalog (optional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="p in products" :key="p.id" :value="p.id">
                                                <SelectItemText>
                                                    {{ p.name }}{{ p.sku ? ` (${p.sku})` : '' }} - ${{ p.price.toFixed(2) }} - {{ p.available }} available
                                                </SelectItemText>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="partForm.errors.product_id" />
                                </div>
                                <div class="grid gap-1 sm:col-span-4">
                                    <Label>Part name (supplier part)</Label>
                                    <Input v-model="partForm.name" placeholder="e.g. iPhone 13 Screen (OLED)" :class="{ 'border-destructive': partForm.errors.name }" />
                                    <InputError :message="partForm.errors.name" />
                                </div>
                                <div class="grid gap-1 sm:col-span-3">
                                    <Label>Unit price</Label>
                                    <Input v-model.number="partForm.price" type="number" step="0.01" min="0" placeholder="0.00" />
                                </div>
                                <div class="grid gap-1 sm:col-span-3">
                                    <Label>Qty</Label>
                                    <Input v-model.number="partForm.quantity" type="number" min="1" />
                                </div>
                                <div class="grid gap-1 sm:col-span-4">
                                    <Label class="flex items-center gap-2">
                                        <Checkbox v-model="partForm.is_purchase" :true-value="true" :false-value="false" />
                                        Purchasing from supplier (restocks shelf on receive)
                                    </Label>
                                </div>
                                <div class="flex items-end gap-2 sm:col-span-5">
                                    <Button type="submit" size="sm" :disabled="partForm.processing">
                                        <Plus class="h-4 w-4" />
                                        Add part
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Testing & QC checklist -->
                    <Card>
                        <CardHeader class="flex-row items-center justify-between">
                            <CardTitle><ClipboardCheck class="mr-2 inline h-4 w-4" />Testing &amp; QC</CardTitle>
                            <div class="text-sm font-medium" :class="checklistProgress.failed > 0 ? 'text-destructive' : 'text-muted-foreground'">
                                {{ checklistProgress.passed }}/{{ checklistProgress.total }} passed
                                <span v-if="checklistProgress.failed > 0"> Â· {{ checklistProgress.failed }} failed</span>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="checklist_items.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                                No checklist items yet â€” record the device condition up front, then the QC sign-off after the repair.
                            </div>

                            <div v-for="group in checklistGroups" :key="group.key" class="space-y-2">
                                <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ group.title }}</div>
                                <div v-for="i in group.items" :key="i.id" class="flex items-center gap-3 rounded-lg border p-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                                :class="statusToneFor(i.status)"
                                            >{{ i.status }}</span>
                                            <span class="text-sm font-medium">{{ i.label }}</span>
                                        </div>
                                        <div v-if="i.note" class="mt-0.5 text-xs text-muted-foreground">{{ i.note }}</div>
                                        <div v-if="i.checked_by" class="mt-0.5 text-xs text-muted-foreground">
                                            by {{ i.checked_by }}<template v-if="i.checked_at"> Â· {{ i.checked_at }}</template>
                                        </div>
                                    </div>
                                    <Select :model-value="i.status" @update:model-value="(v: string) => setChecklistStatus(i, v)">
                                        <SelectTrigger class="w-32">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="st in checklist_statuses" :key="st" :value="st">
                                                <SelectItemText>{{ pretty(st) }}</SelectItemText>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button variant="ghost" size="icon" @click="removeChecklistItem(i.id)">
                                        <Trash2 class="h-4 w-4 text-muted-foreground" />
                                    </Button>
                                </div>
                            </div>

                            <Separator />

                            <form @submit.prevent="addChecklistItem" class="grid gap-3 sm:grid-cols-12">
                                <div class="grid gap-1 sm:col-span-4">
                                    <Label>Check</Label>
                                    <Input v-model="checklistForm.label" placeholder="e.g. No cracks, screen lights up" required :class="{ 'border-destructive': checklistForm.errors.label }" />
                                    <InputError :message="checklistForm.errors.label" />
                                </div>
                                <div class="grid gap-1 sm:col-span-3">
                                    <Label>Note</Label>
                                    <Input v-model="checklistForm.note" placeholder="Details (optional)" />
                                </div>
                                <div class="grid gap-1 sm:col-span-2">
                                    <Label>Stage</Label>
                                    <Select v-model="checklistForm.phase">
                                        <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="ph in checklist_phases" :key="ph" :value="ph">
                                                <SelectItemText>{{ pretty(ph) }}</SelectItemText>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-1 sm:col-span-2">
                                    <Label>Status</Label>
                                    <Select v-model="checklistForm.status">
                                        <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="st in checklist_statuses" :key="st" :value="st">
                                                <SelectItemText>{{ pretty(st) }}</SelectItemText>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex items-end gap-2 sm:col-span-1">
                                    <Button type="submit" size="sm" class="w-full" :disabled="checklistForm.processing">
                                        <Plus class="h-4 w-4" />
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
                        <span
                            v-if="invoice?.approved_at"
                            class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-300"
                        >
                            <CheckCircle2 class="h-3 w-3" />
                            Approved
                        </span>
                        <Button v-if="!invoice" variant="outline" size="sm" :disabled="invoiceForm.processing" @click="generateInvoice">
                            <FileText class="mr-1 h-4 w-4" />
                            Generate invoice
                        </Button>
                        <Button v-else-if="canApproveInvoice" size="sm" :disabled="approveForm.processing" @click="approveInvoice">
                            <CheckCircle2 class="mr-1 h-4 w-4" />
                            Approve estimate
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

                    <InputError class="mb-4 text-sm" :message="approveForm.errors.approve" />

                    <template v-if="invoice">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <!-- Adjustments -->
                            <div class="space-y-3">
                                <h3 class="text-sm font-medium">Adjustments</h3>
                                <p v-if="invoice.adjustments.length === 0" class="text-xs text-muted-foreground">No adjustments.</p>
                                <div v-for="a in invoice.adjustments" :key="a.id" class="flex items-center gap-2 rounded-lg border p-2 text-sm">
                                    <span class="flex-1 capitalize">{{ a.type }} Â· {{ pretty(a.reason) }}</span>
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
                                        Â· {{ tx.method }}
                                        <span v-if="tx.note" class="text-muted-foreground"> Â· {{ tx.note }}</span>
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
