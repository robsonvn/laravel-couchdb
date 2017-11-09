<?php

use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Tag extends Eloquent
{
    protected static $unguarded = true;

    public function movies()
    {
        return $this->morphedByMany('Movie', 'taggable');
    }

    public function books()
    {
        return $this->morphedByMany('Book', 'book','book_tag','tag_id','book_id');
    }
}
