<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Customer {
    id: number;
    name: string;
    company: string | null;
    vat_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    note: string | null;
}

interface Props {
    customer: Customer;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: '/customers' },
    { title: props.customer.name, href: route('customers.show', props.customer.id) },
    { title: 'Edit', href: '' },
];

const form = useForm({
    name: props.customer.name,
    company: props.customer.company ?? '',
    vat_number: props.customer.vat_number ?? '',
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    address: props.customer.address ?? '',
    note: props.customer.note ?? '',
});

const submit = () => form.put(route('customers.update', props.customer.id), { preserveScroll: true });
</script>

<template>
    <Head title="Edit customer" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading title="Edit customer" :description="'Update details for ' + customer.name + '.'" />

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="company">Company</Label>
                        <Input id="company" v-model="form.company" />
                        <InputError :message="form.errors.company" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" v-model="form.phone" />
                        <InputError :message="form.errors.phone" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="vat_number">VAT / Tax number</Label>
                        <Input id="vat_number" v-model="form.vat_number" />
                        <InputError :message="form.errors.vat_number" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input id="address" v-model="form.address" />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-2">
                    <Label for="note">Notes</Label>
                    <textarea
                        id="note"
                        v-model="form.note"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <InputError :message="form.errors.note" />
                </div>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                    <Button type="button" variant="ghost" as-child>
                        <Link :href="route('customers.show', customer.id)">Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
