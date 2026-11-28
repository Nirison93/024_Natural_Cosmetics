<template>
  <Modal :show="open" @close="closeModal" max-width="4xl">
    <div class="max-h-[90vh] overflow-y-auto bg-gray-50 p-6">
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-blue-600">
          ✨ {{ $t('products.add_new_product') }}
        </h2>
        <button
          type="button"
          @click="closeModal"
          class="rounded-full p-2 text-gray-600 transition-all duration-200 hover:bg-gray-200 hover:text-gray-800"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <!-- ========== Basic Information ========== -->
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold text-blue-600">
            📋 {{ $t('products.basic_information') }}
          </h3>

          <div class="grid grid-cols-1 gap-x-4 gap-y-4 md:grid-cols-2">
            <!-- Product Name -->
            <div class="min-w-0">
              <label :class="labelClass">
                {{ $t('products.product_name') }} <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                :class="inputClass"
                :placeholder="$t('products.enter_name')"
                required
              />
              <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
            </div>

            <!-- Category -->
            <div class="min-w-0">
              <label :class="labelClass">{{ $t('fields.category') }}</label>
              <SearchableSelect
                v-model="form.category_id"
                :options="categoryOptions"
                :placeholder="$t('products.search_categories')"
                :add-button-action="openCategoryModal"
              />
              <p v-if="form.errors.category_id" :class="errorClass">{{ form.errors.category_id }}</p>
            </div>
          </div>
        </section>

        <!-- ========== Pricing ========== -->
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold text-green-600">
            💰 {{ $t('products.pricing_information') }} ({{ page.props.currency || "" }})
          </h3>

          <div class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Purchase Price -->
            <div class="min-w-0">
              <label :class="labelClass">{{ $t('products.purchase_price_label') }}</label>
              <input
                v-model="form.purchase_price"
                type="number"
                step="0.01"
                min="0"
                :class="inputClass"
                placeholder="0.00"
              />
              <p v-if="form.errors.purchase_price" :class="errorClass">{{ form.errors.purchase_price }}</p>
            </div>

            <!-- Wholesale Price -->
            <div class="min-w-0">
              <label :class="labelClass">{{ $t('products.wholesale_price_label') }}</label>
              <input
                v-model="form.wholesale_price"
                type="number"
                step="0.01"
                :class="inputClass"
                placeholder="0.00"
              />
              <p v-if="form.errors.wholesale_price" :class="errorClass">{{ form.errors.wholesale_price }}</p>
            </div>

            <!-- Retail Price -->
            <div class="min-w-0">
              <label :class="labelClass">{{ $t('products.retail_price_label') }}</label>
              <input
                v-model="form.retail_price"
                type="number"
                step="0.01"
                :class="inputClass"
                placeholder="0.00"
              />
              <p v-if="form.errors.retail_price" :class="errorClass">{{ form.errors.retail_price }}</p>
            </div>

            <!-- Discount -->
            <div class="min-w-0 sm:col-span-2 lg:col-span-1">
              <label :class="labelClass">{{ $t('discounts.discount_type') }}</label>
              <div class="flex gap-2">
                <select v-model="form.discount_id" :class="[inputClass, 'min-w-0 flex-1']">
                  <option value="">{{ $t('products.select_discount') }}</option>
                  <option
                    v-for="discount in discounts"
                    :key="discount.id"
                    :value="discount.id"
                  >
                    {{ discount.name }} -
                    {{ discount.value }} {{ discount.type === 0 ? '%' : (page.props.currency || '') }}
                  </option>
                </select>
                <button
                  type="button"
                  @click="openDiscountModal"
                  class="flex h-[38px] w-[38px] flex-shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition duration-200 hover:bg-blue-700"
                  title="Add New Discount"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- ========== Inventory ========== -->
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold text-orange-600">
            📦 {{ $t('products.inventory_units') }}
          </h3>

          <div class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Quantity -->
            <div class="min-w-0">
              <label :class="labelClass">Quantity <span class="text-red-500">*</span></label>
              <input
                v-model.number="form.quantity"
                type="number"
                min="0"
                step="0.01"
                required
                :class="inputClass"
                placeholder="0"
              />
              <p v-if="form.errors.quantity" :class="errorClass">{{ form.errors.quantity }}</p>
            </div>

            <!-- Auto-generated Batch Number -->
            <div class="min-w-0">
              <label :class="labelClass">{{ $t('products.batch_number_auto_generated') }}</label>
              <input
                :value="form.batch_number"
                type="text"
                readonly
                :class="[inputClass, 'bg-gray-100 font-mono font-semibold text-gray-700']"
                aria-readonly="true"
              />
              <p v-if="form.errors.batch_number" :class="errorClass">{{ form.errors.batch_number }}</p>
            </div>

            <!-- Expiry Date -->
            <div class="min-w-0">
              <label :class="labelClass">Expiry Date</label>
              <input v-model="form.expire_date" type="date" :class="inputClass" />
              <p v-if="form.errors.expire_date" :class="errorClass">{{ form.errors.expire_date }}</p>
            </div>

            <!-- Low Stock Alert -->
            <div class="min-w-0">
              <label :class="labelClass">
                {{ $t('products.shop_low_stock_alert') }}
                <span v-if="form.sales_unit_id" class="text-green-600">
                  ({{ getSalesUnitName(form.sales_unit_id) }})
                </span>
              </label>
              <input
                v-model="form.shop_low_stock_margin"
                type="number"
                :class="inputClass"
                placeholder="10"
              />
              <p class="mt-1 text-xs text-gray-500">
                {{ $t('common.optional_blank') }} · {{ $t('products.shop_stock_alert_hint') }}
              </p>
            </div>

            <!-- Barcode -->
            <div class="min-w-0">
              <label :class="labelClass">{{ $t('fields.barcode') }}</label>
              <input
                v-model="form.barcode"
                type="text"
                :class="inputClass"
                :placeholder="$t('products.enter_barcode')"
              />
              <p v-if="form.errors.barcode" :class="errorClass">{{ form.errors.barcode }}</p>
            </div>
          </div>
        </section>

        <!-- ========== Additional Options ========== -->
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold text-indigo-600">
            ⚙️ {{ $t('products.additional_options') }}
          </h3>

          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <label :class="optionCardClass">
              <input
                v-model="form.return_product"
                type="checkbox"
                class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
              />
              <span class="text-sm font-medium text-gray-800">{{ $t('products.allow_returns') }}</span>
            </label>

            <label :class="optionCardClass">
              <input
                v-model="form.batch_tracking_enabled"
                type="checkbox"
                class="h-5 w-5 rounded border-gray-300 text-green-700 focus:ring-green-600"
              />
              <span class="text-sm font-medium text-gray-800">Enable batch tracking</span>
            </label>

            <label :class="[optionCardClass, !form.batch_tracking_enabled && 'opacity-50']">
              <input
                v-model="form.expiry_date_required"
                type="checkbox"
                :disabled="!form.batch_tracking_enabled"
                class="h-5 w-5 rounded border-gray-300 text-green-700 focus:ring-green-600"
              />
              <span class="text-sm font-medium text-gray-800">Expiry date required</span>
            </label>
          </div>
        </section>

        <!-- ========== Actions ========== -->
        <div class="flex justify-end gap-3 border-t border-gray-300 pt-4">
          <button
            type="button"
            @click="closeModal"
            class="rounded-[5px] bg-gray-500 px-8 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:bg-gray-600"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-[5px] bg-blue-600 px-8 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {{ form.processing ? "✨ " + $t('products.creating') : "✨ " + $t('products.create_title') }}
          </button>
        </div>
      </form>
    </div>
  </Modal>

  <!-- Quick Add Modals -->
  <QuickAddModal
    :show="quickAddModal.brand"
    type="brand"
    route-name="brands.store"
    @close="quickAddModal.brand = false"
    @created="(item) => handleQuickCreated('brand', item)"
  />
  <QuickAddModal
    :show="quickAddModal.category"
    type="category"
    route-name="categories.store"
    :extra-fields="['parent_id', 'status']"
    :parent-categories="categories"
    @close="quickAddModal.category = false"
    @created="(item) => handleQuickCreated('category', item)"
  />
  <QuickAddModal
    :show="quickAddModal.type"
    type="type"
    route-name="types.store"
    @close="quickAddModal.type = false"
    @created="(item) => handleQuickCreated('type', item)"
  />
  <QuickAddModal
    :show="quickAddModal.unit"
    type="unit"
    route-name="measurement-units.store"
    @close="quickAddModal.unit = false"
    @created="(item) => handleQuickCreated('unit', item)"
  />
  <QuickAddModal
    :show="quickAddModal.tax"
    type="tax"
    route-name="taxes.store"
    @close="quickAddModal.tax = false"
    @created="(item) => handleQuickCreated('tax', item)"
  />
  <QuickAddModal
    :show="quickAddModal.discount"
    type="discount"
    route-name="discounts.store"
    @close="quickAddModal.discount = false"
    @created="(item) => handleQuickCreated('discount', item)"
  />
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { logActivity } from "@/composables/useActivityLog";

