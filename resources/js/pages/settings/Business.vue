<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { CreditCard, CircleCheck, Loader2 } from 'lucide-vue-next';

interface ProviderMeta {
    label: string;
    description: string;
}

interface Props {
    business: {
        name: string;
        currency: string;
        payment_provider_id: string | null;
    };
    providers: Record<string, ProviderMeta>;
    default_provider_id: string;
    active_provider_id: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: '/settings/business' },
];

const form = useForm({
    payment_provider_id: props.business.payment_provider_id ?? 'counter',
});

const submit = () => {
    form.put(route('business.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Re-sync with the server's resolved active provider (a gateway that
            // is not configured falls back to the counter).
            if (form.data.payment_provider_id && !props.providers[form.data.payment_provider_id]) {
                form.data.payment_provider_id = 'counter';
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Payments" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Payment provider"
                    description="Choose how the shop takes payments at checkout. This is vendor-agnostic — add a gateway in config to enable more."
                />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-3">
                        <Card
                            v-for="(meta, id) in providers"
                            :key="id"
                            :class="{ 'border-primary': (id === active_provider_id) && !form.processing }"
                        >
                            <CardContent class="flex items-start gap-3 p-4">
                                <input
                                    type="radio"
                                    :id="`provider-${id}`"
                                    name="payment_provider_id"
                                    :value="id"
                                    v-model="form.payment_provider_id"
                                    class="mt-1 h-4 w-4 border-muted-foreground"
                                />
                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <Label :for="`provider-${id}`" class="font-medium">{{ meta.label }}</Label>
                                        <CreditCard class="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <p class="text-sm text-muted-foreground">{{ meta.description }}</p>
                                    <p
                                        v-if="id === active_provider_id"
                                        class="flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400"
                                    >
                                        <CircleCheck class="h-3.5 w-3.5" />
                                        Active for this shop
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <InputError class="mt-2" :message="form.errors.payment_provider_id" />

                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <CircleCheck v-else class="h-4 w-4" />
                        Save provider
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
