<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesReport extends Model
{
    protected $fillable = [
        'store_id',
        'report_date',
        'total_sales',
        'orders_count'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}