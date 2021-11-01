<?php


namespace PickBazar\Database\Repositories;

use PickBazar\Database\Models\Courier;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;

class CourierRepository extends BaseRepository
{

    /**
     * @var array
     */  
    protected $fieldSearchable = [
        'name'        => 'like',
    ];
    protected $dataArray = [
        'name',
        'phone',
        'image', 
        'status',

    ];

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
        }
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Courier::class;
    }
}
