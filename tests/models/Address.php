<?php

use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Address extends Eloquent
{
    protected static $unguarded = true;

    public function addresses()
    {
        return $this->embedsMany('Address');
    }

    public function country()
    {
        return $this->belongsTo('Country');
    }
}
