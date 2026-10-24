<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supply extends Model
{
    protected $guarded = ['id'];

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
