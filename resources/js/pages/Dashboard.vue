<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Wrench,
    Users,
    TriangleAlert,
    Plus,
    UserPlus,
    Smartphone,
    Package,
    type LucideIcon,
} from 'lucide-vue-next';
import { Head, Link } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Stats {
    open_tickets: number;
    ready_devices: number;
    customers: number;
    low_stock_products: number;
}

interface Ticket {
    id: number;
    title: string;
    status: string;
    priority: string;
    device: string | null;
    customer: string | null;
    assignee: string | null;
    created_at: string;
}

interface LowStockProduct {
    id: number;
    name: string;
    sku: string | null;
    stock: number;
    reorder_level: number;
    category: string | null;
}

interface Business {
    name: string;
    trade: string | null;
    currency: string;
}

interface Props {
    business: Business | null;
    stats: Stats;
    recent_tickets: Ticket[];
    low_stock_products: LowStockProduct[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

const statCards: { label: string; value: number; icon: LucideIcon; tone: string }[] = [
    { label: 'Open tickets', value: props.stats.open_tickets, icon: Wrench, tone: 'text-primary' },
    { label: 'Ready for pickup', value: props.stats.ready_devices, icon: Wrench, tone: 'text-green-600 dark:text-green-400' },
    { label: 'Customers', value: props.stats.customers, icon: Users, tone: 'text-foreground' },
    { label: 'Low-stock items', value: props.stats.low_stock_products, icon: TriangleAlert, tone: 'text-amber-600 dark:text-amber-400' },
];

const statusTone: Record<string, string> = {
    new: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    on_hold: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400',
    resolved: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    closed: 'bg-neutral-100 text-neutral-500 dark:bg-neutral-900 dark:text-neutral-400',
};

const quickActions: { label: string; icon: LucideIcon; href: string }[] = [
    { label: 'New intake', icon: Plus, href: route('tickets.create') },
    { label: 'Add customer', icon: UserPlus, href: route('customers.create') },
    { label: 'Register device', icon: Smartphone, href: route('devices.create') },
    { label: 'New product', icon: Package, href: route('products.create') },
];
</script>

<template>
    <Head :title="business ? business.name : 'Dashboard'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight">
                        {{ business?.name ?? 'Dashboard' }}
                    </h2>
                    <p v-if="business?.trade" class="text-sm text-muted-foreground">
                        {{ business.trade }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('products.index')" class="text-sm font-medium underline underline-offset-4">
                        Manage products
                    </Link>
                    <Link :href="route('tickets.create')">
                        <Button size="lg">
                            <Plus class="h-4 w-4" />
                            New intake
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Link v-for="action in quickActions" :key="action.label" :href="action.href" class="rounded-xl">
                    <Button variant="outline" class="w-full justify-start gap-2 font-normal">
                        <component :is="action.icon" class="h-4 w-4" />
                        {{ action.label }}
                    </Button>
                </Link>
            </div>

            <!-- Stat cards -->
            <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card v-for="card in statCards" :key="card.label">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            {{ card.label }}
                        </CardTitle>
                        <component :is="card.icon" :class="['h-4 w-4', card.tone]" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ card.value }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Two-column: recent tickets + low stock -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Recent tickets</CardTitle>
                        <CardDescription>Latest work orders across the shop</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p v-if="recent_tickets.length === 0" class="text-sm text-muted-foreground">
                            No tickets yet.
                        </p>
                        <ul v-else class="divide-y">
                            <li v-for="ticket in recent_tickets" :key="ticket.id" class="flex items-center justify-between gap-2 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ ticket.title }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ ticket.device ?? '—' }} · {{ ticket.customer ?? 'No customer' }}
                                        <template v-if="ticket.assignee"> · {{ ticket.assignee }}</template>
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="statusTone[ticket.status] ?? 'bg-neutral-100 dark:bg-neutral-900'"
                                >
                                    {{ ticket.status.replace('_', ' ') }}
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Low stock</CardTitle>
                        <CardDescription>Items at or below their reorder level</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p v-if="low_stock_products.length === 0" class="text-sm text-muted-foreground">
                            All stocked items are healthy.
                        </p>
                        <ul v-else class="divide-y">
                            <li v-for="product in low_stock_products" :key="product.id" class="flex items-center justify-between gap-2 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ product.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ product.sku ?? 'No SKU' }}
                                        <template v-if="product.category"> · {{ product.category }}</template>
                                    </p>
                                </div>
                                <span class="shrink-0 text-sm font-medium text-amber-600 dark:text-amber-400">
                                    {{ product.stock }} / {{ product.reorder_level }}
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