useI18n();
import Modal from "@/Components/Modal.vue";
import QuickAddModal from "@/Pages/Products/Components/QuickAddModal.vue";
import SearchableSelect from "@/Pages/Products/Components/SearchableSelect.vue";

// ---- Shared style tokens (keeps every field aligned & identical) ----
const labelClass = "mb-1.5 block text-sm font-medium text-gray-700";
const inputClass =
  "h-[38px] w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500";
const errorClass = "mt-1 text-sm text-red-500";
const optionCardClass =
  "flex cursor-pointer items-center gap-3 rounded-xl border border-gray-300 bg-white p-4 transition hover:bg-gray-50";

// Track which field opened the unit modal (purchase/sales/transfer)
const unitTargetField = ref(null);

const quickAddModal = ref({
  brand: false,
  category: false,
  type: false,
  unit: false,
  tax: false,
  discount: false,
});

const props = defineProps({
  open: Boolean,
  brands: Array,
  categories: Array,
  types: Array,
  measurementUnits: {
    type: Array,
    default: () => [],
  },
  suppliers: Array,
  customers: Array,
  discounts: Array,
  taxes: Array,
});

const emit = defineEmits(["update:open"]);

const generateBatchNumber = () => {
  const date = new Date();
  const dateString = `${date.getFullYear()}${String(date.getMonth() + 1).padStart(2, "0")}${String(date.getDate()).padStart(2, "0")}`;
  const randomNumber = String(Math.floor(Math.random() * 10000)).padStart(4, "0");

  return `BATCH-${dateString}-${randomNumber}`;
};

