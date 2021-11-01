<?php

namespace PickBazar\Database\Models;

use Illuminate\Database\Eloquent\Model;


class Faq extends Model
{
    protected $table = 'faqs';

    public $guarded = [];

    protected $casts = [
       
    ];

    /**
     * @return BelongsTo
     */

}
