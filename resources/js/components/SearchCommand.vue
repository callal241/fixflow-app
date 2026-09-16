<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Search, Ticket, User, Smartphone, Package, Command as CommandIcon } from 'lucide-vue-next';

interface Hit {
    id: number;
    title?: string;
    name?: string;
    model?: string;
    ticket_number?: string | null;
    status?: string;
    customer?: string | null;
    company?: string | null;
    phone?: string | null;
    email?: string | null;
    serial_number?: string | null;
    sku?: string | null;
    stock?: number;
    url: string;
}

interface Results {
    tickets: Hit[];
    customers: Hit[];
    devices: Hit[];
    products: Hit[];
}

const EMPTY: Results = { tickets: [], customers: [], devices: [], products: [] };

const open = ref(false);
const query = ref('');
const results = ref<Results>(EMPTY);
const loading = ref(false);
const active = ref(0);
const listEl = ref<HTMLElement | null>(null);

let debounce: ReturnType<typeof setTimeout> | null = null;
let seq = 0;

const flat = computed<Hit[]>(() => [
    ...results.value.tickets,
    ...results.value.customers,
    ...results.value.devices,
    ...results.value.products,
]);

const hasResults = computed(() => flat.value.length > 0);
const hasQuery = computed(() => query.value.trim().length >= 2);

// Global (flat-list) index offset for each group, used to drive keyboard
// highlighting and scroll-into-view from the flat `active` cursor.
const offsets = computed(() => ({
    tickets: 0,
    customers: results.value.tickets.length,
    devices: results.value.tickets.length + results.value.customers.length,
    products:
        results.value.tickets.length +
        results.value.customers.length +
        results.value.devices.length,
}));

async function runSearch() {
    const q = query.value.trim();
    const mySeq = ++seq;
    if (q.length < 2) {
        results.value = EMPTY;
        return;
    }
    loading.value = true;
    try {
        const res = await fetch(`${route('search')}?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data: Results = await res.json();
        if (mySeq === seq) {
            results.value = data;
            active.value = 0;
        }
    } finally {
        if (mySeq === seq) {
            loading.value = false;
        }
    }
}

function onInput() {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(runSearch, 200);
}

function openDialog() {
    open.value = true;
    nextTick(() => document.getElementById('search-palette-input')?.focus());
}

function openUrl(url: string) {
    open.value = false;
    query.value = '';
    results.value = EMPTY;
    router.visit(url);
}

function onKeydown(e: KeyboardEvent) {
    if (!hasResults.value) return;
    const len = flat.value.length;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        active.value = (active.value + 1) % len;
        scrollActive();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = (active.value - 1 + len) % len;
        scrollActive();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const hit = flat.value[active.value];
        if (hit) openUrl(hit.url);
    }
}

function scrollActive() {
    const el = listEl.value?.querySelector<HTMLElement>(`[data-index="${active.value}"]`);
    el?.scrollIntoView({ block: 'nearest' });
}

function onGlobalKeydown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openDialog();
    }
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    if (debounce) clearTimeout(debounce);
});
</script>

<template>
    <div>
        <Button
            variant="outline"
            class="hidden h-9 w-full items-center justify-between gap-2 text-muted-foreground sm:flex sm:w-56 lg:w-64"
            @click="openDialog"
        >
            <span class="flex items-center gap-2">
                <Search class="h-4 w-4" />
                Search…
            </span>
            <span class="flex items-center gap-1 text-xs text-muted-foreground">
                <CommandIcon class="h-3 w-3" />K
            </span>
        </Button>

        <Dialog v-model:open="open">
            <DialogContent class="top-[10%] max-w-xl translate-y-0 p-0">
                <DialogTitle class="sr-only">Search</DialogTitle>
                <DialogDescription class="sr-only">
                    Search tickets, customers, devices and products.
                </DialogDescription>

                <div class="flex items-center gap-2 border-b px-4">
                    <Search class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <input
                        id="search-palette-input"
                        v-model="query"
                        class="h-12 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        placeholder="Search tickets, customers, devices, products…"
                        @input="onInput"
                        @keydown="onKeydown"
                    />
                    <span
                        v-if="loading"
                        class="shrink-0 text-xs text-muted-foreground"
                    >
                        …
                    </span>
                </div>

                <div ref="listEl" class="max-h-[50vh] overflow-y-auto p-2">
                    <template v-if="!hasQuery">
                        <p class="px-3 py-8 text-center text-sm text-muted-foreground">
                            Type at least 2 characters to search.
                        </p>
                    </template>

                    <template v-else-if="!hasResults">
                        <p class="px-3 py-8 text-center text-sm text-muted-foreground">
                            No matches for “{{ query }}”.
                        </p>
                    </template>

                    <template v-else>
                        <!-- Tickets -->
                        <template v-if="results.tickets.length">
                            <p class="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Tickets</p>
                            <button
                                v-for="(t, i) in results.tickets"
                                :key="'t' + t.id"
                                :data-index="offsets.tickets + i"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                :class="{ 'bg-accent': active === offsets.tickets + i }"
                                @click="openUrl(t.url)"
                            >
                                <Ticket class="h-4 w-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 flex-1 truncate">
                                    <span class="font-medium">{{ t.title }}</span>
                                    <span v-if="t.ticket_number" class="ml-2 text-muted-foreground">{{ t.ticket_number }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ t.customer }}</span>
                            </button>
                        </template>

                        <!-- Customers -->
                        <template v-if="results.customers.length">
                            <p class="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Customers</p>
                            <button
                                v-for="(c, i) in results.customers"
                                :key="'c' + c.id"
                                :data-index="offsets.customers + i"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                :class="{ 'bg-accent': active === offsets.customers + i }"
                                @click="openUrl(c.url)"
                            >
                                <User class="h-4 w-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 flex-1 truncate">
                                    <span class="font-medium">{{ c.name }}</span>
                                    <span v-if="c.company" class="ml-2 text-muted-foreground">{{ c.company }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ c.phone }}</span>
                            </button>
                        </template>

                        <!-- Devices -->
                        <template v-if="results.devices.length">
                            <p class="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Devices</p>
                            <button
                                v-for="(d, i) in results.devices"
                                :key="'d' + d.id"
                                :data-index="offsets.devices + i"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                :class="{ 'bg-accent': active === offsets.devices + i }"
                                @click="openUrl(d.url)"
                            >
                                <Smartphone class="h-4 w-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 flex-1 truncate">
                                    <span class="font-medium">{{ d.model }}</span>
                                    <span v-if="d.imei || d.serial_number" class="ml-2 text-muted-foreground">{{ d.imei || d.serial_number }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ d.customer }}</span>
                            </button>
                        </template>

                        <!-- Products -->
                        <template v-if="results.products.length">
                            <p class="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Products</p>
                            <button
                                v-for="(p, i) in results.products"
                                :key="'p' + p.id"
                                :data-index="offsets.products + i"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                :class="{ 'bg-accent': active === offsets.products + i }"
                                @click="openUrl(p.url)"
                            >
                                <Package class="h-4 w-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 flex-1 truncate">
                                    <span class="font-medium">{{ p.name }}</span>
                                    <span v-if="p.sku" class="ml-2 text-muted-foreground">{{ p.sku }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground">Stock {{ p.stock }}</span>
                            </button>
                        </template>
                    </template>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>


