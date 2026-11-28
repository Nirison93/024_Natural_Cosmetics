<template>
  <Modal :show="open" @close="closeModal" max-width="2xl">
    <div class="bg-gray-50 p-6">
      <div class="mb-6 flex items-start justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">{{ $t('products.stock_update') }}</h2>
          <p class="mt-1 text-sm text-gray-600">{{ product?.name }}</p>
        </div>
        <button
          type="button"
          @click="closeModal"
          class="rounded-full p-2 text-gray-600 transition hover:bg-gray-200 hover:text-gray-900"
          :aria-label="$t('common.cancel')"
        >
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form class="space-y-5" @submit.prevent="submit">
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
          <label class="mb-2 block text-sm font-semibold text-gray-700">
            {{ $t('products.batch_number_auto_generated') }}
          </label>
          <input
            v-model="form.batch_number"
            type="text"
            readonly
            class="w-full cursor-not-allowed rounded-lg border border-blue-200 bg-white px-4 py-3 font-mono font-semibold text-blue-800"
          />
          <p v-if="form.errors.batch_number" class="mt-1 text-sm text-red-600">
            {{ form.errors.batch_number }}
          </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">
              {{ $t('products.stock_quantity') }} ({{ product?.sales_unit?.symbol || product?.sales_unit?.name || 'Unit' }})
            </label>
            <input
              v-model="form.quantity"
              type="number"
              min="0.01"
              step="0.01"
              required
              autofocus
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-lg text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            />
            <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-600">{{ form.errors.quantity }}</p>
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">
              {{ $t('products.expiry_date') }}
              <span v-if="product?.expiry_date_required" class="text-red-500">*</span>
            </label>
            <input
              v-model="form.expiry_date"
              type="date"
              :required="product?.expiry_date_required"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            />
            <p v-if="form.errors.expiry_date" class="mt-1 text-sm text-red-600">
              {{ form.errors.expiry_date }}
            </p>
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">
              {{ $t('products.purchase_price_label') }}
            </label>
            <input
              v-model="form.purchase_price"
              type="number"
              min="0"
              step="0.01"
              required
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-lg text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            />
            <p v-if="form.errors.purchase_price" class="mt-1 text-sm text-red-600">
              {{ form.errors.purchase_price }}
            </p>
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">
              {{ $t('products.selling_price') }}
            </label>
            <input
              v-model="form.selling_price"
              type="number"
              min="0"
              step="0.01"
              required
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-lg text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            />
            <p v-if="form.errors.selling_price" class="mt-1 text-sm text-red-600">
              {{ form.errors.selling_price }}
            </p>
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">
              {{ $t('products.wholesale_price_label') }}
            </label>
            <input
              v-model="form.wholesale_price"
              type="number"
              min="0"
              step="0.01"
              required
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-lg text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            />
            <p v-if="form.errors.wholesale_price" class="mt-1 text-sm text-red-600">
              {{ form.errors.wholesale_price }}
            </p>
          </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
          <button
            type="button"
            @click="closeModal"
            class="rounded-lg border border-gray-300 bg-white px-5 py-3 font-medium text-gray-700 hover:bg-gray-100"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ form.processing ? $t('products.saving_stock') : $t('products.save_stock') }}
          </button>
        </div>
      </form>
    </div>
  </Modal>
</template>

<script setup>
import { watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  product: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:open"]);
const form = useForm({
  batch_number: "",
  quantity: "",
  purchase_price: "",
  selling_price: "",
  wholesale_price: "",
  expiry_date: "",
});

const generateBatchNumber = () => {
  const now = new Date();
  const date = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, "0"),
    String(now.getDate()).padStart(2, "0"),
  ].join("");
  const suffix = String(Math.floor(Math.random() * 10000)).padStart(4, "0");

  return `BATCH-${date}-${suffix}`;
};

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen && props.product) {
      form.reset();
      form.clearErrors();
      form.batch_number = generateBatchNumber();
      form.purchase_price = props.product.purchase_price ?? "";
      form.selling_price = props.product.retail_price ?? "";
      form.wholesale_price = props.product.wholesale_price ?? "";
    }
  }
);

const closeModal = () => {
  emit("update:open", false);
};

const submit = () => {
  if (!props.product) {
    return;
  }

  form.post(route("products.stock-update", props.product.id), {
    preserveScroll: true,
    onSuccess: closeModal,
    onError: (errors) => {
      if (errors.batch_number) {
        form.batch_number = generateBatchNumber();
        form.clearErrors("batch_number");
      }
    },
  });
};
</script>
