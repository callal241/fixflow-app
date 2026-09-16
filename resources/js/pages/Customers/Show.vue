<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Pencil, Phone, Plus, Smartphone, Wrench } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface Device {
    id: number;
    model: string;
    brand: string | null;
    serial_number: string | null;
    imei: string | null;
    type: string;
    status: string;
    tickets_count: number;
}

interface Ticket {
    id: number;
    ticket_number: string | null;
    title: string;
    status: string;
    priority: string;
    device: string | null;
    created_at: string | null;
}

interface Customer {
    id: number;
    name: string;
    company: string | null;
    vat_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    note: string | null;
    is_business: boolean;
    created_at: string | null;
}

interface Props {
    customer: Customer;
    devices: Device[];
    tickets: Ticket[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: '/customers' },
    { title: props.customer.name, href: '' },
];

const page = usePage();
const flashMessage = computed(() => (page.props as { flash?: { success: string | null } }).flash?.success ?? null);

const statusTone: Record<string, string> = {
    new: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    on_hold: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    resolved: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    closed: 'bg-neutral-200 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-500',
};

const deviceTone: Record<string, string> = {
    received: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    on_hold: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    under_repair: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    ready: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    delivered: 'bg-neutral-200 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-500',
};

const pretty = (s: string) => s.replace(/_/g, ' ');
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex items-start justify-between">
                <Heading
                    :title="customer.name"
                    :description="customer.company ?? 'Individual customer'"
                />
                <div class="flex items-center gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('customers.edit', customer.id)">
                            <Pencil class="h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('devices.create', { customer: customer.id })">
                            <Smartphone class="h-4 w-4" />
                            Add device
                        </Link>
                    </Button>
                </div>
            </div>

            <p
                v-if="flashMessage"
                class="rounded-lg border border-green-300/60 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800/60 dark:bg-green-950/40 dark:text-green-200"
            >
                {{ flashMessage }}
            </p>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Contact</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Type</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="customer.is_business
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400'"
                            >
                                {{ customer.is_business ? 'Business' : 'Individual' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Email</span>
                            <span>{{ customer.email ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Phone</span>
                            <span>{{ customer.phone ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">VAT / Tax</span>
                            <span>{{ customer.vat_number ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Address</span>
                            <span class="text-right">{{ customer.address ?? '—' }}</span>
                        </div>
                        <p v-if="customer.note" class="mt-2 rounded-md bg-muted/40 p-2 text-xs">{{ customer.note }}</p>
                    </CardContent>
                </Card>

                <div class="flex flex-col gap-4 lg:col-span-2">
                    <Card>
                        <CardHeader class="flex-row items-center justify-between">
                            <CardTitle>
                                <Smartphone class="mr-2 inline h-4 w-4" />
                                Devices ({{ devices.length }})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p v-if="devices.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                No devices yet.
                            </p>
                            <div v-else class="overflow-x-auto rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/40">
                                        <tr class="text-left text-muted-foreground">
                                            <th class="px-4 py-2 font-medium">Device</th>
                                            <th class="px-4 py-2 font-medium">Serial / IMEI</th>
                                            <th class="px-4 py-2 font-medium">Status</th>
                                            <th class="px-4 py-2 text-right font-medium">Repairs</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr v-for="d in devices" :key="d.id" class="hover:bg-muted/20">
                                            <td class="px-4 py-2">
                                                <Link :href="route('devices.show', d.id)" class="font-medium hover:underline">
                                                    {{ d.model }}
                                                </Link>
                                                <div v-if="d.brand" class="text-xs text-muted-foreground">{{ d.brand }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-xs">
                                                <div v-if="d.serial_number">{{ d.serial_number }}</div>
                                                <div v-if="d.imei" class="text-muted-foreground">IMEI {{ d.imei }}</div>
                                                <span v-if="!d.serial_number && !d.imei" class="text-muted-foreground">—</span>
                                            </td>
                                            <td class="px-4 py-2">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                                    :class="deviceTone[d.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                                                >
                                                    {{ pretty(d.status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-right">{{ d.tickets_count }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex-row items-center justify-between">
                            <CardTitle>
                                <Wrench class="mr-2 inline h-4 w-4" />
                                Repair history ({{ tickets.length }})
                            </CardTitle>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="route('tickets.create', { customer: customer.id })">
                                    <Plus class="h-4 w-4" />
                                    New repair
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <p v-if="tickets.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                No repair tickets yet.
                            </p>
                            <div v-else class="overflow-x-auto rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/40">
                                        <tr class="text-left text-muted-foreground">
                                            <th class="px-4 py-2 font-medium">Ticket</th>
                                            <th class="px-4 py-2 font-medium">Device</th>
                                            <th class="px-4 py-2 font-medium">Status</th>
                                            <th class="px-4 py-2 font-medium">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr v-for="t in tickets" :key="t.id" class="hover:bg-muted/20">
                                            <td class="px-4 py-2">
                                                <Link :href="route('tickets.show', t.id)" class="font-medium hover:underline">
                                                    {{ t.ticket_number ?? `#${t.id}` }}
                                                </Link>
                                                <div class="text-xs text-muted-foreground">{{ t.title }}</div>
                                            </td>
                                            <td class="px-4 py-2">{{ t.device ?? '—' }}</td>
                                            <td class="px-4 py-2">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                                    :class="statusTone[t.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                                                >
                                                    {{ pretty(t.status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-muted-foreground">{{ t.created_at ?? '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
