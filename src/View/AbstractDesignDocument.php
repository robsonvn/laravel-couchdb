<?php
namespace Robsonvn\CouchDB\View;

use Doctrine\CouchDB\View\DesignDocument;

class AbstractDesignDocument implements DesignDocument
{
    public function __construct($collection){
      $this->collection = $collection;
    }
    public function getData()
    {
        return array(
            'language' => 'javascript',
            'views' => array(
                'all' => array(
                    'map' => 'function(doc){ if(\''.$this->collection.'\' == doc.type){ emit(doc._id, doc._rev); } }',
                    'reduce' => '_count'
                ),
            ),
        );
    }
}
