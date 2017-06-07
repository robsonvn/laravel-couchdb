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
}
