<?php

use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Book extends Eloquent
{
    protected $collection = 'books';
    protected static $unguarded = true;

    public function author()
    {
        return $this->belongsTo('User', 'author_id');
    }

    public function mysqlAuthor()
    {
        return $this->belongsTo('MysqlUser', 'author_id');
    }

    public function tags()
    {
        return $this->morphToMany('Tag', 'book','book_tag','book_id','tag_id');
    }
}
