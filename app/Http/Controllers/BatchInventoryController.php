<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAvailableQuantity;
use App\Models\ProductMovement;
use App\Services\BatchInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BatchInventoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $batches = ProductAvailableQuantity::query()
            ->with([
                'product:id,name,barcode,batch_tracking_enabled',
                'supplier:id,name',
            ])
            ->whereHas('product', function ($query) {
                $query->where('batch_tracking_enabled', true);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('batch_number', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('barcode', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $movementHistory = collect();
        foreach ($batches->getCollection() as $batch) {
            $movementHistory->put($batch->id, ProductMovement::query()
                ->with('user:id,name')
                ->where('product_available_quantity_id', $batch->id)
                ->latest('id')
                ->limit(5)
                ->get());
        }

        $batches->getCollection()->transform(function ($batch) use ($movementHistory) {
            $batch->setAttribute(
                'movement_history',
                $movementHistory->get($batch->id, collect())
            );
            return $batch;
        });

        return Inertia::render('Inventory/Batches/Index', [
            'batches' => $batches,
            'filters' => ['search' => $search],
        ]);
    }

    public function adjust(Request $request, ProductAvailableQuantity $batch, BatchInventoryService $batchInventory)
    {
        $validated = $request->validate([
            'operation' => 'required|in:increase,decrease,quarantine,release_quarantine',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($batch, $validated, $batchInventory) {
            $product = Product::query()
                ->whereKey($batch->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedBatch = ProductAvailableQuantity::query()
                ->whereKey($batch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$product->batch_tracking_enabled) {
                abort(404);
            }

            $batchInventory->adjustBatch(
                $product,
                $lockedBatch,
                $validated['operation'],
                (float) $validated['quantity'],
                $validated['reason'],
                $validated['notes'] ?? null,
                'ADJ-' . now()->format('YmdHis') . '-' . $lockedBatch->id
            );
        });

        return back()->with('success', 'Batch stock adjustment recorded.');
    }
}
