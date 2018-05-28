# Laravel CouchDB


[![Build Status](http://img.shields.io/travis/robsonvn/laravel-couchdb.svg?branch=master)](https://travis-ci.org/robsonvn/laravel-couchdb) [![StyleCI](https://styleci.io/repos/90929490/shield?style=flat)](https://styleci.io/repos/90929490)
[![codecov.io](https://codecov.io/gh/robsonvn/laravel-couchdb/coverage.svg?branch=master)](https://codecov.io/gh/robsonvn/laravel-couchdb)

Laravel CouchDB is an Eloquent model and Query builder with support for **CouchDB 2.x**, using the original Laravel API. This library extends the original Laravel classes, so it uses exactly the same methods.

## Good to know before using it

* CouchDB has many limitations dealing with Mango Query that force us to process somethings in memory, which directly impacts on our library performance, please check out the [Couch Limitations](#couchdb-limitations) and the [Limitations](#limitations) sections for more details.

Table of contents
-----------------
* [Installation](#installation)
* [Configuration](#configuration)
* [Eloquent](#eloquent)
* [Query Builder](#query-builder)
* [Extensions](#extensions)
* [Examples](#examples)
* [Inserts, updates and deletes](#inserts-updates-and-deletes)
* [Relations](#relations)
* [CouchDB specific operators](#couchdb-specific-operators)
* [CouchDB Limitations](#couchdb-limitations)
* [Limitations](#limitations)
* [TODO](#todo)

## Installation


Installation using composer:

```
composer require robsonvn/laravel-couchdb
```

And add the service provider in `config/app.php`:

```php
Robsonvn\CouchDB\ServiceProvider::class,
```
### Laravel version Compatibility
For now, this project only works with Laravel 5.4.x


Configuration
-------------

Change your default database connection name in `config/database.php`:

```php
'default' => env('DB_CONNECTION', 'couchdb'),
```

And add a new couchdb connection:

```php
'couchdb' => [    
    'driver'   => 'couchdb',
    'type'     => env('DB_CONNECTION_TYPE', 'socket'),
    'host'     => env('DB_HOST', 'localhost'),
    'ip'       => env('DB_IP', null),
    'port'     => env('DB_PORT', '5984'),
    'dbname'   => env('DB_DATABASE', 'forge'),
    'user'     => env('DB_USERNAME', null),
    'password' => env('DB_PASSWORD', null),
    'logging'  => env('DB_LOGGING', false),
],
```

And this on yours .env file

```
DB_CONNECTION=couchdb
DB_HOST=dbhost
DB_PORT=5984
DB_DATABASE=dbname
DB_USERNAME=
DB_PASSWORD=
```
***Please note, the database user must be an admin since this library creates indexes on the fly (design_docs)***

You can read more about CouchDB Authorization [here](http://docs.couchdb.org/en/2.1.1/intro/security.html#authorization).

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


Query Builder
-------------

The database driver plugs right into the original query builder. When using couchdb connections, you will be able to build fluent queries to perform database operations. For your convenience, there is a `collection` alias for `table` as well as some additional couchdb specific operators/operations.

```php
$users = DB::collection('users')->get();

$user = DB::collection('users')->where('name', 'John')->first();
```

If you did not change your default database connection, you will need to specify it when querying.

```php
$user = DB::connection('couchdb')->collection('users')->get();
```

Read more about the query builder on http://laravel.com/docs/queries

Extensions
----------

### Auth

If you want to use Laravel's native Auth functionality, register this included service provider:

```php
Robsonvn\CouchDB\Auth\PasswordResetServiceProvider::class,
```

This service provider will slightly modify the internal DatabaseTokenRepository to add support for CouchDB based password reminders. If you don't use password reminders, you don't have to register this service provider and everything else should work just fine.

You also needs to extends the CouchDB Authenticatable class in your User class instead of the native one.

```php
use Robsonvn\CouchDB\Auth\User as Authenticatable;

class User extends Authenticatable
{
}
```

### Queues

If you want to use CouchDB as your database backend, change the the driver in `config/queue.php`:

```php
'connections' => [
    'database' => [
        'driver' => 'couchdb',
        'table'  => 'jobs',
        'queue'  => 'default',
        'expire' => 60,
    ],
```

If you want to use CouchDB to handle failed jobs, change the database in `config/queue.php`:

```php
'failed' => [
    'database' => 'couchdb',
    'table'    => 'failed_jobs',
    ],
```

And add the service provider in `config/app.php`:

```php
Robsonvn\CouchDB\CouchDBQueueServiceProvider::class,
```
Examples
--------

### Basic Usage

**Retrieving All Models**

```php
$users = User::all();
```

**Retrieving A Record By Primary Key**

```php
$user = User::find('517c43667db388101e00000f');
```

**Wheres**

```php
$users = User::where('votes', '>', 100)->take(10)->get();
```

**Or Statements**

```php
$users = User::where('votes', '>', 100)->orWhere('name', 'John')->get();
```

**And Statements**

```php
$users = User::where('votes', '>', 100)->where('name', '=', 'John')->get();
```

**Using Where In With An Array**

```php
$users = User::whereIn('age', [16, 18, 20])->get();
```

When using `whereNotIn` objects will be returned if the field is non existent. Combine with `whereNotNull('age')` to leave out those documents.

**Using Where Between**

```php
$users = User::whereBetween('votes', [1, 100])->get();
```

**Where null**

```php
$users = User::whereNull('updated_at')->get();
```

**Order By**

```php
$users = User::orderBy('name', 'desc')->get();
```

**Offset & Limit**

```php
$users = User::skip(10)->take(5)->get();
```

**Advanced Wheres**

```php
$users = User::where('name', '=', 'John')->orWhere(function($query)
    {
        $query->where('votes', '>', 100)
              ->where('title', '<>', 'Admin');
    })
    ->get();
```

**Like (case-sensitive)**

```php
$user = Comment::where('body', 'like', '%spam%')->get();
```
**Like (case-insensitive)**

```php
$user = Comment::where('body', 'ilike', '%spam%')->get();
```

**Incrementing or decrementing a value of a column**

Perform increments or decrements (default 1) on specified attributes:

```php
User::where('name', 'John Doe')->increment('age');
User::where('name', 'Jaques')->decrement('weight', 50);
```

The number of updated objects is returned:

```php
$count = User->increment('age');
```

You may also specify additional columns to update:

```php
User::where('age', '29')->increment('age', 1, ['group' => 'thirty something']);
User::where('bmi', 30)->decrement('bmi', 1, ['category' => 'overweight']);
```

**Soft deleting**

When soft deleting a model, it is not actually removed from your database. Instead, a deleted_at timestamp is set on the record. To enable soft deletes for a model, apply the SoftDeletingTrait to the model:

```php
use Robsonvn\CouchDB\Eloquent\SoftDeletes;

class User extends Eloquent {

    use SoftDeletes;

    protected $dates = ['deleted_at'];

}
```

For more information check http://laravel.com/docs/eloquent#soft-deleting

### CouchDB specific operators

**Exists**

Matches documents that have the specified field.

```php
User::where('age', 'exists', true)->get();
```

**All**

Matches arrays that contain all elements specified in the query.

```php
User::where('roles', 'all', ['moderator', 'author'])->get();
```

**Size**

Selects documents if the array field is a specified size.

```php
User::where('tags', 'size', 3)->get();
```

**Regex**

Selects documents where values match a specified regular expression.

```php
User::where('name', 'regex', '(?i).*doe$')->get();
User::where('name', 'not regex', '(?i).*doe$')->get()
```

**NOTE:** Mango query uses Erlang regular expression implementation.

>Most selector expressions work exactly as you would expect for the given operator. The matching algorithms used by the $regex operator are currently based on the Perl Compatible Regular Expression (PCRE) library. However, not all of the PCRE library is implemented, and some parts of the $regex operator go beyond what PCRE offers. For more information about what is implemented, see the Erlang Regular Expression information http://erlang.org/doc/man/re.html.


**Type**

Selects documents if a field is of the specified type.
Valid values are "null", "boolean", "number", "string", "array", and "object".

```php
User::where('age', 'type', 2)->get();
```

**Mod**

Performs a modulo operation on the value of a field and selects documents with a specified result.

```php
User::where('age', 'mod', [10, 0])->get();
```
### Inserts, updates and deletes

Inserting, updating and deleting records works just like the original Eloquent.

**Saving a new model**

```php
$user = new User;
$user->name = 'John';
$user->save();
```

You may also use the create method to save a new model in a single line:

```php
User::create(['name' => 'John']);
```

**Updating a model**

To update a model, you may retrieve it, change an attribute, and use the save method.

```php
$user = User::first();
$user->email = 'john@foo.com';
$user->save();
```

**Deleting a model**

To delete a model, simply call the delete method on the instance:

```php
$user = User::first();
$user->delete();
```

Or deleting a model by its key:

```php
User::destroy('517c43667db388101e00000f');
```

For more information about model manipulation, check http://laravel.com/docs/eloquent#insert-update-delete

### Dates

Eloquent allows you to work with Carbon/DateTime object. Internally, these dates will be converted to a formated string 'yyyy-mm-dd H:i:s' when saved to the database. If you wish to use this functionality on non-default date fields you will need to manually specify them as described here: http://laravel.com/docs/eloquent#date-mutators

Example:

```php
use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class User extends Eloquent {

    protected $dates = ['birthday'];

}
```

Which allows you to execute queries like:

```php
$users = User::where('birthday', '>', new DateTime('-18 years'))->get();
```

### Relations

Supported relations are:

 - hasOne
 - hasMany
 - belongsTo
 - belongsToMany
 - morphToMany
 - embedsOne
 - embedsMany

Example:

```php
use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class User extends Eloquent {

    public function items()
    {
        return $this->hasMany('Item');
    }

}
```

And the inverse relation:

```php
use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Item extends Eloquent {

    public function user()
    {
        return $this->belongsTo('User');
    }

}
```

The belongsToMany relation will not use a pivot "table", but will push id's to a __related_ids__ attribute instead. This makes the second parameter for the belongsToMany method useless. If you want to define custom keys for your relation, set it to `null`:

```php
use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class User extends Eloquent {

    public function groups()
    {
        return $this->belongsToMany('Group', null, 'user_ids', 'group_ids');
    }

}
```


Other relations are not yet supported, but may be added in the future. Read more about these relations on http://laravel.com/docs/eloquent#relationships

### EmbedsMany Relations

If you want to embed models, rather than referencing them, you can use the `embedsMany` relation. This relation is similar to the `hasMany` relation, but embeds the models inside the parent object.

**REMEMBER**: these relations return Eloquent collections, they don't return query builder objects!

```php
use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class User extends Eloquent {

    public function books()
    {
        return $this->embedsMany('Book');
    }

}
```

You access the embedded models through the dynamic property:

```php
$books = User::first()->books;
```

The inverse relation is auto*magically* available, you don't need to define this reverse relation.

```php
$user = $book->user;
```

Inserting and updating embedded models works similar to the `hasMany` relation:

```php
$book = new Book(['title' => 'A Game of Thrones']);

$user = User::first();

$book = $user->books()->save($book);
// or
$book = $user->books()->create(['title' => 'A Game of Thrones'])
```

You can update embedded models using their `save` method:

```php
$book = $user->books()->first();

$book->title = 'A Game of Thrones';

$book->save();
```

You can remove an embedded model by using the `destroy` method on the relation, or the `delete` method on the model:

```php
$book = $user->books()->first();

$book->delete();
// or
$user->books()->destroy($book);
```

If you want to add or remove an embedded model, without touching the database, you can use the `associate` and `dissociate` methods. To eventually write the changes to the database, save the parent object:

```php
$user->books()->associate($book);

$user->save();
```

Like other relations, embedsMany assumes the local key of the relationship based on the model name. You can override the default local key by passing a second argument to the embedsMany method:

```php
return $this->embedsMany('Book', 'local_key');
```

Embedded relations will return a Collection of embedded items instead of a query builder. Check out the available operations here: https://laravel.com/docs/master/collections

### EmbedsOne Relations

The embedsOne relation is similar to the EmbedsMany relation, but only embeds a single model.

```php
use Robsonvn\CouchDB\Eloquent\Model as Eloquent;

class Book extends Eloquent {

    public function author()
    {
        return $this->embedsOne('Author');
    }

}
```

You access the embedded models through the dynamic property:

```php
$author = Book::first()->author;
```

Inserting and updating embedded models works similar to the `hasOne` relation:

```php
$author = new Author(['name' => 'John Doe']);

$book = Books::first();

$author = $book->author()->save($author);
// or
$author = $book->author()->create(['name' => 'John Doe']);
```

You can update the embedded model using the `save` method:

```php
$author = $book->author;

$author->name = 'Jane Doe';
$author->save();
```

You can replace the embedded model with a new model like this:

```php
$newAuthor = new Author(['name' => 'Jane Doe']);
$book->author()->save($newAuthor);
```

### Raw Expressions

These expressions will be injected directly into the query.

```php
User::whereRaw(['age' => array('$gt' => 30, '$lt' => 40)])->get();
```

You can also perform raw expressions on the internal CouchDBCollection object. If this is executed on the model class, it will return a collection of models. If this is executed on the query builder, it will return the original response.

```php
// Returns a collection of User models.
$models = User::raw(function($collection)
{
    return $collection->find(['_id'=>['$gt'=>null]]);
});

// Returns the original CouchDB response.
$cursor = DB::collection('users')->raw(function($collection)
{
    return $collection->find(['_id'=>['$gt'=>null]]);
});
```

The internal CouchDBClient can be accessed like this:

```php
$client = DB::getCouchDBClient();
```

CouchDB Limitations
------------
* Currently, there's no way to update and delete using Mango Query. In this case, we have to query the data, bring it to memory, update the fields and bulk an update.
* CouchDB is really touchy in matter of indexes, even the documentation [recommends](http://docs.couchdb.org/en/2.0.0/api/database/find.html#index-selection) to always explicit the index that your query should use. In this case, **we are automatically creating all necessaries index on the fly**.  
* CouchDB does not have the concept of collection as MongoDB, so we are using "collections" by adding an attribute (type) in every single document. Please, treat type as a reserved attribute. Use of collections is not optional.

Limitations
------------

* Due the way we're creating index this library does not work with the Full Text Search engine enabled yet.
* Aggregation, group by and distinct operations is not supported yet.
* If you want to use any library that extends the original Eloquent classes you may have to fork it and change to our classes.


TODO
------------

* Add compatibility to work with Full Text Search engine.
* Add support to aggregation, group by and distinct operations.
* Create a query cursor.
* Add support to get casted attribute using doting notation.

## Special Thanks

[Fred Booking](https://www.fredbooking.com) for supporting this project.

[Jens Segers](https://github.com/jenssegers) and the [Laravel MongoDB contributors](https://github.com/jenssegers/laravel-mongodb/graphs/contributors) because many of the code and structure of this project came from there.
