<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Invoice;
use App\Customer;
use DB;
class InvoiceController extends Controller
{
    public function GetInvoices(Request $request)
    {
        $invoices = Invoice::with('customer')->simplePaginate();
        return $invoices;
    }

    public function GetInvoice(Request $request, $id)
    {
        return Invoice::findOrfail($id);
    }

    public function AddInvoice(Request $request)
    {
        $request->validate([
            'total_amount' => 'bail|required|integer|gte:payed_amount',
            'customer_id' => 'bail|required|integer',
            'payed_amount' => 'bail|required|integer',
        ]);

        $customer = Customer::findOrfail($request->input('customer_id'));
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

        if ($invoice->save()) {
            $customer->credit = - ($request->input('total_amount') - $request->input('payed_amount'));
            if (!$customer->save()){
                http_response_code(500);
                DB::rollBack();
                return "can't save credit";
            }
            DB::commit();
            http_response_code(200);
            return $invoice;
        }
        else {
            DB::rollBack();
            http_response_code(500);
            return;
        }
    }

    public function payFixedAmount(Request $request)
    {
        
        $request->validate([
            'customer_id' => 'bail|required|integer',
            'amount' => 'bail|required|integer',
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
                $invoice->remaining_amount = 0;
                $invoice->payed_amount = $invoice->total_amount;
                $invoice->is_fully_paid = true;
                $invoiceToUpdate[] = $invoice;
                $payedAmount = $payedAmount - $invoice->remaining_amount;
            }
            else if ($invoice->remaining_amount > $payedAmount) {
                $invoice->remaining_amount = $invoice->remaining_amount - $payedAmount;
                $invoice->payed_amount = $invoice->payed_amount + $payedAmount;
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
