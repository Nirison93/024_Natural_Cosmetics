<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesProductBatch extends Model
{
    protected $fillable = [
        'sales_product_id',
        'product_available_quantity_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function salesProduct()
    {
        return $this->belongsTo(SalesProduct::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductAvailableQuantity::class, 'product_available_quantity_id');
    }
}
