<template>
  <Head title="Batch & Expiry Reports" />
  <AppLayout>
    <div class="min-h-screen bg-gray-50 p-4 sm:p-6">
      <div class="mx-auto max-w-[1700px]">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="flex items-start gap-3">
            <a :href="route('dashboard')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</a>
            <div>
              <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Batch &amp; Expiry Reports</h1>
              <p class="mt-1 text-sm text-gray-500">Batch-level stock, expiry exposure, sales, adjustments and supplier returns</p>
            </div>
          </div>
          <div class="flex gap-2">
            <a :href="exportHref('xlsx')" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Export Excel</a>
            <a :href="exportHref('pdf')" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Export PDF</a>
          </div>
        </div>

        <nav class="mb-5 flex gap-2 overflow-x-auto rounded-xl border border-gray-200 bg-white p-2 shadow-sm">
          <button v-for="item in reports" :key="item.key" type="button" @click="openReport(item.key)"
            :class="['shrink-0 rounded-lg px-3 py-2 text-sm font-semibold', report === item.key ? 'bg-blue-700 text-white' : 'text-gray-600 hover:bg-gray-100']">
            {{ item.label }}
          </button>
        </nav>

        <section class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-gray-900">Report Filters</h2>
            <div class="flex gap-2">
              <button type="button" @click="applyFilters" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply</button>
              <button type="button" @click="resetFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Reset</button>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            <label class="text-xs font-medium text-gray-600">Search
              <input v-model="form.search" type="search" placeholder="Product, code, batch..." class="mt-1 w-full rounded-lg border-gray-300 text-sm" @keyup.enter="applyFilters">
            </label>
            <label class="text-xs font-medium text-gray-600">Product
              <select v-model="form.product_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option value="">All products</option>
                <option v-for="product in lookups.products" :key="product.id" :value="product.id">{{ product.name }}{{ product.product_search_code ? ` (${product.product_search_code})` : '' }}</option>
              </select>
            </label>
            <label class="text-xs font-medium text-gray-600">Category
              <select v-model="form.category_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option value="">All categories</option><option v-for="item in lookups.categories" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </label>
            <label class="text-xs font-medium text-gray-600">Brand
              <select v-model="form.brand_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option value="">All brands</option><option v-for="item in lookups.brands" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </label>
            <label class="text-xs font-medium text-gray-600">Supplier
              <select v-model="form.supplier_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option value="">All suppliers</option><option v-for="item in lookups.suppliers" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </label>
            <label v-if="supportsExpiryStatus" class="text-xs font-medium text-gray-600">Expiry Status
              <select v-model="form.status" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option value="">All statuses</option><option value="expired">Already Expired</option><option value="today">Expires Today</option>
                <option value="7">Expiring Within 7 Days</option><option value="30">Expiring Within 30 Days</option>
                <option value="60">Expiring Within 60 Days</option><option value="90">Expiring Within 90 Days</option><option value="safe">Safe Stock</option>
              </select>
            </label>
            <label class="text-xs font-medium text-gray-600">{{ dateFilterLabel }} From
              <input v-model="form.start_date" type="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <label class="text-xs font-medium text-gray-600">{{ dateFilterLabel }} To
              <input v-model="form.end_date" type="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <label class="text-xs font-medium text-gray-600">Batch Number
              <input v-model="form.batch_number" type="search" placeholder="Filter batch" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <label v-if="report === 'sales'" class="text-xs font-medium text-gray-600">Invoice Number
              <input v-model="form.invoice_no" type="search" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <label v-if="report === 'sales'" class="text-xs font-medium text-gray-600">Customer
              <select v-model="form.customer_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option value="">All customers</option><option v-for="item in lookups.customers" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </label>
            <label v-if="report === 'adjustments'" class="text-xs font-medium text-gray-600">Adjusted By
              <select v-model="form.user_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option value="">All users</option><option v-for="item in lookups.users" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </label>
            <label v-if="report === 'adjustments'" class="text-xs font-medium text-gray-600">Adjustment Reason
              <input v-model="form.reason" type="search" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <label v-if="report === 'near'" class="text-xs font-medium text-gray-600">Near Expiry Window
              <select v-model.number="form.days" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option :value="7">Next 7 Days</option><option :value="15">Next 15 Days</option><option :value="30">Next 30 Days</option><option :value="60">Next 60 Days</option><option :value="90">Next 90 Days</option>
              </select>
            </label>
            <label class="text-xs font-medium text-gray-600">Rows per page
              <select v-model.number="form.per_page" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <option :value="25">25</option><option :value="50">50</option><option :value="100">100</option>
              </select>
            </label>
          </div>
          <div v-if="report === 'near'" class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-gray-600">Quick filter:</span>
            <button v-for="days in [7, 15, 30, 60, 90]" :key="days" type="button" @click="selectNearWindow(days)" class="rounded-full border border-blue-200 px-3 py-1 text-xs text-blue-700">Next {{ days }} Days</button>
            <span class="text-xs text-gray-500">Choose dates above for a custom range.</span>
          </div>
        </section>

        <section v-if="report === 'dashboard' && dashboard" class="mb-5">
          <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
            <SummaryCard label="Total Active Batches" :value="dashboard.active_batches" />
            <SummaryCard label="Total Expired Batches" :value="dashboard.expired_batches" tone="rose" />
            <SummaryCard label="Expires Today" :value="dashboard.expiring_today_batches" tone="amber" />
            <SummaryCard label="Expiring Within 7 Days" :value="dashboard.expiring_7_days_batches" tone="amber" />
            <SummaryCard label="Expiring Within 30 Days" :value="dashboard.expiring_30_days_batches" tone="orange" />
            <SummaryCard label="Expiring Within 60 Days" :value="dashboard.expiring_60_days_batches" tone="orange" />
            <SummaryCard label="Expiring Within 90 Days" :value="dashboard.expiring_90_days_batches" tone="yellow" />
            <SummaryCard label="Expired Stock Quantity" :value="formatNumber(dashboard.expired_quantity)" tone="rose" />
            <SummaryCard label="Near Expiry Stock Quantity" :value="formatNumber(dashboard.near_quantity)" tone="amber" />
            <SummaryCard label="Expired Stock Purchase Value" :value="formatCurrency(dashboard.expired_value)" tone="rose" />
            <SummaryCard label="Near Expiry Stock Purchase Value" :value="formatCurrency(dashboard.near_value)" tone="amber" />
          </div>
          <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div v-for="chart in chartPanels" :key="chart.key" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
              <h3 class="mb-4 font-semibold text-gray-900">{{ chart.title }}</h3>
              <div v-if="chart.rows?.length" class="space-y-3">
                <div v-for="row in chart.rows" :key="row.label" class="grid grid-cols-[minmax(90px,150px)_1fr_auto] items-center gap-3 text-xs">
                  <span class="truncate text-gray-600" :title="row.label">{{ row.label }}</span>
                  <div class="h-3 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-blue-600" :style="{ width: `${barPercent(chart.rows, row[chart.valueKey], chart.valueKey)}%` }"></div></div>
                  <span class="min-w-[76px] text-right font-semibold">{{ chart.currency ? formatCurrency(row[chart.valueKey]) : formatNumber(row[chart.valueKey]) }}</span>
                </div>
              </div>
              <p v-else class="py-5 text-center text-sm text-gray-500">No chart data for the selected filters.</p>
            </div>
          </div>
        </section>

        <section v-if="report !== 'dashboard' && report !== 'value' && summaryCards.length" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <SummaryCard v-for="card in summaryCards" :key="card.key" :label="card.label" :value="card.value" :tone="card.tone" />
        </section>

        <section v-if="report === 'supplier' && summary.by_supplier?.length" class="mb-5 overflow-x-auto rounded-xl border border-gray-200 bg-white">
          <h2 class="px-5 py-4 font-semibold">Supplier Summary — expired and near-expiry stock</h2>
          <table class="w-full min-w-[700px] text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="p-3">Supplier</th><th class="p-3 text-right">Expired Qty</th><th class="p-3 text-right">Expired Value</th><th class="p-3 text-right">Near Qty</th><th class="p-3 text-right">Near Value</th></tr></thead>
            <tbody><tr v-for="item in summary.by_supplier" :key="item.supplier_name" class="border-t">
              <td class="p-3">{{ item.supplier_name }}</td><td class="p-3 text-right">{{ formatNumber(item.expired_quantity) }}</td><td class="p-3 text-right">{{ formatCurrency(item.expired_value) }}</td><td class="p-3 text-right">{{ formatNumber(item.near_quantity) }}</td><td class="p-3 text-right">{{ formatCurrency(item.near_value) }}</td>
            </tr></tbody>
          </table>
        </section>

        <section v-if="report === 'value'" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div class="col-span-full"><h3 class="font-semibold">Cumulative expiry windows</h3><p class="text-xs text-gray-500">Each window includes stock from shorter windows; do not add these values.</p></div>
          <SummaryCard label="Next 7 Days" :value="formatCurrency(summary.next_7_value)" tone="amber" />
          <SummaryCard label="Next 15 Days" :value="formatCurrency(summary.next_15_value)" tone="amber" />
          <SummaryCard label="Next 30 Days" :value="formatCurrency(summary.next_30_value)" tone="orange" />
          <SummaryCard label="Next 60 Days" :value="formatCurrency(summary.next_60_value)" tone="yellow" />
          <SummaryCard label="Next 90 Days" :value="formatCurrency(summary.next_90_value)" tone="yellow" />
          <div class="col-span-full mt-2"><h3 class="font-semibold">Non-overlapping value bands</h3><p class="text-xs text-gray-500">These bands are mutually exclusive and can be added together.</p></div>
          <SummaryCard label="Expired Stock Value" :value="formatCurrency(summary.expired_value)" tone="rose" />
          <SummaryCard label="Days 1–7" :value="formatCurrency(summary.next_7_value)" tone="amber" />
          <SummaryCard label="Days 8–15" :value="formatCurrency(summary.days_8_15_value)" tone="amber" />
          <SummaryCard label="Days 16–30" :value="formatCurrency(summary.days_16_30_value)" tone="orange" />
          <SummaryCard label="Days 31–60" :value="formatCurrency(summary.days_31_60_value)" tone="yellow" />
          <SummaryCard label="Days 61–90" :value="formatCurrency(summary.days_61_90_value)" tone="yellow" />
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4">
            <div><h2 class="text-lg font-semibold">{{ activeReportLabel }}</h2><p class="text-xs text-gray-500">{{ rows.total || 0 }} matching records · stock value uses remaining quantity × purchase price</p></div>
            <span v-if="report === 'near'" class="rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-800">Today’s expiry is shown separately.</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[1150px] text-left">
              <thead class="bg-gray-50"><tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th v-for="column in columns" :key="column.key" class="whitespace-nowrap px-4 py-3">
                  <button v-if="sortableKeys.includes(column.key)" type="button" @click="sortBy(column.key)" class="hover:text-blue-700">{{ column.label }} <span v-if="filters.sort === column.key">{{ filters.direction === 'asc' ? '↑' : '↓' }}</span></button>
                  <span v-else>{{ column.label }}</span>
                </th>
                <th v-if="report === 'products' || report === 'sales'" class="px-4 py-3 text-right">Actions</th>
              </tr></thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="row in rows.data" :key="row.batch_id || row.product_id || row.sale_allocation_id || row.adjustment_id || row.return_id || `${row.sale_id || ''}-${row.batch_number || ''}-${row.product_name || ''}-${row.adjustment_date || row.return_date || ''}`" class="text-sm text-gray-700 hover:bg-blue-50/30">
                  <td v-for="column in columns" :key="column.key" class="whitespace-nowrap px-4 py-3">
                    <span v-if="column.key === 'expiry_status'" :class="statusClass(row[column.key])" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ row[column.key] }}</span>
                    <span v-else-if="column.type === 'currency'">{{ formatCurrency(row[column.key]) }}</span>
                    <span v-else-if="column.type === 'number'">{{ formatNumber(row[column.key]) }}</span>
                    <span v-else-if="column.key.includes('date')">{{ formatDate(row[column.key]) }}</span>
                    <a v-else-if="column.key === 'invoice_no' && report === 'sales'" :href="route('sales.all', { search: row.invoice_no })" class="font-semibold text-blue-700 hover:underline">{{ row[column.key] }}</a>
                    <span v-else>{{ row[column.key] ?? '—' }}</span>
                  </td>
                  <td v-if="report === 'products'" class="whitespace-nowrap px-4 py-3 text-right">
                    <a :href="reportHref('batches', { product_id: row.product_id })" class="mr-3 font-semibold text-blue-700 hover:underline">View batches</a>
                    <a :href="reportHref('sales', { product_id: row.product_id })" class="font-semibold text-emerald-700 hover:underline">Sales history</a>
                  </td>
                  <td v-else-if="report === 'sales'" class="whitespace-nowrap px-4 py-3 text-right"><a :href="route('sales.all', { search: row.invoice_no })" class="font-semibold text-blue-700 hover:underline">Open invoice</a></td>
                </tr>
                <tr v-if="rows.data.length === 0"><td :colspan="columns.length + (report === 'products' || report === 'sales' ? 1 : 0)" class="px-5 py-12 text-center text-sm text-gray-500">No records found for the selected filters.</td></tr>
              </tbody>
            </table>
          </div>
          <div v-if="rows.links?.length" class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4">
            <p class="text-sm text-gray-500">Showing {{ rows.from || 0 }}–{{ rows.to || 0 }} of {{ rows.total || 0 }}</p>
            <div class="flex flex-wrap justify-end gap-1">
              <button v-for="(link, index) in rows.links" :key="index" type="button" v-html="link.label" :disabled="!link.url" @click="visitPage(link.url)" class="rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50" />
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, defineComponent, h, reactive, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  report: { type: String, default: 'dashboard' },
  reports: { type: Array, default: () => [] },
  columns: { type: Array, default: () => [] },
  rows: { type: Object, default: () => ({ data: [], links: [] }) },
  summary: { type: Object, default: () => ({}) },
  dashboard: { type: Object, default: null },
  charts: { type: Object, default: null },
  lookups: { type: Object, default: () => ({ products: [], categories: [], brands: [], suppliers: [], customers: [], users: [] }) },
  filters: { type: Object, default: () => ({}) },
  currency: { type: String, default: 'Rs.' },
});