const form = useForm({
  name: "",
  barcode: "",
  brand_id: "",
  category_id: "",
  type_id: "",
  discount_id: "",
  discount_value: null,
  discount_type: null,
  tax_id: "",
  quantity: 0,
  batch_number: generateBatchNumber(),
  expire_date: "",
  tax_value: null,
  tax_percentage: null,
  shop_quantity_in_sales_unit: 0,
  shop_low_stock_margin: 0,
  store_quantity_in_purchase_unit: 0,
  store_low_stock_margin: 0,
  purchase_price: 0,
  wholesale_price: null,
  retail_price: null,
  market_price: null,
  return_product: false,
  batch_tracking_enabled: false,
  expiry_date_required: false,
  purchase_unit_id: "",
  sales_unit_id: "",
  transfer_unit_id: "",
  purchase_to_transfer_rate: null,
  transfer_to_sales_rate: null,
  status: 1,
  image: null,
});

// Helper: unit names by ID
const getPurchaseUnitName = (unitId) => {
  if (!unitId) return "";
  const unit = props.measurementUnits.find((u) => u.id === unitId);
  return unit ? unit.name : "";
};

const getPurchaseUnitShortName = (unitId) => {
  if (!unitId) return "";
  const unit = props.measurementUnits.find((u) => u.id === unitId);
  return unit ? unit.short_name || unit.name : "";
};

const getPurchaseUnitConvertedName = (unitId) => {
  if (!unitId) return "";
  const unit = props.measurementUnits.find((u) => u.id === unitId);
  return unit ? unit.name : "";
};

const getSalesUnitName = (unitId) => {
  if (!unitId) return "";
  const unit = props.measurementUnits.find((u) => u.id === unitId);
  return unit ? unit.name : "";
};

