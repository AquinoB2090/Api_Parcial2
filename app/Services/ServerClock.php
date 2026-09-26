<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ServerClock
{
    public function now(): CarbonImmutable
    {
        if (DB::getDriverName() === 'sqlsrv') {
            return CarbonImmutable::parse(DB::selectOne('SELECT SYSUTCDATETIME() AS utc_now')->utc_now, 'UTC');
        }

        return CarbonImmutable::now('UTC');
    }
}