const SummaryCard = defineComponent({
  props: { label: String, value: [String, Number], tone: { type: String, default: 'blue' } },
  setup(card) {
    const tones = { blue: 'border-blue-100 text-blue-800', rose: 'border-rose-100 text-rose-800', amber: 'border-amber-100 text-amber-800', orange: 'border-orange-100 text-orange-800', yellow: 'border-yellow-100 text-yellow-800' };
    return () => h('div', { class: `rounded-xl border bg-white p-4 shadow-sm ${tones[card.tone] || tones.blue}` }, [
      h('p', { class: 'text-xs font-medium text-gray-500' }, card.label),
      h('p', { class: 'mt-2 break-words text-xl font-bold' }, String(card.value ?? '0')),
    ]);
  },
});

const form = reactive({
  start_date: props.filters.start_date || '', end_date: props.filters.end_date || '',
  product_id: props.filters.product_id || '', category_id: props.filters.category_id || '',
  brand_id: props.filters.brand_id || '', supplier_id: props.filters.supplier_id || '',
  batch_number: props.filters.batch_number || '', status: props.filters.status || '',
  search: props.filters.search || '', days: Number(props.filters.days || 30),
  invoice_no: props.filters.invoice_no || '', customer_id: props.filters.customer_id || '',
  user_id: props.filters.user_id || '', reason: props.filters.reason || '',
  sort: props.filters.sort || '', direction: props.filters.direction || 'asc',
  per_page: Number(props.filters.per_page || 25),
});

