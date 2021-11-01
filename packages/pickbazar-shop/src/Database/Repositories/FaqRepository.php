<?php


namespace PickBazar\Database\Repositories;

use PickBazar\Database\Models\Faq;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;

class FaqRepository extends BaseRepository
{

    /**
     * @var array
     */
    protected $fieldSearchable = [
        'title'        => 'like',
    ];
    protected $dataArray = [
        'title',
        'description',
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
        return Faq::class;
    }
}
