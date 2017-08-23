<?php

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Robsonvn\CouchDB\Eloquent\Model;
use Doctrine\CouchDB\Mango\MangoQuery;

class ModelTest extends TestCase
{
    public function tearDown()
    {
        User::truncate();
        Soft::truncate();
        Book::truncate();
        Item::truncate();
    }

    public function testNewModel()
    {
        $user = new User();
        $this->assertInstanceOf(Model::class, $user);
        $this->assertInstanceOf('Robsonvn\CouchDB\Connection', $user->getConnection());
        $this->assertEquals(false, $user->exists);
        $this->assertEquals('users', $user->getTable());
        $this->assertEquals('_id', $user->getKeyName());
    }

    public function testInsert()
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->birthday = new DateTime('1980/1/1');
        $user->age = 35;

        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals(1, User::count());

        $this->assertTrue(isset($user->_id));

        $this->assertTrue(is_string($user->_id));
        $this->assertNotEquals('', (string) $user->_id);
        $this->assertNotEquals(0, strlen((string) $user->_id));
        $this->assertInstanceOf(Carbon::class, $user->created_at);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(35, $user->age);
    }

    public function testAll()
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $user = new User();
        $user->name = 'Jane Doe';
        $user->title = 'user';
        $user->age = 32;
        $user->save();

        $all = User::all();

        $this->assertEquals(2, count($all));

        $this->assertContains('John Doe', $all->pluck('name'));
        $this->assertContains('Jane Doe', $all->pluck('name'));
    }

    public function testFind()
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $check = User::find($user->id);

        $this->assertInstanceOf(User::class, $check);
        $this->assertEquals($user->name, $check->name);
        $this->assertEquals($user->title, $check->title);
        $this->assertEquals($user->age, $check->age);
        $this->assertEquals($user->updated_at, $check->updated_at);
        $this->assertEquals($user->created_at, $check->created_at);
    }

    public function testInsertWithId()
    {
        $user = new User();
        $user->id = '1';
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;

        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals(1, User::count());

        $check = User::find($user->id);
        $this->assertInstanceOf(User::class, $check);
        $this->assertEquals($user->name, $check->name);
        $this->assertEquals($user->title, $check->title);
        $this->assertEquals($user->age, $check->age);
        $this->assertEquals($user->created_at, $check->created_at);
        $this->assertEquals($user->updated_at, $check->updated_at);
    }

    public function testUpdate()
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        //Sleep one second to confront updated_at
        sleep(1);

        $last_updated_at = $user->updated_at;
        $last_rev = $user->_rev;
        $user->age = 36;

        $user->save();

        $this->assertNotEquals($last_rev, $user->_rev);
        $this->assertNotEquals($last_updated_at, $user->updated_at);
        $this->assertEquals(36, $user->age);

        $check = User::find($user->_id);

        $this->assertEquals(true, $check->exists);
        $this->assertInstanceOf(Carbon::class, $check->created_at);
        $this->assertInstanceOf(Carbon::class, $check->updated_at);
        $this->assertEquals(1, User::count());

        $this->assertEquals($user->name, $check->name);
        $this->assertEquals($user->age, $check->age);
        $this->assertEquals($user->_rev, $check->_rev);

        $last_updated_at = $check->updated_at;
        $last_rev = $check->_rev;

        //Sleep one second to confront updated_at
        sleep(1);
        $user->update(['age' => 20]);

        $check = User::find($user->_id);
        $this->assertNotEquals($last_rev, $check->_rev);
        $this->assertNotEquals($last_updated_at, $check->updated_at);
        $this->assertEquals(20, $check->age);
    }

    public function testSelect()
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $user = new User();
        $user->name = 'Jane Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

      //Simple select
      $result = User::where('_id', $user->id)->first();
        $this->assertInstanceOf(User::class, $result);

        $result = User::where('name', 'John Doe')->first();
        $this->assertInstanceOf(User::class, $result);

        $result = User::where('title', 'admin')->get();
        $this->assertEquals(2, $result->count());

      //Nested where
      $result = User::where([['title', '=', 'admin']])->get();
        $this->assertEquals(2, $result->count());

        $result = User::where([['title', '=', 'admin'], ['name', '=', 'John Doe']])->get();
        $this->assertEquals(1, $result->count());
    }

    public function testDelete()
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals(1, User::count());

        $user->delete();

        $this->assertEquals(0, User::count());
    }

    public function testGet()
    {
        $result = User::insert([
            ['name' => 'John Doe'],
            ['name' => 'Jane Doe'],
        ]);

        $users = User::get();

        $this->assertEquals(2, count($users));
        $this->assertInstanceOf(Collection::class, $users);
        $this->assertInstanceOf(Model::class, $users[0]);
    }

    public function testFirst()
    {
        User::insert([
            ['name' => 'John Doe'],
            ['name' => 'Jane Doe'],
        ]);

        $user = User::first();

        $this->assertInstanceOf(Model::class, $user);
        $this->assertEquals('John Doe', $user->name);
    }

    public function testNoDocument()
    {
        $items = Item::where('name', 'nothing')->get();
        $this->assertInstanceOf(Collection::class, $items);
        $this->assertEquals(0, $items->count());

        $item = Item::where('name', 'nothing')->first();
        $this->assertEquals(null, $item);

        $item = Item::find('51c33d8981fec6813e00000a');
        $this->assertEquals(null, $item);
    }

    public function testFindOrfail()
    {
        $this->expectException(Illuminate\Database\Eloquent\ModelNotFoundException::class);
        User::findOrfail('51c33d8981fec6813e00000a');
    }

    public function testCreate()
    {
        $user = User::create(['name' => 'Jane Poe']);

        $this->assertInstanceOf(Model::class, $user);
        $this->assertEquals(true, $user->exists);
        $this->assertEquals('Jane Poe', $user->name);
        $this->assertEquals(true, is_string($user->_rev));

        $check = User::where('name', 'Jane Poe')->first();
        $this->assertEquals($user->_id, $check->_id);
    }

    public function testDestroy()
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        User::destroy((string) $user->_id);

        $this->assertEquals(0, User::count());
    }

    public function testTouch()
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $old = $user->updated_at;

        sleep(1);
        $user->touch();
        $check = User::find($user->_id);

        $this->assertNotEquals($old, $check->updated_at);
    }

    public function testSoftDelete()
    {
        Soft::create(['name' => 'John Doe']);
        $test = Soft::create(['name' => 'Jane Doe']);

        $all = Soft::all();

        $this->assertEquals(2, Soft::count());

        $user = Soft::where('name', 'John Doe')->first();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals(false, $user->trashed());
        $this->assertNull($user->deleted_at);

        $user->delete();

        $this->assertEquals(true, $user->trashed());
        $this->assertNotNull($user->deleted_at);

        $user = Soft::where('name', 'John Doe')->first();
        $this->assertNull($user);

        $this->assertEquals(1, Soft::count());
        $this->assertEquals(2, Soft::withTrashed()->count());

        $user = Soft::withTrashed()->where('name', 'John Doe')->first();

        $this->assertNotNull($user);
        $this->assertInstanceOf(Carbon::class, $user->deleted_at);
        $this->assertEquals(true, $user->trashed());

        $user->restore();
        $all = Soft::withTrashed()->get();

        $this->assertEquals(2, Soft::count());
    }

    public function testScope()
    {
        Item::insert([
            ['name' => 'knife', 'object_type' => 'sharp'],
            ['name' => 'spoon', 'object_type' => 'round'],
        ]);

        $sharp = Item::sharp()->get();
        $this->assertEquals(1, $sharp->count());
    }

    public function testGetMangoQuery(){
      $query = User::where('age', '>', 37);
      $query->first();
      $mangoQuery = $query->getMangoQuery();

      $this->assertEquals(['age'=>['$gt'=>37,'$lt'=>'a']], $mangoQuery->selector());
      $this->assertEquals(1, $mangoQuery->limit());
    }

    public function testToArray()
    {
        $item = Item::create(['name' => 'fork', 'object_type' => 'sharp']);

        $array = $item->toArray();

        $keys = array_keys($array);
        sort($keys);

        $this->assertEquals(['_id', '_rev', 'created_at', 'name', 'object_type', 'updated_at'], $keys);
        $this->assertTrue(is_string($array['created_at']));
        $this->assertTrue(is_string($array['updated_at']));
        $this->assertTrue(is_string($array['_id']));
        $this->assertTrue(is_string($array['_rev']));
    }

    public function testUnset()
    {
        $user1 = User::create(['name' => 'John Doe', 'note1' => 'ABC', 'note2' => 'DEF']);
        $user2 = User::create(['name' => 'Jane Doe', 'note1' => 'ABC', 'note2' => 'DEF']);

        $user1->unset('note1');

        $this->assertFalse(isset($user1->note1));
        $this->assertTrue(isset($user1->note2));
        $this->assertTrue(isset($user2->note1));
        $this->assertTrue(isset($user2->note2));

        // Re-fetch to be sure
        $user1 = User::find($user1->_id);
        $user2 = User::find($user2->_id);

        $this->assertFalse(isset($user1->note1));
        $this->assertTrue(isset($user1->note2));
        $this->assertTrue(isset($user2->note1));
        $this->assertTrue(isset($user2->note2));

        $user2->unset(['note1', 'note2']);

        $this->assertFalse(isset($user2->note1));
        $this->assertFalse(isset($user2->note2));
    }

    public function testDates()
    {
        $birthday = new DateTime('1980/1/1');
        $user = User::create(['name' => 'John Doe', 'birthday' => $birthday]);
        $this->assertInstanceOf(Carbon::class, $user->birthday);

        $check = User::find($user->_id);
        $this->assertInstanceOf(Carbon::class, $check->birthday);
        $this->assertEquals($user->birthday, $check->birthday);

        $user = User::where('birthday', '>', new DateTime('1975/1/1'))->first();

        $this->assertEquals('John Doe', $user->name);

        // test custom date format for json output
        $json = $user->toArray();
        $this->assertEquals($user->birthday->format('l jS \of F Y h:i:s A'), $json['birthday']);
        $this->assertEquals($user->created_at->format('l jS \of F Y h:i:s A'), $json['created_at']);

        // test created_at
        $item = Item::create(['name' => 'sword']);
        $this->assertRegExp('/^(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})$/', $item->getOriginal('created_at'));
        $this->assertEquals(strtotime($item->getOriginal('created_at')), $item->created_at->getTimestamp());
        $this->assertTrue(abs(time() - $item->created_at->getTimestamp()) < 2);

        // test default date format for json output
        $item = Item::create(['name' => 'sword']);
        $json = $item->toArray();
        $this->assertEquals($item->created_at->format('Y-m-d H:i:s'), $json['created_at']);

        $user = User::create(['name' => 'Jane Doe', 'birthday' => time()]);
        $this->assertInstanceOf(Carbon::class, $user->birthday);

        $user = User::create(['name' => 'Jane Doe', 'birthday' => 'Monday 8th of August 2005 03:12:46 PM']);
        $this->assertInstanceOf(Carbon::class, $user->birthday);

        $user = User::create(['name' => 'Jane Doe', 'birthday' => '2005-08-08']);
        $this->assertInstanceOf(Carbon::class, $user->birthday);
/*
        $params = [
          'name' => 'ExtremeInsaneTest',
          'entry' => [
            'date' => 'Monday 8th of August 2005 03:12:46 PM',
            'logs'=>[
              [
                'log_date'=>'Monday 8th of August 2005 03:12:46 PM',
                'not_castable_data'=> 'Monday 9th of August 2005 03:12:46 PM',
                'insane_tests'=>[
                  [
                    'date'=>'Monday 8th of August 2005 03:12:46 PM'
                  ],
                  [
                    'date'=>'Monday 8th of August 2005 03:12:46 PM'
                  ]
                ]
              ]
            ],
          ],
          'entry.extreme_insane_test' => [
            'dates'=>[
              [
                'danger_date'=>'Monday 8th of August 2005 03:12:46 PM',
              ],
              [
                'danger_date'=>'Monday 8th of August 2005 03:12:46 PM',
              ],
            ]
          ]
        ];

        $user = User::create($params);
        $this->assertInstanceOf(Carbon::class, $user->getAttribute('entry.date'));
        $this->assertInternalType('array',$logs = $user->getAttribute('entry.logs'));
*/

        /*foreach($logs as $log){
          $this->assertInstanceOf(Carbon::class, $log['log_date']);
          $this->assertNotInstanceOf(Carbon::class, $log['not_castable_data']);
          $this->assertInternalType('array', $log['insane_tests']);
          foreach($log['insane_tests'] as $insane){
            $this->assertInstanceOf(Carbon::class, $insane['date']);
            echo $insane['date'];
          }
        }*/

        //print_r($user);

        //return;

        $user = User::create(['name' => 'Jane Doe', 'entry' => ['date' => '2005-08-08']]);
        $this->assertInstanceOf(Carbon::class, $user->getAttribute('entry.date'));

        $user->setAttribute('entry.date', new DateTime());
        $this->assertInstanceOf(Carbon::class, $user->getAttribute('entry.date'));

        $data = $user->toArray();

        $this->assertEquals((string) $user->getAttribute('entry.date')->format('Y-m-d H:i:s'), $data['entry']['date']);
    }

    public function testPushPull()
    {
        $user = User::create(['name' => 'John Doe']);
        $last_rev = $user->_rev;
        //Simple
        $user->push('tags', 'tag1');

        //verify new revision
        $this->assertEquals(true, is_string($user->_rev));
        $this->assertNotEquals($last_rev, $user->_rev);
        $this->assertEquals(['tag1'], $user->tags);
        //fetch and check
        $user = User::where('_id', $user->_id)->first();
        $this->assertEquals(['tag1'], $user->tags);

        //Simple array
        $user->push('tags', ['tag1', 'tag2']);
        $this->assertEquals(['tag1', 'tag1', 'tag2'], $user->tags);
        //fetch and check
        $user = User::where('_id', $user->_id)->first();
        $this->assertEquals(['tag1', 'tag1', 'tag2'], $user->tags);

        //simple unique
        $user->push('tags', 'tag2', true);
        $this->assertEquals(['tag1', 'tag1', 'tag2'], $user->tags);
        //fetch and check
        $user = User::where('_id', $user->_id)->first();
        $this->assertEquals(['tag1', 'tag1', 'tag2'], $user->tags);

        //simple pull
        $last_rev = $user->_rev;
        $user->pull('tags', 'tag1');
        $this->assertNotEquals($last_rev, $user->_rev);
        $this->assertEquals(['tag2'], $user->tags);
        //fetch and check
        $user = User::where('_id', $user->_id)->first();
        $this->assertEquals(['tag2'], $user->tags);

        //simple push again
        $user->push('tags', 'tag3');
        $this->assertEquals(['tag2', 'tag3'], $user->tags);

        //remove all
        $user->pull('tags', ['tag2', 'tag3']);
        $this->assertEquals([], $user->tags);

        $user = User::where('_id', $user->_id)->first();

        $this->assertEquals([], $user->tags);
    }

    public function testRaw()
    {
        User::create(['name' => 'John Doe', 'age' => 35]);
        User::create(['name' => 'Jane Doe', 'age' => 35]);
        User::create(['name' => 'Harry Hoe', 'age' => 15]);

        $users = User::raw(function ($collection) {
            return $collection->find(new MangoQuery(['age' => 35]));
        });

        $this->assertInstanceOf(Model::class, $users[0]);

        $user = User::raw(function ($collection) {
            return $collection->findOne(['age' => 35]);
        });

        $this->assertInstanceOf(Model::class, $user);

        $result = User::raw(function ($collection) {
            return $collection->insertOne(['name' => 'Yvonne Yoe', 'age' => 35]);
        });
        $this->assertNotNull($result);
    }

    public function testDotNotation()
    {
        $user = User::create([
            'name'    => 'John Doe',
            'address' => [
                'city'    => 'Paris',
                'country' => 'France',
            ],
        ]);

        $this->assertEquals('Paris', $user->getAttribute('address.city'));
        $this->assertEquals('Paris', $user['address.city']);
        $this->assertEquals('Paris', $user->{'address.city'});

        // Fill
        $user->fill([
            'address.city' => 'Strasbourg',
        ]);

        $this->assertEquals('Strasbourg', $user['address.city']);
    }

    public function testMultipleLevelDotNotation()
    {
        $book = Book::create([
            'title'    => 'A Game of Thrones',
            'chapters' => [
                'one' => [
                    'title' => 'The first chapter',
                ],
            ],
        ]);

        $this->assertEquals(['one' => ['title' => 'The first chapter']], $book->chapters);
        $this->assertEquals(['title' => 'The first chapter'], $book['chapters.one']);
        $this->assertEquals('The first chapter', $book['chapters.one.title']);
    }

    public function testGetDirtyDates()
    {
        $this->markTestSkipped('i have to study it more, not implemented yet');

        $user = new User();
        $user->setRawAttributes(['name' => 'John Doe', 'birthday' => new DateTime('19 august 1989')], true);
        $this->assertEmpty($user->getDirty());

        $user->birthday = new DateTime('19 august 1989');
        $this->assertEmpty($user->getDirty());
    }
}