watch(() => props.filters, (next) => Object.assign(form, next), { deep: true });

const supportsExpiryStatus = computed(() => ['dashboard', 'expiry', 'expired', 'near', 'batches', 'products', 'value', 'supplier'].includes(props.report));
const dateFilterLabel = computed(() => ['sales', 'adjustments', 'returns'].includes(props.report) ? 'Transaction Date' : 'Expiry Date');
const activeReportLabel = computed(() => props.reports.find((item) => item.key === props.report)?.label || 'Batch & Expiry Report');
const sortableKeys = [
  'product_name', 'product_code', 'category', 'brand', 'batch_number', 'expiry_date',
  'available_quantity', 'purchase_quantity', 'sold_quantity', 'supplier_returned_quantity',
  'customer_returned_quantity', 'adjusted_quantity', 'purchase_price', 'selling_price',
  'stock_purchase_value', 'expiry_status', 'grn_number', 'supplier_name', 'expiry_bucket',
  'sale_date', 'invoice_no', 'customer_name', 'unit_selling_price', 'total_amount',
  'cashier_name', 'adjustment_date', 'reason', 'adjustment_quantity', 'adjustment_value',
  'adjusted_by', 'return_date', 'return_quantity', 'return_value', 'return_reason',
  'processed_by', 'total_purchase_value', 'total_batches', 'nearest_expiry_date',
  'earliest_expiry_date', 'earliest_expiring_batch', 'latest_expiry_date', 'latest_expiring_batch',
];
const chartPanels = computed(() => !props.charts ? [] : [
  { key: 'expiry_status', title: 'Expiry Status Distribution', rows: props.charts.expiry_status, valueKey: 'quantity' },
  { key: 'monthly_quantity', title: 'Monthly Expiry Stock Quantity', rows: props.charts.monthly_quantity, valueKey: 'value' },
  { key: 'monthly_value', title: 'Monthly Expiry Stock Value', rows: props.charts.monthly_value, valueKey: 'value', currency: true },
  { key: 'category_near_expiry', title: 'Category-wise Near Expiry Stock', rows: props.charts.category_near_expiry, valueKey: 'quantity' },
  { key: 'supplier_near_expiry', title: 'Supplier-wise Expiry Stock Value', rows: props.charts.supplier_near_expiry, valueKey: 'value', currency: true },
  { key: 'comparison', title: 'Expired vs Near Expiry Stock Comparison', rows: props.charts.comparison, valueKey: 'value', currency: true },
]);

