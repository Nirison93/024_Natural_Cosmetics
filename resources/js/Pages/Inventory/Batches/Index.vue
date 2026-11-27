<template>
  <AppLayout>
    <div class="min-h-screen bg-gray-50 p-6">
      <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Batch inventory</h1>
          <p class="mt-1 text-sm text-gray-600">Expiry-aware stock and batch adjustments.</p>
        </div>
        <button
          type="button"
          @click="$inertia.visit(route('products.index'))"
          class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
        >
          Back to products
        </button>
      </div>

      <div class="mb-4">
        <input
          v-model="search"
          @keyup.enter="searchBatches"
          type="search"
          placeholder="Search product or batch"
          class="w-full max-w-lg rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
        />
        <button
          type="button"
          @click="searchBatches"
          class="ml-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
        >
          Search
        </button>
      </div>

      <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full min-w-[1100px] text-left text-sm">
          <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th class="px-4 py-3">Product / batch</th>
              <th class="px-4 py-3">Expiry</th>
              <th class="px-4 py-3 text-right">Sellable</th>
              <th class="px-4 py-3 text-right">Quarantined</th>
              <th class="px-4 py-3">Supplier</th>
              <th class="px-4 py-3">Adjust stock</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="batch in batches.data" :key="batch.id">
            <tr class="border-t border-gray-100 align-top">
              <td class="px-4 py-4">
                <div class="font-semibold text-gray-900">{{ batch.product?.name || "Product unavailable" }}</div>
                <div class="mt-1 text-xs text-gray-500">
                  {{ batch.product?.barcode || "No barcode" }} · {{ batch.batch_number || "Unnumbered batch" }}
                </div>
                <button
                  type="button"
                  @click="expandedBatchId = expandedBatchId === batch.id ? null : batch.id"
                  class="mt-2 text-xs font-medium text-emerald-700 hover:underline"
                >
                  {{ expandedBatchId === batch.id ? "Hide history" : "Recent movement history" }}
                </button>
              </td>
              <td class="px-4 py-4" :class="isExpired(batch.expiry_date) ? 'font-semibold text-red-600' : 'text-gray-700'">
                {{ batch.expiry_date || "Not recorded" }}
                <span v-if="isExpired(batch.expiry_date)" class="block text-xs">Expired</span>
              </td>
              <td class="px-4 py-4 text-right font-medium text-gray-900">
                {{ formatQuantity(batch.available_sales_quantity) }}
              </td>
              <td class="px-4 py-4 text-right text-amber-700">
                {{ formatQuantity(batch.quarantined_quantity) }}
              </td>
              <td class="px-4 py-4 text-gray-700">{{ batch.supplier?.name || "N/A" }}</td>
              <td class="px-4 py-4">
                <form class="grid grid-cols-2 gap-2" @submit.prevent="adjust(batch)">
                  <select
                    v-model="formFor(batch.id).operation"
                    class="rounded border border-gray-300 px-2 py-1.5 text-xs"
                    required
                  >
                    <option value="decrease">Decrease sellable</option>
                    <option value="increase">Increase sellable</option>
                    <option value="quarantine">Quarantine</option>
                    <option value="release_quarantine">Release quarantine</option>
                  </select>
                  <input
                    v-model="formFor(batch.id).quantity"
                    type="number"
                    min="0.01"
                    step="0.01"
                    required
                    placeholder="Quantity"
                    class="rounded border border-gray-300 px-2 py-1.5 text-xs"
                  />
                  <input
                    v-model="formFor(batch.id).reason"
                    type="text"
                    maxlength="100"
                    required
                    placeholder="Reason"
                    class="rounded border border-gray-300 px-2 py-1.5 text-xs"
                  />
                  <input
                    v-model="formFor(batch.id).notes"
                    type="text"
                    maxlength="1000"
                    placeholder="Notes (optional)"
                    class="rounded border border-gray-300 px-2 py-1.5 text-xs"
                  />
                  <button
                    type="submit"
                    :disabled="processingId === batch.id"
                    class="col-span-2 rounded bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                  >
                    {{ processingId === batch.id ? "Saving..." : "Record adjustment" }}
                  </button>
                  <p v-if="errors.operation || errors.quantity" class="col-span-2 text-xs text-red-600">
                    {{ errors.operation || errors.quantity }}
                  </p>
                </form>
              </td>
            </tr>
            <tr v-if="expandedBatchId === batch.id" class="bg-gray-50">
              <td colspan="6" class="px-4 py-4">
                <div v-if="batch.movement_history?.length" class="space-y-2">
                  <div
                    v-for="movement in batch.movement_history"
                    :key="movement.id"
                    class="grid gap-1 border-b border-gray-200 pb-2 text-xs text-gray-700 md:grid-cols-5"
                  >
                    <span>{{ movement.created_at }}</span>
                    <span>Qty: {{ formatQuantity(movement.quantity) }} ({{ movement.quantity_before }} → {{ movement.quantity_after }})</span>
                    <span>{{ movement.reason || `Type ${movement.movement_type}` }}</span>
                    <span>{{ movement.user?.name || "System" }}</span>
                    <span>{{ movement.notes || movement.reference || "" }}</span>
                  </div>
                </div>
                <p v-else class="text-sm text-gray-500">No movement history recorded for this batch.</p>
              </td>
            </tr>
            </template>
            <tr v-if="batches.data.length === 0">
              <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                No batches found for batch-tracked products.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex flex-wrap gap-2">
        <Link
          v-for="link in batches.links"
          :key="link.label"
          :href="link.url || '#'"
          :preserve-scroll="true"
          class="rounded border px-3 py-1.5 text-sm"
          :class="link.active ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-gray-300 bg-white text-gray-700'"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";

const props = defineProps({
  batches: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const search = ref(props.filters.search || "");
const processingId = ref(null);
const expandedBatchId = ref(null);
const forms = reactive({});
const errors = computed(() => page.props.errors || {});

const formFor = (id) => {
  if (!forms[id]) {
    forms[id] = { operation: "decrease", quantity: "", reason: "", notes: "" };
  }
  return forms[id];
};

const searchBatches = () => {
  router.get(route("inventory.batches.index"), { search: search.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const adjust = (batch) => {
  const form = formFor(batch.id);
  processingId.value = batch.id;
  router.post(route("inventory.batches.adjust", batch.id), form, {
    preserveScroll: true,
    onSuccess: () => {
      forms[batch.id] = { operation: form.operation, quantity: "", reason: "", notes: "" };
    },
    onFinish: () => {
      processingId.value = null;
    },
  });
};

const formatQuantity = (value) => Number(value || 0).toFixed(2);
const isExpired = (date) => Boolean(date && new Date(`${date}T00:00:00`) < new Date(new Date().toDateString()));
</script>
