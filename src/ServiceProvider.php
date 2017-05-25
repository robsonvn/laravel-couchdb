<?php

namespace Robsonvn\CouchDB;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Robsonvn\CouchDB\Eloquent\Model;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);

        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the provider.
     *
     * @return void
     */
    public function register()
    {
        // Add couchdb to the database manager
        $this->app['db']->extend('couchdb', function ($config) {
            return new Connection($config);
        });
    }
}
