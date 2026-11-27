<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BatchInventoryMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('goods_received_notes_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('goods_received_note_id');
            $table->string('batch_number')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('product_available_quantities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('batch_number')->nullable();
            $table->decimal('available_quantity', 10, 2)->default(0);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('goods_received_note_id')->nullable();
            $table->decimal('quantity_in_transfer_unit', 12, 2)->default(0);
            $table->decimal('quantity_in_sales_unit', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('sales_products', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('sales_return_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
        });
        Schema::create('goods_received_note_return_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_received_note_return_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->timestamps();
        });
        Schema::create('product_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('movement_type')->default(0);
            $table->decimal('quantity', 10, 2);
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    public function test_batch_inventory_migration_applies_to_an_isolated_sqlite_schema(): void
    {
        $migration = require database_path('migrations/2026_11_26_000001_add_batch_expiry_tracking.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumns('products', ['batch_tracking_enabled', 'expiry_date_required']));
        $this->assertTrue(Schema::hasColumns('product_available_quantities', [
            'expiry_date',
            'available_sales_quantity',
            'received_sales_quantity',
            'quarantined_quantity',
            'supplier_id',
        ]));
        $this->assertTrue(Schema::hasColumns('sales_return_products', ['sales_product_id', 'return_condition']));
        $this->assertTrue(Schema::hasColumns('goods_received_note_return_products', [
            'goods_received_note_product_id',
            'product_available_quantity_id',
        ]));

        $migration->up();

        $migration->down();

        $this->assertFalse(Schema::hasColumn('products', 'batch_tracking_enabled'));
        $this->assertFalse(Schema::hasColumn('product_available_quantities', 'expiry_date'));
        $this->assertFalse(Schema::hasTable('sales_product_batches'));
    }
}
