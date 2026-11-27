<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAvailableQuantity;
use App\Models\ProductMovement;
use App\Models\SalesProduct;
use App\Models\SalesProductBatch;
use App\Services\BatchInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BatchInventoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createBatchInventorySchema();
    }

    public function test_sale_allocation_consumes_batches_in_fefo_order(): void
    {
        $product = $this->createProduct();
        $soon = $this->createBatch($product, 'SOON', today()->addDays(3)->toDateString(), 2);
        $later = $this->createBatch($product, 'LATER', today()->addDays(10)->toDateString(), 3);
        $saleLine = SalesProduct::create([
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        app(BatchInventoryService::class)->deductForSale($product, $saleLine, 3, 'TEST-SALE');

        $this->assertSame('0.00', $soon->fresh()->available_sales_quantity);
        $this->assertSame('2.00', $later->fresh()->available_sales_quantity);
        $this->assertSame(2, SalesProductBatch::where('sales_product_id', $saleLine->id)->count());
        $this->assertEquals(2, $product->fresh()->shop_quantity);
    }

    public function test_receipts_merge_the_same_expiry_batch_and_reject_mismatched_expiry(): void
    {
        $product = $this->createProduct();
        $service = app(BatchInventoryService::class);
        $expiry = today()->addDays(10)->toDateString();

        $batch = $service->receive($product, 'RECEIVED', $expiry, 3, 5, 8, null, 1);
        $service->receive($product, 'RECEIVED', $expiry, 2, 7, 9, null, 2);

        $batch->refresh();
        $this->assertSame('5.00', $batch->available_sales_quantity);
        $this->assertSame('5.00', $batch->received_sales_quantity);
        $this->assertSame('5.80', $batch->purchase_price);
        $this->assertSame('9.00', $batch->selling_price);
        $this->assertEquals(5, $product->fresh()->shop_quantity);

        try {
            $service->receive($product, 'RECEIVED', today()->addDays(11)->toDateString(), 1, 7, 9, null, 3);
            $this->fail('A batch cannot be received with a different expiry date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('products', $exception->errors());
        }

        $this->assertSame('5.00', $batch->fresh()->available_sales_quantity);
    }

    public function test_quarantine_adjustments_and_returns_are_audited_without_increasing_sellable_stock(): void
    {
        $product = $this->createProduct();
        $batch = $this->createBatch($product, 'BATCH-1', today()->addDays(10)->toDateString(), 8);
        $service = app(BatchInventoryService::class);

        $service->adjustBatch($product, $batch, 'quarantine', 2, 'damage', 'Seal broken', 'ADJ-1');
        $service->receiveReturn($product, $batch->fresh(), 1, false, 'RETURN-1', 42);

        $batch->refresh();
        $this->assertSame('6.00', $batch->available_sales_quantity);
        $this->assertSame('3.00', $batch->quarantined_quantity);
        $this->assertEquals(6, $product->fresh()->shop_quantity);

        $adjustment = ProductMovement::where('reference', 'ADJ-1')->firstOrFail();
        $this->assertSame('8.00', $adjustment->quantity_before);
        $this->assertSame('6.00', $adjustment->quantity_after);
        $this->assertSame('0.00', $adjustment->quarantined_quantity_before);
        $this->assertSame('2.00', $adjustment->quarantined_quantity_after);
    }

    public function test_expired_batches_cannot_be_added_to_sellable_stock(): void
    {
        $product = $this->createProduct();
        $batch = $this->createBatch($product, 'EXPIRED', today()->subDay()->toDateString(), 0);

        try {
            app(BatchInventoryService::class)->adjustBatch(
                $product,
                $batch,
                'increase',
                1,
                'count correction',
                null,
                'ADJ-EXPIRED'
            );
            $this->fail('Expired stock must not be added as sellable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation', $exception->errors());
        }

        $this->assertSame('0.00', $batch->fresh()->available_sales_quantity);
    }

    public function test_expired_stock_is_excluded_and_insufficient_sales_do_not_change_stock(): void
    {
        $product = $this->createProduct();
        $expired = $this->createBatch($product, 'EXPIRED', today()->subDay()->toDateString(), 5);
        $valid = $this->createBatch($product, 'VALID', today()->addDays(2)->toDateString(), 2);
        $saleLine = SalesProduct::create([
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
        $service = app(BatchInventoryService::class);

        $this->assertEquals(2, $service->sellableQuantity($product));

        try {
            $service->deductForSale($product, $saleLine, 3, 'TEST-INSUFFICIENT');
            $this->fail('A sale must not exceed unexpired batch stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame('5.00', $expired->fresh()->available_sales_quantity);
        $this->assertSame('2.00', $valid->fresh()->available_sales_quantity);
        $this->assertSame(0, SalesProductBatch::count());
    }

    private function createProduct(): Product
    {
        return Product::create([
            'name' => 'Batch product',
            'shop_quantity' => 0,
            'batch_tracking_enabled' => true,
            'expiry_date_required' => true,
        ]);
    }

    private function createBatch(Product $product, string $number, string $expiry, float $quantity): ProductAvailableQuantity
    {
        return ProductAvailableQuantity::create([
            'product_id' => $product->id,
            'batch_number' => $number,
            'expiry_date' => $expiry,
            'available_sales_quantity' => $quantity,
            'received_sales_quantity' => $quantity,
            'quarantined_quantity' => 0,
        ]);
    }

    private function createBatchInventorySchema(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('shop_quantity', 15, 2)->default(0);
            $table->boolean('batch_tracking_enabled')->default(false);
            $table->boolean('expiry_date_required')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('product_available_quantities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('available_sales_quantity', 15, 2)->default(0);
            $table->decimal('received_sales_quantity', 15, 2)->default(0);
            $table->decimal('quarantined_quantity', 15, 2)->default(0);
            $table->decimal('available_quantity', 15, 2)->default(0);
            $table->decimal('quantity_in_transfer_unit', 15, 2)->default(0);
            $table->decimal('quantity_in_sales_unit', 15, 2)->default(0);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('goods_received_note_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('sales_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sales_product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_product_id');
            $table->unsignedBigInteger('product_available_quantity_id');
            $table->decimal('quantity', 15, 2);
            $table->timestamps();
        });

        Schema::create('product_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_available_quantity_id')->nullable();
            $table->integer('movement_type');
            $table->decimal('quantity', 15, 2);
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('quantity_before', 15, 2)->nullable();
            $table->decimal('quantity_after', 15, 2)->nullable();
            $table->decimal('quarantined_quantity_before', 15, 2)->nullable();
            $table->decimal('quarantined_quantity_after', 15, 2)->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
