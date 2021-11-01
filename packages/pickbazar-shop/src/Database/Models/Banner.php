<?php

namespace PickBazar\Database\Models;

use Illuminate\Database\Eloquent\Model;


class Banner extends Model
{
    protected $table = 'banners';

    public $guarded = [];

    protected $casts = [
       
    ];
    
    /**
     * @return BelongsTo
     */

}
