<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import DeviceForm from '@/components/DeviceForm.vue';
import { Head, Link } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface CustomerOption {
    id: number;
    name: string;
    company: string | null;
}

interface Props {
    customers: CustomerOption[];
    default_customer_id: number | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devices', href: '/devices' },
    { title: 'Add', href: '/devices/create' },
];
</script>

<template>
    <Head title="Add device" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading
                title="Add a device"
                description="Record the device being brought in. Serial and IMEI make it easy to find later."
            />
            <div class="max-w-3xl">
                <DeviceForm
                    title="Device details"
                    submit-label="Save device"
                    :customers="customers"
                    :default-customer-id="default_customer_id"
                />
            </div>
        </div>
    </AppLayout>
</template>
