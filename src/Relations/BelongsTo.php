<?php

namespace Robsonvn\CouchDB\Relations;

use Illuminate\Database\Eloquent\Builder;

class BelongsTo extends \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    /**
     * {@inheritdoc}
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query->where($this->getOwnerKey(), '=', $this->parent->{$this->foreignKey});
            //Force index
            $this->query->orderBy($this->getOwnerKey());
        }
    }

    /**
     * {@inheritdoc}
     */
     public function addEagerConstraints(array $models)
     {
         // We'll grab the primary key name of the related models since it could be set to
         // a non-standard name and not "id". We will then construct the constraint for
         // our eagerly loading query so it returns the proper models from execution.
         $key = $this->getOwnerKey();
         $eager_keys = $this->getEagerModelKeys($models);

         if($eager_keys === [null]){
           $eager_keys = [];
         }

         $this->query->whereIn($key, $eager_keys);
     }

    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $query;
    }

    /**
     * Get the owner key with backwards compatible support.
     *
     * @return string
     */
    public function getOwnerKey()
    {
        return property_exists($this, 'ownerKey') ? $this->ownerKey : $this->otherKey;
    }
}
