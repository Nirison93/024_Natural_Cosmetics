<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAvailableQuantity extends Model
{
    use HasFactory;

    protected $table = 'product_available_quantities';

    protected $fillable = [
        'product_id',
        'batch_number',
        'available_quantity',
        'unit_id',
        'goods_received_note_id',
        'supplier_id',
        'expiry_date',
        'purchase_price',
        'selling_price',
        'wholesale_price',
        'available_sales_quantity',
        'received_sales_quantity',
        'quarantined_quantity',
        'quantity_in_transfer_unit',
        'quantity_in_sales_unit',
    ];

    protected $casts = [
        'available_quantity' => 'decimal:2',
        'available_sales_quantity' => 'decimal:2',
        'received_sales_quantity' => 'decimal:2',
        'quarantined_quantity' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'expiry_date' => 'date:Y-m-d',
        'quantity_in_transfer_unit' => 'decimal:2',
        'quantity_in_sales_unit' => 'decimal:2',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    public function goodsReceivedNote()
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'goods_received_note_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function salesAllocations()
    {
        return $this->hasMany(SalesProductBatch::class, 'product_available_quantity_id');
    }
}
