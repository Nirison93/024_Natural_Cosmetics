<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturnProduct extends Model
{
    use HasFactory;

    protected $table = 'sales_return_products';

    protected $fillable = [
        'sales_return_id',
        'product_id',
        'sales_product_id',
        'quantity',
        'return_condition',
        'price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relationships
    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function salesProduct()
    {
        return $this->belongsTo(SalesProduct::class);
    }

    public function batchAllocations()
    {
        return $this->hasMany(SalesReturnProductBatch::class);
    }

    // Accessor for formatted price
    public function getFormattedPriceAttribute()
    {
        return number_format((float)$this->price, 2);
    }

    // Accessor for formatted total
    public function getFormattedTotalAttribute()
    {
        return number_format((float)$this->total, 2);
    }
}