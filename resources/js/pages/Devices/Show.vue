<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Pencil, Plus, ShieldCheck, ShieldOff } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface Device {
    id: number;
    model: string;
    model_number: string | null;
    brand: string | null;
    serial_number: string | null;
    imei: string | null;
    color: string | null;
    storage: string | null;
    carrier: string | null;
    type: string;
    status: string;
    purchase_date: string | null;
    warranty_expire_date: string | null;
    has_warranty: boolean;
    customer: {
        id: number;
        name: string;
        company: string | null;
        phone: string | null;
    } | null;
}

interface Ticket {
    id: number;
    ticket_number: string | null;
    title: string;
    status: string;
    priority: string;
    created_at: string | null;
}

interface Props {
    device: Device;
    tickets: Ticket[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devices', href: '/devices' },
    { title: props.device.model, href: '' },
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

const pretty = (s: string) => s.replace(/_/g, ' ');
</script>

<template>
    <Head :title="device.model" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex items-start justify-between">
                <Heading
                    :title="device.model"
                    :description="`${device.brand ?? 'Unknown brand'} · ${pretty(device.type)}`"
                />
                <div class="flex items-center gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('devices.edit', device.id)">
                            <Pencil class="h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                    <Button v-if="device.customer" as-child>
                        <Link :href="route('tickets.create', { device: device.id })">
                            <Plus class="h-4 w-4" />
                            New repair
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
                <div class="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Device</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Model number</span>
                                <span>{{ device.model_number ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Serial</span>
                                <span class="font-mono text-xs">{{ device.serial_number ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">IMEI</span>
                                <span class="font-mono text-xs">{{ device.imei ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Color</span>
                                <span>{{ device.color ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Storage</span>
                                <span>{{ device.storage ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Carrier</span>
                                <span>{{ device.carrier ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Status</span>
                                <span class="capitalize">{{ pretty(device.status) }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Customer &amp; warranty</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Customer</span>
                                <Link v-if="device.customer" :href="route('customers.show', device.customer.id)" class="font-medium hover:underline">
                                    {{ device.customer.name }}
                                </Link>
                                <span v-else>—</span>
                            </div>
                            <div v-if="device.customer?.company" class="flex justify-between">
                                <span class="text-muted-foreground">Company</span>
                                <span>{{ device.customer.company }}</span>
                            </div>
                            <div v-if="device.customer?.phone" class="flex justify-between">
                                <span class="text-muted-foreground">Phone</span>
                                <span>{{ device.customer.phone }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Purchased</span>
                                <span>{{ device.purchase_date ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Warranty</span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="device.has_warranty
                                        ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                        : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-900'"
                                >
                                <component :is="device.has_warranty ? ShieldCheck : ShieldOff" class="h-3.5 w-3.5" />
                                {{ device.has_warranty ? 'Active' : 'Expired / none' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Expires</span>
                                <span>{{ device.warranty_expire_date ?? '—' }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader class="flex-row items-center justify-between">
                            <CardTitle>Repairs on this device ({{ tickets.length }})</CardTitle>
                            <Button v-if="device.customer" variant="outline" size="sm" as-child>
                                <Link :href="route('tickets.create', { device: device.id })">
                                    <Plus class="h-4 w-4" />
                                    New repair
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <p v-if="tickets.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                No repair tickets on this device yet.
                            </p>
                            <div v-else class="overflow-x-auto rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/40">
                                        <tr class="text-left text-muted-foreground">
                                            <th class="px-4 py-2 font-medium">Ticket</th>
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
