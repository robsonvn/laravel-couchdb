<?php

namespace Robsonvn\CouchDB\Relations;

use Illuminate\Database\Eloquent\Relations\MorphToMany as EloquentMorphToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MorphToMany extends EloquentMorphToMany
{
    /**
     * Create a new morph to many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $relatedKey
     * @param  string  $relationName
     * @param  bool  $inverse
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $name, $table, $foreignKey, $relatedKey, $relationName = null, $inverse = false)
    {
        $this->parent = $parent;
        $this->fresh_query = clone $query;
        parent::__construct($query, $parent, $name, $table, $foreignKey, $relatedKey, $relationName, $inverse);
    }


    public function get($columns = ['*'])
    {

        //Retrive pivot entries
        $pivot = \DB::collection($this->table)->where([
          [$this->morphType,'=',$this->morphClass],
          [$this->foreignKey,'=',$this->parent->id]
        ])->get();

        $builder = $this->fresh_query->applyScopes();
        $builder->whereIn('_id', $pivot->pluck($this->relatedKey)->toArray());

        $models = $builder->getModels();

        $this->hydratePivotRelation($models);

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }
}
