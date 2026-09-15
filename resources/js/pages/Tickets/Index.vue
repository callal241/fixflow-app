<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Wrench } from 'lucide-vue-next';
import { Head, Link } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Ticket {
    id: number;
    title: string;
    status: string;
    priority: string;
    device: string | null;
    customer: string | null;
    assignee: string | null;
    created_at: string | null;
}

interface Props {
    tickets: Ticket[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets', href: '/tickets' },
];

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
    <Head title="Tickets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading
                title="Repair tickets"
                description="Work in progress — open one to log repairs and sell parts."
            />

            <Card>
                <CardHeader>
                    <CardTitle>
                        <Wrench class="mr-2 inline h-4 w-4" />
                        {{ tickets.length }} ticket{{ tickets.length === 1 ? '' : 's' }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="tickets.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No tickets yet.
                    </p>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr class="text-left text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Ticket</th>
                                    <th class="px-4 py-2 font-medium">Device</th>
                                    <th class="px-4 py-2 font-medium">Customer</th>
                                    <th class="px-4 py-2 font-medium">Assignee</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="ticket in tickets" :key="ticket.id" class="align-middle">
                                    <td class="px-4 py-2">
                                        <Link :href="route('tickets.show', { ticket: ticket.id })" class="font-medium text-primary hover:underline">
                                            #{{ ticket.id }} · {{ ticket.title }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2">{{ ticket.device ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ ticket.customer ?? 'No customer' }}</td>
                                    <td class="px-4 py-2">{{ ticket.assignee ?? 'Unassigned' }}</td>
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
