<?php

namespace Tests\Feature;

use App\Exports\BatchExpiryReportExport;
use App\Services\BatchExpiryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BatchExpiryReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->seedBatches();
    }

    public function test_dashboard_and_expiry_rows_use_remaining_stock_and_separate_expiry_today(): void
    {
        $reports = app(BatchExpiryReportService::class);
        $filters = $reports->filters([]);
        $summary = $reports->dashboardSummary($filters);

        $this->assertSame(9, $summary['active_batches']);
        $this->assertSame(1, $summary['expired_batches']);
        $this->assertSame(1, $summary['expiring_today_batches']);
        $this->assertSame(3, $summary['expiring_30_days_batches']);
        $this->assertSame(5, $summary['expiring_90_days_batches']);
        $this->assertSame(2.0, $summary['expired_quantity']);
        $this->assertSame(24.0, $summary['near_quantity']);
        $this->assertSame(20.0, $summary['expired_value']);
        $this->assertSame(1130.0, $summary['near_value']);

        $rows = $reports->query('expiry', $filters)->get()->keyBy('batch_number');
        $this->assertSame('Expires Today', $rows['TODAY']->expiry_status);
        $this->assertSame('Already Expired', $rows['EXPIRED']->expiry_status);
        $this->assertSame('Safe Stock', $rows['SAFE']->expiry_status);
        $this->assertFalse($rows->has('NO-EXPIRY'));
    }

    public function test_near_expiry_product_summary_and_non_overlapping_purchase_value_periods(): void
    {
        $reports = app(BatchExpiryReportService::class);
        $near = $reports->summary('near', $reports->filters(['days' => 15]));
        $this->assertSame(2, $near['batches']);
        $this->assertSame(9.0, $near['quantity']);
        $this->assertSame(320.0, $near['value']);

        $filters = $reports->filters([]);
        $product = $reports->query('products', $filters)->first();
        $this->assertSame(10, (int) $product->total_batches);
        $this->assertSame('EXPIRED', $product->earliest_expiring_batch);
        $this->assertSame('SAFE', $product->latest_expiring_batch);
        $filtered = $reports->query('products', $reports->filters(['batch_number' => 'NEXT-15']))->first();
        $this->assertSame('NEXT-15', $filtered->earliest_expiring_batch);
        $this->assertSame('NEXT-15', $filtered->latest_expiring_batch);

        $value = $reports->summary('value', $filters);
        $this->assertSame(20.0, $value['expired_value']);
        $this->assertSame(120.0, $value['next_7_value']);
        $this->assertSame(200.0, $value['days_8_15_value']);
        $this->assertSame(90.0, $value['days_16_30_value']);
        $this->assertSame(300.0, $value['days_31_60_value']);
        $this->assertSame(420.0, $value['days_61_90_value']);
        $this->assertSame(320.0, $value['next_15_value']);
        $this->assertSame(410.0, $value['next_30_value']);
        $this->assertSame(710.0, $value['next_60_value']);
        $this->assertSame(1130.0, $value['next_90_value']);
    }

    public function test_sales_adjustment_supplier_return_and_supplier_summary_use_batch_links(): void
    {
        $batchId = DB::table('product_available_quantities')->where('batch_number', 'NEXT-7')->value('id');
        $productId = DB::table('product_available_quantities')->where('id', $batchId)->value('product_id');
        $supplierId = DB::table('suppliers')->value('id');
        $grnId = DB::table('goods_received_notes')->value('id');
        $grnLineId = DB::table('goods_received_notes_products')->insertGetId([]);
        $userId = DB::table('users')->insertGetId(['name' => 'Cashier']);
        $customerId = DB::table('customers')->insertGetId(['name' => 'Customer']);

        $saleId = DB::table('sales')->insertGetId([
            'invoice_no' => 'INV-TEST-1', 'customer_id' => $customerId,
            'user_id' => $userId, 'sale_date' => today()->toDateString(),
        ]);
        $saleLineId = DB::table('sales_products')->insertGetId([
            'sale_id' => $saleId, 'product_id' => $productId, 'quantity' => 1.5, 'price' => 100,
        ]);
        DB::table('sales_product_batches')->insert([
            'sales_product_id' => $saleLineId, 'product_available_quantity_id' => $batchId, 'quantity' => 1.5,
        ]);
        $saleReturnId = DB::table('sales_return')->insertGetId(['sale_id' => $saleId, 'status' => 1]);
        $returnLineId = DB::table('sales_return_products')->insertGetId([
            'sales_return_id' => $saleReturnId, 'product_id' => $productId,
        ]);
        DB::table('sales_return_product_batches')->insert([
            'sales_return_product_id' => $returnLineId, 'product_available_quantity_id' => $batchId, 'quantity' => 0.5,
        ]);
        $supplierReturnId = DB::table('goods_received_note_returns')->insertGetId([
            'goods_received_note_id' => $grnId, 'date' => today()->toDateString(), 'user_id' => $userId,
        ]);
        DB::table('goods_received_note_return_products')->insert([
            'goods_received_note_return_id' => $supplierReturnId, 'product_id' => $productId,
            'goods_received_note_product_id' => $grnLineId,
            'product_available_quantity_id' => $batchId, 'quantity' => 1, 'remarks' => 'Damaged packaging',
        ]);
        DB::table('product_movements')->insert([
            'product_id' => $productId, 'product_available_quantity_id' => $batchId,
            'movement_type' => 7, 'quantity' => -1, 'quantity_before' => 5,
            'reference' => 'ADJ-TEST-1', 'user_id' => $userId, 'reason' => 'damaged',
            'notes' => 'Seal broken', 'created_at' => now(),
        ]);

        $reports = app(BatchExpiryReportService::class);
        $filters = $reports->filters([]);
        $batch = $reports->query('batches', $filters)->where('batch.id', $batchId)->first();
        $this->assertSame(1.5, (float) $batch->sold_quantity);
        $this->assertSame(0.5, (float) $batch->customer_returned_quantity);
        $this->assertSame(1.0, (float) $batch->supplier_returned_quantity);
        $this->assertSame(1.0, (float) $batch->adjusted_quantity);
        $this->assertSame(150.0, (float) $reports->query('sales', $filters)->first()->total_amount);
        $this->assertSame(30.0, (float) $reports->query('adjustments', $filters)->first()->adjustment_value);
        $return = $reports->query('returns', $filters)->first();
        $this->assertSame('GRN-RETURN-1', $return->reference_number);
        $this->assertSame(30.0, (float) $return->return_value);

        $supplierSummary = $reports->summary('supplier', $filters)['by_supplier'];
        $supplierA = collect($supplierSummary)->firstWhere('supplier_name', 'Supplier A');
        $this->assertSame(9.0, $supplierA['near_quantity']);
        $this->assertSame(1, count(array_filter($supplierSummary, fn ($item) => $item['supplier_name'] === 'Supplier A')));
    }

    public function test_excel_and_pdf_exports_render_filtered_report_rows(): void
    {
        $reports = app(BatchExpiryReportService::class);
        $filters = $reports->filters(['batch_number' => 'NEXT-7']);
        $query = $reports->query('expiry', $filters);
        $columns = $reports->columns('expiry');
        $metadata = [
            'title' => 'Expiry Date Report', 'generated_at' => now()->format('Y-m-d H:i:s'),
            'filters' => ['Batch Number' => 'NEXT-7'], 'summary' => ['records' => 1],
            'company' => null, 'currency' => 'Rs.',
        ];
        $xlsx = Excel::raw(new BatchExpiryReportExport($query, $columns, $metadata), \Maatwebsite\Excel\Excel::XLSX);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'batch-expiry-report-');
        try {
            file_put_contents($temporaryFile, $xlsx);
            $workbook = IOFactory::load($temporaryFile);
            $this->assertSame('Expiry Date Report', $workbook->getSheet(0)->getCell('A1')->getValue());
            $this->assertSame('Batch Number', $workbook->getSheet(1)->getCell('F1')->getValue());
            $this->assertSame('NEXT-7', $workbook->getSheet(1)->getCell('F2')->getValue());
            $this->assertSame('"Rs." #,##0.00', $workbook->getSheet(1)->getStyle('I2')->getNumberFormat()->getFormatCode());
            $workbook->disconnectWorksheets();
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        $pdf = Pdf::loadView('reports.batch-expiry-pdf', [
            ...$metadata, 'columns' => $columns,
            'rows' => $query->get()->map(fn ($row) => (array) $row), 'truncated' => false,
        ])->setPaper('a4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    private function createSchema(): void
    {
        Schema::create('categories', fn (Blueprint $table) => $table->id() && $table->string('name'));
        Schema::create('brands', fn (Blueprint $table) => $table->id() && $table->string('name'));
        Schema::create('suppliers', fn (Blueprint $table) => $table->id() && $table->string('name'));
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id(); $table->string('goods_received_note_no')->nullable(); $table->unsignedBigInteger('supplier_id')->nullable();
        });
        Schema::create('goods_received_notes_products', fn (Blueprint $table) => $table->id());
        Schema::create('users', fn (Blueprint $table) => $table->id() && $table->string('name'));
        Schema::create('customers', fn (Blueprint $table) => $table->id() && $table->string('name'));
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('product_search_code')->nullable();
            $table->string('barcode')->nullable(); $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
        });
        Schema::create('product_available_quantities', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('product_id'); $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable(); $table->decimal('available_sales_quantity', 15, 2)->default(0);
            $table->decimal('received_sales_quantity', 15, 2)->default(0); $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable(); $table->unsignedBigInteger('goods_received_note_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id(); $table->string('invoice_no'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id'); $table->date('sale_date');
        });
        Schema::create('sales_products', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('sale_id'); $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 2); $table->decimal('price', 15, 2);
        });
        Schema::create('sales_product_batches', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('sales_product_id'); $table->unsignedBigInteger('product_available_quantity_id');
            $table->decimal('quantity', 15, 2);
        });
        Schema::create('sales_return', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('sale_id')->nullable(); $table->integer('status')->default(0);
        });
        Schema::create('sales_return_products', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('sales_return_id'); $table->unsignedBigInteger('product_id');
        });
        Schema::create('sales_return_product_batches', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('sales_return_product_id');
            $table->unsignedBigInteger('product_available_quantity_id'); $table->decimal('quantity', 15, 2);
        });
        Schema::create('goods_received_note_returns', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('goods_received_note_id'); $table->date('date'); $table->unsignedBigInteger('user_id');
        });
        Schema::create('goods_received_note_return_products', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('goods_received_note_return_id'); $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('goods_received_note_product_id')->nullable();
            $table->unsignedBigInteger('product_available_quantity_id')->nullable(); $table->decimal('quantity', 15, 2);
            $table->text('remarks')->nullable();
        });
        Schema::create('product_movements', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('product_id'); $table->unsignedBigInteger('product_available_quantity_id')->nullable();
            $table->integer('movement_type'); $table->decimal('quantity', 15, 2); $table->decimal('quantity_before', 15, 2)->nullable();
            $table->string('reference')->nullable(); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reason')->nullable(); $table->text('notes')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }

    private function seedBatches(): void
    {
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Skin Care']);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Natural']);
        $supplierId = DB::table('suppliers')->insertGetId(['name' => 'Supplier A']);
        $grnId = DB::table('goods_received_notes')->insertGetId([
            'goods_received_note_no' => 'GRN-TEST-1', 'supplier_id' => $supplierId,
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Expiry test product', 'product_search_code' => 'EXP-001',
            'barcode' => '123456789', 'category_id' => $categoryId, 'brand_id' => $brandId,
        ]);
        $fixtures = [
            ['EXPIRED', today()->subDay()->toDateString(), 2, 10],
            ['TODAY', today()->toDateString(), 3, 20],
            ['NEXT-7', today()->addDays(5)->toDateString(), 4, 30],
            ['NEXT-15', today()->addDays(10)->toDateString(), 5, 40],
            ['NEXT-30', today()->addDays(20)->toDateString(), 2, 45],
            ['NEXT-60', today()->addDays(45)->toDateString(), 6, 50],
            ['NEXT-90', today()->addDays(75)->toDateString(), 7, 60],
            ['SAFE', today()->addDays(120)->toDateString(), 8, 70],
            ['NO-EXPIRY', null, 9, 80],
            ['ZERO-STOCK', today()->addDays(3)->toDateString(), 0, 100],
        ];
        foreach ($fixtures as [$number, $expiry, $quantity, $price]) {
            $batch = [
                'product_id' => $productId, 'batch_number' => $number, 'expiry_date' => $expiry,
                'available_sales_quantity' => $quantity, 'received_sales_quantity' => $quantity,
                'purchase_price' => $price, 'selling_price' => $price + 10,
                'supplier_id' => null, 'goods_received_note_id' => null,
            ];
            if ($number === 'NEXT-7') $batch['supplier_id'] = $supplierId;
            if ($number === 'NEXT-15') $batch['goods_received_note_id'] = $grnId;
            DB::table('product_available_quantities')->insert($batch);
        }
    }
}
