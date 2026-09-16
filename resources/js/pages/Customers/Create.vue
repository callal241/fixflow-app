<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: '/customers' },
    { title: 'Add', href: '/customers/create' },
];

const form = useForm({
    name: '',
    company: '',
    vat_number: '',
    email: '',
    phone: '',
    address: '',
    note: '',
});

const submit = () => form.post(route('customers.store'), { preserveScroll: true });
</script>

<template>
    <Head title="Add customer" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading
                title="Add a customer"
                description="Fill in only what you have. Fill in the company to make it a business account."
            />

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" required placeholder="Full name or contact name" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="company">Company <span class="text-muted-foreground">(optional)</span></Label>
                        <Input id="company" v-model="form.company" placeholder="Business name — makes this a business account" />
                        <InputError :message="form.errors.company" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" placeholder="name@example.com" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" v-model="form.phone" placeholder="Used for SMS updates" />
                        <InputError :message="form.errors.phone" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="vat_number">VAT / Tax number <span class="text-muted-foreground">(optional)</span></Label>
                        <Input id="vat_number" v-model="form.vat_number" />
                        <InputError :message="form.errors.vat_number" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input id="address" v-model="form.address" placeholder="Street, city, state, zip" />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-2">
                    <Label for="note">Notes</Label>
                    <textarea
                        id="note"
                        v-model="form.note"
                        rows="3"
                        placeholder="Preferences, net terms, reminders…"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <InputError :message="form.errors.note" />
                </div>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save customer' }}
                    </Button>
                    <Button type="button" variant="ghost" as-child>
                        <Link :href="route('customers.index')">Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
