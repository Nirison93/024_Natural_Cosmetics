<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAvailableQuantity;
use App\Models\ProductMovement;
use App\Models\SalesProduct;
use App\Models\SalesProductBatch;
use Illuminate\Validation\ValidationException;

class BatchInventoryService
{
    public function receiveManual(
        Product $product,
        string $batchNumber,
        ?string $expiryDate,
        float $quantity,
        float $purchasePrice,
        float $sellingPrice
    ): ProductAvailableQuantity {
        $existingBatch = ProductAvailableQuantity::query()
            ->where('product_id', $product->id)
            ->where('batch_number', $batchNumber)
            ->lockForUpdate()
            ->exists();

        if ($existingBatch) {
            throw ValidationException::withMessages([
                'batch_number' => 'This batch number already exists for the selected product. Please try again.',
            ]);
        }

        $batch = ProductAvailableQuantity::create([
            'product_id' => $product->id,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate,
            'available_quantity' => 0,
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'available_sales_quantity' => $quantity,
            'received_sales_quantity' => $quantity,
            'quarantined_quantity' => 0,
            'quantity_in_transfer_unit' => 0,
            'quantity_in_sales_unit' => 0,
        ]);

        $this->recordBatchMovement(
            $product,
            $batch,
            ProductMovement::TYPE_STOCK_ADJUSTMENT,
            $quantity,
            'STOCK-UPDATE-' . $batchNumber,
            ProductAvailableQuantity::class,
            $batch->id,
            0,
            $quantity,
            'manual_stock_update'
        );

        if ($product->batch_tracking_enabled) {
            $this->syncSellableQuantity($product);
        } else {
            $product->increment('shop_quantity', $quantity);
        }

        return $batch;
    }

    public function receive(
        Product $product,
        string $batchNumber,
        ?string $expiryDate,
        float $quantity,
        float $purchasePrice,
        float $sellingPrice,
        ?int $supplierId,
        int $grnId
    ): ProductAvailableQuantity {
        $batch = ProductAvailableQuantity::query()
            ->where('product_id', $product->id)
            ->where('batch_number', $batchNumber)
            ->lockForUpdate()
            ->first();

        $existingExpiry = $batch?->expiry_date?->format('Y-m-d');
        if ($batch && $existingExpiry !== $expiryDate) {
            throw ValidationException::withMessages([
                'products' => "Batch {$batchNumber} already exists with a different expiry date.",
            ]);
        }

        if (!$batch) {
            $batch = new ProductAvailableQuantity([
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'supplier_id' => $supplierId,
                'goods_received_note_id' => $grnId,
                'available_quantity' => 0,
                'available_sales_quantity' => 0,
                'received_sales_quantity' => 0,
                'quarantined_quantity' => 0,
                'quantity_in_transfer_unit' => 0,
                'quantity_in_sales_unit' => 0,
            ]);
        }

        $previousReceived = (float) $batch->received_sales_quantity;
        $newReceived = $previousReceived + $quantity;
        $batch->purchase_price = $newReceived > 0
            ? round((((float) $batch->purchase_price * $previousReceived) + ($purchasePrice * $quantity)) / $newReceived, 2)
            : $purchasePrice;
        $batch->selling_price = $sellingPrice;
        $batch->received_sales_quantity = $newReceived;
        $batch->available_sales_quantity = (float) $batch->available_sales_quantity + $quantity;
        $batch->save();

        $this->syncSellableQuantity($product);

        return $batch;
    }

    public function deductForSale(Product $product, SalesProduct $salesProduct, float $quantity, string $reference): void
    {
        $batches = $this->sellableBatchesQuery($product)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (float) $batches->sum('available_sales_quantity');
        if ($available + 0.00001 < $quantity) {
            throw ValidationException::withMessages([
                'items' => "Insufficient unexpired batch stock for {$product->name}. Available: " . number_format($available, 2),
            ]);
        }

        $remaining = $quantity;
        foreach ($batches as $batch) {
            if ($remaining <= 0.00001) {
                break;
            }

            $before = (float) $batch->available_sales_quantity;
            $deducted = min($before, $remaining);
            if ($deducted <= 0) {
                continue;
            }

            $batch->available_sales_quantity = round($before - $deducted, 2);
            $batch->save();
            SalesProductBatch::create([
                'sales_product_id' => $salesProduct->id,
                'product_available_quantity_id' => $batch->id,
                'quantity' => $deducted,
            ]);

            ProductMovement::create([
                'product_id' => $product->id,
                'product_available_quantity_id' => $batch->id,
                'movement_type' => ProductMovement::TYPE_SALE,
                'quantity' => -$deducted,
                'reference' => $reference,
                'user_id' => auth()->id(),
                'source_type' => SalesProduct::class,
                'source_id' => $salesProduct->id,
                'quantity_before' => $before,
                'quantity_after' => $before - $deducted,
            ]);

            $remaining = round($remaining - $deducted, 2);
        }

        $this->syncSellableQuantity($product);
    }

