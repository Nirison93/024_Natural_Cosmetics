<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && Schema::hasTable('product_available_quantities')) {
            $hasQuantity = Schema::hasColumn('products', 'quantity');
            $hasExpiry = Schema::hasColumn('products', 'expire_date');

            if ($hasQuantity || $hasExpiry) {
                $columns = ['id'];
                if ($hasQuantity) {
                    $columns[] = 'quantity';
                }
                if ($hasExpiry) {
                    $columns[] = 'expire_date';
                }

                DB::table('products')->select($columns)->orderBy('id')->chunkById(500, function ($products) use (
                    $hasQuantity,
                    $hasExpiry
                ) {
                    foreach ($products as $product) {
                        $quantity = $hasQuantity ? (float) $product->quantity : 0;
                        $expiryDate = $hasExpiry ? $product->expire_date : null;

                        $batch = DB::table('product_available_quantities')
                            ->where('product_id', $product->id)
                            ->where('batch_number', 'OPENING')
                            ->first();

                        if ($quantity <= 0 && $expiryDate === null && !$batch) {
                            continue;
                        }

                        if ($batch) {
                            $received = (float) ($batch->received_sales_quantity ?? 0);
                            $available = (float) ($batch->available_sales_quantity ?? 0);
                            $quarantined = (float) ($batch->quarantined_quantity ?? 0);
                            $consumed = max(0, $received - $available - $quarantined);
                            $requiredQuantity = $consumed + $quarantined;
                            $newReceived = max($quantity, $requiredQuantity);

                            $updates = [];
                            if ($hasQuantity && Schema::hasColumn('product_available_quantities', 'received_sales_quantity')) {
                                $updates['received_sales_quantity'] = $newReceived;
                            }
                            if ($hasQuantity && Schema::hasColumn('product_available_quantities', 'available_sales_quantity')) {
                                $updates['available_sales_quantity'] = max(0, $newReceived - $requiredQuantity);
                            }
                            if ($hasExpiry && Schema::hasColumn('product_available_quantities', 'expiry_date')) {
                                $updates['expiry_date'] = $expiryDate;
                            }
                            if (Schema::hasColumn('product_available_quantities', 'updated_at')) {
                                $updates['updated_at'] = now();
                            }

                            if ($updates) {
                                DB::table('product_available_quantities')->where('id', $batch->id)->update($updates);
                            }

                            continue;
                        }

                        $batch = [
                            'product_id' => $product->id,
                            'batch_number' => 'OPENING',
                        ];
                        if (Schema::hasColumn('product_available_quantities', 'expiry_date')) {
                            $batch['expiry_date'] = $expiryDate;
                        }
                        if (Schema::hasColumn('product_available_quantities', 'available_sales_quantity')) {
                            $batch['available_sales_quantity'] = $quantity;
                        }
                        if (Schema::hasColumn('product_available_quantities', 'received_sales_quantity')) {
                            $batch['received_sales_quantity'] = $quantity;
                        }
                        if (Schema::hasColumn('product_available_quantities', 'quarantined_quantity')) {
                            $batch['quarantined_quantity'] = 0;
                        }
                        if (Schema::hasColumn('product_available_quantities', 'created_at')) {
                            $batch['created_at'] = now();
                        }
                        if (Schema::hasColumn('product_available_quantities', 'updated_at')) {
                            $batch['updated_at'] = now();
                        }

                        DB::table('product_available_quantities')->insert($batch);
                    }
                });
            }
        }

        $columnsToDrop = array_values(array_filter(['quantity', 'expire_date'], function ($column) {
            return Schema::hasColumn('products', $column);
        }));

        if ($columnsToDrop) {
            Schema::table('products', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        $addQuantity = !Schema::hasColumn('products', 'quantity');
        $addExpiry = !Schema::hasColumn('products', 'expire_date');

        if ($addQuantity || $addExpiry) {
            Schema::table('products', function (Blueprint $table) use ($addQuantity, $addExpiry) {
                if ($addQuantity) {
                    $table->decimal('quantity', 15, 2)->default(0);
                }
                if ($addExpiry) {
                    $table->date('expire_date')->nullable();
                }
            });
        }

        if (!Schema::hasTable('product_available_quantities')) {
            return;
        }

        DB::table('products')->orderBy('id')->chunkById(500, function ($products) {
            foreach ($products as $product) {
                $batch = DB::table('product_available_quantities')
                    ->where('product_id', $product->id)
                    ->where('batch_number', 'OPENING')
                    ->first();

                if (!$batch) {
                    continue;
                }

                $updates = [
                    'quantity' => $batch->received_sales_quantity ?? 0,
                    'expire_date' => $batch->expiry_date ?? null,
                ];
                DB::table('products')->where('id', $product->id)->update($updates);
            }
        });
    }
};
