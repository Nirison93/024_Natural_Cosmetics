<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceivedNoteReturnProduct extends Model
{
    protected $table = 'goods_received_note_return_products';

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    protected $fillable = [
        'goods_received_note_return_id',
        'product_id',
        'goods_received_note_product_id',
        'product_available_quantity_id',
        'quantity',
        'measurement_unit_id',
        'remarks',
    ];

    public function goodsReceivedNoteReturn()
    {
        return $this->belongsTo(GoodsReceivedNoteReturn::class, 'goods_received_note_return_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function measurement_unit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductAvailableQuantity::class, 'product_available_quantity_id');
    }

    public function goodsReceivedNoteProduct()
    {
        return $this->belongsTo(GoodsReceivedNoteProduct::class, 'goods_received_note_product_id');
    }
}
