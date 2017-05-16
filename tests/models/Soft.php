<?php

use Robsonvn\CouchDB\Eloquent\Model as Eloquent;
use Robsonvn\CouchDB\Eloquent\SoftDeletes;

class Soft extends Eloquent
{
    use SoftDeletes;

    protected $collection = 'soft';
    protected static $unguarded = true;
    protected $dates = ['deleted_at'];
}
