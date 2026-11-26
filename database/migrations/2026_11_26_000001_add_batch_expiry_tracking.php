<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('products', 'batch_tracking_enabled', function (Blueprint $table) {
            $table->boolean('batch_tracking_enabled')->default(false);
        });
        $this->addColumnIfMissing('products', 'expiry_date_required', function (Blueprint $table) {
            $table->boolean('expiry_date_required')->default(false);
        });

        $this->addColumnIfMissing('goods_received_notes', 'inventory_reversed_at', function (Blueprint $table) {
            $table->timestamp('inventory_reversed_at')->nullable();
        });
        $this->addColumnIfMissing('goods_received_notes_products', 'batch_tracked', function (Blueprint $table) {
            $table->boolean('batch_tracked')->default(false);
        });

        $this->addColumnIfMissing('product_available_quantities', 'expiry_date', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('batch_number');
        });
        $this->addColumnIfMissing('product_available_quantities', 'purchase_price', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->nullable()->after('expiry_date');
        });
        $this->addColumnIfMissing('product_available_quantities', 'selling_price', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 2)->nullable()->after('purchase_price');
        });
        $this->addColumnIfMissing('product_available_quantities', 'available_sales_quantity', function (Blueprint $table) {
            $table->decimal('available_sales_quantity', 15, 2)->default(0)->after('available_quantity');
        });
        $this->addColumnIfMissing('product_available_quantities', 'received_sales_quantity', function (Blueprint $table) {
            $table->decimal('received_sales_quantity', 15, 2)->default(0)->after('available_sales_quantity');
        });
        $this->addColumnIfMissing('product_available_quantities', 'quarantined_quantity', function (Blueprint $table) {
            $table->decimal('quarantined_quantity', 15, 2)->default(0)->after('available_sales_quantity');
        });
        $this->addColumnIfMissing('product_available_quantities', 'supplier_id', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('goods_received_note_id');
        });
        $this->addIndexIfMissing('product_available_quantities', ['product_id', 'expiry_date']);
        $this->addForeignKeyIfMissing(
            'product_available_quantities',
            'supplier_id',
            'pav_supplier_fk',
            'suppliers',
            'set null'
        );

        $this->addColumnIfMissing('goods_received_notes_products', 'selling_price', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 2)->nullable()->after('purchase_price');
        });
        $this->addColumnIfMissing(
            'goods_received_notes_products',
            'product_available_quantity_id',
            function (Blueprint $table) {
                $table->foreignId('product_available_quantity_id')->nullable()->after('batch_number');
            }
        );
        Schema::table('goods_received_notes_products', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->default(0)->change();
        });
        $this->addForeignKeyIfMissing(
            'goods_received_notes_products',
            'product_available_quantity_id',
            'grnp_batch_fk',
            'product_available_quantities',
            'set null'
        );

        if (!Schema::hasTable('sales_product_batches')) {
            Schema::create('sales_product_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_product_id');
                $table->foreignId('product_available_quantity_id');
                $table->decimal('quantity', 15, 2);
                $table->timestamps();

                $table->unique(['sales_product_id', 'product_available_quantity_id'], 'sale_product_batch_unique');
                $table->index('product_available_quantity_id', 'spb_batch_index');
            });
        }
        $this->addForeignKeyIfMissing('sales_product_batches', 'sales_product_id', 'spb_sale_fk', 'sales_products', 'cascade');
        $this->addForeignKeyIfMissing(
            'sales_product_batches',
            'product_available_quantity_id',
            'spb_batch_fk',
            'product_available_quantities',
            'restrict'
        );

        $this->addColumnIfMissing('sales_return_products', 'sales_product_id', function (Blueprint $table) {
            $table->foreignId('sales_product_id')->nullable()->after('product_id');
        });
        $this->addColumnIfMissing('sales_return_products', 'return_condition', function (Blueprint $table) {
            $table->string('return_condition')->nullable()->after('quantity');
        });
        $this->addForeignKeyIfMissing(
            'sales_return_products',
            'sales_product_id',
            'srp_sales_product_fk',
            'sales_products',
            'set null'
        );

        if (!Schema::hasTable('sales_return_product_batches')) {
            Schema::create('sales_return_product_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_return_product_id');
                $table->foreignId('product_available_quantity_id');
                $table->decimal('quantity', 15, 2);
                $table->timestamps();

                $table->unique(
                    ['sales_return_product_id', 'product_available_quantity_id'],
                    'sales_return_product_batch_unique'
                );
                $table->index('product_available_quantity_id', 'srpb_batch_index');
            });
        }
        $this->addForeignKeyIfMissing(
            'sales_return_product_batches',
            'sales_return_product_id',
            'srpb_return_product_fk',
            'sales_return_products',
            'cascade'
        );
        $this->addForeignKeyIfMissing(
            'sales_return_product_batches',
            'product_available_quantity_id',
            'srpb_batch_fk',
            'product_available_quantities',
            'restrict'
        );

        $this->addColumnIfMissing('goods_received_note_return_products', 'goods_received_note_product_id', function (Blueprint $table) {
            $table->foreignId('goods_received_note_product_id')->nullable()->after('product_id');
        });
        $this->addColumnIfMissing(
            'goods_received_note_return_products',
            'product_available_quantity_id',
            function (Blueprint $table) {
                $table->foreignId('product_available_quantity_id')->nullable()->after('goods_received_note_product_id');
            }
        );
        Schema::table('goods_received_note_return_products', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->default(0)->change();
        });
        $this->addForeignKeyIfMissing(
            'goods_received_note_return_products',
            'goods_received_note_product_id',
            'grnrp_grn_product_fk',
            'goods_received_notes_products',
            'set null'
        );
        $this->addForeignKeyIfMissing(
            'goods_received_note_return_products',
            'product_available_quantity_id',
            'grnrp_batch_fk',
            'product_available_quantities',
            'set null'
        );

        $movementColumns = [
            'product_available_quantity_id' => function (Blueprint $table) {
                $table->foreignId('product_available_quantity_id')->nullable()->after('product_id');
            },
            'user_id' => function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('reference');
            },
            'source_type' => function (Blueprint $table) {
                $table->string('source_type')->nullable()->after('user_id');
            },
            'source_id' => function (Blueprint $table) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            },
            'quantity_before' => function (Blueprint $table) {
                $table->decimal('quantity_before', 15, 2)->nullable()->after('quantity');
            },
            'quantity_after' => function (Blueprint $table) {
                $table->decimal('quantity_after', 15, 2)->nullable()->after('quantity_before');
            },
            'quarantined_quantity_before' => function (Blueprint $table) {
                $table->decimal('quarantined_quantity_before', 15, 2)->nullable()->after('quantity_after');
            },
            'quarantined_quantity_after' => function (Blueprint $table) {
                $table->decimal('quarantined_quantity_after', 15, 2)->nullable()->after('quarantined_quantity_before');
            },
            'reason' => function (Blueprint $table) {
                $table->string('reason')->nullable()->after('source_id');
            },
            'notes' => function (Blueprint $table) {
                $table->text('notes')->nullable()->after('reason');
            },
        ];
        foreach ($movementColumns as $column => $definition) {
            $this->addColumnIfMissing('product_movements', $column, $definition);
        }
        $this->addIndexIfMissing('product_movements', ['source_type', 'source_id']);
        $this->addForeignKeyIfMissing(
            'product_movements',
            'product_available_quantity_id',
            'pm_batch_fk',
            'product_available_quantities',
            'set null'
        );
        $this->addForeignKeyIfMissing('product_movements', 'user_id', 'pm_user_fk', 'users', 'set null');
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('product_movements', 'product_available_quantity_id');
        $this->dropForeignKeyIfExists('product_movements', 'user_id');
        $this->dropIndexIfExists('product_movements', ['source_type', 'source_id']);
        $this->dropColumnsIfPresent('product_movements', [
            'product_available_quantity_id',
            'user_id',
            'source_type',
            'source_id',
            'quantity_before',
            'quantity_after',
            'quarantined_quantity_before',
            'quarantined_quantity_after',
            'reason',
            'notes',
        ]);

        Schema::dropIfExists('sales_return_product_batches');
        $this->dropForeignKeyIfExists('sales_return_products', 'sales_product_id');
        $this->dropColumnsIfPresent('sales_return_products', ['sales_product_id', 'return_condition']);
        Schema::dropIfExists('sales_product_batches');

        $this->dropForeignKeyIfExists('goods_received_note_return_products', 'goods_received_note_product_id');
        $this->dropForeignKeyIfExists('goods_received_note_return_products', 'product_available_quantity_id');
        if (Schema::hasColumn('goods_received_note_return_products', 'quantity')) {
            Schema::table('goods_received_note_return_products', function (Blueprint $table) {
                $table->integer('quantity')->default(0)->change();
            });
        }
        $this->dropColumnsIfPresent('goods_received_note_return_products', [
            'goods_received_note_product_id',
            'product_available_quantity_id',
        ]);

        $this->dropForeignKeyIfExists('goods_received_notes_products', 'product_available_quantity_id');
        $this->dropColumnsIfPresent('goods_received_notes_products', [
            'product_available_quantity_id',
            'selling_price',
        ]);
        if (Schema::hasColumn('goods_received_notes_products', 'quantity')) {
            Schema::table('goods_received_notes_products', function (Blueprint $table) {
                $table->integer('quantity')->default(0)->change();
            });
        }

        $this->dropIndexIfExists('product_available_quantities', ['product_id', 'expiry_date']);
        $this->dropForeignKeyIfExists('product_available_quantities', 'supplier_id');
        $this->dropColumnsIfPresent('product_available_quantities', [
            'expiry_date',
            'purchase_price',
            'selling_price',
            'available_sales_quantity',
            'received_sales_quantity',
            'quarantined_quantity',
            'supplier_id',
        ]);

        $this->dropColumnsIfPresent('products', ['batch_tracking_enabled', 'expiry_date_required']);
        $this->dropColumnsIfPresent('goods_received_notes', ['inventory_reversed_at']);
        $this->dropColumnsIfPresent('goods_received_notes_products', ['batch_tracked']);
    }

    private function addColumnIfMissing(string $tableName, string $columnName, \Closure $definition): void
    {
        if (!Schema::hasColumn($tableName, $columnName)) {
            Schema::table($tableName, function (Blueprint $table) use ($definition) {
                $definition($table);
            });
        }
    }

    private function addIndexIfMissing(string $tableName, array $columns): void
    {
        if (!Schema::hasIndex($tableName, $columns)) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->index($columns);
            });
        }
    }

    private function addForeignKeyIfMissing(
        string $tableName,
        string $columnName,
        string $constraintName,
        string $referencedTable,
        string $onDelete
    ): void {
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (in_array($columnName, $foreignKey['columns'], true)) {
                return;
            }
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName, $constraintName, $referencedTable, $onDelete) {
            $foreignKey = $table->foreign($columnName, $constraintName)
                ->references('id')
                ->on($referencedTable);

            if ($onDelete === 'cascade') {
                $foreignKey->cascadeOnDelete();
            } elseif ($onDelete === 'restrict') {
                $foreignKey->restrictOnDelete();
            } else {
                $foreignKey->nullOnDelete();
            }
        });
    }

    private function dropForeignKeyIfExists(string $tableName, string $columnName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (in_array($columnName, $foreignKey['columns'], true)) {
                Schema::table($tableName, function (Blueprint $table) use ($foreignKey) {
                    if (DB::connection()->getDriverName() === 'sqlite') {
                        $table->dropForeign($foreignKey['columns']);
                    } else {
                        $table->dropForeign($foreignKey['name']);
                    }
                });
                return;
            }
        }
    }

    private function dropIndexIfExists(string $tableName, array $columns): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        foreach (Schema::getIndexes($tableName) as $index) {
            if ($index['columns'] === $columns) {
                Schema::table($tableName, function (Blueprint $table) use ($index) {
                    $table->dropIndex($index['name']);
                });
                return;
            }
        }
    }

    private function dropColumnsIfPresent(string $tableName, array $columns): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        $existingColumns = array_values(array_filter($columns, function ($column) use ($tableName) {
            return Schema::hasColumn($tableName, $column);
        }));

        if ($existingColumns) {
            Schema::table($tableName, function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }
};
