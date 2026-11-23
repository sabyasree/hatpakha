<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * D264 (owner, 2026-11-23: "is there a way to reduce database connections?"; Hostinger: 20 new connections a second per
 * account, and on the degraded server connections stayed open too long). Before the site waits on an outside service
 * (Claude, OpenAI pictures and voice, Facebook, Google), it gives its database connection back; the next query opens a
 * new one. A wait of one or three minutes no longer holds a connection the whole time. Never inside a transaction, and
 * never with SQLite (tests and local copies keep their database in that one connection).
 */
final class DbRelease
{
    public static function now(): void
    {
        foreach (self::releasable(DB::getConnections()) as $name) {
            DB::disconnect($name);
        }
    }

    /**
     * @param  array<string, \Illuminate\Database\ConnectionInterface>  $connections
     * @return list<string> the open connections that may be given back
     */
    public static function releasable(array $connections): array
    {
        return array_keys(array_filter($connections, fn ($c) => $c->getDriverName() !== 'sqlite' && $c->transactionLevel() === 0));
    }
}
