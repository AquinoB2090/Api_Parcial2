<?php

namespace App\Services;

use App\Models\EventoPendiente;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

class EventPublisher
{
    public function publish(): int
    {
        if (! config('subastas.pusher_enabled')) {
            return 0;
        }
        $ids = EventoPendiente::whereNull('sent_at')->where('available_at', '<=', now('UTC'))->orderBy('id')->limit(100)->pluck('id');
        $sent = 0;
        foreach ($ids as $id) {
            $sent += DB::transaction(function () use ($id) {
                $e = EventoPendiente::whereKey($id)->lockForUpdate()->first();
                if (! $e || $e->sent_at !== null) {
                    return 0;
                }
                try {
                    Broadcast::connection('pusher')->broadcast(['private-'.$e->channel], $e->name, $e->payload + ['event_id' => $e->event_id]);
                    $e->sent_at = now('UTC');
                    $e->save();

                    return 1;
                } catch (\Throwable $exception) {
                    report($exception);
                    $e->attempts++;
                    $e->available_at = now('UTC')->addSeconds(min(300, 2 ** min($e->attempts, 8)));
                    $e->save();

                    return 0;
                }
            }, 3);
        }

        return $sent;
    }
}