const summaryLabels = {
  products: 'Expired Products', batches: 'Batches', records: 'Transactions',
  quantity: 'Quantity', value: 'Purchase Value', sales_value: 'Sales Value',
  sold_quantity: 'Sold Quantity', total_purchase_value: 'Purchase Value',
  total_batches: 'Batches', expired_quantity: 'Expired Quantity',
  expired_value: 'Expired Value', near_quantity: 'Near Expiry Quantity', near_value: 'Near Expiry Value',
};
const formatNumber = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatCurrency = (value) => `${props.currency} ${formatNumber(value)}`;
const summaryCards = computed(() => Object.entries(props.summary || {})
  .filter(([, value]) => !Array.isArray(value) && value !== null && typeof value !== 'object')
  .map(([key, value]) => ({
    key, label: summaryLabels[key] || key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()),
    value: key.includes('value') ? formatCurrency(value) : formatNumber(value),
    tone: key.includes('expired') ? 'rose' : key.includes('near') ? 'amber' : 'blue',
  })));
const queryFilters = () => Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '' && value !== null && value !== undefined));
const openReport = (key) => router.get(route('reports.batch-expiry.index', { report: key }), queryFilters(), { preserveScroll: true });
const reportHref = (key, extra = {}) => route('reports.batch-expiry.index', { ...queryFilters(), ...extra, report: key });
const applyFilters = () => router.get(route('reports.batch-expiry.index', { report: props.report }), queryFilters(), { preserveState: true, preserveScroll: true, replace: true });
const resetFilters = () => {
  Object.assign(form, {
    start_date: '', end_date: '', product_id: '', category_id: '', brand_id: '',
    supplier_id: '', batch_number: '', status: '', search: '', days: 30,
    invoice_no: '', customer_id: '', user_id: '', reason: '', sort: '',
    direction: 'asc', per_page: 25,
  });
  applyFilters();
};
const selectNearWindow = (days) => { form.days = days; form.start_date = ''; form.end_date = ''; applyFilters(); };
const sortBy = (key) => { form.direction = form.sort === key && form.direction === 'asc' ? 'desc' : 'asc'; form.sort = key; applyFilters(); };
const exportHref = (format) => route('reports.batch-expiry.export', { report: props.report, format, ...queryFilters() });
const visitPage = (url) => { if (url) router.visit(url, { preserveScroll: true, preserveState: true }); };
const formatDate = (value) => value ? String(value).slice(0, 10) : '—';
const barPercent = (data, value, valueKey = 'value') => {
  const max = Math.max(...data.map((item) => Number(item[valueKey] || 0)), 0);
  return !max || !Number(value) ? 0 : Math.max(2, Number(value) / max * 100);
};
const statusClass = (status) => {
  if (status === 'Already Expired') return 'bg-rose-100 text-rose-800';
  if (status === 'Expires Today') return 'bg-red-100 text-red-800';
  if (status?.includes('7 Days')) return 'bg-orange-100 text-orange-800';
  if (status?.includes('30 Days') || status?.includes('60 Days')) return 'bg-amber-100 text-amber-800';
  if (status?.includes('90 Days')) return 'bg-yellow-100 text-yellow-800';
  return 'bg-emerald-100 text-emerald-800';
};
</script>
