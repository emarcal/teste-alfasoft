<?php

namespace PickBazar\Database\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderPending extends Model
{
    use SoftDeletes; 

    protected $table = 'orders';

    public $guarded = [];

    protected $casts = [
        'shipping_address' => 'json',
        'billing_address'  => 'json',
    ];

    protected static function boot()
    {
        parent::boot();
        // Order by created_at desc
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('created_at', 'desc');
        });
    }


}