const getTransferUnitName = (unitId) => {
  if (!unitId) return "";
  const unit = props.measurementUnits.find((u) => u.id === unitId);
  return unit ? unit.name : "";
};

// Convert brands to searchable format
const brandOptions = computed(() => {
  return props.brands.map((brand) => ({
    id: brand.id,
    label: brand.name,
    name: brand.name,
  }));
});

// Convert categories to searchable format
const categoryOptions = computed(() => {
  return props.categories.map((category) => ({
    id: category.id,
    label: category.hierarchy_string
      ? category.hierarchy_string + " → " + category.name
      : category.name,
    name: category.name,
    hierarchy_string: category.hierarchy_string,
  }));
});

const purchaseUnitDisplayName = computed(() => {
  return getPurchaseUnitName(form.purchase_unit_id);
});

const calculateStoreInTransfer = computed(() => {
  const store = parseFloat(form.store_quantity_in_purchase_unit) || 0;
  const rate = parseFloat(form.purchase_to_transfer_rate) || 0;
  return (store * rate).toFixed(2);
});

const calculateStoreInSales = computed(() => {
  const store = parseFloat(form.store_quantity_in_purchase_unit) || 0;
  const purchaseToTransfer = parseFloat(form.purchase_to_transfer_rate) || 0;
  const transferToSales = parseFloat(form.transfer_to_sales_rate) || 0;
  return (store * purchaseToTransfer * transferToSales).toFixed(2);
});

const storeQuantityAsSalesUnit = computed(() => {
  const store = Number(form.store_quantity_in_purchase_unit);
  const p2t = Number(form.purchase_to_transfer_rate || 0);
  const t2s = Number(form.transfer_to_sales_rate || 0);
  if (!store || !p2t || !t2s) return "";
  const qty = store * p2t * t2s;
  const unit = getSalesUnitName(form.sales_unit_id);
  return unit ? `${qty} ${unit}` : `${qty}`;
});

const calculateShopInTransfer = computed(() => {
  const shop = parseFloat(form.shop_quantity_in_sales_unit) || 0;
  const transferToSales = parseFloat(form.transfer_to_sales_rate) || 0;
  if (transferToSales === 0) return "0.00";
  return (shop / transferToSales).toFixed(2);
});

const calculateShopInPurchase = computed(() => {
  const shop = parseFloat(form.shop_quantity_in_sales_unit) || 0;
  const purchaseToTransfer = parseFloat(form.purchase_to_transfer_rate) || 0;
  const transferToSales = parseFloat(form.transfer_to_sales_rate) || 0;
  if (purchaseToTransfer === 0 || transferToSales === 0) return "0.00";
  return (shop / (purchaseToTransfer * transferToSales)).toFixed(2);
});

// Open functions
const openBrandModal = () => (quickAddModal.value.brand = true);
const openCategoryModal = () => (quickAddModal.value.category = true);
const openTypeModal = () => (quickAddModal.value.type = true);
const openTaxModal = () => (quickAddModal.value.tax = true);
const openDiscountModal = () => (quickAddModal.value.discount = true);

const openUnitModal = (field) => {
  unitTargetField.value = field;
  quickAddModal.value.unit = true;
};

// Handle all quick creations
const handleQuickCreated = (field, newItem) => {
  if (!newItem) return;

  if (field === "brand") {
    props.brands.push(newItem);
    form.brand_id = newItem.id;
  } else if (field === "category") {
    props.categories.push(newItem);
    form.category_id = newItem.id;
  } else if (field === "type") {
    props.types.push(newItem);
    form.type_id = newItem.id;
  } else if (field === "unit") {
    props.measurementUnits.push(newItem);

    if (unitTargetField.value === "purchase") form.purchase_unit_id = newItem.id;
    if (unitTargetField.value === "sales") form.sales_unit_id = newItem.id;
    if (unitTargetField.value === "transfer") form.transfer_unit_id = newItem.id;

    unitTargetField.value = null;
  } else if (field === "tax") {
    props.taxes.push(newItem);
    form.tax_id = newItem.id;
  } else if (field === "discount") {
    props.discounts.push(newItem);
    form.discount_id = newItem.id;
  }

  quickAddModal.value[field] = false;
};

