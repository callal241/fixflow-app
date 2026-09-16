<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectItemText, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { Smartphone } from 'lucide-vue-next';

interface CustomerOption {
    id: number;
    name: string;
    company: string | null;
}

interface Props {
    title: string;
    submitLabel: string;
    customers: CustomerOption[];
    defaultCustomerId?: number | null;
    device?: {
        id?: number;
        customer_id: number | null;
        model: string;
        model_number: string;
        brand: string;
        serial_number: string;
        imei: string;
        color: string;
        storage: string;
        carrier: string;
        type: string;
        status: string;
        purchase_date: string;
        warranty_expire_date: string;
    };
}

const props = defineProps<Props>();

const d = props.device;

const form = useForm({
    customer_id: (d?.customer_id ?? props.defaultCustomerId ?? null) as number | null,
    model: d?.model ?? '',
    model_number: d?.model_number ?? '',
    brand: d?.brand ?? '',
    serial_number: d?.serial_number ?? '',
    imei: d?.imei ?? '',
    color: d?.color ?? '',
    storage: d?.storage ?? '',
    carrier: d?.carrier ?? '',
    type: d?.type ?? 'other',
    status: d?.status ?? 'received',
    purchase_date: d?.purchase_date ?? '',
    warranty_expire_date: d?.warranty_expire_date ?? '',
});

const deviceTypes = [
    'phone', 'tablet', 'laptop', 'desktop', 'wearable', 'other',
];
const deviceStatuses = [
    'received', 'on_hold', 'under_repair', 'ready', 'delivered',
];

function submit() {
    if (d?.id) {
        form.put(route('devices.update', d.id), { preserveScroll: true });
    } else {
        form.post(route('devices.store'), { preserveScroll: true });
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>
                <Smartphone class="mr-2 inline h-4 w-4" />
                {{ title }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="customer_id">Customer</Label>
                        <Select v-model="form.customer_id" :disabled="form.processing">
                            <SelectTrigger id="customer_id" class="w-full" :class="{ 'border-destructive': form.errors.customer_id }">
                                <SelectValue placeholder="Choose a customer…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in customers" :key="c.id" :value="c.id">
                                    <SelectItemText>{{ c.name }}{{ c.company ? ` — ${c.company}` : '' }}</SelectItemText>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.customer_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="model">Model</Label>
                        <Input id="model" v-model="form.model" required placeholder="e.g. iPhone 15 Pro" />
                        <InputError :message="form.errors.model" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="brand">Brand / Manufacturer</Label>
                        <Input id="brand" v-model="form.brand" placeholder="Apple" />
                        <InputError :message="form.errors.brand" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="model_number">Model number</Label>
                        <Input id="model_number" v-model="form.model_number" placeholder="A2848" />
                        <InputError :message="form.errors.model_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="type">Type</Label>
                        <Select v-model="form.type">
                            <SelectTrigger id="type" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in deviceTypes" :key="t" :value="t">
                                    <SelectItemText>{{ t }}</SelectItemText>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.type" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="serial_number">Serial number</Label>
                        <Input id="serial_number" v-model="form.serial_number" placeholder="Scan or type" />
                        <InputError :message="form.errors.serial_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="imei">IMEI</Label>
                        <Input id="imei" v-model="form.imei" placeholder="15-digit IMEI" />
                        <InputError :message="form.errors.imei" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <Select v-model="form.status">
                            <SelectTrigger id="status" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in deviceStatuses" :key="s" :value="s">
                                    <SelectItemText>{{ s.replace(/_/g, ' ') }}</SelectItemText>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="color">Color</Label>
                        <Input id="color" v-model="form.color" placeholder="Natural Titanium" />
                        <InputError :message="form.errors.color" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="storage">Storage</Label>
                        <Input id="storage" v-model="form.storage" placeholder="256 GB" />
                        <InputError :message="form.errors.storage" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="carrier">Carrier</Label>
                        <Input id="carrier" v-model="form.carrier" placeholder="T-Mobile" />
                        <InputError :message="form.errors.carrier" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="purchase_date">Purchase date</Label>
                        <Input id="purchase_date" v-model="form.purchase_date" type="date" />
                        <InputError :message="form.errors.purchase_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="warranty_expire_date">Warranty expires</Label>
                        <Input id="warranty_expire_date" v-model="form.warranty_expire_date" type="date" />
                        <InputError :message="form.errors.warranty_expire_date" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : submitLabel }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
