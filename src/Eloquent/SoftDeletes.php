<?php

namespace Robsonvn\CouchDB\Eloquent;

trait SoftDeletes
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /**
     * {@inheritdoc}
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }

    /**
     * {@inheritdoc}
     */
    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newQueryWithoutScopes());

        $this->{$this->getDeletedAtColumn()} = $time = $this->freshTimestamp();

        $query->update($this->getAttributes());
    }

    /**
     * {@inheritdoc}
     */
    public function restore()
    {
        // If the restoring event does not return false, we will proceed with this
        // restore operation. Otherwise, we bail out so the developer will stop
        // the restore totally. We will clear the deleted timestamp and save.
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = null;

        // Once we have saved the model, we will fire the "restored" event so this
        // developer will do anything they need to after a restore operation is
        // totally finished. Then we will return the result of the save call.
        $this->exists = true;

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }
}
