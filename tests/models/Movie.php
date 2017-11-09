<?php

use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Movie extends Eloquent
{
    protected static $unguarded = true;

    public function tags()
    {
        return $this->morphToMany('Tag', 'taggable');
    }
}
