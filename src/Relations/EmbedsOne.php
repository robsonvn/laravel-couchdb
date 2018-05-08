<?php

namespace Robsonvn\CouchDB\Relations;

use Illuminate\Database\Eloquent\Model;

class EmbedsOne extends EmbedsOneOrMany
{
    /**
     * {@inheritdoc}
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults()
    {
        $embedded = $this->getEmbedded();
        $model = $this->toModel($this->getEmbedded());

        if ($model) {
            $collection = $this->eagerLoadRelations([$model]);
            $model = current($collection);
        }

        return $model;
    }

    /**
     * Save a new model and attach it to the parent model.
     *
     * @param Model $model
     *
     * @return Model|bool
     */
    public function performInsert(Model $model)
    {
        // Generate a new key if needed.
        if ($model->getKeyName() == '_id' and !$model->getKey()) {
            $model->setAttribute('_id', uniqid());
        }

        // For deeply nested documents, let the parent handle the changes.
        if ($this->isNested()) {
            $this->associate($model);

            return $this->parent->save() ? $model : false;
        }

        $result = $this->getBaseQuery()->update([$this->localKey => $model->getAttributes()]);

        $result = current($result);
        //update parent rev
        $this->parent->setAttribute($this->parent->getRevisionAttributeName(), $result['rev']);
        $this->parent->syncOriginal();

        // Attach the model to its parent.
        if ($result) {
            $this->associate($model);
        }

        return $result ? $model : false;
    }

    /**
     * Save an existing model and attach it to the parent model.
     *
     * @param Model $model
     *
     * @return Model|bool
     */
    public function performUpdate(Model $model)
    {
        if ($this->isNested()) {
            $this->associate($model);

            return $this->parent->save();
        }

        $result = $this->getBaseQuery()->update([$this->localKey => $model->getAttributes()]);

        $result = current($result);
        //update parent rev
        $this->parent->setAttribute($this->parent->getRevisionAttributeName(), $result['rev']);
        $this->parent->syncOriginal();

        // Attach the model to its parent.
        if ($result) {
            $this->associate($model);
        }

        return $result ? $model : false;
    }

    /**
     * Delete an existing model and detach it from the parent model.
     *
     * @return int
     */
    public function performDelete()
    {
        // For deeply nested documents, let the parent handle the changes.
        if ($this->isNested()) {
            $this->dissociate();

            return $this->parent->save();
        }

        // Overwrite the local key with an empty array.
        $result = $this->getBaseQuery()->update([$this->localKey => null]);

        // Detach the model from its parent.
        if ($result) {
            $this->dissociate();
        }

        return $result;
    }

    /**
     * Attach the model to its parent.
     *
     * @param Model $model
     *
     * @return Model
     */
    public function associate(Model $model)
    {
        return $this->setEmbedded($model->getAttributes());
    }

    /**
     * Detach the model from its parent.
     *
     * @return Model
     */
    public function dissociate()
    {
        return $this->setEmbedded(null);
    }

    /**
     * Delete all embedded models.
     *
     * @return int
     */
    public function delete()
    {
        return $this->performDelete();
    }
}
