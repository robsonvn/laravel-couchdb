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
    public function release($delay = 60)
    {
        //Release failed job with 60 seconds delay
        parent::release($delay);

        $this->delete();

        return $this->database->release($this->queue, $this->job, $delay);
    }
}
