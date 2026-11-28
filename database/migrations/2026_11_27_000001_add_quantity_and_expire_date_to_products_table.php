<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'expire_date')) {
            Schema::table('products', function (Blueprint $table) {
                $table->date('expire_date')->nullable();
            });
        }

        if (!Schema::hasColumn('products', 'quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('quantity', 15, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(['expire_date', 'quantity'], function ($column) {
            return Schema::hasColumn('products', $column);
        }));

        if ($columns) {
            Schema::table('products', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
