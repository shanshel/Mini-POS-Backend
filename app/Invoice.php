<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function invoiceitems()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id');
    }
}
