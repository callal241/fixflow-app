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

interface Device {
    id: number;
    customer_id: number | null;
    model: string;
    model_number: string | null;
    brand: string | null;
    serial_number: string | null;
    imei: string | null;
    color: string | null;
    storage: string | null;
    carrier: string | null;
    type: string;
    status: string;
    purchase_date: string | null;
    warranty_expire_date: string | null;
}

interface Props {
    device: Device;
    customers: CustomerOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devices', href: '/devices' },
    { title: props.device.model, href: route('devices.show', props.device.id) },
    { title: 'Edit', href: '' },
];

const formDevice = {
    id: props.device.id,
    customer_id: props.device.customer_id,
    model: props.device.model,
    model_number: props.device.model_number ?? '',
    brand: props.device.brand ?? '',
    serial_number: props.device.serial_number ?? '',
    imei: props.device.imei ?? '',
    color: props.device.color ?? '',
    storage: props.device.storage ?? '',
    carrier: props.device.carrier ?? '',
    type: props.device.type,
    status: props.device.status,
    purchase_date: props.device.purchase_date ?? '',
    warranty_expire_date: props.device.warranty_expire_date ?? '',
};
</script>

<template>
    <Head title="Edit device" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading title="Edit device" :description="'Update details for ' + device.model + '.'" />
            <div class="max-w-3xl">
                <DeviceForm
                    title="Device details"
                    submit-label="Save changes"
                    :customers="customers"
                    :device="formDevice"
                />
            </div>
        </div>
    </AppLayout>
</template>
