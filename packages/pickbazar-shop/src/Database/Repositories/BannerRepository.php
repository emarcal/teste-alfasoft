<?php


namespace PickBazar\Database\Repositories;

use PickBazar\Database\Models\Banner;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;

class BannerRepository extends BaseRepository
{

    /**
     * @var array
     */
    protected $fieldSearchable = [
        'link'        => 'like',
    ];
    protected $dataArray = [
        'link',
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
        return Banner::class;
    }
}
