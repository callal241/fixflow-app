<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectItemText, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Smartphone, UserPlus, Wrench } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface CustomerOption {
    id: number;
    name: string;
    company: string | null;
}

interface DeviceOption {
    id: number;
    model: string;
    brand: string | null;
    serial_number: string | null;
}

interface Staff {
    id: number;
    name: string;
}

interface Props {
    customers: CustomerOption[];
    devices: DeviceOption[];
    selected_customer_id: number | null;
    selected_device_id: number | null;
    device_types: string[];
    priorities: string[];
    staff: Staff[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets', href: '/tickets' },
    { title: 'Intake', href: '/tickets/create' },
];

const form = useForm({
    // customer
    customer_id: props.selected_customer_id,
    new_customer: {
        name: '',
        company: '',
        email: '',
        phone: '',
        address: '',
    },
    // device
    device_id: props.selected_device_id,
    new_device: {
        model: '',
        model_number: '',
        brand: '',
        serial_number: '',
        imei: '',
        color: '',
        storage: '',
        carrier: '',
        type: 'phone',
    },
    // ticket
    title: '',
    description: '',
    internal_notes: '',
    intake_type: 'walk_in',
    priority: 'medium',
    due_date: '',
    assignee_id: null as number | null,
});

const submit = () => form.post(route('tickets.store'), { preserveScroll: true });

const intakeTypes = [
    { value: 'walk_in', label: 'Walk-in' },
    { value: 'appointment', label: 'Appointment' },
    { value: 'mail_in', label: 'Mail-in' },
    { value: 'warranty', label: 'Warranty' },
    { value: 'refurbishment', label: 'Refurbishment' },
    { value: 'b2b', label: 'Business (B2B)' },
];
</script>

<template>
    <Head title="New repair" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading
                title="New repair intake"
                description="Pull in a device in one shot — find the customer and device or type them as you go."
            />

            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <!-- CUSTOMER -->
                <Card>
                    <CardHeader>
                        <CardTitle>
                            <UserPlus class="mr-2 inline h-4 w-4" />
                            Customer
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label>Who is bringing it in?</Label>
                            <Select v-model="form.customer_id">
                                <SelectTrigger class="w-full" :class="{ 'border-destructive': form.errors.customer_id }">
                                    <SelectValue placeholder="Choose an existing customer…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">— New customer (type below) —</SelectItem>
                                    <SelectItem v-for="c in customers" :key="c.id" :value="c.id">
                                        <SelectItemText>{{ c.name }}{{ c.company ? ` — ${c.company}` : '' }}</SelectItemText>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.customer_id" />
                        </div>

                        <template v-if="!form.customer_id">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="nc-name">Name <span class="text-red-500">*</span></Label>
                                    <Input id="nc-name" v-model="form.new_customer.name" required placeholder="Full name" />
                                    <InputError :message="form.errors['new_customer.name']" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="nc-company">Company <span class="text-muted-foreground">(optional)</span></Label>
                                    <Input id="nc-company" v-model="form.new_customer.company" placeholder="Business name" />
                                    <InputError :message="form.errors['new_customer.company']" />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="nc-email">Email</Label>
                                    <Input id="nc-email" v-model="form.new_customer.email" type="email" />
                                    <InputError :message="form.errors['new_customer.email']" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="nc-phone">Phone <span class="text-muted-foreground">(for SMS updates)</span></Label>
                                    <Input id="nc-phone" v-model="form.new_customer.phone" />
                                    <InputError :message="form.errors['new_customer.phone']" />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label for="nc-address">Address</Label>
                                <Input id="nc-address" v-model="form.new_customer.address" />
                                <InputError :message="form.errors['new_customer.address']" />
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <!-- DEVICE -->
                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Smartphone class="mr-2 inline h-4 w-4" />
                            Device
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label>Which device?</Label>
                            <Select v-model="form.device_id">
                                <SelectTrigger class="w-full" :class="{ 'border-destructive': form.errors.device_id }">
                                    <SelectValue placeholder="Choose an existing device or add a new one…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">— New device (type below) —</SelectItem>
                                    <SelectItem v-for="d in devices" :key="d.id" :value="d.id">
                                        <SelectItemText>
                                            {{ d.brand ? `${d.brand} ` : '' }}{{ d.model }}{{ d.serial_number ? ` (${d.serial_number})` : '' }}
                                        </SelectItemText>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="devices.length === 0 && form.customer_id" class="text-xs text-muted-foreground">
                                This customer has no devices on file yet — add one below.
                            </p>
                            <InputError :message="form.errors.device_id" />
                        </div>

