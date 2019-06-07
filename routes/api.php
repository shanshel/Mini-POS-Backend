<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('Customers/GetCustomers', 'CustomerController@GetCustomers');
Route::get('Customers/{id}/GetCustomer', 'CustomerController@GetCustomer');
Route::post('Customers/AddCustomer', 'CustomerController@AddCustomer');
Route::post('Customers/{id}/UpdateCustomer', 'CustomerController@UpdateCustomer');

Route::get('Invoices/GetInvoices', 'InvoiceController@GetInvoices');
Route::get('Invoices/{id}/GetInvoice', 'InvoiceController@GetInvoice');
Route::post('Invoices/AddInvoice', 'InvoiceController@AddInvoice');
Route::post('Invoices/{id}/UpdateInvoice', 'InvoiceController@UpdateInvoice');
Route::post('Invoices/PayFixedAmount', 'InvoiceController@payFixedAmount');

Route::get('Items/GetItems', 'ItemController@GetItems');
Route::get('Items/{id}/GetItem', 'ItemController@GetItem');
Route::post('Items/AddItem', 'ItemController@AddItem');
Route::post('Items/{id}/UpdateItem', 'ItemController@UpdateItem');
