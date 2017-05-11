<?php

namespace Robsonvn\CouchDB\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;

use Robsonvn\CouchDB\Query\Builder as QueryBuilder;

abstract class Model extends BaseModel
{
    public $incrementing = true;

    protected $primaryKey = '_id';
    protected $revisionAttributeName = '_rev';

    /**
     * @inheritdoc
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * @inheritdoc
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection, $connection->getPostProcessor());
    }

  /**
   * @inheritdoc
   */
    protected function insertAndSetId(BaseBuilder $query, $attributes)
    {
        list($id, $rev) = $query->insertGetId($attributes, $keyName = $this->getKeyName());
        $this->setAttribute($keyName, $id);
        $this->setAttribute($this->getRevisionAttributeName(), $rev);
    }

    public function getRevisionAttributeName()
    {
        return $this->revisionAttributeName;
    }

    public function getRevision()
    {
        $attr = $this->getRevisionAttributeName();
        return $this->$attr;
    }

    /**
     * @inheritdoc
     */
    protected function setKeysForSaveQuery(BaseBuilder $query)
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());
        $query->where($this->getRevisionAttributeName(), '=', $this->getRevision());
        return $query;
    }
}
