<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    //

    // add fillable
    protected $fillable = [
        'name',
        'description',
        'cost_price'
    ];
    // add guaded
    protected $guarded = ['id'];
    // add hidden
    protected $hidden = ['created_at', 'updated_at'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
