<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GoodsReceivedNoteProduct extends Model
{
    use HasFactory;

    protected $table = 'goods_received_notes_products';

    protected $fillable = [
        'goods_received_note_id',
        'product_id',
        'batch_number',
        'product_available_quantity_id',
        'batch_tracked',
        'quantity',
        'purchase_price', 
        'selling_price',
        'discount',
        'total',
    ];

    protected $casts = [
        'batch_tracked' => 'boolean',
        'quantity' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $appends = ['parent_batch_number'];

    public function grn()
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'goods_received_note_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductAvailableQuantity::class, 'product_available_quantity_id');
    }

    /**
     * Get the batch number from the parent GoodsReceivedNote if not set on product
     * Returns the product's batch_number first, then falls back to parent GRN batch_number
     */
    public function getParentBatchNumberAttribute()
    {
        // If this product has its own batch_number, return it
        if ($this->attributes['batch_number'] ?? null) {
            return $this->attributes['batch_number'];
        }
        
        // Otherwise, try to get it from the parent GRN
        if ($this->grn && $this->grn->batch_number) {
            return $this->grn->batch_number;
        }
        
        return null;
    }
}
