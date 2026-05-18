<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = ['name', 'city'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'store_products')
            ->withPivot('quantity', 'version')
            ->withTimestamps();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function salesReports()
    {
        return $this->hasMany(DailySalesReport::class);
    }
}
