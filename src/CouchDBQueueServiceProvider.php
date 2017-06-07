<?php namespace Robsonvn\CouchDB;

use Illuminate\Queue\QueueServiceProvider;
use Robsonvn\CouchDB\Queue\Failed\CouchFailedJobProvider;

class CouchDBQueueServiceProvider extends QueueServiceProvider
{
    /**
     * @inheritdoc
     */
    protected function registerFailedJobServices()
    {
        // Add compatible queue failer if couchdb is configured.
        if (config('queue.failed.database') == 'couchdb') {
            $this->app->singleton('queue.failer', function ($app) {
                return new CouchFailedJobProvider($app['db'], config('queue.failed.database'), config('queue.failed.table'));
            });
        } else {
            parent::registerFailedJobServices();
        }
    }
}
