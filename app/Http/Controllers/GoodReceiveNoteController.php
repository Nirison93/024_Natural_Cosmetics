<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteProduct;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\CompanyInformation;
use App\Models\ProductMovement;
use App\Models\MeasurementUnit;
use App\Models\ProductAvailableQuantity;
use App\Services\BatchInventoryService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * GoodReceiveNoteController
 * 
 * Manages the receipt of goods from suppliers (GRN - Goods Received Note).
 * Handles the complete goods receipt workflow including:
 * - Recording received goods with quantities and pricing
 * - Inventory adjustment (incrementing stock upon receipt)
 * - Product movement tracking for audit trail
 * - Batch quantity tracking
 * 
 * Business Logic:
 * - GRNs are linked directly to suppliers
 * - Stock is incremented when goods are received (by issued_quantity)
 * - GRN numbers are auto-generated with format: GRN-YYYYMMDD-XXXX
 * - All operations wrapped in database transactions for consistency
 * 
 * @package App\Http\Controllers
 */
class GoodReceiveNoteController extends Controller
{
    /**
     * Display a listing of all Goods Received Notes
     * 
     * Provides paginated list of GRNs with:
     * - Associated products with quantities and pricing
     * - Product measurement units
     * - Supplier information
     * - Available products for addition
     * - Auto-generated GRN number for new entries
     * 
     * @return \Inertia\Response
     */
    public function index()
    {
        // Eager-load related data to optimize performance
        $goodsReceivedNotes = GoodsReceivedNote::with(['goods_received_note_products.product', 'goods_received_note_products.product.measurement_unit', 'supplier'])
            ->paginate(10);
        
        // Load active suppliers for the dropdown
        $suppliers = Supplier::where('status', '!=', 0)->get();
        
        // Load active products for item selection
        $products = Product::where('status', '!=', 0)->get();
        
         $currencySymbol  = CompanyInformation::first();
        
        // Load measurement units for display purposes
        $measurementUnits = MeasurementUnit::orderBy('name')->get();

        // GRN Statistics for the summary box
        $grnStats = [
            'total' => GoodsReceivedNote::count(),
            'this_month' => GoodsReceivedNote::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count(),
            'total_value' => GoodsReceivedNote::sum('subtotal') ?? 0,
            'recent' => GoodsReceivedNote::select('id', 'goods_received_note_no', 'subtotal', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return Inertia::render('GoodsReceivedNotes/Index', [
            'goodsReceivedNotes' => $goodsReceivedNotes,
            'measurementUnits' => $measurementUnits,
            'suppliers' => $suppliers,
            'availableProducts' => $products,
            'grnNumber' => $this->generateGoodReceiveNoteNumber(),
            'currencySymbol' => $currencySymbol,
            'grnStats' => $grnStats,
        ]);
    }


    /**
     * Show the form for creating a new Goods Received Note
     * 
     * Provides the create form page with:
     * - Auto-generated GRN number
     * - List of active suppliers
     * - List of available products
     * - Measurement units for display
     * 
     * @return \Inertia\Response
     */
    public function create()
    {
        // Load active suppliers for the dropdown
        $suppliers = Supplier::where('status', '!=', 0)->orderBy('name')->get();
        
        // Load active products for item selection
        $availableProducts = Product::where('status', '!=', 0)->with(['salesUnit', 'purchaseUnit'])->get();
        
        // Load measurement units for display purposes
        $measurementUnits = MeasurementUnit::orderBy('name')->get();

        $currencySymbol = CompanyInformation::first();

        return Inertia::render('GoodsReceivedNotes/Create', [
            'suppliers' => $suppliers,
            'availableProducts' => $availableProducts,
            'grnNumber' => $this->generateGoodReceiveNoteNumber(),
            'measurementUnits' => $measurementUnits,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    /**
     * Store a newly created Goods Received Note
     * 
     * Process flow:
     * 1. Validates GRN data (unique GRN number, supplier, products, pricing)
     * 2. Creates GRN record with metadata
     * 3. For each received product:
     *    - Creates GRN product line item with pricing
     *    - Records product movement (Type 0: PURCHASE) for audit trail
     *    - Increments product shop_quantity (all stock managed via GRN)
     * 4. All operations are atomic (wrapped in transaction).
     * 
     * @param Request $request - Contains GRN data and product array
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, BatchInventoryService $batchInventory)
    {
        $validated = $request->validate([
            'goods_received_note_no'   => 'required|string|unique:goods_received_notes,goods_received_note_no',
            'supplier_id'   => 'required|exists:suppliers,id',
            'goods_received_note_date'      => 'required|date',
            'batch_number'  => 'nullable|string|max:255',
            'subtotal'      => 'nullable|numeric|min:0',
            'discount'      => 'nullable|numeric|min:0',
            'tax_total'     => 'nullable|numeric|min:0',
            'remarks'       => 'nullable|string',

            'products'                      => 'required|array|min:1',
            'products.*.product_id'         => 'required|exists:products,id',
            'products.*.batch_number'       => 'nullable|string|max:255',
            'products.*.expiry_date'        => 'nullable|date_format:Y-m-d',
            'products.*.issued_quantity'    => 'required|numeric|min:0.01',
            'products.*.purchase_price'     => 'required|numeric|min:0',
            'products.*.selling_price'      => 'nullable|numeric|min:0',
            'products.*.discount'           => 'nullable|numeric|min:0',
            'products.*.unit'               => 'nullable|string',
            'products.*.total'              => 'nullable|numeric',
        ]);

        $validationErrors = [];
        foreach ($validated['products'] as $index => $line) {
            $product = Product::find($line['product_id']);
            if (!$product || !$product->batch_tracking_enabled) {
                continue;
            }

            $batchNumber = trim((string) ($line['batch_number'] ?? ''));
            if ($batchNumber === '') {
                $batchNumber = trim((string) ($validated['batch_number'] ?? ''));
            }
            if ($batchNumber === '') {
                $validationErrors["products.{$index}.batch_number"] = 'A batch number is required for this product.';
            }

            if ($product->expiry_date_required && empty($line['expiry_date'])) {
                $validationErrors["products.{$index}.expiry_date"] = 'An expiry date is required for this product.';
            }

            if (!empty($line['expiry_date']) && Carbon::parse($line['expiry_date'])->isBefore(today())) {
                $validationErrors["products.{$index}.expiry_date"] = 'Expired batches cannot be received.';
            }
        }

        if ($validationErrors) {
            throw ValidationException::withMessages($validationErrors);
        }

        try {
            DB::transaction(function () use ($validated, $batchInventory) {
                $products = Product::whereIn(
                    'id',
                    collect($validated['products'])->pluck('product_id')->unique()
                )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $grn = GoodsReceivedNote::create([
                    'goods_received_note_no' => $validated['goods_received_note_no'],
                    'batch_number' => $validated['batch_number'] ?? null,
                    'supplier_id' => $validated['supplier_id'],
                    'user_id' => auth()->id(),
                    'goods_received_note_date' => $validated['goods_received_note_date'],
                    'subtotal' => $validated['subtotal'] ?? 0,
                    'discount' => $validated['discount'] ?? 0,
                    'tax_total' => $validated['tax_total'] ?? 0,
                    'remarks' => $validated['remarks'] ?? null,
                    'status' => 1,
                    'error' => null,
                ]);

                foreach ($validated['products'] as $line) {
                    $productModel = $products->get((int) $line['product_id']);
                    if (!$productModel) {
                        throw ValidationException::withMessages([
                            'products' => 'A selected product is no longer available.',
                        ]);
                    }

                    $quantity = (float) $line['issued_quantity'];
                    $purchasePrice = (float) $line['purchase_price'];
                    $sellingPrice = (float) ($line['selling_price'] ?? $productModel->retail_price ?? 0);
                    $discount = (float) ($line['discount'] ?? 0);
                    $lineTotal = ($quantity * $purchasePrice) - $discount;
                    $batchNumber = trim((string) ($line['batch_number'] ?? $validated['batch_number'] ?? ''));
                    $batch = null;

                    if ($productModel->batch_tracking_enabled) {
                        $batch = $batchInventory->receive(
                            $productModel,
                            $batchNumber,
                            $line['expiry_date'] ?? null,
                            $quantity,
                            $purchasePrice,
                            $sellingPrice,
                            (int) $validated['supplier_id'],
                            $grn->id
                        );
                    } elseif ($batchNumber !== '') {
                        $batch = ProductAvailableQuantity::where('product_id', $productModel->id)
                            ->where('batch_number', $batchNumber)
                            ->lockForUpdate()
                            ->first();

                        if ($batch) {
                            $batch->increment('available_quantity', $quantity);
                        } else {
                            $batch = ProductAvailableQuantity::create([
                                'product_id' => $productModel->id,
                                'batch_number' => $batchNumber,
                                'available_quantity' => $quantity,
                                'quantity_in_transfer_unit' => 0,
                                'quantity_in_sales_unit' => 0,
                                'unit_id' => $line['measurement_unit_id'] ?? null,
                                'goods_received_note_id' => $grn->id,
                            ]);
                        }
                    }

                    $grnProduct = GoodsReceivedNoteProduct::create([
                        'goods_received_note_id' => $grn->id,
                        'product_id' => $productModel->id,
                        'batch_number' => $batchNumber !== '' ? $batchNumber : null,
                        'product_available_quantity_id' => $batch?->id,
                        'batch_tracked' => (bool) $productModel->batch_tracking_enabled,
                        'quantity' => $quantity,
                        'purchase_price' => $purchasePrice,
                        'selling_price' => $sellingPrice,
                        'discount' => $discount,
                        'total' => $lineTotal,
                    ]);

                    if ($productModel->batch_tracking_enabled) {
                        $batchInventory->recordBatchMovement(
                            $productModel,
                            $batch,
                            ProductMovement::TYPE_PURCHASE,
                            $quantity,
                            $validated['goods_received_note_no'],
                            GoodsReceivedNote::class,
                            $grn->id,
                            (float) $batch->available_sales_quantity - $quantity,
                            (float) $batch->available_sales_quantity,
                            null,
                            'GRN product row ' . $grnProduct->id
                        );
                    } else {
                        ProductMovement::recordMovement(
                            $productModel->id,
                            ProductMovement::TYPE_PURCHASE,
                            $quantity,
                            $validated['goods_received_note_no']
                        );
                        $productModel->increment('shop_quantity', $quantity);
                    }
                }
            });

            return redirect()
                ->route('good-receive-notes.index')
                ->with('success', 'GRN created successfully!');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withErrors(['error' => 'Failed to create Good Receive Note. No stock changes were saved.'])
                ->withInput();
        }
    }

    // public function edit(Grn $grn)
    // {
    //     $grn->load('grnProducts');
    //     $suppliers = Supplier::where('status', '!=', 0)->get();
    //     $purchaseOrders = Por::where('status', 'approved')->get();
    //     $products = Product::where('status', '!=', 0)->get();

    //     return Inertia::render('Grn/Edit', [
    //         'grn' => $grn,
    //         'suppliers' => $suppliers,
    //         'purchaseOrders' => $purchaseOrders,
    //         'availableProducts' => $products,
    //     ]);
    // }

    // public function update(Request $request, GoodsReceivedNote $goodsReceivedNote)
    // {
    //     $validated = $request->validate([
    //         'goods_received_note_no'        => 'required|unique:goods_received_notes,goods_received_note_no,' . $goodsReceivedNote->id,
    //         'supplier_id'   => 'required|exists:suppliers,id',
    //         'goods_received_note_date'      => 'required|date',
    //         'purchase_order_request_id'        => 'nullable|exists:purchase_order_requests,id',
    //         'discount'      => 'nullable|numeric|min:0',
    //         'tax_total'     => 'nullable|numeric|min:0',
    //         'remarks'       => 'nullable|string',
    //         'status'        => 'required|in:0,1,2',
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         $goodsReceivedNote->update([
    //             'goods_received_note_no'        => $validated['goods_received_note_no'],
    //             'supplier_id'   => $validated['supplier_id'],
    //             'goods_received_note_date'      => $validated['goods_received_note_date'],
    //             'purchase_order_request_id'        => $validated['purchase_order_request_id'] ?? null,
    //             'discount'      => $validated['discount'] ?? 0,
    //             'tax_total'     => $validated['tax_total'] ?? 0,
    //             'remarks'       => $validated['remarks'] ?? null,
    //             'status'        => $validated['status'],
    //         ]);

    //         DB::commit();

    //         return redirect()->route('good-receive-notes.index')->with('success', 'Good Receive Note updated successfully');

    //     } catch (\Throwable $e) {
    //         DB::rollBack();

    //         return redirect()
    //             ->back()
    //             ->withErrors(['error' => 'Failed to update Good Receive Note: ' . $e->getMessage()])
    //             ->withInput();
    //     }
    // }

    /**
     * Update the status of a Goods Received Note
     * 
     * Status values:
     * - 0: Inactive/Deleted
     * - 1: Active/Standard
     * - 2: Completed/Archived
     * 
     * @param Request $request - Contains 'status' field (0, 1, or 2)
     * @param GoodsReceivedNote $goodReceiveNote - The GRN to update (route model binding)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStatus(Request $request, GoodsReceivedNote $goodReceiveNote, BatchInventoryService $batchInventory)
    {
        $validated = $request->validate([
            'status' => 'required|in:0,1,2',
        ]);

        $this->changeStatus($goodReceiveNote, (int) $validated['status'], $batchInventory);

        return redirect()->back()->with('success', 'Status updated successfully');
    }

    /**
     * Mark a Goods Received Note as inactive (soft delete)
     * 
     * Sets status to 0 to mark as deleted without removing data.
     * This preserves audit trail and historical records.
     * 
     * Note: This is a soft delete - the record remains in database
     * but is hidden from normal operations (status filter = 1).
     * 
     * @param GoodsReceivedNote $goodsReceivedNote - The GRN to delete (route model binding)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(GoodsReceivedNote $goodsReceivedNote, BatchInventoryService $batchInventory)
    {
        $this->changeStatus($goodsReceivedNote, 0, $batchInventory);

        return redirect()->back()->with('success', 'Good Receive Note marked as inactive successfully');
    }

    private function changeStatus(
        GoodsReceivedNote $goodReceiveNote,
        int $status,
        BatchInventoryService $batchInventory
    ): void {
        DB::transaction(function () use ($goodReceiveNote, $status, $batchInventory) {
            $grn = GoodsReceivedNote::query()
                ->whereKey($goodReceiveNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = (int) $grn->status;
            if ($currentStatus === $status) {
                return;
            }

            if ($currentStatus > 0 && $status === 0 && !$grn->inventory_reversed_at) {
                $lines = GoodsReceivedNoteProduct::query()
                    ->where('goods_received_note_id', $grn->id)
                    ->where('batch_tracked', true)
                    ->orderBy('id')
                    ->get();

                foreach ($lines as $line) {
                    $product = Product::query()->whereKey($line->product_id)->lockForUpdate()->firstOrFail();
                    $batch = ProductAvailableQuantity::query()
                        ->whereKey($line->product_available_quantity_id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$batch) {
                        throw ValidationException::withMessages([
                            'status' => "Cannot cancel GRN {$grn->goods_received_note_no}: batch inventory is missing.",
                        ]);
                    }

                    $batchInventory->reverseReceipt(
                        $product,
                        $batch,
                        (float) $line->quantity,
                        $grn->goods_received_note_no,
                        $grn->id
                    );
                }

                $grn->inventory_reversed_at = now();
            } elseif ($currentStatus === 0 && $status > 0 && $grn->inventory_reversed_at) {
                $lines = GoodsReceivedNoteProduct::query()
                    ->where('goods_received_note_id', $grn->id)
                    ->where('batch_tracked', true)
                    ->orderBy('id')
                    ->get();

                foreach ($lines as $line) {
                    $product = Product::query()->whereKey($line->product_id)->lockForUpdate()->firstOrFail();
                    $batch = ProductAvailableQuantity::query()
                        ->whereKey($line->product_available_quantity_id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$batch) {
                        throw ValidationException::withMessages([
                            'status' => "Cannot reactivate GRN {$grn->goods_received_note_no}: batch inventory is missing.",
                        ]);
                    }

                    $batchInventory->restoreReceipt(
                        $product,
                        $batch,
                        (float) $line->quantity,
                        $grn->goods_received_note_no,
                        $grn->id
                    );
                }

                $grn->inventory_reversed_at = null;
            }

            $grn->status = $status;
            $grn->save();
        });
    }

    /**
     * Generate a unique Goods Received Note number
     * 
     * Format: GRN-YYYYMMDD-XXXX
     * - GRN: Static prefix
     * - YYYYMMDD: Date of creation (YEAR-MONTH-DAY)
     * - XXXX: Sequential number for the day (0001, 0002, etc.)
     * 
     * Examples:
     * - GRN-20251215-0001 (First GRN created on Dec 15, 2025)
     * - GRN-20251215-0002 (Second GRN created same day)
     * - GRN-20251216-0001 (First GRN created on Dec 16, 2025)
     * 
     * @return string Unique GRN number
     */
    private function generateGoodReceiveNoteNumber()
    {
        // Set prefix and current date
        $prefix = 'GRN';
        $date = date('Ymd');

        // Find last GRN created today to determine sequence
        $lastGrn = GoodsReceivedNote::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        // Extract sequence number from last GRN, or start at 1 if none found
        $sequence = 1;
        if ($lastGrn && !empty($lastGrn->goods_received_note_no)) {
            // Parse existing GRN number: GRN-YYYYMMDD-XXXX
            $parts = explode('-', $lastGrn->goods_received_note_no);
            if (count($parts) >= 3) {
                // Extract sequence from last part and increment
                $lastSeq = (int) $parts[2];
                $sequence = $lastSeq + 1;
            }
        }

        // Return formatted GRN number with zero-padded sequence
        return $prefix . '-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
   
}