                        <template v-if="!form.device_id">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="nd-model">Model <span class="text-red-500">*</span></Label>
                                    <Input id="nd-model" v-model="form.new_device.model" required placeholder="e.g. iPhone 15 Pro" />
                                    <InputError :message="form.errors['new_device.model']" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="nd-type">Type <span class="text-red-500">*</span></Label>
                                    <Select v-model="form.new_device.type">
                                        <SelectTrigger id="nd-type" class="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="t in device_types" :key="t" :value="t">
                                                <SelectItemText>{{ t }}</SelectItemText>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors['new_device.type']" />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label for="nd-brand">Brand</Label>
                                    <Input id="nd-brand" v-model="form.new_device.brand" placeholder="Apple" />
                                    <InputError :message="form.errors['new_device.brand']" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="nd-model_number">Model number</Label>
                                    <Input id="nd-model_number" v-model="form.new_device.model_number" placeholder="A2848" />
                                    <InputError :message="form.errors['new_device.model_number']" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="nd-color">Color</Label>
                                    <Input id="nd-color" v-model="form.new_device.color" />
                                    <InputError :message="form.errors['new_device.color']" />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label for="nd-serial">Serial number</Label>
                                    <Input id="nd-serial" v-model="form.new_device.serial_number" placeholder="Scan or type" />
                                    <InputError :message="form.errors['new_device.serial_number']" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="nd-imei">IMEI</Label>
                                    <Input id="nd-imei" v-model="form.new_device.imei" placeholder="15-digit IMEI" />
                                    <InputError :message="form.errors['new_device.imei']" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="nd-storage">Storage</Label>
                                    <Input id="nd-storage" v-model="form.new_device.storage" placeholder="256 GB" />
                                    <InputError :message="form.errors['new_device.storage']" />
                                </div>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <!-- REPAIR DETAILS -->
                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Wrench class="mr-2 inline h-4 w-4" />
                            Repair details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label for="title">Title <span class="text-red-500">*</span></Label>
                            <Input id="title" v-model="form.title" required placeholder="e.g. Cracked screen, won't charge" />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Reported problem</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                placeholder="What the customer says is wrong…"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="intake_type">Intake type</Label>
                                <Select v-model="form.intake_type">
                                    <SelectTrigger id="intake_type" class="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="t in intakeTypes" :key="t.value" :value="t.value">
                                            <SelectItemText>{{ t.label }}</SelectItemText>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.intake_type" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="priority">Priority</Label>
                                <Select v-model="form.priority">
                                    <SelectTrigger id="priority" class="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="p in priorities" :key="p" :value="p">
                                            <SelectItemText>{{ p }}</SelectItemText>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.priority" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="due_date">Due date</Label>
                                <Input id="due_date" v-model="form.due_date" type="date" />
                                <InputError :message="form.errors.due_date" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="assignee_id">Assign to</Label>
                            <Select v-model="form.assignee_id">
                                <SelectTrigger id="assignee_id" class="w-full">
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">Unassigned</SelectItem>
                                    <SelectItem v-for="s in staff" :key="s.id" :value="s.id">
                                        <SelectItemText>{{ s.name }}</SelectItemText>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.assignee_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="internal_notes">Internal notes <span class="text-muted-foreground">(not shown to customer)</span></Label>
                            <textarea
                                id="internal_notes"
                                v-model="form.internal_notes"
                                rows="3"
                                placeholder="Findings, suspected cause, parts needed, team handoff…"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <InputError :message="form.errors.internal_notes" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating…' : 'Create ticket' }}
                    </Button>
                    <Button type="button" variant="ghost" as-child>
                        <Link :href="route('tickets.index')">Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
