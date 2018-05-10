<?php

namespace Robsonvn\CouchDB\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Robsonvn\CouchDB\Eloquent\Model;

abstract class EmbedsOneOrMany extends Relation
{
    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * Create a new embeds many relationship instance.
     *
     * @param Builder $query
     * @param Model   $parent
     * @param Model   $related
     * @param string  $localKey
     * @param string  $foreignKey
     * @param string  $relation
     */
    public function __construct(Builder $query, Model $parent, Model $related, $localKey, $foreignKey, $relation)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $related;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->relation = $relation;

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->getQualifiedParentKeyName(), '=', $this->getParentKey());
            //Force use of index
            $this->query->orderBy($this->getQualifiedParentKeyName());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models)
    {
        // There is no eager loading constraint.
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, Collection $results = null, $relation)
    {
        /**
        * Basically $results will always be null since we're not querying them.
        * We're going to considere $results per model retriving them from the
        * very same model
        */
        foreach ($models as $model) {
            //store the eager load
            $eagerLoad = $this->query->getEagerLoads();
            $relationObject = $model->$relation();

            //repass the eager load values to the relation query
            $relationObject->query->setEagerLoads($eagerLoad);

            $results = $relationObject->getResults();
            $model->setParentRelation($this->parent, $this->relation);
            $model->setRelation($relation, $results);
        }

        return $models;
    }

    /**
     * This method is used to query the eager values from the database
     * and match them to all models.
     * Since we already have the data embedded, we won't query nothing
     *
     * {@inheritdoc}
     */
    public function getEager(){
        return null;
    }


    /**
     * Shorthand to get the results of the relationship.
     *
     * @return Collection
     */
    public function get($colums = array())
    {
        return $this->getResults($colums);
    }

    /**
     * Get the number of embedded models.
     *
     * @return int
     */
    public function count()
    {
        return count($this->getEmbedded());
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param Model $model
     *
     * @return Model|bool
     */
    public function save(Model $model)
    {
        $model->setParentRelation($this->parent, $this->relation);

        return $model->save() ? $model : false;
    }

    /**
     * Attach a collection of models to the parent instance.
     *
     * @param Collection|array $models
     *
     * @return Collection|array
     */
    public function saveMany($models)
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param array $attributes
     *
     * @return Model
     */
    public function create(array $attributes)
    {
        // Here we will set the raw attributes to avoid hitting the "fill" method so
        // that we do not have to worry about a mass accessor rules blocking sets
        // on the models. Otherwise, some of these attributes will not get set.
        $instance = $this->related->newInstance($attributes);

        $instance->setParentRelation($this->parent, $this->relation);

        $instance->save();

        return $instance;
    }

    /**
     * Create an array of new instances of the related model.
     *
     * @param array $records
     *
     * @return array
     */
    public function createMany(array $records)
    {
        $instances = [];

        foreach ($records as $record) {
            $instances[] = $this->create($record);
        }

        return $instances;
    }

    /**
     * Transform single ID, single Model or array of Models into an array of IDs.
     *
     * @param mixed $ids
     *
     * @return array
     */
    protected function getIdsArrayFrom($ids)
    {
        if ($ids instanceof \Illuminate\Support\Collection) {
            $ids = $ids->all();
        }

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as &$id) {
            if ($id instanceof Model) {
                $id = $id->getKey();
            }
        }

        return $ids;
    }

    /**
     * {@inheritdoc}
     */
    protected function getEmbedded()
    {
        // Get raw attributes to skip relations and accessors.
        $attributes = $this->parent->getAttributes();

        // Get embedded models form parent attributes.
        $embedded = isset($attributes[$this->localKey]) ? (array) $attributes[$this->localKey] : null;

        return $embedded;
    }

    /**
     * {@inheritdoc}
     */
    protected function setEmbedded($records)
    {
        // Assign models to parent attributes array.
        $attributes = $this->parent->getAttributes();
        $attributes[$this->localKey] = $records;

        // Set raw attributes to skip mutators.
        $this->parent->setRawAttributes($attributes);

        // Set the relation on the parent.
        return $this->parent->setRelation($this->relation, $records === null ? null : $this->getResults());
    }

    /**
     * Get the foreign key value for the relation.
     *
     * @param mixed $id
     *
     * @return mixed
     */
    protected function getForeignKeyValue($id)
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        return $id;
    }

    /**
     * Convert an array of records to a Collection.
     *
     * @param array $records
     *
     * @return Collection
     */
    protected function toCollection(array $records = [])
    {
        $models = [];

        foreach ($records as $attributes) {
            $models[] = $this->toModel($attributes);
        }

        if (count($models) > 0) {
            $models = $this->eagerLoadRelations($models);
        }

        return new Collection($models);
    }

    /**
     * Create a related model instanced.
     *
     * @param array $attributes
     *
     * @return Model
     */
    protected function toModel($attributes = [])
    {
        if (is_null($attributes)) {
            return;
        }

        $model = $this->related->newFromBuilder((array) $attributes);
        $model->setParentRelation($this->parent, $this->relation);
        $model->setRelation($this->foreignKey, $this->parent);
        $model->setConnection($this->parent->getConnectionName());

        // If you remove this, you will get segmentation faults!
        $model->setHidden(array_merge($model->getHidden(), [$this->foreignKey]));

        return $model;
    }

    /**
     * Get the relation instance of the parent.
     *
     * @return Relation
     */
    protected function getParentRelation()
    {
        return $this->parent->getParentRelation();
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        // Because we are sharing this relation instance to models, we need
        // to make sure we use separate query instances.
        return $this->query;;
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseQuery()
    {
        // Because we are sharing this relation instance to models, we need
        // to make sure we use separate query instances.
        return clone $this->query->getQuery();
    }

    /**
     * Check if this relation is nested in another relation.
     *
     * @return bool
     */
    protected function isNested()
    {
        return $this->getParentRelation() != null;
    }

    /**
     * Get the fully qualified local key name.
     *
     * @param string $glue
     *
     * @return string
     */
    protected function getPathHierarchy($glue = '.')
    {
        if ($parentRelation = $this->getParentRelation()) {
            return $parentRelation->getPathHierarchy($glue).$glue.$this->localKey;
        }

        return $this->localKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getQualifiedParentKeyName()
    {
        if ($parentRelation = $this->getParentRelation()) {
            return $parentRelation->getPathHierarchy().'.'.$this->parent->getKeyName();
        }

        return $this->parent->getKeyName();
    }

    /**
     * Get the primary key value of the parent.
     *
     * @return string
     */
    protected function getParentKey()
    {
        return $this->parent->getKey();
    }
}
