<?php

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

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
      protected function getMocks()
     {
         $mocks = [
             'tokens' => Mockery::mock('Illuminate\Auth\Passwords\TokenRepositoryInterface'),
             'users'  => Mockery::mock('Illuminate\Contracts\Auth\UserProvider'),
             'mailer' => Mockery::mock('Illuminate\Contracts\Mail\Mailer'),
             'view'   => 'resetLinkView',
         ];
         return $mocks;
     }
    public function testRemind()
    {
        $this->markTestSkipped('Not finished');

        $mailer = Mockery::mock('Illuminate\Mail\Mailer');
        $tokens = Password::getRepository();
        $users = $this->app['auth']->getProvider();

        $broker = new PasswordBroker($tokens, $users, $mailer, 'resetLinkView');
        $user = User::create([
            'name'     => 'John Doe',
            'email'    => 'john@doe.com',
            'password' => Hash::make('foobar'),
        ]);

        $mailer->shouldReceive('send')->once();
        $response = $broker->sendResetLink(['email'=>'john@doe.com']);
        $this->assertEquals(PasswordBroker::RESET_LINK_SENT, $response);

        $this->assertEquals(1, DB::collection('password_resets')->count());
        $reminder = DB::collection('password_resets')->first();
        $this->assertEquals('john@doe.com', $reminder['email']);
        $this->assertNotNull($reminder['token']);
        $this->assertTrue(is_string($reminder['created_at']));
        $credentials = [
            'email'                 => 'john@doe.com',
            'password'              => 'foobar',
            'password_confirmation' => 'foobar',
            'token'                 => $reminder['token'],
        ];

        $response = $broker->reset($credentials, function ($user, $password) {
            $user->password = bcrypt($password);
            $user->save();
        });

        $this->assertEquals('passwords.token', $response);

        $this->assertEquals(0, DB::collection('password_resets')->count());
    }
}
