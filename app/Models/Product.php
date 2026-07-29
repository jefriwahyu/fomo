<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [ 'name', 'price', 'flash_sale_price', 'is_flash_sale', 'stock'];
}
