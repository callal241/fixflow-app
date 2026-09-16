<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Plus, Search, Smartphone } from 'lucide-vue-next';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Device {
    id: number;
    model: string;
    brand: string | null;
    serial_number: string | null;
    imei: string | null;
    type: string;
    status: string;
    customer: string | null;
    company: string | null;
    tickets_count: number;
}

interface Props {
    devices: Device[];
    search: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devices', href: '/devices' },
];

const form = useForm({ search: props.search });

function searchNow() {
    router.get(route('devices.index'), form.data(), { preserveState: true, replace: true });
}

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
    <Head title="Devices" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <Heading
                    title="Devices"
                    description="Every device on the bench — searchable by model, brand, serial, or IMEI."
                />
                <Button as-child>
                    <Link :href="route('devices.create')">
                        <Plus class="h-4 w-4" />
                        Add device
                    </Link>
                </Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                    class="pl-9"
                    placeholder="Search model, brand, serial, or IMEI…"
                    :value="form.search"
                    @update:model-value="form.search = $event"
                    @keyup.enter="searchNow()"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        <Smartphone class="mr-2 inline h-4 w-4" />
                        {{ devices.length }} device{{ devices.length === 1 ? '' : 's' }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="devices.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No devices found{{ search ? ` for “${search}”` : '' }}.
                    </p>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr class="text-left text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Device</th>
                                    <th class="px-4 py-2 font-medium">Serial / IMEI</th>
                                    <th class="px-4 py-2 font-medium">Customer</th>
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
                                        <div v-if="d.brand" class="text-xs text-muted-foreground">
                                            {{ d.brand }} · {{ pretty(d.type) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-xs">
                                        <div v-if="d.serial_number">{{ d.serial_number }}</div>
                                        <div v-if="d.imei" class="text-muted-foreground">IMEI {{ d.imei }}</div>
                                        <span v-if="!d.serial_number && !d.imei" class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span v-if="d.customer">{{ d.customer }}</span>
                                        <span v-else class="text-muted-foreground">—</span>
                                        <div v-if="d.company" class="text-xs text-muted-foreground">{{ d.company }}</div>
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
        </div>
    </AppLayout>
</template>
