<?php

namespace Tests\Feature;

use App\Support\DbRelease;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D264: waits on outside services do not hold a database connection. */
class DbReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_outside_call_gives_the_connection_back_but_never_inside_a_transaction_or_with_sqlite(): void
    {
        $this->assertTrue(Event::hasListeners(\Illuminate\Http\Client\Events\RequestSending::class));

        // SQLite (tests, local copies) keeps its database in its one connection: left alone
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->makePost(['slug' => 'still-here']);
        Http::get('https://example.com/');
        $this->assertSame(1, \App\Models\Post::count(), 'The test database survived');

        // A MySQL connection is given back; one inside a transaction, or SQLite, is not
        $conn = fn (string $driver, int $level) => \Mockery::mock(Connection::class, fn ($m) => $m->shouldReceive('getDriverName')->andReturn($driver)->shouldReceive('transactionLevel')->andReturn($level));
        $this->assertSame(['idle'], DbRelease::releasable(['idle' => $conn('mysql', 0), 'busy' => $conn('mysql', 1), 'local' => $conn('sqlite', 0)]));
    }
}
