<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Customer;

class CustomerController extends Controller
{
    public function GetCustomers(Request $request)
    {
        $search = $request->input('search', '');
        $customers = new Customer;
        if ($search != '') {
            $customers = $customers->where('name', 'LIKE', $search.'%');
        } 

        $customers = $customers->simplePaginate(20);
        
        return $customers;
    }

    public function GetCustomer(Request $request, $id)
    {
        return Customer::findOrfail($id);
    }

    public function AddCustomer(Request $request)
    {
        
        $request->validate([
            'name' => 'bail|required',
            'phone' => 'bail|required|regex:/[0-9]{9}/'
        ]);
        
        $customer = new Customer;
        $customer->name = $request->input('name');
        $customer->phone = $request->input('phone');
        if ($customer->save()) {
            http_response_code(200);
            return $customer;
        }
        else {
            http_response_code(201);
            return "";
        }
    }

    public function UpdateCustomer(Request $request, $id)
    {
        $request->validate([
            'name' => 'bail|required',
            'phone' => 'bail|required|regex:/[0-9]{9}/'
        ]);
        $customer = Customer::findOrfail($id);
        $customer->name = $request->input('name');
        $customer->phone = $request->input('phone');
        if ($customer->save()) {
            return $customer;
        }
        else {
            http_response_code(500);
            return "";
        }
    }
}