const submit = () => {
  const storeInTransfer = parseFloat(calculateStoreInTransfer.value) || 0;
  const storeInSales = parseFloat(calculateStoreInSales.value) || 0;
  const shopInTransfer = parseFloat(calculateShopInTransfer.value) || 0;
  const shopInPurchase = parseFloat(calculateShopInPurchase.value) || 0;

  const formData = {
    name: form.name,
    barcode: form.barcode,
    brand_id: form.brand_id,
    category_id: form.category_id,
    type_id: form.type_id,
    discount_id: form.discount_id,
    tax_id: form.tax_id,
    quantity: parseFloat(form.quantity) || 0,
    batch_number: form.batch_number,
    expire_date: form.expire_date || null,
    shop_quantity_in_sales_unit: parseFloat(form.shop_quantity_in_sales_unit) || 0,
    shop_low_stock_margin: parseFloat(form.shop_low_stock_margin) || 0,

    store_quantity_in_purchase_unit: parseFloat(form.store_quantity_in_purchase_unit) || 0,
    store_low_stock_margin: parseFloat(form.store_low_stock_margin) || 0,

    purchase_price: form.purchase_price,
    wholesale_price: form.wholesale_price,
    retail_price: form.retail_price,
    market_price: form.market_price,
    return_product: form.return_product,
    batch_tracking_enabled: form.batch_tracking_enabled,
    expiry_date_required: form.expiry_date_required,
    purchase_unit_id: form.purchase_unit_id,
    sales_unit_id: form.sales_unit_id,
    transfer_unit_id: form.transfer_unit_id,
    purchase_to_transfer_rate: form.purchase_to_transfer_rate,
    transfer_to_sales_rate: form.transfer_to_sales_rate,
    status: form.status,
    image: form.image,
    discount_value: form.discount_value,
    discount_type: form.discount_type,
    tax_value: form.tax_value,
    tax_percentage: form.tax_percentage,
    store_in_transfer_units: storeInTransfer,
    store_in_sales_units: storeInSales,
    shop_in_transfer_units: shopInTransfer,
    shop_in_purchase_units: shopInPurchase,
  };

  form
    .transform(() => formData)
    .post(route("products.store"), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: async () => {
        await logActivity("create", "products", {
          product_name: form.name,
          barcode: form.barcode || "Auto-generated",
          brand_id: form.brand_id,
          category_id: form.category_id,
          purchase_price: form.purchase_price,
          selling_price: form.selling_price,
          store_quantity_in_purchase_unit: form.store_quantity_in_purchase_unit,
          shop_quantity_in_sales_unit: form.shop_quantity_in_sales_unit,
          status: form.status,
        });

        closeModal();
        form.reset();
      },
      onError: (errors) => {
        console.error("Validation errors:", errors);
      },
    });
};

// expose page props for template access (currency, currencySymbol)
const page = usePage();

/**
 * Fetch purchase price from goods_received_notes_products table
 * based on product_id and batch_number
 */
const fetchPurchasePriceByBatch = async (productId, batchNumber) => {
  if (!productId || !batchNumber) {
    form.purchase_price = 0;
    return;
  }

  try {
    const response = await fetch("/products/pricing-by-batch", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content,
      },
      body: JSON.stringify({
        product_id: productId,
        batch_number: batchNumber,
      }),
    });

    if (response.ok) {
      const data = await response.json();
      if (data.success && data.purchase_price) {
        form.purchase_price = parseFloat(data.purchase_price).toFixed(2);
      } else {
        form.purchase_price = 0;
      }
    } else {
      form.purchase_price = 0;
    }
  } catch (error) {
    console.error("Error fetching purchase price:", error);
    form.purchase_price = 0;
  }
};

// Watch for modal open and fetch purchase price if product has batch
const watchOpen = watch(
  () => props.open,
  (newVal) => {
    if (newVal) {
      form.batch_number = generateBatchNumber();
    }

    if (newVal && props.selectedProduct && props.selectedProduct.current_batch) {
      fetchPurchasePriceByBatch(
        props.selectedProduct.id,
        props.selectedProduct.current_batch.batch_number
      );
    }
  }
);

const closeModal = () => {
  emit("update:open", false);
  form.reset();
  form.clearErrors();
};
</script>