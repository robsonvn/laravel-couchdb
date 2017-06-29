<?php namespace Robsonvn\CouchDB\Queue;

use Carbon\Carbon;
use Illuminate\Queue\DatabaseQueue;
use Robsonvn\CouchDB\Connection;

class CouchQueue extends DatabaseQueue
{
    /**
     * @inheritdoc
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        if (! is_null($this->retryAfter)) {
            $this->releaseJobsThatHaveBeenReservedTooLong($queue);
        }

        if ($job = $this->getNextAvailableJobAndReserve($queue)) {
            return new CouchJob(
                $this->container, $this, $job, $this->connectionName, $queue
            );
        }
    }

    /**
     * Get the next available job for the queue and mark it as reserved.
     *
     * When using multiple daemon queue listeners to process jobs there
     * is a possibility that multiple processes can end up reading the
     * same record before one has flagged it as reserved.
     *
     * This race condition can result in random jobs being run more then
     * once. To solve this we'll try to update the document using the _rev
     * if it fails means that the _rev is outdate and we try to get
     * the next available job.
     *
     * @param  string|null $queue
     *
     * @return \StdClass|null
     */
    protected function getNextAvailableJobAndReserve($queue)
    {
      $job = $this->database->collection($this->table)
          ->where('queue', $this->getQueue($queue))
          ->whereNull('reserved_at')
          ->where('available_at', '<=', Carbon::now()->getTimestamp())->first();

      if (count($job)) {
        
          $job['reserved'] = 1;
          $job['attempts']++;
          $job['reserved_at'] = Carbon::now()->getTimestamp();

          try{
            list($_id, $_rev) = $this->database->getCollection($this->table)->putDocument($job, $job['_id'],$job['_rev']);

            $job['_rev'] = $_rev;
            $job = (object) $job;
            $job->id = $job->_id;

          }catch(\Doctrine\CouchDB\HTTP\HTTPException $e){
            $job = $this->getNextAvailableJobAndReserve($queue);
          }
      }
      return $job;
    }

    /**
     * Release the jobs that have been reserved for too long.
     *
     * @param  string $queue
     * @return void
     */
    protected function releaseJobsThatHaveBeenReservedTooLong($queue)
    {
        $expiration = Carbon::now()->subSeconds($this->retryAfter)->getTimestamp();

        $reserved = $this->database->collection($this->table)
            ->where('queue', $this->getQueue($queue))
            ->where('reserved_at', '<=', $expiration)->get();

        foreach ($reserved as $job) {
          $this->database->table($this->table)->where('_id', $job['_id'])->update([
              'reserved' => 0,
              'reserved_at' => null,
          ]);
        }
    }


    /**
     * @inheritdoc
     */
    public function deleteReserved($queue, $id)
    {
        $this->database->collection($this->table)->where('_id', $id)->delete();
    }
}
