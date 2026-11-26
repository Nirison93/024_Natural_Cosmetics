<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnProductBatch extends Model
{
    protected $fillable = [
        'sales_return_product_id',
        'product_available_quantity_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function salesReturnProduct()
    {
        return $this->belongsTo(SalesReturnProduct::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductAvailableQuantity::class, 'product_available_quantity_id');
    }
}
