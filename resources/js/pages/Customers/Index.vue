<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Plus, Search, Users } from 'lucide-vue-next';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Customer {
    id: number;
    name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    is_business: boolean;
    device_count: number;
}

interface Props {
    customers: Customer[];
    search: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: '/customers' },
];

const form = useForm({ search: props.search });

function searchNow() {
    router.get(route('customers.index'), form.data(), { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <Heading
                    title="Customers"
                    description="Everyone who brings in a repair — individuals and businesses."
                />
                <Button as-child>
                    <Link :href="route('customers.create')">
                        <Plus class="h-4 w-4" />
                        Add customer
                    </Link>
                </Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                    class="pl-9"
                    placeholder="Search name, company, email, or phone…"
                    :value="form.search"
                    @update:model-value="form.search = $event"
                    @keyup.enter="searchNow()"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        <Users class="mr-2 inline h-4 w-4" />
                        {{ customers.length }} customer{{ customers.length === 1 ? '' : 's' }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="customers.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No customers found{{ search ? ` for “${search}”` : '' }}.
                    </p>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr class="text-left text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Name</th>
                                    <th class="px-4 py-2 font-medium">Contact</th>
                                    <th class="px-4 py-2 font-medium">Type</th>
                                    <th class="px-4 py-2 text-right font-medium">Devices</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="c in customers" :key="c.id" class="hover:bg-muted/20">
                                    <td class="px-4 py-2">
                                        <Link :href="route('customers.show', c.id)" class="font-medium hover:underline">
                                            {{ c.name }}
                                        </Link>
                                        <div v-if="c.company" class="text-xs text-muted-foreground">{{ c.company }}</div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div v-if="c.email">{{ c.email }}</div>
                                        <div v-if="c.phone" class="text-xs text-muted-foreground">{{ c.phone }}</div>
                                        <span v-if="!c.email && !c.phone" class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="c.is_business
                                                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                                : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400'"
                                        >
                                            {{ c.is_business ? 'Business' : 'Individual' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ c.device_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
