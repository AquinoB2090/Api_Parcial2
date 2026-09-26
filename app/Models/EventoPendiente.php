<?php

namespace App\Models;

class EventoPendiente extends LegacyModel
{
    protected $table = 'eventos_pendientes';

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'available_at' => 'immutable_datetime'];
    }
}
