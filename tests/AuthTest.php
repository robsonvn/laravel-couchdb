<?php

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Application;

class AuthTest extends TestCase
{
    public function tearDown()
    {
        Mockery::close();
        User::truncate();
    }

    public function testAuthAttempt()
    {
        $user = User::create([
            'name'     => 'John Doe',
            'email'    => 'john@doe.com',
            'password' => Hash::make('foobar'),
        ]);

        $this->assertTrue(Auth::attempt(['email' => 'john@doe.com', 'password' => 'foobar'], true));
        $this->assertTrue(Auth::check());
    }
}
