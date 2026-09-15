<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Category {
    id: number;
    name: string;
}

interface Props {
    categories: Category[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: '/products' },
    { title: 'Add', href: '/products/create' },
];

const form = useForm({
    name: '',
    category_id: null as number | null,
    sku: '',
    barcode: '',
    condition: '',
    price: 0,
    cost: 0,
    stock: 0,
    reorder_level: 0,
    description: '',
    is_active: true,
});

const submit = () => {
    form.post(route('products.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Add product" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading
                title="Add a product"
                description="Add a new item to your shop's stock. Condition is free-text so any item works."
            />

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        required
                        placeholder="e.g. Replacement Screen - 5.5&quot;"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="category_id">Category</Label>
                        <select
                            id="category_id"
                            v-model.number="form.category_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option :value="null">Uncategorised</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="condition">Condition</Label>
                        <Input
                            id="condition"
                            v-model="form.condition"
                            placeholder="new / refurbished / used / faulty…"
                        />
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
                        <Label for="stock">Stock on hand</Label>
                        <Input id="stock" v-model.number="form.stock" type="number" min="0" required />
                        <InputError :message="form.errors.stock" />
                    </div>
                </div>

                <div class="grid gap-2 sm:max-w-[200px]">
                    <Label for="reorder_level">Reorder level</Label>
                    <Input id="reorder_level" v-model.number="form.reorder_level" type="number" min="0" />
                    <InputError :message="form.errors.reorder_level" />
                    <p class="text-xs text-muted-foreground">Flag the item when stock falls to this number.</p>
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
                    <input
                        type="checkbox"
                        v-model="form.is_active"
                        class="h-4 w-4 rounded border-input"
                    />
                    Available for sale
                </label>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save product' }}
                    </Button>
                    <Button type="button" variant="ghost" as-child>
                        <Link :href="route('products.index')">Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
