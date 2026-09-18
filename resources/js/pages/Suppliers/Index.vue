<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { CircleCheck, Loader2, PackageSearch, Search, TriangleAlert, ExternalLink } from 'lucide-vue-next';

interface SupplierMeta {
    label: string;
    description: string;
    configured: boolean;
    hint: string | null;
}

interface Offer {
    supplier: string;
    name: string;
    external_id: string | null;
    url: string | null;
    image: string | null;
    price: number | null;
    stock: number | null;
    category: string | null;
    description: string | null;
}

interface SearchPayload {
    supplier: string;
    success: boolean;
    offers: Offer[];
    count: number;
    failure_reason: string | null;
    status_code: number | null;
}

interface Props {
    business: {
        supplier_provider_id: string | null;
    };
    suppliers: Record<string, SupplierMeta>;
    default_supplier_id: string;
    active_supplier_id: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suppliers', href: '/suppliers' },
];

const form = useForm({
    supplier_provider_id: props.business.supplier_provider_id ?? props.default_supplier_id,
});

const submit = () => {
    form.put(route('suppliers.update'), {
        preserveScroll: true,
    });
};

// ---- parts search ----
const query = ref('');
const offers = ref<Offer[]>([]);
const searching = ref(false);
const searchFailure = ref<string | null>(null);
const searchedFor = ref<string | null>(null);
let debounce: ReturnType<typeof setTimeout> | undefined;
let seq = 0;

async function runSearch() {
    const q = query.value.trim();
    const mySeq = ++seq;
    if (q.length < 2) {
        offers.value = [];
        searchFailure.value = null;
        searchedFor.value = null;
        return;
    }
    searching.value = true;
    try {
        const res = await fetch(
            `${route('suppliers.search')}?q=${encodeURIComponent(q)}&limit=12`,
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
        );
        const data: SearchPayload = await res.json();
        if (mySeq === seq) {
            offers.value = data.offers ?? [];
            searchFailure.value = data.success ? null : (data.failure_reason ?? 'Supplier unavailable.');
            searchedFor.value = q;
        }
    } finally {
        if (mySeq === seq) {
            searching.value = false;
        }
    }
}

function onInput() {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(runSearch, 300);
}

const activeMeta = props.suppliers[props.active_supplier_id];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Suppliers" />

        <div class="flex flex-col gap-6">
            <HeadingSmall
                title="Parts suppliers"
                description="Choose where the shop sources parts, then search the active supplier's catalog. This is vendor-agnostic -- add a B2B partner in config to enable more."
            />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- provider picker -->
                <section class="space-y-4">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid gap-3">
                            <Card
                                v-for="(meta, id) in suppliers"
                                :key="id"
                                :class="{ 'border-primary': id === active_supplier_id && !form.processing }"
                            >
                                <CardContent class="flex items-start gap-3 p-4">
                                    <input
                                        type="radio"
                                        :id="`supplier-${id}`"
                                        name="supplier_provider_id"
                                        :value="id"
                                        v-model="form.supplier_provider_id"
                                        class="mt-1 h-4 w-4 border-muted-foreground"
                                    />
                                    <div class="flex-1 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Label :for="`supplier-${id}`" class="font-medium">{{ meta.label }}</Label>
                                            <PackageSearch class="h-4 w-4 text-muted-foreground" />
                                            <span
                                                v-if="meta.configured"
                                                class="flex items-center gap-1 rounded-full bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-600 dark:text-green-400"
                                            >
                                                <CircleCheck class="h-3 w-3" /> Configured
                                            </span>
                                            <span
                                                v-else
                                                class="flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                                            >
                                                <TriangleAlert class="h-3 w-3" /> Not configured
                                            </span>
                                        </div>
                                        <p class="text-sm text-muted-foreground">{{ meta.description }}</p>
                                        <p v-if="!meta.configured && meta.hint" class="text-xs text-amber-600 dark:text-amber-400">
                                            {{ meta.hint }}
                                        </p>
                                        <p
                                            v-if="id === active_supplier_id"
                                            class="flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400"
                                        >
                                            <CircleCheck class="h-3.5 w-3.5" />
                                            Active for this shop
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <InputError class="mt-2" :message="form.errors.supplier_provider_id" />

                        <Button type="submit" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <CircleCheck v-else class="h-4 w-4" />
                            Save supplier
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            Tip: unconfigured suppliers can't be selected until their credentials are set in config.
                        </p>
                    </form>
                </section>

                <!-- live search against the active supplier -->
                <section class="space-y-4">
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <div>
                                <Label for="part-search" class="mb-1 block text-sm font-medium">
                                    Search {{ activeMeta?.label ?? 'supplier' }} parts
                                </Label>
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="part-search"
                                        v-model="query"
                                        class="pl-9"
                                        placeholder="e.g. iphone 13 screen, galaxy s23 battery"
                                        @input="onInput"
                                        @keyup.enter="runSearch"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Searches the active supplier ({{ activeMeta?.label ?? 'none' }}). iFixit is free and needs no key.
                                </p>
                            </div>

                            <div v-if="searching" class="flex items-center gap-2 text-sm text-muted-foreground">
                                <Loader2 class="h-4 w-4 animate-spin" /> Searching…
                            </div>

                            <div
                                v-else-if="searchedFor && searchFailure"
                                class="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-700 dark:text-amber-400"
                            >
                                <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{{ searchFailure }}</span>
                            </div>

                            <div v-else-if="searchedFor && offers.length === 0" class="text-sm text-muted-foreground">
                                No parts found for “{{ searchedFor }}”.
                            </div>

                            <ul v-else-if="offers.length > 0" class="space-y-2">
                                <li v-for="(offer, i) in offers" :key="i" class="flex items-start gap-3 rounded-md border p-2.5">
                                    <img
                                        v-if="offer.image"
                                        :src="offer.image"
                                        :alt="offer.name"
                                        class="h-12 w-12 shrink-0 rounded object-cover"
                                        loading="lazy"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ offer.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            <span v-if="offer.category">{{ offer.category }}</span>
                                            <span v-if="offer.price != null" class="ml-2 text-green-600 dark:text-green-400">${{ offer.price.toFixed(2) }}</span>
                                            <span v-if="offer.stock != null" class="ml-2">{{ offer.stock }} in stock</span>
                                            <span v-if="offer.price == null" class="ml-2 italic">price on request</span>
                                        </p>
                                    </div>
                                    <a
                                        v-if="offer.url"
                                        :href="offer.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center gap-1 text-xs text-primary hover:underline"
                                    >
                                        View <ExternalLink class="h-3 w-3" />
                                    </a>
                                </li>
                            </ul>

                            <div v-else class="text-sm text-muted-foreground">
                                Type at least two characters to search.
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
