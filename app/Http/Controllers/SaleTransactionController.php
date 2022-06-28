<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleTransaction;
use App\Models\SaleVariation;
use App\Models\PurchaseVariation;
use App\Models\ProductModel;
use App\Models\Product;
use App\Models\User;
use DB;
use Exception;
use Carbon\Carbon;

class SaleTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(!auth()->user()->hasPermission("sale.index"))
        {
            return response(['message' => 'Permission Denied !'], 403);
        }

        $sale_transactions = SaleTransaction::join('users as u', 'u.id', '=', 'sale_transactions.finalized_by')
            ->join('users as u2', 'u2.id', '=', 'sale_transactions.customer_id')
            ->join('sale_variations as sv', 'sv.sale_transaction_id', '=', 'sale_transactions.id')
            ->select(

                'sale_transactions.id',
                DB::raw('DATE_FORMAT(sale_transactions.transaction_date, "%m/%d/%Y") as date'),
                'sale_transactions.invoice_no',
                DB::raw('CONCAT_WS(" ", u2.first_name, u2.last_name) as customer'),
                DB::raw('SUM(sv.quantity - sv.return_quantity) as total_items'),
                'sale_transactions.payment_status',
                DB::raw('sale_transactions.amount - IFNULL((select SUM(amount) from sale_return_transactions where sale_transaction_id = sale_transactions.id), 0) as total_payable'),
                DB::raw('CONCAT_WS(" ", u.first_name, DATE_FORMAT(sale_transactions.finalized_at, "%m/%d/%Y %H:%i:%s")) as finalized_by')

            )->groupBy('sale_transactions.id')
            ->orderBy('sale_transactions.transaction_date', 'desc')
            ->get();

        return response(['sale_transactions' => $sale_transactions], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if(!auth()->user()->hasPermission("sale.store"))
        {
            return response(['message' => 'Permission Denied !'], 403);
        }

        $request->validate([
            'sale_transaction.transaction_date' => 'required | date',
            'sale_transaction.customer_id' => 'required | numeric',

            'sale_variations.*.product_id' => 'required | numeric',
            'sale_variations.*.purchase_variation_id' => 'required | numeric',
            'sale_variations.*.quantity' => 'required | numeric',
            'sale_variations.*.selling_price' => 'required | numeric'
        ], [
            'sale_transaction.transaction_date.required' => 'Please specify the transaction date !',
            'sale_transaction.transaction_date.date' => 'Please specify a valid date !',

            'sale_transaction.customer_id.required' => 'Please select the customer !',
            'sale_transaction.customer_id.numeric' => 'Customer ID should be numeric !',

            'sale_variations.*.product_id.required' => 'Product ID is required !',
            'sale_variations.*.product_id.numeric' => 'Product ID should be numeric !',

            'sale_variations.*.purchase_variation_id.required' => 'Purchase Variation ID is required !',
            'sale_variations.*.purchase_variation_id.numeric' => 'Purchase Variation ID should be numeric !',

            'sale_variations.*.quantity.required' => 'Quantity is required !',
            'sale_variations.*.quantity.numeric' => 'Quantity should be numeric !',

            'sale_variations.*.selling_price.required' => 'Selling Price is required !',
            'sale_variations.*.selling_price.numeric' => 'Selling Price should be numeric !'
        ]);

        DB::beginTransaction();

        try {

            $sale_transaction = new SaleTransaction();

            $sale_transaction->status = "Final";

            $sale_transaction->payment_status = "Due";

            $sale_transaction->transaction_date = Carbon::parse($request->sale_transaction['transaction_date']);

            $sale_transaction->customer_id = $request->sale_transaction['customer_id'];

            $sale_transaction->finalized_by = auth()->user()->id;

            $sale_transaction->finalized_at = Carbon::now();

            $sale_transaction->save();


            $amount = 0;

            foreach($request->sale_variations as $entry)
            {
                $purchase_variation = PurchaseVariation::find($entry['purchase_variation_id']);

                if($entry['quantity'] < 1 || $entry['quantity'] > $purchase_variation->quantity_available)
                {
                    DB::rollBack();

                    return response(['message' => 'Sale quantity cannot be less than 1 or greater than available quantity !'], 409);
                }

                // ADJUSTING THE QUANTITY OF THE PURCHASE VARIATION RELATED TO THIS SALE VARIATION
                $purchase_variation->quantity_available -= $entry['quantity'];

                $purchase_variation->quantity_sold += $entry['quantity'];

                $purchase_variation->save();


                $sale_variation = new SaleVariation();

                $sale_variation->sale_transaction_id = $sale_transaction->id;

                $sale_variation->product_id = $entry['product_id'];

                $sale_variation->purchase_variation_id = $entry['purchase_variation_id'];

                $sale_variation->quantity = $entry['quantity'];

                $sale_variation->selling_price = $entry['selling_price'];

                $sale_variation->purchase_price = $purchase_variation->purchase_price;

                $sale_variation->save();


                $amount += ($entry['selling_price'] * $entry['quantity']);
            }


            $sale_transaction->invoice_no = "Sale#" . ($sale_transaction->id + 1000);

            $sale_transaction->amount += $amount;

            $sale_transaction->save();


            DB::commit();

            return response(['sale_transaction' => $sale_transaction], 201);

        } catch(Exception $ex) {

            DB::rollBack();

            return response([
                'message' => 'Internal Server Error !',
                'error' => $ex->getMessage()
            ], 500);

        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function imeiScan(Request $request)
    {
        if(!auth()->user()->hasPermission("sale.store"))
        {
            return response(['message' => 'Permission Denied !'], 403);
        }

        if(empty($request->imei))
        {
            return response(['message' => 'Nothing to scan !'], 404);
        }

        $purchase_variation = PurchaseVariation::join('products as p', 'p.id', '=', 'purchase_variations.product_id')
            ->select(

                'purchase_variations.id',
                'p.id as product_id',
                'p.name',
                'p.sku',
                'purchase_variations.serial as imei',
                'purchase_variations.quantity_available',
                'purchase_variations.purchase_price',

            )->where('purchase_variations.serial', '=', $request->imei)
            ->where('purchase_variations.quantity_available', '>', 0)
            ->first();
        
        if($purchase_variation == null)
        {
            return response(['message' => 'Not Available !'], 404);
        }

        return response(['purchase_variation' => $purchase_variation], 200);
    }

    public function imeiScanAlternative(Request $request)
    {
        if(!auth()->user()->hasPermission("sale.store"))
        {
            return response(['message' => 'Permission Denied !'], 403);
        }

        if(empty($request->purchase_variation_id))
        {
            return response(['message' => 'Purchase Variation not specified !'], 404);
        }

        $purchase_variation = PurchaseVariation::join('products as p', 'p.id', '=', 'purchase_variations.product_id')
            ->select(

                'purchase_variations.id',
                'p.id as product_id',
                'p.name',
                'p.sku',
                DB::raw('IF(purchase_variations.serial is null, "N/A", purchase_variations.serial) as imei'),
                'purchase_variations.quantity_available',
                'purchase_variations.purchase_price',

            )->where('purchase_variations.id', '=', $request->purchase_variation_id)
            ->where('purchase_variations.quantity_available', '>', 0)
            ->first();
        
        if($purchase_variation == null)
        {
            return response(['message' => 'Not Available !'], 404);
        }

        return response(['purchase_variation' => $purchase_variation], 200);
    }

    public function purchaseVariationsForSale(Request $request)
    {
        if(!auth()->user()->hasPermission("sale.store"))
        {
            return response(['message' => 'Permission Denied !'], 403);
        }

        if(empty($request->product_model_id) && empty($request->product_id))
        {
            return response(['purchase_variations' => []], 200);
        }

        $purchase_variations = PurchaseVariation::join('products as p', 'p.id', '=', 'purchase_variations.product_id')
            ->select(

                'purchase_variations.id',
                'p.name',
                'p.sku'

            )->where('purchase_variations.quantity_available', '>', 0);
            
        if(!empty($request->product_model_id))
        {
            $purchase_variations->where('p.product_model_id', '=', $request->product_model_id);
        }
        
        if(!empty($request->product_id))
        {
            $purchase_variations->where('p.id', '=', $request->product_id);
        }
        
        return response(['purchase_variations' => $purchase_variations->get()], 200);
    }

    public function getSaleVariations($sale_transaction_id)
    {
        if(!auth()->user()->hasPermission("sale.index"))
        {
            return response(['message' => 'Permission Denied !'], 403);
        }

        $sale_transaction = SaleTransaction::find($sale_transaction_id);

        if($sale_transaction == null)
        {
            return response(['message' => 'Sale Transaction not found !'], 404);
        }

        $sale_variations = SaleVariation::join('sale_transactions as st', 'st.id', '=', 'sale_variations.sale_transaction_id')
            ->join('products as p', 'p.id', '=', 'sale_variations.product_id')
            ->join('purchase_variations as pv', 'pv.id', '=', 'sale_variations.purchase_variation_id')
            ->select(

                'sale_variations.id',
                'st.invoice_no',
                'p.name',
                'p.sku',
                DB::raw('IF(pv.serial is null, "N/A", pv.serial) as imei'),
                DB::raw('sale_variations.quantity - sale_variations.return_quantity as quantity'),
                'sale_variations.selling_price',
                'sale_variations.purchase_price'

            )->where('sale_variations.sale_transaction_id', '=', $sale_transaction_id)
            ->where(DB::raw('sale_variations.quantity - sale_variations.return_quantity'), '>', 0)
            ->get();

        return response(['sale_variations' => $sale_variations], 200);
    }

    public function storeSaleView()
    {
        $customers = User::select(['id', 'first_name', 'last_name'])->where('type', '=', 2)->orderBy('first_name', 'asc')->get();

        $product_models = ProductModel::select(['id', 'name'])->orderBy('name', 'asc')->get();

        $products = Product::select(['id', 'name', 'sku'])->orderBy('sku', 'asc')->get();

        return response([
            'customers' => $customers,
            'product_models' => $product_models,
            'products' => $products
        ], 200);
    }


}
