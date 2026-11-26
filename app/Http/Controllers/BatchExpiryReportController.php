<?php

namespace App\Http\Controllers;

use App\Exports\BatchExpiryReportExport;
use App\Models\CompanyInformation;
use App\Services\BatchExpiryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BatchExpiryReportController extends Controller
{
    public function index(Request $request, BatchExpiryReportService $reports, string $report = 'dashboard')
    {
        abort_unless(in_array($report, BatchExpiryReportService::REPORTS, true), 404);
        $filters = $reports->filters($request->validate($this->filterRules()));
        $rows = $reports->query($report, $filters)->paginate($filters['per_page'])->withQueryString();

        return Inertia::render('Reports/BatchExpiry/Index', [
            'report' => $report,
            'reports' => $this->reportMenu(),
            'columns' => $reports->columns($report),
            'rows' => $rows,
            'summary' => $report === 'dashboard' ? [] : $reports->summary($report, $filters),
            'dashboard' => $report === 'dashboard' ? $reports->dashboardSummary($filters) : null,
            'charts' => $report === 'dashboard' ? $reports->dashboardCharts($filters) : null,
            'lookups' => $reports->lookups(),
            'filters' => $filters,
            'currency' => CompanyInformation::query()->value('currency') ?: 'Rs.',
            'company' => CompanyInformation::query()->first(['company_name', 'address', 'phone', 'email', 'website']),
        ]);
    }

    public function export(
        Request $request,
        BatchExpiryReportService $reports,
        string $report,
        string $format
    ) {
        abort_unless(in_array($report, BatchExpiryReportService::REPORTS, true), 404);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $filters = $reports->filters($request->validate($this->filterRules()));
        $columns = $reports->columns($report);
        $company = CompanyInformation::query()->first([
            'company_name', 'address', 'phone', 'email', 'website', 'currency',
        ]);
        $metadata = [
            'title' => $this->reportTitle($report),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'filters' => $this->selectedFilters($filters, $report),
            'summary' => $reports->summary($report, $filters),
            'company' => $company,
            'currency' => $company?->currency ?: 'Rs.',
        ];
        $query = $reports->query($report, $filters);
        $filename = 'batch-expiry-' . $report . '-' . now()->format('Ymd-His');

        if ($format === 'xlsx') {
            return Excel::download(new BatchExpiryReportExport($query, $columns, $metadata), $filename . '.xlsx');
        }

        $pdfRows = $query->limit(1000)->get()->map(fn ($row) => (array) $row);
        return Pdf::loadView('reports.batch-expiry-pdf', [
            ...$metadata,
            'columns' => $columns,
            'rows' => $pdfRows,
            'truncated' => $pdfRows->count() === 1000,
        ])->setPaper('a4', 'landscape')->download($filename . '.pdf');
    }

    private function filterRules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:expired,today,7,30,60,90,safe'],
            'search' => ['nullable', 'string', 'max:255'],
            'days' => ['nullable', 'integer', 'in:7,15,30,60,90'],
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ];
    }

    private function reportMenu(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Expiry Dashboard'],
            ['key' => 'expiry', 'label' => 'Expiry Date Report'],
            ['key' => 'expired', 'label' => 'Expired Stock'],
            ['key' => 'near', 'label' => 'Near Expiry'],
            ['key' => 'batches', 'label' => 'Batch Wise Stock'],
            ['key' => 'products', 'label' => 'Product Batch Summary'],
            ['key' => 'value', 'label' => 'Expiry Stock Value'],
            ['key' => 'supplier', 'label' => 'Supplier Wise Expiry'],
            ['key' => 'sales', 'label' => 'Batch Sales History'],
            ['key' => 'adjustments', 'label' => 'Stock Adjustments'],
            ['key' => 'returns', 'label' => 'Supplier Returns'],
        ];
    }

    private function selectedFilters(array $filters, string $report): array
    {
        $labels = [
            'start_date' => 'Date From', 'end_date' => 'Date To',
            'batch_number' => 'Batch Number', 'status' => 'Expiry Status',
            'search' => 'Search', 'invoice_no' => 'Invoice Number',
            'reason' => 'Adjustment Reason',
        ];
        if ($report === 'near') {
            $labels['days'] = 'Near Expiry Window (Days)';
        }
        $selected = [];
        foreach ($labels as $key => $label) {
            if (($filters[$key] ?? '') !== '') {
                $selected[$label] = $filters[$key];
            }
        }
        if (!empty($filters['status'])) {
            $selected['Expiry Status'] = [
                'expired' => 'Already Expired', 'today' => 'Expires Today',
                '7' => 'Expiring Within 7 Days', '30' => 'Expiring Within 30 Days',
                '60' => 'Expiring Within 60 Days', '90' => 'Expiring Within 90 Days',
                'safe' => 'Safe Stock',
            ][$filters['status']] ?? $filters['status'];
        }
        foreach ([
            'product_id' => ['Product', 'products'],
            'category_id' => ['Category', 'categories'],
            'brand_id' => ['Brand', 'brands'],
            'supplier_id' => ['Supplier', 'suppliers'],
            'customer_id' => ['Customer', 'customers'],
            'user_id' => ['User', 'users'],
        ] as $key => [$label, $table]) {
            if (!empty($filters[$key])) {
                $selected[$label] = DB::table($table)->where('id', $filters[$key])->value('name') ?? $filters[$key];
            }
        }
        return $selected;
    }

    private function reportTitle(string $report): string
    {
        return collect($this->reportMenu())->firstWhere('key', $report)['label'] ?? 'Batch & Expiry Report';
    }
}
