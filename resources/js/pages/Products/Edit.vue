<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectItemText, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Category {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
    barcode: string | null;
    condition: string | null;
    price: number;
    cost: number;
    stock: number;
    reorder_level: number;
    description: string | null;
    is_active: boolean;
    category_id: number | null;
}

interface Props {
    product: Product;
    categories: Category[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: '/products' },
    { title: props.product.name, href: route('products.edit', props.product.id) },
    { title: 'Edit', href: '' },
];

const form = useForm({
    name: props.product.name,
    category_id: props.product.category_id,
    sku: props.product.sku ?? '',
    barcode: props.product.barcode ?? '',
    condition: props.product.condition ?? '',
    price: props.product.price,
    cost: props.product.cost,
    reorder_level: props.product.reorder_level,
    description: props.product.description ?? '',
    is_active: props.product.is_active,
});

const submit = () =>
    form.put(route('products.update', props.product.id), { preserveScroll: true });
</script>

<template>
    <Head :title="'Edit ' + product.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading
                :title="'Edit ' + product.name"
                description="Update this item's details. On-hand stock is adjusted from the stock list so movements stay explicit."
            />

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="category_id">Category</Label>
                        <Select v-model="form.category_id">
                            <SelectTrigger id="category_id" class="w-full" :class="{ 'border-destructive': form.errors.category_id }">
                                <SelectValue placeholder="Uncategorised" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">Uncategorised</SelectItem>
                                <SelectItem v-for="c in categories" :key="c.id" :value="c.id">
                                    <SelectItemText>{{ c.name }}</SelectItemText>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.category_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="condition">Condition</Label>
                        <Input id="condition" v-model="form.condition" placeholder="new / refurbished / used / faulty…" />
                        <InputError :message="form.errors.condition" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="sku">SKU</Label>
                        <Input id="sku" v-model="form.sku" placeholder="SKU-1000AB" />
                        <InputError :message="form.errors.sku" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="barcode">Barcode</Label>
                        <Input id="barcode" v-model="form.barcode" placeholder="Scan or type" />
                        <InputError :message="form.errors.barcode" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="price">Price</Label>
                        <Input id="price" v-model.number="form.price" type="number" step="0.01" min="0" required />
                        <InputError :message="form.errors.price" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cost">Cost</Label>
                        <Input id="cost" v-model.number="form.cost" type="number" step="0.01" min="0" />
                        <InputError :message="form.errors.cost" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="reorder_level">Reorder level</Label>
                        <Input id="reorder_level" v-model.number="form.reorder_level" type="number" min="0" />
                        <InputError :message="form.errors.reorder_level" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Notes, provenance, defects…"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-input" />
                    Available for sale
                </label>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                    <Button type="button" variant="ghost" as-child>
                        <Link :href="route('products.index')">Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
