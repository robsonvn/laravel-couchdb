# Laravel CouchDB


[![Build Status](http://img.shields.io/travis/robsonvn/laravel-couchdb.svg)](https://travis-ci.org/robsonvn/laravel-couchdb) [![StyleCI](https://styleci.io/repos/90929490/shield?style=flat)](https://styleci.io/repos/90929490)
[![codecov.io](https://codecov.io/gh/robsonvn/laravel-couchdb/coverage.svg?branch=master)](https://codecov.io/gh/robsonvn/laravel-couchdb)

Inspired on [jenssegers/laravel-mongodb](https://github.com/jenssegers/laravel-mongodb)

Laravel CouchDB is an Eloquent model and Query builder with support for **CouchDB 2.0**, using the original Laravel API. This library extends the original Laravel classes, so it uses exactly the same methods.


## Good to know before using it

1. This library is under development, so use on your own.
2. CouchDB **IS NOT** Mongo DB, do not works as Mongo DB and do not has the same resources as Mongo DB, so **DO NOT** expect that this library does everthing that [jenssegers/laravel-mongodb](https://github.com/jenssegers/laravel-mongodb) library does.
3. CouchDB does not have the concept of collection as MongoDB, so we are using "collections" by adding an attribute (doc_collection) in every single document. Please, treat doc_collection as a reserved attribute. Use of collections is not optional.

* CouchDB has many limitations dealing with Mango Query that force us to process somethings in memory, what directly impacts on our library performance, please check out the [Couch Limitations](#couchdb-limitations) and the [Limitations](#limitations) sections for more details.


## Installation

Installation using composer:

```
composer require robsonvn/laravel-couchdb
```

And add the service provider in `config/app.php`:

```php
Robsonvn\CouchDB\CouchDBServiceProvider::class,
```
Configuration
-------------

Change your default database connection name in `config/database.php`:

```php
'default' => env('DB_CONNECTION', 'couchdb'),
```

And add a new mongodb connection:

```php
'couchdb' => [
    'driver'   => 'couchdb',
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => env('DB_PORT', 5984),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD')
],
```

Eloquent
--------

This package includes a CouchDB enabled Eloquent class that you can use to define models for corresponding collections.

```php
use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Book extends Eloquent{}
```

Note that we did not tell Eloquent which collection to use for the `Book` model. Just like the original Eloquent, the lower-case, plural name of the class will be used as the collection name unless another name is explicitly specified. You may specify a custom collection (alias for table) by defining a `collection` property on your model:

```php
use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Book extends Eloquent{
  protected $collection = 'books_collection';
}
```

CouchDB Limitations
------------
* Currently, there's no way to update and delete using Mango Query. In this case, we have to query the data, bring it to memory, update the fields and bulk an update.
* CouchDB is really touchy in matter of indexes, even the documentation recommends to always explicit the index that your query should use. In this case, **we are automatically creating all necessaries index on the fly**.  

Limitations
------------

* Due the way we're creating index this library do not works with the Full Text Search engine enabled yet.


TODO
------------

* Add compatibility to work with Full Text Search engine on.
* Add support to MorphToMany relationship.
