<?php

use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Company extends Eloquent
{
    protected static $unguarded = true;

    public function users()
    {
        return $this->hasMany('User');
    }
}
