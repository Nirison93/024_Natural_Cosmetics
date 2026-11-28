<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
use App\Models\Product;
use App\Models\ProductAvailableQuantity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductOpeningStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('barcode')->nullable()->unique();
            $table->decimal('shop_quantity', 15, 2)->default(0);
            $table->boolean('return_product')->default(false);
            $table->boolean('batch_tracking_enabled')->default(false);
            $table->boolean('expiry_date_required')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->string('product_search_code')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('product_available_quantities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('batch_number')->nullable();
            $table->unsignedBigInteger('goods_received_note_id')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('available_quantity', 15, 2)->default(0);
            $table->decimal('available_sales_quantity', 15, 2)->default(0);
            $table->decimal('received_sales_quantity', 15, 2)->default(0);
            $table->decimal('quarantined_quantity', 15, 2)->default(0);
            $table->decimal('quantity_in_transfer_unit', 15, 2)->default(0);
            $table->decimal('quantity_in_sales_unit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_product_creation_saves_quantity_and_expiry_and_creates_generated_batch(): void
    {
        app(ProductController::class)->store($this->productRequest([
            'name' => 'Tracked product',
            'quantity' => 3.5,
            'batch_number' => 'BATCH-20261128-2889',
            'expire_date' => '2027-12-31',
            'batch_tracking_enabled' => true,
            'expiry_date_required' => true,
        ]));

        $product = Product::firstOrFail();
        $batch = ProductAvailableQuantity::firstOrFail();

        $this->assertSame('3.50', $product->quantity);
        $this->assertSame('2027-12-31', $product->expire_date);
        $this->assertSame('BATCH-20261128-2889', $batch->batch_number);
        $this->assertSame('2027-12-31', $batch->expiry_date->format('Y-m-d'));
        $this->assertSame('3.50', $batch->available_sales_quantity);
        $this->assertEquals(3.5, $product->shop_quantity);
        $this->assertFalse(Schema::hasColumn('products', 'quantity'));
        $this->assertFalse(Schema::hasColumn('products', 'expire_date'));
    }

    public function test_untracked_product_creation_sets_initial_shop_stock(): void
    {
        app(ProductController::class)->store($this->productRequest([
            'name' => 'Untracked product',
            'quantity' => 4,
            'expire_date' => null,
        ]));

        $product = Product::firstOrFail();
        $batch = ProductAvailableQuantity::firstOrFail();

        $this->assertSame('4.00', $product->quantity);
        $this->assertNull($product->expire_date);
        $this->assertMatchesRegularExpression('/^BATCH-\d{8}-\d{4}$/', $batch->batch_number);
        $this->assertSame('4.00', $batch->available_sales_quantity);
        $this->assertEquals(4, $product->shop_quantity);
        $this->assertSame(1, ProductAvailableQuantity::count());
    }

    public function test_product_creation_creates_a_batch_even_without_opening_stock(): void
    {
        app(ProductController::class)->store($this->productRequest([
            'name' => 'Empty stock product',
            'batch_number' => 'BATCH-20261128-1234',
            'quantity' => 0,
            'expire_date' => null,
        ]));

        $product = Product::firstOrFail();
        $batch = ProductAvailableQuantity::firstOrFail();

        $this->assertSame('BATCH-20261128-1234', $batch->batch_number);
        $this->assertSame('0.00', $product->quantity);
        $this->assertSame('0.00', $batch->available_sales_quantity);
        $this->assertSame('0.00', $batch->received_sales_quantity);
    }

    public function test_product_update_saves_quantity_and_expiry(): void
    {
        $product = Product::create([
            'name' => 'Existing product',
            'status' => 1,
        ]);
        ProductAvailableQuantity::create([
            'product_id' => $product->id,
            'batch_number' => 'OPENING',
            'expiry_date' => '2027-01-01',
            'available_sales_quantity' => 1,
            'received_sales_quantity' => 1,
            'quarantined_quantity' => 0,
        ]);

        app(ProductController::class)->update($this->productRequest([
            'name' => 'Updated product',
            'quantity' => 8.25,
            'expire_date' => '2028-06-30',
        ]), $product);

        $product->refresh();
        $batch = ProductAvailableQuantity::where('product_id', $product->id)->firstOrFail();

        $this->assertSame('Updated product', $product->name);
        $this->assertSame('8.25', $product->quantity);
        $this->assertSame('2028-06-30', $product->expire_date);
        $this->assertSame('8.25', $batch->available_sales_quantity);
        $this->assertSame('2028-06-30', $batch->expiry_date->format('Y-m-d'));
    }

    public function test_duplicate_creates_new_product_with_opening_stock_and_expiry(): void
    {
        $source = Product::create([
            'name' => 'Source product',
            'shop_quantity' => 20,
            'status' => 1,
        ]);

        app(ProductController::class)->duplicate($this->productRequest([
            'name' => 'Duplicated product',
            'quantity' => 2.5,
            'expire_date' => '2028-06-30',
            'batch_tracking_enabled' => true,
            'expiry_date_required' => true,
        ]), $source);

        $duplicate = Product::where('name', 'Duplicated product')->firstOrFail();
        $batch = ProductAvailableQuantity::where('product_id', $duplicate->id)->firstOrFail();

        $this->assertNotSame($source->id, $duplicate->id);
        $this->assertSame('2.50', $duplicate->quantity);
        $this->assertSame('2028-06-30', $duplicate->expire_date);
        $this->assertMatchesRegularExpression('/^BATCH-\d{8}-\d{4}$/', $batch->batch_number);
        $this->assertSame('2028-06-30', $batch->expiry_date->format('Y-m-d'));
        $this->assertSame('2.50', $batch->available_sales_quantity);
        $this->assertEquals(2.5, $duplicate->shop_quantity);
    }

    public function test_update_keeps_the_generated_opening_batch_number(): void
    {
        $product = Product::create([
            'name' => 'Existing generated-batch product',
            'status' => 1,
        ]);
        ProductAvailableQuantity::create([
            'product_id' => $product->id,
            'batch_number' => 'BATCH-20261128-5555',
            'goods_received_note_id' => null,
            'expiry_date' => '2027-01-01',
            'available_sales_quantity' => 1,
            'received_sales_quantity' => 1,
            'quarantined_quantity' => 0,
        ]);

        app(ProductController::class)->update($this->productRequest([
            'name' => 'Updated generated-batch product',
            'quantity' => 8.25,
            'expire_date' => '2028-06-30',
        ]), $product);

        $product->refresh();
        $batch = ProductAvailableQuantity::where('product_id', $product->id)->firstOrFail();

        $this->assertSame('BATCH-20261128-5555', $batch->batch_number);
        $this->assertSame(1, ProductAvailableQuantity::where('product_id', $product->id)->count());
        $this->assertSame('8.25', $product->quantity);
        $this->assertSame('2028-06-30', $batch->expiry_date->format('Y-m-d'));
    }

    public function test_migration_moves_legacy_opening_stock_and_expiry_before_dropping_product_columns(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->default(0);
            $table->date('expire_date')->nullable();
        });

        $productId = DB::table('products')->insertGetId([
            'name' => 'Legacy product',
            'quantity' => 4.5,
            'expire_date' => '2027-12-31',
            'status' => 1,
        ]);

        $migration = require database_path('migrations/2026_11_28_000002_move_product_opening_stock_to_batches.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('products', 'quantity'));
        $this->assertFalse(Schema::hasColumn('products', 'expire_date'));

        $batch = DB::table('product_available_quantities')
            ->where('product_id', $productId)
            ->where('batch_number', 'OPENING')
            ->first();

        $this->assertSame('4.50', number_format((float) $batch->available_sales_quantity, 2, '.', ''));
        $this->assertSame('4.50', number_format((float) $batch->received_sales_quantity, 2, '.', ''));
        $this->assertSame('2027-12-31', $batch->expiry_date);

        $migration->down();

        $this->assertTrue(Schema::hasColumns('products', ['quantity', 'expire_date']));
        $restoredProduct = DB::table('products')->where('id', $productId)->first();
        $this->assertSame('4.50', number_format((float) $restoredProduct->quantity, 2, '.', ''));
        $this->assertSame('2027-12-31', $restoredProduct->expire_date);
    }

    private function productRequest(array $attributes): Request
    {
        return Request::create('/products', 'POST', array_merge([
            'quantity' => 0,
            'expire_date' => null,
            'batch_tracking_enabled' => false,
            'expiry_date_required' => false,
            'status' => 1,
        ], $attributes));
    }
}
