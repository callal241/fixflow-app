<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Plus, Search, Wrench } from 'lucide-vue-next';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Ticket {
    id: number;
    ticket_number: string | null;
    title: string;
    status: string;
    priority: string;
    device: string | null;
    customer: string | null;
    company: string | null;
    assignee: string | null;
    due_date: string | null;
    created_at: string | null;
}

interface Props {
    tickets: Ticket[];
    search: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets', href: '/tickets' },
];

const form = useForm({ search: props.search });

function searchNow() {
    router.get(route('tickets.index'), form.data(), { preserveState: true, replace: true });
}

const statusTone: Record<string, string> = {
    new: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    on_hold: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    resolved: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    closed: 'bg-neutral-200 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-500',
};

const priorityTone: Record<string, string> = {
    low: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400',
    medium: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
    urgent: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
};

const pretty = (s: string) => s.replace(/_/g, ' ');
</script>

<template>
    <Head title="Tickets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <Heading
                    title="Repair tickets"
                    description="Work in progress — open one to log repairs and sell parts."
                />
                <Button as-child>
                    <Link :href="route('tickets.create')">
                        <Plus class="h-4 w-4" />
                        Intake
                    </Link>
                </Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                    class="pl-9"
                    placeholder="Search number, title, device, serial, customer…"
                    :value="form.search"
                    @update:model-value="form.search = $event"
                    @keyup.enter="searchNow()"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        <Wrench class="mr-2 inline h-4 w-4" />
                        {{ tickets.length }} ticket{{ tickets.length === 1 ? '' : 's' }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="tickets.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No tickets found{{ search ? ` for “${search}”` : ' yet' }}.
                    </p>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr class="text-left text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Ticket</th>
                                    <th class="px-4 py-2 font-medium">Device</th>
                                    <th class="px-4 py-2 font-medium">Customer</th>
                                    <th class="px-4 py-2 font-medium">Assignee</th>
                                    <th class="px-4 py-2 font-medium">Priority</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="ticket in tickets" :key="ticket.id" class="align-middle hover:bg-muted/20">
                                    <td class="px-4 py-2">
                                        <Link :href="route('tickets.show', ticket.id)" class="font-medium text-primary hover:underline">
                                            {{ ticket.ticket_number ?? `#${ticket.id}` }}
                                        </Link>
                                        <div class="text-xs text-muted-foreground">{{ ticket.title }}</div>
                                    </td>
                                    <td class="px-4 py-2">{{ ticket.device ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span v-if="ticket.customer">{{ ticket.customer }}</span>
                                        <span v-else class="text-muted-foreground">—</span>
                                        <div v-if="ticket.company" class="text-xs text-muted-foreground">{{ ticket.company }}</div>
                                    </td>
                                    <td class="px-4 py-2">{{ ticket.assignee ?? 'Unassigned' }}</td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="priorityTone[ticket.priority] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                                        >
                                            {{ ticket.priority }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="statusTone[ticket.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                                        >
                                            {{ pretty(ticket.status) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
