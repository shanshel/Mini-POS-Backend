<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Invoice;
use App\Customer;
use DB;
use App\Item;
use App\InvoiceItem;

class InvoiceController extends Controller
{
    public function GetInvoices(Request $request)
    {
        $invoices = Invoice::with('customer')->simplePaginate();
        return $invoices;
    }

    public function GetInvoice(Request $request, $id)
    {
        return Invoice::with('customer')->with('invoiceitems')->findOrfail($id);
    }

    public function GetPreviousInvoice($id){
        return Invoice::with('customer')->with('invoiceitems')->where('id', '<', $id)->orderBy('id', 'DESC')->first();
    }

    public function GetLastInvoice(){
        return Invoice::with('customer')->with('invoiceitems')->orderBy('id', 'DESC')->first();
    }


    public function GetNextInvoice($id){
        return Invoice::with('customer')->with('invoiceitems')->where('id', '>', $id)->orderBy('id')->first();
    }

    public function AddInvoice(Request $request)
    {
        $request->validate([
            'total_amount' => 'bail|required|integer|gte:payed_amount',
            'customer_id' => 'bail|required|integer',
            'payed_amount' => 'bail|required|integer|min:0',
        ]);

        if ($request->has('items')) {
            
            $request->validate([
                'items.*.id' => 'bail|required|numeric',
                'items.*.count' => 'bail|nullable|numeric',
            ]);
        }

        //if customer is -1 invoice should fully paid
        if ($request->customer_id == -1) {
            if ($request->input('total_amount') != $request->input('payed_amount')) {
                http_response_code(500);
                return "normal customer should always pay full amount";
            }
        }

        if ($request->customer_id != -1) {
            $customer = Customer::findOrfail($request->input('customer_id'));
        }

        
        DB::beginTransaction();
        $invoice = new Invoice();
        $invoice->customer_id = $request->input('customer_id');
        $invoice->total_amount = $request->input('total_amount');
        $invoice->payed_amount = $request->input('payed_amount');
        $invoice->remaining_amount = $request->input('total_amount') - $request->input('payed_amount');
        if ($request->input('total_amount') == $request->input('payed_amount')) {
            $invoice->is_fully_paid = true;
        } else {
            $invoice->is_fully_paid = false;
        }
        if (!$invoice->save()){
            DB::rollBack();
            http_response_code(500);
            return "can't save invoice";
        }

        if ($request->customer_id != -1) {
            $customer->credit = (- ($request->input('total_amount') - $request->input('payed_amount'))) + $customer->credit;
            if (!$customer->save()){
                DB::rollBack();
                http_response_code(500);
                return "can't save customer cridet";
            }
        }

    
        
        if (!$request->has('items')) {
            DB::commit();
            http_response_code(200);
            return $invoice;
        }

        $arrayOfIds = [];
        
        foreach ($request->input('items') as $key => $item) {
            $arrayOfIds[] = $item['id'];
        }
        //get all items 
        $items = Item::whereIn('id', $arrayOfIds)->get();
        if (count($items) != count($arrayOfIds)) {
            DB::rollBack();
            http_response_code(500);
            return "missing item";
        }

        //check if total price match 
        $totalPrice = 0;
        foreach($items as $itemFromDb){
            foreach ($request->input('items') as $itemFromRequest) {
                if ($itemFromDb['id'] == $itemFromRequest['id']) {
                   $totalPrice += $itemFromRequest['count'] * $itemFromDb['sell_price'];
                }
            }
        }

        if ($totalPrice != $request->input('total_amount')) {
            DB::rollBack();
            http_response_code(500);
            return "wrong totalPrice Sent";
        }



        foreach($items as $itemFromDb){
            foreach ($request->input('items') as $itemFromRequest) {
                if ($itemFromDb['id'] == $itemFromRequest['id']) {
                    $invoiceItem = new InvoiceItem();
                    $invoiceItem->invoice_id = $invoice->id;
                    $invoiceItem->item_id = $itemFromDb['id'];
                    $invoiceItem->name = $itemFromDb['name'];
                    $invoiceItem->barcode = $itemFromDb['barcode'];
                    $invoiceItem->buy_price = $itemFromDb['buy_price'];
                    $invoiceItem->sell_price = $itemFromDb['sell_price'];
 
                    $invoiceItem->count = $itemFromRequest['count'];
                    $invoiceItem->sub_price = $itemFromRequest['count'] * $itemFromDb['sell_price'];
                    if (!$invoiceItem->save()){
                        DB::rollBack();
                        http_response_code(500);
                        return "can't add item to invoice items";
                    }
                }
            }
        }

        DB::commit();
        http_response_code(200);
        return $invoice;
    }

    public function payFixedAmount(Request $request)
    {
        
        $request->validate([
            'customer_id' => 'bail|required|integer',
            'amount' => 'bail|required|integer|min:1',
        ]);
       
        
        $customer = Customer::findOrfail($request->input('customer_id'));
        
        
        $payedAmount = $request->input('amount');
        $originalPayedAmount = $payedAmount;
        $invoices = Invoice::where('customer_id', $request->input('customer_id'))
        ->where('is_fully_paid', false)->get();
        
        
        $invoiceToUpdate = [];
        foreach ($invoices as $key => $invoice) {
            
            if ($invoice->remaining_amount == $payedAmount) {
                $invoice->remaining_amount = 0;
                $invoice->payed_amount = $invoice->total_amount;
                $invoice->is_fully_paid = true;
                $invoiceToUpdate[] = $invoice;
                $payedAmount = 0;
                break;
            }
            else if ($invoice->remaining_amount < $payedAmount) {
                $payedAmount = $payedAmount - $invoice->remaining_amount;
                $invoice->remaining_amount = 0;
                $invoice->payed_amount = $invoice->total_amount;
                $invoice->is_fully_paid = true;
                $invoiceToUpdate[] = $invoice;
            }
            else if ($invoice->remaining_amount > $payedAmount) {
                $invoice->remaining_amount = $invoice->remaining_amount - $payedAmount;
                $invoice->payed_amount = $invoice->payed_amount + $payedAmount;
                $invoice->is_fully_paid = false;
                $invoiceToUpdate[] = $invoice;
                $payedAmount = 0;
                break;
            }
        }

       
        if ($payedAmount == $request->input('amount')) {
            http_response_code(400);
            return ['nothing_to_pay'];
        }
        if ($payedAmount > ($customer->credit * -1)) {
            http_response_code(400);
            return ['customer_payed_more', $customer->credit];
        }
        if ($payedAmount != 0){
            http_response_code(400);
            return ['conflict'];
        }
      


        DB::beginTransaction();

        try {
            
            foreach ($invoiceToUpdate as $invoice) {
                $invoice->save();
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            throw $e;
        }

        try {
            $customer->credit = $customer->credit + $originalPayedAmount;
            $customer->save();
        } catch(\Exception $e)
        {
            DB::rollback();
            throw $e;
        }

      
        DB::commit();
       
        return;

    }

}
