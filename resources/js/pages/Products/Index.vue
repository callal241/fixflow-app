<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus, Package } from 'lucide-vue-next';
import { Head, Link } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Category {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
    condition: string | null;
    price: number;
    stock: number;
    reorder_level: number;
    is_active: boolean;
    is_low_stock: boolean;
    category: string | null;
}

interface Props {
    products: Product[];
    categories: Category[];
    low_stock_count: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: '/products' },
];

const currency = (n: number) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n);
</script>

<template>
    <Head title="Products" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <Heading
                    title="Products & stock"
                    description="Everything you sell — new, used, and the odd stuff."
                />
                <Button as-child>
                    <Link :href="route('products.create')">
                        <Plus class="h-4 w-4" />
                        Add product
                    </Link>
                </Button>
            </div>

            <div
                v-if="low_stock_count > 0"
                class="rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200"
            >
                {{ low_stock_count }} item{{ low_stock_count === 1 ? '' : 's' }} at or below reorder level.
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        <Package class="mr-2 inline h-4 w-4" />
                        {{ products.length }} product{{ products.length === 1 ? '' : 's' }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="products.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No products yet. Add your first item to start selling.
                    </p>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr class="text-left text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Name</th>
                                    <th class="px-4 py-2 font-medium">Category</th>
                                    <th class="px-4 py-2 font-medium">Condition</th>
                                    <th class="px-4 py-2 text-right font-medium">Price</th>
                                    <th class="px-4 py-2 text-right font-medium">Stock</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="product in products" :key="product.id" class="hover:bg-muted/20">
                                    <td class="px-4 py-2">
                                        <div class="font-medium">{{ product.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ product.sku ?? 'No SKU' }}</div>
                                    </td>
                                    <td class="px-4 py-2">{{ product.category ?? '—' }}</td>
                                    <td class="px-4 py-2 capitalize">{{ product.condition ?? '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ currency(product.price) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <span
                                            class="font-medium"
                                            :class="product.is_low_stock ? 'text-amber-600 dark:text-amber-400' : ''"
                                        >
                                            {{ product.stock }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            v-if="!product.is_active"
                                            class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500 dark:bg-neutral-900"
                                        >
                                            Inactive
                                        </span>
                                        <span
                                            v-else-if="product.stock === 0"
                                            class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300"
                                        >
                                            Out of stock
                                        </span>
                                        <span
                                            v-else-if="product.is_low_stock"
                                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                        >
                                            Low
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-300"
                                        >
                                            In stock
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
