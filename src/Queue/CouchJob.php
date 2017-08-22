<?php namespace Robsonvn\CouchDB\Queue;

use Illuminate\Queue\Jobs\DatabaseJob;

class CouchJob extends DatabaseJob
{
    /**
     * Indicates if the job has been reserved.
     *
     * @return bool
     */
    public function isReserved()
    {
        return $this->job->reserved;
    }

    /**
     * @return \DateTime
     */
    public function reservedAt()
    {
        return $this->job->reserved_at;
    }

    /**
     * @inheritdoc
     */
     public function release($delay = 0)
     {
         //Release failed job with 60 seconds of delay
         if($this->job->attempts>1){
           $delay +=60;
         }
         parent::release($delay);
     }
}
