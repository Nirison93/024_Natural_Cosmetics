<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_available_quantities', 'wholesale_price')) {
            Schema::table('product_available_quantities', function (Blueprint $table) {
                $table->decimal('wholesale_price', 15, 2)->nullable()->after('selling_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_available_quantities', 'wholesale_price')) {
            Schema::table('product_available_quantities', function (Blueprint $table) {
                $table->dropColumn('wholesale_price');
            });
        }
    }
};
