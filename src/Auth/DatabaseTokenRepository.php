<?php
namespace Robsonvn\CouchDB\Auth;

use Carbon\Carbon;
use Illuminate\Auth\Passwords\DatabaseTokenRepository as BaseDatabaseTokenRepository;

class DatabaseTokenRepository extends BaseDatabaseTokenRepository
{
    /**
     * @inheritdoc
     */
    protected function getPayload($email, $token)
    {
      return ['email' => $email, 'token' => $this->hasher->make($token), 'created_at' => (new Carbon())->format('Y-m-d H:i:s')];
    }

    /**
     * @inheritdoc
     */
    protected function tokenExpired($createdAt)
    {
      return Carbon::createFromFormat('Y-m-d H:i:s',$createdAt)->addSeconds($this->expires)->isPast();
    }
}
