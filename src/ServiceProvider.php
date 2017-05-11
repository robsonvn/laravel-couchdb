<?php
namespace Robsonvn\CouchDB;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
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
