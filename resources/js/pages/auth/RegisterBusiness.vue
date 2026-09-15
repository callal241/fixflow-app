<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectItemText, SelectTrigger, SelectValue } from '@/components/ui/select';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Building2, Check, LoaderCircle, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    currencies: string[];
}>();

const step = ref(1);
const steps = [
    { id: 1, label: 'Business', icon: Building2 },
    { id: 2, label: 'Admin account', icon: UserRound },
    { id: 3, label: 'Review', icon: Check },
];

const form = useForm({
    // Step 1 - business
    business_name: '',
    trade: '',
    business_email: '',
    business_phone: '',
    website: '',
    address: '',
    city: '',
    state: '',
    zip_code: '',
    tax_number: '',
    currency: 'USD',
    tax_rate: 0,
    notes: '',
    // Step 2 - admin
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const selectClasses =
    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

const step1Valid = computed(() => form.business_name.trim().length > 0);
const step2Valid = computed(
    () => form.name.trim().length > 0 && form.email.includes('@') && form.password.length >= 8 && form.password === form.password_confirmation,
);

const fieldErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([, v]) => v)
        .map(([k, v]) => `• ${k.replace(/_/g, ' ')}: ${v}`),
);

const next = () => {
    if (step.value === 1 && !step1Valid.value) return;
    if (step.value === 2 && !step2Valid.value) return;
    if (step.value < 3) step.value++;
};

const back = () => {
    if (step.value > 1) step.value--;
};

const submit = () => {
    form.post(route('register.business'), {
        onFinish: () => {
            if (Object.keys(form.errors).length === 0) {
                form.reset('password', 'password_confirmation');
            }
        },
    });
};

const reviewRows = computed(() => [
    ['Business name', form.business_name],
    ['Trade', form.trade],
    ['Email', form.business_email],
    ['Phone', form.business_phone],
    ['Website', form.website],
    ['Address', [form.address, form.city, form.state, form.zip_code].filter(Boolean).join(', ')],
    ['Tax number', form.tax_number],
    ['Currency', form.currency],
    ['Tax rate', `${form.tax_rate}%`],
    ['Admin name', form.name],
    ['Admin email', form.email],
]);
</script>

<template>
    <AuthBase title="Set up your business" description="Create your business profile and admin account">
        <Head title="Business signup" />

        <!-- Step indicator -->
        <ol class="mb-6 flex items-center justify-center gap-2">
            <li v-for="s in steps" :key="s.id" class="flex items-center gap-2">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full border text-sm font-medium"
                    :class="
                        step >= s.id
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-muted-foreground/30 text-muted-foreground'
                    "
                >
                    <component :is="s.icon" class="h-4 w-4" />
                </div>
                <span class="hidden text-sm sm:inline" :class="step >= s.id ? 'font-medium' : 'text-muted-foreground'">{{ s.label }}</span>
                <ArrowRight v-if="s.id < 3" class="h-4 w-4 text-muted-foreground/50" />
            </li>
        </ol>

        <form @submit.prevent="step === 3 ? submit() : next()" class="flex flex-col gap-6">
            <div v-if="fieldErrors.length" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                <p v-for="e in fieldErrors" :key="e">{{ e }}</p>
            </div>

            <!-- STEP 1: Business -->
            <div v-if="step === 1" class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="business_name">Business name <span class="text-destructive">*</span></Label>
                    <Input id="business_name" v-model="form.business_name" required placeholder="e.g. Acme Electronics Repair" />
                    <InputError :message="form.errors.business_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="trade">Trade / services</Label>
                    <Input id="trade" v-model="form.trade" placeholder="e.g. phone & laptop repair, parts sales" />
                    <InputError :message="form.errors.trade" />
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="business_email">Business email</Label>
                        <Input id="business_email" type="email" v-model="form.business_email" placeholder="office@business.com" />
                        <InputError :message="form.errors.business_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_phone">Phone</Label>
                        <Input id="business_phone" v-model="form.business_phone" placeholder="+1 555 000 0000" />
                        <InputError :message="form.errors.business_phone" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="website">Website</Label>
                    <Input id="website" v-model="form.website" placeholder="https://" />
                    <InputError :message="form.errors.website" />
                </div>

                <div class="grid gap-2">
                    <Label for="address">Street address</Label>
                    <Input id="address" v-model="form.address" placeholder="123 Main St" />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="city">City</Label>
                        <Input id="city" v-model="form.city" />
                        <InputError :message="form.errors.city" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="state">State / region</Label>
                        <Input id="state" v-model="form.state" />
                        <InputError :message="form.errors.state" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="zip_code">ZIP / postal code</Label>
                        <Input id="zip_code" v-model="form.zip_code" />
                        <InputError :message="form.errors.zip_code" />
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="tax_number">Tax / VAT number</Label>
                        <Input id="tax_number" v-model="form.tax_number" placeholder="optional" />
                        <InputError :message="form.errors.tax_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="currency">Currency</Label>
                        <Select v-model="form.currency">
                            <SelectTrigger id="currency" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in props.currencies" :key="c" :value="c">
                                    <SelectItemText>{{ c }}</SelectItemText>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.currency" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tax_rate">Tax rate (%)</Label>
                        <Input id="tax_rate" type="number" step="0.01" min="0" max="100" v-model.number="form.tax_rate" placeholder="0" />
                        <InputError :message="form.errors.tax_rate" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        placeholder="Anything else about the business (optional)"
                        :class="selectClasses + ' h-auto py-2'"
                    ></textarea>
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <!-- STEP 2: Admin account -->
            <div v-if="step === 2" class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Your name <span class="text-destructive">*</span></Label>
                    <Input id="name" v-model="form.name" required placeholder="Full name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address <span class="text-destructive">*</span></Label>
                    <Input id="email" type="email" v-model="form.email" required placeholder="you@business.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" v-model="form.phone" placeholder="optional" />
                    <InputError :message="form.errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password <span class="text-destructive">*</span></Label>
                    <Input id="password" type="password" v-model="form.password" required placeholder="At least 8 characters" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password <span class="text-destructive">*</span></Label>
                    <Input id="password_confirmation" type="password" v-model="form.password_confirmation" required placeholder="Repeat password" />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <p class="text-sm text-muted-foreground">
                    This account will be created with <strong>admin</strong> access to the business.
                </p>
            </div>

            <!-- STEP 3: Review -->
            <div v-if="step === 3" class="grid gap-6">
                <div class="rounded-xl border p-4">
                    <h3 class="mb-3 text-sm font-medium">Please confirm</h3>
                    <dl class="grid gap-2 text-sm">
                        <div v-for="row in reviewRows" :key="row[0]" class="grid grid-cols-3 gap-2">
                            <dt class="text-muted-foreground">{{ row[0] }}</dt>
                            <dd class="col-span-2 break-words">{{ row[1] || '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between">
                <Button v-if="step > 1" type="button" variant="outline" @click="back">
                    <ArrowLeft class="h-4 w-4" /> Back
                </Button>
                <span v-else />
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    <template v-if="step < 3">Continue <ArrowRight class="h-4 w-4" /></template>
                    <template v-else>Create business &amp; account</template>
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Just need a personal account?
                <TextLink :href="route('register')" class="underline underline-offset-4">Create one instead</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
