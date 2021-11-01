<?php

namespace PickBazar\Database\Models;

use Illuminate\Database\Eloquent\Model;


class Courier extends Model
{
    protected $table = 'couriers';

    public $guarded = [];

    protected $casts = [
       
    ];

    /**
     * @return BelongsTo
     */ 

}
