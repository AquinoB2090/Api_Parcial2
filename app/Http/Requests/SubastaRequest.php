<?php

namespace App\Http\Requests;

use App\Rules\DecimalAmount;
use App\Rules\ZonedDate;
use Illuminate\Foundation\Http\FormRequest;

class SubastaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $r = $this->isMethod('POST') ? 'required' : 'sometimes';

        return ['id_vehiculo' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer', 'min:1'],
            'monto_base' => [$r, new DecimalAmount(2000000)],
            'fecha_inicio' => [$r, new ZonedDate], 'fecha_cierre' => [$r, new ZonedDate]];
    }
}
