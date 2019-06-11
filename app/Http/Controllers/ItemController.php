<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Item;

class ItemController extends Controller
{
    public function GetItems(Request $request)
    {
        $search = $request->input('search', '');
        $items = new Item();
        if ($search != '') {
            if (ctype_digit($search)) {
                $items = $items->where('barcode', $search);
            } 
            else {
                $items = $items->where('name', 'LIKE', '%'.$search.'%');
            }
        } 

        $items = $items->simplePaginate(20);
        
        return $items;
    }

    public function GetItem(Request $request, $id)
    {
        return Item::findOrfail($id);
    }

    public function AddItem(Request $request)
    {
        $request->validate([
            'name' => 'bail|required',
            'barcode' => 'bail|nullable|numeric',
            'buy_price' => 'bail|required|numeric',
            'sell_price' => 'bail|required|numeric|gt:buy_price',
            'quantity' => 'bail|nullable|numeric',
        ]);
    
        $item = new Item;
        $item->name = $request->input('name');
        $item->buy_price = $request->input('buy_price');
        $item->sell_price = $request->input('sell_price');

        if ($request->has('quantity') && $request->input('quantity')) {
            $item->quantity = $request->input('quantity');
        }

        if ($request->has('barcode') && $request->input('barcode')) {
            $item->barcode = $request->input('barcode');
        } else {
            $item->barcode = round(microtime(true) * 1000);
        }

        if ($item->save()) {
            http_response_code(200);
            return $item;
        }
        else {
            http_response_code(500);
            return "";
        }
    }

    public function UpdateItem(Request $request, $id)
    {
        $request->validate([
            'name' => 'bail|required',
            'barcode' => 'bail|numeric',
            'buy_price' => 'bail|required|numeric',
            'sell_price' => 'bail|required|numeric|gt:buy_price',
            'quantity' => 'bail|numeric',
        ]);
        $item = Item::findOrfail($id);
       
        $item->name = $request->input('name');
        $item->buy_price = $request->input('buy_price');
        $item->sell_price = $request->input('sell_price');

        if ($request->has('quantity')) {
            $item->quantity = $request->input('quantity');
        }

        if ($request->has('barcode')) {
            $item->barcode = $request->input('barcode');
        } else {
            $item->barcode = round(microtime(true) * 1000);
        }

        if ($item->save()) {
            http_response_code(200);
            return $item;
        }
        else {
            http_response_code(500);
            return "";
        }
    }
}
