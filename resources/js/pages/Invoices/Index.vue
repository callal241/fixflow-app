<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FileText, Receipt } from 'lucide-vue-next';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface Invoice {
    id: number;
    ticket: string | null;
    customer: string | null;
    company: string | null;
    status: string;
    total: number;
    paid_amount: number;
    due_date: string | null;
    created_at: string | null;
}

interface Props {
    invoices: Invoice[];
    search: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Invoices', href: '/invoices' },
];

const currency = (n: number) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n);

const searchInput = ref(props.search);
watch(searchInput, (value) => {
    useForm({ search: value }).get(route('invoices.index'), { preserveState: true, replace: true });
});

const statusTone: Record<string, string> = {
    draft: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    issued: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    sent: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    paid: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    refunded: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
};

const pretty = (s: string) => s.replace(/_/g, ' ');
</script>

<template>
    <Head title="Invoices" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <Heading
                    title="Invoices"
                    description="What each repair and sale is billed for."
                />
            </div>

            <div class="relative max-w-xs">
                <Receipt class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <input
                    v-model="searchInput"
                    type="search"
                    placeholder="Search ticket, device, customer..."
                    class="h-9 w-full rounded-md border bg-transparent pl-8 pr-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        <FileText class="mr-2 inline h-4 w-4" />
                        {{ invoices.length }} invoice{{ invoices.length === 1 ? '' : 's' }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="invoices.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No invoices yet. Generate one from a repair ticket's billable work.
                    </p>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr class="text-left text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Ticket</th>
                                    <th class="px-4 py-2 font-medium">Customer</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                    <th class="px-4 py-2 text-right font-medium">Total</th>
                                    <th class="px-4 py-2 text-right font-medium">Paid</th>
                                    <th class="px-4 py-2 text-right font-medium">Balance</th>
                                    <th class="px-4 py-2 font-medium">Due</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="invoice in invoices"
                                    :key="invoice.id"
                                    class="hover:bg-muted/20"
                                >
                                    <td class="px-4 py-2">
                                        <Link
                                            :href="route('invoices.show', invoice.id)"
                                            class="font-medium hover:underline"
                                        >
                                            {{ invoice.ticket ?? `Invoice #${invoice.id}` }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div>{{ invoice.customer ?? '-' }}</div>
                                        <div v-if="invoice.company" class="text-xs text-muted-foreground">
                                            {{ invoice.company }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="statusTone[invoice.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                                        >
                                            {{ pretty(invoice.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ currency(invoice.total) }}</td>
                                    <td class="px-4 py-2 text-right">{{ currency(invoice.paid_amount) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <span
                                            :class="(invoice.total - invoice.paid_amount) > 0
                                                ? 'font-medium text-amber-600 dark:text-amber-400'
                                                : 'text-muted-foreground'"
                                        >
                                            {{ currency(invoice.total - invoice.paid_amount) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">{{ invoice.due_date ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