    public function sellableBatchesQuery(Product $product)
    {
        $query = ProductAvailableQuantity::query()
            ->where('product_id', $product->id)
            ->where('available_sales_quantity', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today());
            });

        if ($product->expiry_date_required) {
            $query->whereNotNull('expiry_date');
        }

        return $query;
    }

    public function sellableQuantity(Product $product): float
    {
        return (float) $this->sellableBatchesQuery($product)->sum('available_sales_quantity');
    }

    public function reverseReceipt(Product $product, ProductAvailableQuantity $batch, float $quantity, string $reference, int $grnId): void
    {
        $before = (float) $batch->available_sales_quantity;
        if ($before + 0.00001 < $quantity) {
            throw ValidationException::withMessages([
                'status' => "GRN cannot be cancelled because batch {$batch->batch_number} no longer has {$quantity} units available.",
            ]);
        }

        $batch->available_sales_quantity = round($before - $quantity, 2);
        $batch->received_sales_quantity = max(0, (float) $batch->received_sales_quantity - $quantity);
        $batch->save();
        $this->recordBatchMovement(
            $product,
            $batch,
            ProductMovement::TYPE_PURCHASE_RETURN,
            -$quantity,
            $reference,
            \App\Models\GoodsReceivedNote::class,
            $grnId,
            $before,
            (float) $batch->available_sales_quantity,
            'grn_cancellation'
        );
        $this->syncSellableQuantity($product);
    }

    public function restoreReceipt(Product $product, ProductAvailableQuantity $batch, float $quantity, string $reference, int $grnId): void
    {
        $before = (float) $batch->available_sales_quantity;
        $batch->available_sales_quantity = $before + $quantity;
        $batch->received_sales_quantity = (float) $batch->received_sales_quantity + $quantity;
        $batch->save();
        $this->recordBatchMovement(
            $product,
            $batch,
            ProductMovement::TYPE_PURCHASE,
            $quantity,
            $reference,
            \App\Models\GoodsReceivedNote::class,
            $grnId,
            $before,
            (float) $batch->available_sales_quantity,
            'grn_reactivation'
        );
        $this->syncSellableQuantity($product);
    }

    public function receiveReturn(
        Product $product,
        ProductAvailableQuantity $batch,
        float $quantity,
        bool $sellable,
        string $reference,
        int $returnProductId
    ): void {
        $before = (float) $batch->available_sales_quantity;
        $quarantinedBefore = (float) $batch->quarantined_quantity;
        $isEligible = !$batch->expiry_date || !$batch->expiry_date->isBefore(today());
        $isEligible = $isEligible && (!$product->expiry_date_required || $batch->expiry_date);
        $addToSellable = $sellable && $isEligible;

        if ($addToSellable) {
            $batch->available_sales_quantity = $before + $quantity;
        } else {
            $batch->quarantined_quantity = (float) $batch->quarantined_quantity + $quantity;
        }

        $batch->save();
        $this->recordBatchMovement(
            $product,
            $batch,
            ProductMovement::TYPE_SALE_RETURN,
            $quantity,
            $reference,
            \App\Models\SalesReturnProduct::class,
            $returnProductId,
            $before,
            (float) $batch->available_sales_quantity,
            $addToSellable ? 'sales_return' : 'sales_return_quarantined',
            $sellable && !$isEligible ? 'Returned product is expired or has no required expiry date.' : null,
            $quarantinedBefore,
            (float) $batch->quarantined_quantity
        );
        $this->syncSellableQuantity($product);
    }

    public function adjustBatch(
        Product $product,
        ProductAvailableQuantity $batch,
        string $operation,
        float $quantity,
        string $reason,
        ?string $notes,
        string $reference
    ): void {
        $before = (float) $batch->available_sales_quantity;
        $quarantinedBefore = (float) $batch->quarantined_quantity;
        $delta = 0.0;

        if ($operation === 'increase') {
            if (($batch->expiry_date && $batch->expiry_date->isBefore(today()))
                || ($product->expiry_date_required && !$batch->expiry_date)) {
                throw ValidationException::withMessages([
                    'operation' => 'Expired batches or batches without a required expiry date cannot be increased as sellable stock.',
                ]);
            }
            $delta = $quantity;
            $batch->available_sales_quantity = $before + $quantity;
        } elseif ($operation === 'decrease' || $operation === 'quarantine') {
            if ($before + 0.00001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Adjustment exceeds the batch sellable quantity.',
                ]);
            }
            $delta = -$quantity;
            $batch->available_sales_quantity = $before - $quantity;
            if ($operation === 'quarantine') {
                $batch->quarantined_quantity = $quarantinedBefore + $quantity;
            }
        } elseif ($operation === 'release_quarantine') {
            if ($quarantinedBefore + 0.00001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Release quantity exceeds the batch quarantined quantity.',
                ]);
            }
            if (($batch->expiry_date && $batch->expiry_date->isBefore(today()))
                || ($product->expiry_date_required && !$batch->expiry_date)) {
                throw ValidationException::withMessages([
                    'operation' => 'Expired batches or batches without a required expiry date cannot be released as sellable stock.',
                ]);
            }
            $delta = $quantity;
            $batch->available_sales_quantity = $before + $quantity;
            $batch->quarantined_quantity = $quarantinedBefore - $quantity;
        } else {
            throw ValidationException::withMessages([
                'operation' => 'Unsupported batch adjustment operation.',
            ]);
        }

        $batch->save();
        $this->recordBatchMovement(
            $product,
            $batch,
            ProductMovement::TYPE_STOCK_ADJUSTMENT,
            $delta,
            $reference,
            ProductAvailableQuantity::class,
            $batch->id,
            $before,
            (float) $batch->available_sales_quantity,
            $reason,
            $notes,
            $quarantinedBefore,
            (float) $batch->quarantined_quantity
        );
        $this->syncSellableQuantity($product);
    }

    public function deductForGrnReturn(
        Product $product,
        ProductAvailableQuantity $batch,
        float $quantity,
        string $reference,
        int $returnProductId
    ): void {
        $before = (float) $batch->available_sales_quantity;
        if ($before + 0.00001 < $quantity) {
            throw ValidationException::withMessages([
                'products' => "Insufficient batch stock to return {$quantity} units from batch {$batch->batch_number}.",
            ]);
        }

        $batch->available_sales_quantity = $before - $quantity;
        $batch->save();
        $this->recordBatchMovement(
            $product,
            $batch,
            ProductMovement::TYPE_GRN_RETURN,
            -$quantity,
            $reference,
            \App\Models\GoodsReceivedNoteReturnProduct::class,
            $returnProductId,
            $before,
            (float) $batch->available_sales_quantity,
            'grn_return'
        );
        $this->syncSellableQuantity($product);
    }

    public function restoreGrnReturn(
        Product $product,
        ProductAvailableQuantity $batch,
        float $quantity,
        string $reference,
        int $returnProductId
    ): void {
        $before = (float) $batch->available_sales_quantity;
        $batch->available_sales_quantity = $before + $quantity;
        $batch->save();
        $this->recordBatchMovement(
            $product,
            $batch,
            ProductMovement::TYPE_PURCHASE,
            $quantity,
            $reference,
            \App\Models\GoodsReceivedNoteReturnProduct::class,
            $returnProductId,
            $before,
            (float) $batch->available_sales_quantity,
            'grn_return_reversal'
        );
        $this->syncSellableQuantity($product);
    }

    public function syncSellableQuantity(Product $product): void
    {
        $product->shop_quantity = $this->sellableQuantity($product);
        $product->save();
    }

    public function recordBatchMovement(
        Product $product,
        ProductAvailableQuantity $batch,
        int $movementType,
        float $quantity,
        string $reference,
        string $sourceType,
        int $sourceId,
        float $before,
        float $after,
        ?string $reason = null,
        ?string $notes = null,
        ?float $quarantinedBefore = null,
        ?float $quarantinedAfter = null
    ): ProductMovement {
        return ProductMovement::create([
            'product_id' => $product->id,
            'product_available_quantity_id' => $batch->id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'reference' => $reference,
            'user_id' => auth()->id(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'quarantined_quantity_before' => $quarantinedBefore,
            'quarantined_quantity_after' => $quarantinedAfter,
            'reason' => $reason,
            'notes' => $notes,
        ]);
    }
}
