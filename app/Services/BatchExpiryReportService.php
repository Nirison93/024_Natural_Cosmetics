<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BatchExpiryReportService
{
    public const REPORTS = [
        'dashboard', 'expiry', 'expired', 'near', 'batches', 'products',
        'value', 'supplier', 'sales', 'adjustments', 'returns',
    ];

    public function filters(array $input): array
    {
        return [
            'start_date' => $input['start_date'] ?? null,
            'end_date' => $input['end_date'] ?? null,
            'product_id' => $input['product_id'] ?? null,
            'category_id' => $input['category_id'] ?? null,
            'brand_id' => $input['brand_id'] ?? null,
            'supplier_id' => $input['supplier_id'] ?? null,
            'batch_number' => trim((string) ($input['batch_number'] ?? '')),
            'status' => $input['status'] ?? '',
            'search' => trim((string) ($input['search'] ?? '')),
            'days' => (int) ($input['days'] ?? 30),
            'invoice_no' => trim((string) ($input['invoice_no'] ?? '')),
            'customer_id' => $input['customer_id'] ?? null,
            'user_id' => $input['user_id'] ?? null,
            'reason' => trim((string) ($input['reason'] ?? '')),
            'sort' => $input['sort'] ?? '',
            'direction' => strtolower((string) ($input['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            'per_page' => (int) ($input['per_page'] ?? 25),
        ];
    }

    public function columns(string $report): array
    {
        $columns = match ($report) {
            'expired' => [
                'product_name' => 'Product Name', 'product_code' => 'Product Code',
                'category' => 'Category', 'batch_number' => 'Batch Number',
                'expiry_date' => 'Expiry Date', 'available_quantity' => 'Remaining Expired Quantity',
                'purchase_price' => 'Purchase Price', 'stock_purchase_value' => 'Total Purchase Value',
                'supplier_name' => 'Supplier', 'grn_number' => 'GRN Number',
                'expiry_status' => 'Expiry Status',
            ],
            'near' => [
                'product_name' => 'Product Name', 'product_code' => 'Product Code',
                'category' => 'Category', 'batch_number' => 'Batch Number',
                'expiry_date' => 'Expiry Date', 'days_remaining' => 'Days Remaining',
                'available_quantity' => 'Available Quantity', 'purchase_price' => 'Purchase Price',
                'selling_price' => 'Selling Price', 'stock_purchase_value' => 'Total Purchase Value',
                'supplier_name' => 'Supplier',
            ],
            'batches' => [
                'product_name' => 'Product Name', 'product_code' => 'Product Code',
                'category' => 'Category', 'batch_number' => 'Batch Number',
                'expiry_date' => 'Expiry Date', 'purchase_quantity' => 'Purchase Quantity',
                'sold_quantity' => 'Sold Quantity',
                'supplier_returned_quantity' => 'Supplier Returned Quantity',
                'customer_returned_quantity' => 'Customer Returned Quantity',
                'adjusted_quantity' => 'Adjusted Quantity', 'available_quantity' => 'Available Quantity',
                'purchase_price' => 'Purchase Price', 'selling_price' => 'Selling Price',
                'stock_purchase_value' => 'Stock Value', 'supplier_name' => 'Supplier',
            ],
            'products' => [
                'product_name' => 'Product Name', 'product_code' => 'Product Code',
                'barcode' => 'Barcode', 'category' => 'Category', 'brand' => 'Brand',
                'available_quantity' => 'Total Available Quantity',
                'total_purchase_value' => 'Total Purchase Value', 'total_batches' => 'Total Number of Batches',
                'nearest_expiry_date' => 'Nearest Expiry Date',
                'earliest_expiring_batch' => 'Earliest Expiring Batch',
                'earliest_expiry_date' => 'Earliest Expiry Date',
                'latest_expiring_batch' => 'Latest Expiring Batch',
                'latest_expiry_date' => 'Latest Expiry Date',
            ],
            'value' => [
                'expiry_bucket' => 'Expiry Period', 'product_name' => 'Product Name',
                'product_code' => 'Product Code', 'batch_number' => 'Batch Number',
                'expiry_date' => 'Expiry Date', 'available_quantity' => 'Remaining Quantity',
                'purchase_price' => 'Purchase Price', 'stock_purchase_value' => 'Stock Purchase Value',
                'supplier_name' => 'Supplier',
            ],
            'supplier' => [
                'supplier_name' => 'Supplier Name', 'product_name' => 'Product Name',
                'product_code' => 'Product Code', 'batch_number' => 'Batch Number',
                'expiry_date' => 'Expiry Date', 'available_quantity' => 'Available Quantity',
                'purchase_price' => 'Purchase Price', 'stock_purchase_value' => 'Total Purchase Value',
                'grn_number' => 'GRN Number', 'expiry_status' => 'Expiry Status',
            ],
            'sales' => [
                'sale_date' => 'Sale Date', 'invoice_no' => 'Invoice Number',
                'customer_name' => 'Customer Name', 'product_name' => 'Product Name',
                'product_code' => 'Product Code', 'batch_number' => 'Batch Number',
                'expiry_date' => 'Expiry Date', 'sold_quantity' => 'Sold Quantity',
                'unit_selling_price' => 'Unit Selling Price', 'total_amount' => 'Total Amount',
                'cashier_name' => 'Cashier / User',
            ],
            'adjustments' => [
                'adjustment_date' => 'Adjustment Date', 'product_name' => 'Product Name',
                'product_code' => 'Product Code', 'batch_number' => 'Batch Number',
                'expiry_date' => 'Expiry Date', 'previous_quantity' => 'Previous Quantity',
                'adjustment_quantity' => 'Adjustment Quantity', 'reason' => 'Reason',
                'purchase_price' => 'Purchase Price', 'adjustment_value' => 'Total Adjustment Value',
                'supplier_name' => 'Supplier', 'adjusted_by' => 'Adjusted By', 'notes' => 'Notes',
            ],
            'returns' => [
                'return_date' => 'Return Date', 'supplier_name' => 'Supplier',
                'product_name' => 'Product', 'product_code' => 'Product Code',
                'batch_number' => 'Batch Number', 'expiry_date' => 'Expiry Date',
                'return_quantity' => 'Return Quantity', 'purchase_price' => 'Purchase Price',
                'return_value' => 'Total Return Value', 'return_reason' => 'Return Reason',
                'reference_number' => 'Reference Number', 'processed_by' => 'Processed By',
            ],
            default => [
                'product_name' => 'Product Name', 'product_code' => 'Product Code',
                'barcode' => 'Barcode', 'category' => 'Category', 'brand' => 'Brand',
                'batch_number' => 'Batch Number', 'expiry_date' => 'Expiry Date',
                'available_quantity' => 'Available Quantity', 'purchase_price' => 'Purchase Price',
                'selling_price' => 'Selling Price', 'stock_purchase_value' => 'Stock Purchase Value',
                'supplier_name' => 'Supplier Name', 'grn_number' => 'GRN Number',
                'expiry_status' => 'Expiry Status',
            ],
        };

        $money = [
            'purchase_price', 'selling_price', 'stock_purchase_value', 'total_purchase_value',
            'unit_selling_price', 'total_amount', 'adjustment_value', 'return_value',
        ];
        $numbers = [
            'available_quantity', 'purchase_quantity', 'sold_quantity', 'supplier_returned_quantity',
            'customer_returned_quantity', 'adjusted_quantity', 'return_quantity',
            'adjustment_quantity', 'previous_quantity', 'days_remaining', 'total_batches',
        ];

        return array_map(static function (string $key, string $label) use ($money, $numbers) {
            return [
                'key' => $key,
                'label' => $label,
                'type' => in_array($key, $money, true) ? 'currency' : (in_array($key, $numbers, true) ? 'number' : 'text'),
            ];
        }, array_keys($columns), array_values($columns));
    }

    public function query(string $report, array $filters): Builder
    {
        return match ($report) {
            'expired' => $this->expiryRows($filters, 'expired'),
            'near' => $this->expiryRows($filters, 'near'),
            'batches' => $this->batchRows($filters),
            'products' => $this->productSummaryRows($filters),
            'value' => $this->expiryValueRows($filters),
            'supplier' => $this->expiryRows($filters, 'supplier'),
            'sales' => $this->salesRows($filters),
            'adjustments' => $this->adjustmentRows($filters),
            'returns' => $this->supplierReturnRows($filters),
            default => $this->expiryRows($filters, 'expiry'),
        };
    }

    public function summary(string $report, array $filters): array
    {
        if ($report === 'dashboard') {
            return $this->dashboardSummary($filters);
        }
        if ($report === 'expired') {
            return $this->stockSummary($filters, 'expired');
        }
        if ($report === 'near') {
            $query = $this->applyNearRange($this->batchSourceQuery($filters)->where('batch.available_sales_quantity', '>', 0), $filters);
            return $this->aggregateStockSummary($query);
        }
        if ($report === 'value') {
            return $this->valueSummary($filters);
        }
        if ($report === 'supplier') {
            return $this->supplierSummary($filters);
        }
        if ($report === 'sales') {
            $query = $this->salesRows($filters);
            return [
                'records' => (int) (clone $query)->count(),
                'sold_quantity' => (float) (clone $query)->sum('batch_sale.quantity'),
                'sales_value' => (float) (clone $query)->sum(DB::raw('batch_sale.quantity * sale_line.price')),
            ];
        }
        if ($report === 'adjustments') {
            $query = $this->adjustmentRows($filters);
            return [
                'records' => (int) (clone $query)->count(),
                'quantity' => (float) (clone $query)->sum(DB::raw('ABS(movement.quantity)')),
                'value' => (float) (clone $query)->sum(DB::raw('ABS(movement.quantity) * COALESCE(batch.purchase_price, 0)')),
            ];
        }
        if ($report === 'returns') {
            $query = $this->supplierReturnRows($filters);
            return [
                'records' => (int) (clone $query)->count(),
                'quantity' => (float) (clone $query)->sum('return_line.quantity'),
                'value' => (float) (clone $query)->sum(DB::raw('return_line.quantity * COALESCE(batch.purchase_price, 0)')),
            ];
        }

        return $this->aggregateStockSummary(
            $this->batchSourceQuery($filters)->where('batch.available_sales_quantity', '>', 0)
        );
    }

    public function dashboardSummary(array $filters): array
    {
        $today = $this->today();
        $plus7 = $this->day($today, 7);
        $plus30 = $this->day($today, 30);
        $plus60 = $this->day($today, 60);
        $plus90 = $this->day($today, 90);
        $row = $this->batchSourceQuery($filters)
            ->where('batch.available_sales_quantity', '>', 0)
            ->selectRaw('COUNT(batch.id) AS active_batches')
            ->selectRaw('SUM(CASE WHEN batch.expiry_date < ? THEN 1 ELSE 0 END) AS expired_batches', [$today])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date = ? THEN 1 ELSE 0 END) AS expiring_today_batches', [$today])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date > ? AND batch.expiry_date <= ? THEN 1 ELSE 0 END) AS expiring_7_days_batches', [$today, $plus7])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date > ? AND batch.expiry_date <= ? THEN 1 ELSE 0 END) AS expiring_30_days_batches', [$today, $plus30])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date > ? AND batch.expiry_date <= ? THEN 1 ELSE 0 END) AS expiring_60_days_batches', [$today, $plus60])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date > ? AND batch.expiry_date <= ? THEN 1 ELSE 0 END) AS expiring_90_days_batches', [$today, $plus90])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date < ? THEN batch.available_sales_quantity ELSE 0 END) AS expired_quantity', [$today])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date > ? AND batch.expiry_date <= ? THEN batch.available_sales_quantity ELSE 0 END) AS near_quantity', [$today, $plus90])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date < ? THEN batch.available_sales_quantity * COALESCE(batch.purchase_price, 0) ELSE 0 END) AS expired_value', [$today])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date > ? AND batch.expiry_date <= ? THEN batch.available_sales_quantity * COALESCE(batch.purchase_price, 0) ELSE 0 END) AS near_value', [$today, $plus90])
            ->first();

        return [
            'active_batches' => (int) ($row->active_batches ?? 0),
            'expired_batches' => (int) ($row->expired_batches ?? 0),
            'expiring_today_batches' => (int) ($row->expiring_today_batches ?? 0),
            'expiring_7_days_batches' => (int) ($row->expiring_7_days_batches ?? 0),
            'expiring_30_days_batches' => (int) ($row->expiring_30_days_batches ?? 0),
            'expiring_60_days_batches' => (int) ($row->expiring_60_days_batches ?? 0),
            'expiring_90_days_batches' => (int) ($row->expiring_90_days_batches ?? 0),
            'expired_quantity' => (float) ($row->expired_quantity ?? 0),
            'near_quantity' => (float) ($row->near_quantity ?? 0),
            'expired_value' => (float) ($row->expired_value ?? 0),
            'near_value' => (float) ($row->near_value ?? 0),
        ];
    }

    public function dashboardCharts(array $filters): array
    {
        $today = $this->today();
        $plus90 = $this->day($today, 90);
        $positive = fn () => $this->batchSourceQuery($filters)->where('batch.available_sales_quantity', '>', 0);
        $buckets = [
            ['label' => 'Already Expired', 'status' => 'expired'],
            ['label' => 'Expires Today', 'status' => 'today'],
            ['label' => 'Expiring Within 7 Days', 'status' => '7'],
            ['label' => 'Expiring Within 30 Days', 'status' => '30'],
            ['label' => 'Expiring Within 60 Days', 'status' => '60'],
            ['label' => 'Expiring Within 90 Days', 'status' => '90'],
            ['label' => 'Safe Stock', 'status' => 'safe'],
        ];
        $distribution = [];
        foreach ($buckets as $bucket) {
            $query = $positive();
            $this->applyStatusWhere($query, $bucket['status']);
            $distribution[] = [
                'label' => $bucket['label'],
                'quantity' => (float) $query->sum('batch.available_sales_quantity'),
            ];
        }

        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', batch.expiry_date)"
            : "DATE_FORMAT(batch.expiry_date, '%Y-%m')";
        $monthly = $positive()
            ->whereNotNull('batch.expiry_date')
            ->whereDate('batch.expiry_date', '>=', $today)
            ->whereDate('batch.expiry_date', '<=', $this->day($today, 365))
            ->selectRaw("$monthExpression AS expiry_month")
            ->selectRaw('SUM(batch.available_sales_quantity) AS quantity')
            ->selectRaw('SUM(batch.available_sales_quantity * COALESCE(batch.purchase_price, 0)) AS value')
            ->groupBy('expiry_month')->orderBy('expiry_month')->get();

        $near = $positive()->whereDate('batch.expiry_date', '>', $today)->whereDate('batch.expiry_date', '<=', $plus90);
        $category = (clone $near)
            ->selectRaw("COALESCE(category.name, 'Uncategorized') AS label")
            ->selectRaw('SUM(batch.available_sales_quantity) AS quantity')
            ->groupBy('category.name')->orderByDesc('quantity')->limit(10)->get()
            ->map(fn ($row) => ['label' => $row->label, 'quantity' => (float) $row->quantity])->all();
        $supplier = (clone $near)
            ->selectRaw("COALESCE(batch_supplier.name, grn_supplier.name, 'Unknown Supplier') AS label")
            ->selectRaw('SUM(batch.available_sales_quantity * COALESCE(batch.purchase_price, 0)) AS value')
            ->groupByRaw("COALESCE(batch_supplier.name, grn_supplier.name, 'Unknown Supplier')")
            ->orderByDesc('value')->limit(10)->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (float) $row->value])->all();

        $comparison = [];
        foreach ([
            ['label' => 'Expired', 'operator' => '<', 'end' => null],
            ['label' => 'Near Expiry (90 days)', 'operator' => '>', 'end' => $plus90],
        ] as $range) {
            $query = $positive();
            if ($range['operator'] === '<') {
                $query->whereDate('batch.expiry_date', '<', $today);
            } else {
                $query->whereDate('batch.expiry_date', '>', $today)->whereDate('batch.expiry_date', '<=', $range['end']);
            }
            $comparison[] = [
                'label' => $range['label'],
                'quantity' => (float) (clone $query)->sum('batch.available_sales_quantity'),
                'value' => (float) (clone $query)->sum(DB::raw('batch.available_sales_quantity * COALESCE(batch.purchase_price, 0)')),
            ];
        }

        return [
            'expiry_status' => $distribution,
            'monthly_quantity' => $monthly->map(fn ($row) => ['label' => $row->expiry_month, 'value' => (float) $row->quantity])->all(),
            'monthly_value' => $monthly->map(fn ($row) => ['label' => $row->expiry_month, 'value' => (float) $row->value])->all(),
            'category_near_expiry' => $category,
            'supplier_near_expiry' => $supplier,
            'comparison' => $comparison,
        ];
    }

    public function lookups(): array
    {
        return [
            'products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'product_search_code', 'barcode']),
            'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'brands' => DB::table('brands')->orderBy('name')->get(['id', 'name']),
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(['id', 'name']),
            'customers' => DB::table('customers')->orderBy('name')->get(['id', 'name']),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function expiryRows(array $filters, string $kind): Builder
    {
        $query = $this->batchSourceQuery($filters);
        $this->selectBatchRows($query);
        if ($kind !== 'expiry') {
            $query->where('batch.available_sales_quantity', '>', 0);
        }

        if ($kind === 'expired') {
            $query->whereDate('batch.expiry_date', '<', $this->today());
        } elseif ($kind === 'near') {
            $this->applyNearRange($query, $filters);
            $query->selectRaw($this->daysRemainingExpression() . ' AS days_remaining', [$this->today()]);
        } else {
            $query->whereNotNull('batch.expiry_date');
        }

        return $this->sortQuery($query, $filters, [
            'product_name' => 'product.name', 'product_code' => 'product.product_search_code',
            'category' => 'category.name', 'brand' => 'brand.name',
            'batch_number' => 'batch.batch_number', 'expiry_date' => 'batch.expiry_date',
            'available_quantity' => 'batch.available_sales_quantity',
            'purchase_price' => 'batch.purchase_price', 'selling_price' => 'batch.selling_price',
            'stock_purchase_value' => DB::raw('(batch.available_sales_quantity * COALESCE(batch.purchase_price, 0))'),
            'supplier_name' => DB::raw('COALESCE(batch_supplier.name, grn_supplier.name)'),
            'grn_number' => 'grn.goods_received_note_no', 'expiry_status' => 'expiry_status',
        ], 'batch.expiry_date');
    }

    private function batchRows(array $filters): Builder
    {
        $query = $this->batchSourceQuery($filters);
        $this->selectBatchRows($query);
        $query->leftJoinSub(
            DB::table('sales_product_batches')->select('product_available_quantity_id')->selectRaw('SUM(quantity) AS sold_quantity')->groupBy('product_available_quantity_id'),
            'sales_totals', 'sales_totals.product_available_quantity_id', '=', 'batch.id'
        )->leftJoinSub(
            DB::table('sales_return_product_batches as return_batch')
                ->join('sales_return_products as return_line', 'return_line.id', '=', 'return_batch.sales_return_product_id')
                ->join('sales_return as sales_return', 'sales_return.id', '=', 'return_line.sales_return_id')
                ->where('sales_return.status', 1)
                ->select('return_batch.product_available_quantity_id')
                ->selectRaw('SUM(return_batch.quantity) AS returned_quantity')
                ->groupBy('return_batch.product_available_quantity_id'),
            'customer_return_totals', 'customer_return_totals.product_available_quantity_id', '=', 'batch.id'
        )->leftJoinSub(
            DB::table('goods_received_note_return_products')
                ->whereNotNull('product_available_quantity_id')
                ->select('product_available_quantity_id')
                ->selectRaw('SUM(quantity) AS supplier_returned_quantity')
                ->groupBy('product_available_quantity_id'),
            'supplier_return_totals', 'supplier_return_totals.product_available_quantity_id', '=', 'batch.id'
        )->leftJoinSub(
            DB::table('product_movements')->where('movement_type', 7)->whereNotNull('product_available_quantity_id')
                ->select('product_available_quantity_id')->selectRaw('SUM(ABS(quantity)) AS adjusted_quantity')
                ->groupBy('product_available_quantity_id'),
            'adjustment_totals', 'adjustment_totals.product_available_quantity_id', '=', 'batch.id'
        )->addSelect([
            DB::raw('COALESCE(batch.received_sales_quantity, 0) AS purchase_quantity'),
            DB::raw('COALESCE(sales_totals.sold_quantity, 0) AS sold_quantity'),
            DB::raw('COALESCE(customer_return_totals.returned_quantity, 0) AS customer_returned_quantity'),
            DB::raw('COALESCE(supplier_return_totals.supplier_returned_quantity, 0) AS supplier_returned_quantity'),
            DB::raw('COALESCE(adjustment_totals.adjusted_quantity, 0) AS adjusted_quantity'),
        ]);

        return $this->sortQuery($query, $filters, [
            'product_name' => 'product.name', 'product_code' => 'product.product_search_code',
            'category' => 'category.name', 'brand' => 'brand.name',
            'batch_number' => 'batch.batch_number', 'expiry_date' => 'batch.expiry_date',
            'available_quantity' => 'batch.available_sales_quantity',
            'purchase_quantity' => 'purchase_quantity', 'sold_quantity' => 'sold_quantity',
            'supplier_returned_quantity' => 'supplier_returned_quantity',
            'customer_returned_quantity' => 'customer_returned_quantity',
            'adjusted_quantity' => 'adjusted_quantity',
            'purchase_price' => 'batch.purchase_price', 'selling_price' => 'batch.selling_price',
            'stock_purchase_value' => DB::raw('(batch.available_sales_quantity * COALESCE(batch.purchase_price, 0))'),
            'supplier_name' => DB::raw('COALESCE(batch_supplier.name, grn_supplier.name)'),
        ], 'product.name');
    }

    private function productSummaryRows(array $filters): Builder
    {
        $query = $this->batchSourceQuery($filters)
            ->select([
                'product.id AS product_id', 'product.name AS product_name',
                'product.product_search_code AS product_code', 'product.barcode',
                'category.name AS category', 'brand.name AS brand',
            ])
            ->selectRaw('SUM(batch.available_sales_quantity) AS available_quantity')
            ->selectRaw('SUM(batch.available_sales_quantity * COALESCE(batch.purchase_price, 0)) AS total_purchase_value')
            ->selectRaw('COUNT(DISTINCT batch.id) AS total_batches')
            ->selectRaw('MIN(CASE WHEN batch.available_sales_quantity > 0 AND batch.expiry_date >= ? THEN batch.expiry_date ELSE NULL END) AS nearest_expiry_date', [$this->today()])
            ->selectRaw('MIN(CASE WHEN batch.available_sales_quantity > 0 THEN batch.expiry_date ELSE NULL END) AS earliest_expiry_date')
            ->selectRaw('MAX(CASE WHEN batch.available_sales_quantity > 0 THEN batch.expiry_date ELSE NULL END) AS latest_expiry_date')
            ->selectSub($this->productBatchNumberSubquery($filters, 'asc'), 'earliest_expiring_batch')
            ->selectSub($this->productBatchNumberSubquery($filters, 'desc'), 'latest_expiring_batch')
            ->groupBy('product.id', 'product.name', 'product.product_search_code', 'product.barcode', 'category.name', 'brand.name');

        return $this->sortQuery($query, $filters, [
            'product_name' => 'product_name', 'product_code' => 'product_code',
            'category' => 'category', 'brand' => 'brand',
            'available_quantity' => 'available_quantity', 'total_purchase_value' => 'total_purchase_value',
            'total_batches' => 'total_batches', 'nearest_expiry_date' => 'nearest_expiry_date',
            'earliest_expiry_date' => 'earliest_expiry_date', 'latest_expiry_date' => 'latest_expiry_date',
            'earliest_expiring_batch' => 'earliest_expiring_batch', 'latest_expiring_batch' => 'latest_expiring_batch',
        ], 'product_name');
    }

    private function productBatchNumberSubquery(array $filters, string $direction): Builder
    {
        $query = DB::table('product_available_quantities as summary_batch')
            ->leftJoin('goods_received_notes as summary_grn', 'summary_grn.id', '=', 'summary_batch.goods_received_note_id')
            ->leftJoin('suppliers as summary_batch_supplier', 'summary_batch_supplier.id', '=', 'summary_batch.supplier_id')
            ->leftJoin('suppliers as summary_grn_supplier', 'summary_grn_supplier.id', '=', 'summary_grn.supplier_id')
            ->select('summary_batch.batch_number')
            ->whereColumn('summary_batch.product_id', 'product.id')
            ->where('summary_batch.available_sales_quantity', '>', 0)
            ->whereNotNull('summary_batch.expiry_date');
        if (!empty($filters['batch_number'])) {
            $query->where('summary_batch.batch_number', 'like', '%' . $filters['batch_number'] . '%');
        }
        if (!empty($filters['supplier_id'])) {
            $query->where(fn (Builder $q) => $q->where('summary_batch.supplier_id', $filters['supplier_id'])
                ->orWhere('summary_grn.supplier_id', $filters['supplier_id']));
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('summary_batch.expiry_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('summary_batch.expiry_date', '<=', $filters['end_date']);
        }
        if (!empty($filters['status'])) {
            $this->applyStatusWhere($query, $filters['status'], 'summary_batch.expiry_date');
        }

        return $query->orderBy('summary_batch.expiry_date', $direction)
            ->orderBy('summary_batch.id', $direction)->limit(1);
    }

    private function expiryValueRows(array $filters): Builder
    {
        $today = $this->today();
        $plus90 = $this->day($today, 90);
        $query = $this->batchSourceQuery($filters)
            ->where('batch.available_sales_quantity', '>', 0)
            ->whereNotNull('batch.expiry_date')
            ->where(function (Builder $q) use ($today, $plus90) {
                $q->whereDate('batch.expiry_date', '<', $today)
                    ->orWhere(fn (Builder $q) => $q->whereDate('batch.expiry_date', '>', $today)->whereDate('batch.expiry_date', '<=', $plus90));
            });
        $this->selectBatchRows($query);
        $query->selectRaw(
            'CASE WHEN batch.expiry_date < ? THEN ? WHEN batch.expiry_date <= ? THEN ? WHEN batch.expiry_date <= ? THEN ? WHEN batch.expiry_date <= ? THEN ? WHEN batch.expiry_date <= ? THEN ? ELSE ? END AS expiry_bucket',
            [$today, 'Expired', $this->day($today, 7), 'Next 7 Days', $this->day($today, 15), '8-15 Days', $this->day($today, 30), '16-30 Days', $this->day($today, 60), '31-60 Days', '61-90 Days']
        );
        return $this->sortQuery($query, $filters, [
            'product_name' => 'product.name', 'product_code' => 'product.product_search_code',
            'batch_number' => 'batch.batch_number', 'expiry_date' => 'batch.expiry_date',
            'expiry_bucket' => 'expiry_bucket', 'available_quantity' => 'batch.available_sales_quantity',
            'purchase_price' => 'batch.purchase_price',
            'stock_purchase_value' => DB::raw('(batch.available_sales_quantity * COALESCE(batch.purchase_price, 0))'),
            'supplier_name' => DB::raw('COALESCE(batch_supplier.name, grn_supplier.name)'),
        ], 'batch.expiry_date');
    }

    private function salesRows(array $filters): Builder
    {
        $query = DB::table('sales_product_batches as batch_sale')
            ->join('product_available_quantities as batch', 'batch.id', '=', 'batch_sale.product_available_quantity_id')
            ->join('sales_products as sale_line', 'sale_line.id', '=', 'batch_sale.sales_product_id')
            ->join('sales as sale', 'sale.id', '=', 'sale_line.sale_id')
            ->join('products as product', 'product.id', '=', 'sale_line.product_id')
            ->leftJoin('categories as category', 'category.id', '=', 'product.category_id')
            ->leftJoin('brands as brand', 'brand.id', '=', 'product.brand_id')
            ->leftJoin('customers as customer', 'customer.id', '=', 'sale.customer_id')
            ->leftJoin('users as cashier', 'cashier.id', '=', 'sale.user_id')
            ->leftJoin('goods_received_notes as grn', 'grn.id', '=', 'batch.goods_received_note_id')
            ->leftJoin('suppliers as batch_supplier', 'batch_supplier.id', '=', 'batch.supplier_id')
            ->leftJoin('suppliers as grn_supplier', 'grn_supplier.id', '=', 'grn.supplier_id')
            ->select([
                'batch_sale.id AS sale_allocation_id', 'sale.id AS sale_id',
                'sale.sale_date', 'sale.invoice_no', 'customer.name AS customer_name',
                'product.name AS product_name', 'product.product_search_code AS product_code',
                'batch.batch_number', 'batch.expiry_date', 'batch_sale.quantity AS sold_quantity',
                'sale_line.price AS unit_selling_price',
                DB::raw('(batch_sale.quantity * sale_line.price) AS total_amount'),
                'cashier.name AS cashier_name',
            ]);
        $this->applyEventFilters($query, $filters, 'sale.sale_date');
        if (!empty($filters['invoice_no'])) {
            $query->where('sale.invoice_no', 'like', '%' . $filters['invoice_no'] . '%');
        }
        if (!empty($filters['customer_id'])) {
            $query->where('sale.customer_id', $filters['customer_id']);
        }
        return $this->sortQuery($query, $filters, [
            'sale_date' => 'sale.sale_date', 'invoice_no' => 'sale.invoice_no',
            'customer_name' => 'customer.name', 'product_name' => 'product.name',
            'product_code' => 'product.product_search_code', 'batch_number' => 'batch.batch_number',
            'expiry_date' => 'batch.expiry_date', 'sold_quantity' => 'batch_sale.quantity',
            'unit_selling_price' => 'sale_line.price', 'total_amount' => 'total_amount',
            'cashier_name' => 'cashier.name',
        ], 'sale.sale_date', 'desc');
    }

    private function adjustmentRows(array $filters): Builder
    {
        $query = DB::table('product_movements as movement')
            ->join('products as product', 'product.id', '=', 'movement.product_id')
            ->join('product_available_quantities as batch', 'batch.id', '=', 'movement.product_available_quantity_id')
            ->leftJoin('categories as category', 'category.id', '=', 'product.category_id')
            ->leftJoin('brands as brand', 'brand.id', '=', 'product.brand_id')
            ->leftJoin('goods_received_notes as grn', 'grn.id', '=', 'batch.goods_received_note_id')
            ->leftJoin('suppliers as batch_supplier', 'batch_supplier.id', '=', 'batch.supplier_id')
            ->leftJoin('suppliers as grn_supplier', 'grn_supplier.id', '=', 'grn.supplier_id')
            ->leftJoin('users as adjusted_user', 'adjusted_user.id', '=', 'movement.user_id')
            ->where('movement.movement_type', 7)
            ->select([
                'movement.id AS adjustment_id', 'movement.created_at AS adjustment_date',
                'product.name AS product_name', 'product.product_search_code AS product_code',
                'batch.batch_number', 'batch.expiry_date', 'movement.quantity_before AS previous_quantity',
                DB::raw('ABS(movement.quantity) AS adjustment_quantity'), 'movement.reason',
                'batch.purchase_price', DB::raw('(ABS(movement.quantity) * COALESCE(batch.purchase_price, 0)) AS adjustment_value'),
                DB::raw('COALESCE(batch_supplier.name, grn_supplier.name) AS supplier_name'),
                'adjusted_user.name AS adjusted_by', 'movement.notes',
            ]);
        $this->applyEventFilters($query, $filters, 'movement.created_at');
        if (!empty($filters['user_id'])) {
            $query->where('movement.user_id', $filters['user_id']);
        }
        if (!empty($filters['reason'])) {
            $query->where('movement.reason', 'like', '%' . $filters['reason'] . '%');
        }
        return $this->sortQuery($query, $filters, [
            'adjustment_date' => 'movement.created_at', 'product_name' => 'product.name',
            'product_code' => 'product.product_search_code', 'batch_number' => 'batch.batch_number',
            'expiry_date' => 'batch.expiry_date', 'reason' => 'movement.reason',
            'adjustment_quantity' => DB::raw('ABS(movement.quantity)'), 'adjustment_value' => 'adjustment_value',
            'supplier_name' => DB::raw('COALESCE(batch_supplier.name, grn_supplier.name)'),
            'adjusted_by' => 'adjusted_user.name',
        ], 'movement.created_at', 'desc');
    }

    private function supplierReturnRows(array $filters): Builder
    {
        $reference = DB::connection()->getDriverName() === 'sqlite'
            ? "'GRN-RETURN-' || supplier_return.id"
            : "CONCAT('GRN-RETURN-', supplier_return.id)";
        $query = DB::table('goods_received_note_return_products as return_line')
            ->join('goods_received_note_returns as supplier_return', 'supplier_return.id', '=', 'return_line.goods_received_note_return_id')
            ->join('goods_received_notes as grn', 'grn.id', '=', 'supplier_return.goods_received_note_id')
            ->join('goods_received_notes_products as grn_line', 'grn_line.id', '=', 'return_line.goods_received_note_product_id')
            ->join('product_available_quantities as batch', 'batch.id', '=', 'return_line.product_available_quantity_id')
            ->join('products as product', 'product.id', '=', 'return_line.product_id')
            ->leftJoin('categories as category', 'category.id', '=', 'product.category_id')
            ->leftJoin('brands as brand', 'brand.id', '=', 'product.brand_id')
            ->leftJoin('suppliers as supplier', 'supplier.id', '=', 'grn.supplier_id')
            ->leftJoin('users as processor', 'processor.id', '=', 'supplier_return.user_id')
            ->whereNotNull('return_line.product_available_quantity_id')
            ->select([
                'return_line.id AS return_id', 'supplier_return.date AS return_date',
                'supplier.name AS supplier_name', 'product.name AS product_name',
                'product.product_search_code AS product_code', 'batch.batch_number', 'batch.expiry_date',
                'return_line.quantity AS return_quantity', 'batch.purchase_price',
                DB::raw('(return_line.quantity * COALESCE(batch.purchase_price, 0)) AS return_value'),
                'return_line.remarks AS return_reason', DB::raw("$reference AS reference_number"),
                'processor.name AS processed_by',
            ]);
        $this->applyEventFilters($query, $filters, 'supplier_return.date', false);
        if (!empty($filters['supplier_id'])) {
            $query->where('grn.supplier_id', $filters['supplier_id']);
        }
        return $this->sortQuery($query, $filters, [
            'return_date' => 'supplier_return.date', 'supplier_name' => 'supplier.name',
            'product_name' => 'product.name', 'product_code' => 'product.product_search_code',
            'batch_number' => 'batch.batch_number', 'expiry_date' => 'batch.expiry_date',
            'return_quantity' => 'return_line.quantity', 'purchase_price' => 'batch.purchase_price',
            'return_value' => 'return_value', 'return_reason' => 'return_line.remarks',
            'processed_by' => 'processor.name',
        ], 'supplier_return.date', 'desc');
    }

    private function batchSourceQuery(array $filters): Builder
    {
        $query = DB::table('product_available_quantities as batch')
            ->join('products as product', 'product.id', '=', 'batch.product_id')
            ->leftJoin('categories as category', 'category.id', '=', 'product.category_id')
            ->leftJoin('brands as brand', 'brand.id', '=', 'product.brand_id')
            ->leftJoin('goods_received_notes as grn', 'grn.id', '=', 'batch.goods_received_note_id')
            ->leftJoin('suppliers as batch_supplier', 'batch_supplier.id', '=', 'batch.supplier_id')
            ->leftJoin('suppliers as grn_supplier', 'grn_supplier.id', '=', 'grn.supplier_id');
        $this->applyBatchFilters($query, $filters);
        return $query;
    }

    private function selectBatchRows(Builder $query): void
    {
        $today = $this->today();
        $plus7 = $this->day($today, 7);
        $plus30 = $this->day($today, 30);
        $plus60 = $this->day($today, 60);
        $plus90 = $this->day($today, 90);
        $query->select([
            'batch.id AS batch_id', 'product.id AS product_id',
            'product.name AS product_name', 'product.product_search_code AS product_code',
            'product.barcode', 'category.name AS category', 'brand.name AS brand',
            'batch.batch_number', 'batch.expiry_date',
            'batch.available_sales_quantity AS available_quantity', 'batch.purchase_price', 'batch.selling_price',
            'grn.goods_received_note_no AS grn_number',
            DB::raw('COALESCE(batch_supplier.name, grn_supplier.name) AS supplier_name'),
            DB::raw('(batch.available_sales_quantity * COALESCE(batch.purchase_price, 0)) AS stock_purchase_value'),
        ])->selectRaw(
            'CASE WHEN batch.expiry_date IS NULL THEN ? WHEN batch.expiry_date < ? THEN ? WHEN batch.expiry_date = ? THEN ? WHEN batch.expiry_date <= ? THEN ? WHEN batch.expiry_date <= ? THEN ? WHEN batch.expiry_date <= ? THEN ? WHEN batch.expiry_date <= ? THEN ? ELSE ? END AS expiry_status',
            [
                'Safe Stock', $today, 'Already Expired', $today, 'Expires Today',
                $plus7, 'Expiring Within 7 Days', $plus30, 'Expiring Within 30 Days',
                $plus60, 'Expiring Within 60 Days', $plus90, 'Expiring Within 90 Days', 'Safe Stock',
            ]
        );
    }

    private function applyBatchFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['product_id'])) {
            $query->where('product.id', $filters['product_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('product.category_id', $filters['category_id']);
        }
        if (!empty($filters['brand_id'])) {
            $query->where('product.brand_id', $filters['brand_id']);
        }
        if (!empty($filters['supplier_id'])) {
            $query->where(fn (Builder $q) => $q->where('batch.supplier_id', $filters['supplier_id'])
                ->orWhere('grn.supplier_id', $filters['supplier_id']));
        }
        if (!empty($filters['batch_number'])) {
            $query->where('batch.batch_number', 'like', '%' . $filters['batch_number'] . '%');
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('batch.expiry_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('batch.expiry_date', '<=', $filters['end_date']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(fn (Builder $q) => $q->where('product.name', 'like', $term)
                ->orWhere('product.product_search_code', 'like', $term)
                ->orWhere('product.barcode', 'like', $term)
                ->orWhere('batch.batch_number', 'like', $term)
                ->orWhere('category.name', 'like', $term)
                ->orWhere('brand.name', 'like', $term)
                ->orWhere('batch_supplier.name', 'like', $term)
                ->orWhere('grn_supplier.name', 'like', $term));
        }
        if (!empty($filters['status'])) {
            $this->applyStatusWhere($query, $filters['status']);
        }
    }

    private function applyEventFilters(Builder $query, array $filters, string $dateColumn, bool $includeSupplier = true): void
    {
        if (!empty($filters['start_date'])) {
            $query->whereDate($dateColumn, '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate($dateColumn, '<=', $filters['end_date']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('product.id', $filters['product_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('product.category_id', $filters['category_id']);
        }
        if (!empty($filters['brand_id'])) {
            $query->where('product.brand_id', $filters['brand_id']);
        }
        if ($includeSupplier && !empty($filters['supplier_id'])) {
            $query->where(fn (Builder $q) => $q->where('batch.supplier_id', $filters['supplier_id'])
                ->orWhere('grn.supplier_id', $filters['supplier_id']));
        }
        if (!empty($filters['batch_number'])) {
            $query->where('batch.batch_number', 'like', '%' . $filters['batch_number'] . '%');
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(fn (Builder $q) => $q->where('product.name', 'like', $term)
                ->orWhere('product.product_search_code', 'like', $term)
                ->orWhere('product.barcode', 'like', $term)
                ->orWhere('batch.batch_number', 'like', $term));
        }
    }

    private function applyNearRange(Builder $query, array $filters): Builder
    {
        $today = $this->today();
        $start = !empty($filters['start_date']) && $filters['start_date'] > $today
            ? $filters['start_date'] : $this->day($today, 1);
        $end = !empty($filters['end_date']) ? $filters['end_date'] : $this->day($today, max(1, (int) ($filters['days'] ?? 30)));
        return $query->whereDate('batch.expiry_date', '>=', $start)->whereDate('batch.expiry_date', '<=', $end);
    }

    private function applyStatusWhere(Builder $query, string $status, string $column = 'batch.expiry_date'): void
    {
        $today = $this->today();
        $plus7 = $this->day($today, 7);
        $plus30 = $this->day($today, 30);
        $plus60 = $this->day($today, 60);
        $plus90 = $this->day($today, 90);
        match ($status) {
            'expired' => $query->whereDate($column, '<', $today),
            'today' => $query->whereDate($column, '=', $today),
            '7' => $query->whereDate($column, '>', $today)->whereDate($column, '<=', $plus7),
            '30' => $query->whereDate($column, '>', $plus7)->whereDate($column, '<=', $plus30),
            '60' => $query->whereDate($column, '>', $plus30)->whereDate($column, '<=', $plus60),
            '90' => $query->whereDate($column, '>', $plus60)->whereDate($column, '<=', $plus90),
            'safe' => $query->where(fn (Builder $q) => $q->whereNull($column)->orWhereDate($column, '>', $plus90)),
            default => null,
        };
    }

    private function stockSummary(array $filters, string $kind): array
    {
        $query = $this->batchSourceQuery($filters)->where('batch.available_sales_quantity', '>', 0);
        if ($kind === 'expired') {
            $query->whereDate('batch.expiry_date', '<', $this->today());
        }
        $summary = $this->aggregateStockSummary($query);
        $summary['products'] = (int) (clone $query)->distinct()->count('product.id');
        return $summary;
    }

    private function aggregateStockSummary(Builder $query): array
    {
        return [
            'batches' => (int) (clone $query)->count('batch.id'),
            'quantity' => (float) (clone $query)->sum('batch.available_sales_quantity'),
            'value' => (float) (clone $query)->sum(DB::raw('batch.available_sales_quantity * COALESCE(batch.purchase_price, 0)')),
        ];
    }

    private function valueSummary(array $filters): array
    {
        $today = $this->today();
        $ranges = [
            'expired_value' => ['<', $today, null],
            'next_7_value' => ['>', $today, $this->day($today, 7)],
            'days_8_15_value' => ['>', $this->day($today, 7), $this->day($today, 15)],
            'days_16_30_value' => ['>', $this->day($today, 15), $this->day($today, 30)],
            'days_31_60_value' => ['>', $this->day($today, 30), $this->day($today, 60)],
            'days_61_90_value' => ['>', $this->day($today, 60), $this->day($today, 90)],
        ];
        $summary = [];
        foreach ($ranges as $key => [$operator, $start, $end]) {
            $query = $this->batchSourceQuery($filters)
                ->where('batch.available_sales_quantity', '>', 0)->whereNotNull('batch.expiry_date');
            $query->whereDate('batch.expiry_date', $operator, $start);
            if ($end !== null) {
                $query->whereDate('batch.expiry_date', '<=', $end);
            }
            $summary[$key] = (float) $query->sum(DB::raw('batch.available_sales_quantity * COALESCE(batch.purchase_price, 0)'));
        }
        foreach ([
            'next_15_value' => 15, 'next_30_value' => 30,
            'next_60_value' => 60, 'next_90_value' => 90,
        ] as $key => $days) {
            $summary[$key] = (float) $this->batchSourceQuery($filters)
                ->where('batch.available_sales_quantity', '>', 0)
                ->whereDate('batch.expiry_date', '>', $today)
                ->whereDate('batch.expiry_date', '<=', $this->day($today, $days))
                ->sum(DB::raw('batch.available_sales_quantity * COALESCE(batch.purchase_price, 0)'));
        }
        return $summary;
    }

    private function supplierSummary(array $filters): array
    {
        $rows = $this->batchSourceQuery($filters)
            ->where('batch.available_sales_quantity', '>', 0)->whereNotNull('batch.expiry_date')
            ->selectRaw("COALESCE(batch_supplier.name, grn_supplier.name, 'Unknown Supplier') AS supplier_name")
            ->selectRaw('SUM(CASE WHEN batch.expiry_date < ? THEN batch.available_sales_quantity ELSE 0 END) AS expired_quantity', [$this->today()])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date < ? THEN batch.available_sales_quantity * COALESCE(batch.purchase_price, 0) ELSE 0 END) AS expired_value', [$this->today()])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date > ? AND batch.expiry_date <= ? THEN batch.available_sales_quantity ELSE 0 END) AS near_quantity', [$this->today(), $this->day($this->today(), 90)])
            ->selectRaw('SUM(CASE WHEN batch.expiry_date > ? AND batch.expiry_date <= ? THEN batch.available_sales_quantity * COALESCE(batch.purchase_price, 0) ELSE 0 END) AS near_value', [$this->today(), $this->day($this->today(), 90)])
            ->groupByRaw("COALESCE(batch_supplier.id, grn_supplier.id), COALESCE(batch_supplier.name, grn_supplier.name, 'Unknown Supplier')")
            ->orderBy('supplier_name')->get();
        return [
            'expired_quantity' => (float) $rows->sum('expired_quantity'),
            'expired_value' => (float) $rows->sum('expired_value'),
            'near_quantity' => (float) $rows->sum('near_quantity'),
            'near_value' => (float) $rows->sum('near_value'),
            'by_supplier' => $rows->map(fn ($row) => [
                'supplier_name' => $row->supplier_name, 'expired_quantity' => (float) $row->expired_quantity,
                'expired_value' => (float) $row->expired_value, 'near_quantity' => (float) $row->near_quantity,
                'near_value' => (float) $row->near_value,
            ])->all(),
        ];
    }

    private function sortQuery(Builder $query, array $filters, array $sortable, string $default, string $defaultDirection = 'asc'): Builder
    {
        $sort = $filters['sort'] ?? '';
        $direction = $sort === '' ? $defaultDirection : ($filters['direction'] ?? 'asc');
        return $query->orderBy($sortable[$sort] ?? $default, $direction);
    }

    private function daysRemainingExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(julianday(batch.expiry_date) - julianday(?) AS INTEGER)'
            : 'DATEDIFF(batch.expiry_date, ?)';
    }

    private function today(): string
    {
        return CarbonImmutable::today()->toDateString();
    }

    private function day(string $date, int $days): string
    {
        return CarbonImmutable::parse($date)->addDays($days)->toDateString();
    }
}
