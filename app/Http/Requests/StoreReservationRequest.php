<?php

namespace App\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $moneyRules = [
            'bail',
            'required',
            'string',
            'regex:/^[0-9]{1,10}\.[0-9]{2}$/',
        ];

        return [
            'external_id' => ['missing'],

            'room_id' => [
                'required',
                'integer',
                'min:1',
                'exists:rooms,id',
            ],

            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => [
                'required',
                'date_format:Y-m-d',
                'after:check_in',
            ],

            'total' => $moneyRules,

            'guests' => ['required', 'array', 'list', 'min:1'],
            'guests.*' => ['required', 'array:name,last_name,phone'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.last_name' => ['required', 'string', 'max:255'],
            'guests.*.phone' => ['required', 'string', 'max:30'],

            'dailies' => ['required', 'array', 'list', 'min:1'],
            'dailies.*' => ['required', 'array:date,value'],
            'dailies.*.date' => [
                'required',
                'date_format:Y-m-d',
                'distinct',
            ],
            'dailies.*.value' => $moneyRules,

            'payments' => ['sometimes', 'array', 'list'],
            'payments.*' => ['required', 'array:method,value'],
            'payments.*.method' => [
                'required',
                'integer',
                'min:0',
                'max:65535',
            ],
            'payments.*.value' => $moneyRules,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $data = $validator->getData();

                $checkIn = new DateTimeImmutable($data['check_in']);
                $checkOut = new DateTimeImmutable($data['check_out']);
                $nights = (int) $checkIn->diff($checkOut)->format('%a');

                $dailyTotal = 0;

                foreach ($data['dailies'] as $index => $daily) {
                    if (
                        $daily['date'] < $data['check_in']
                        || $daily['date'] >= $data['check_out']
                    ) {
                        $validator->errors()->add(
                            "dailies.{$index}.date",
                            "Diaria {$daily['date']} fora do periodo da hospedagem."
                        );
                    }

                    $dailyTotal += $this->toCents($daily['value']);
                }

                if (count($data['dailies']) !== $nights) {
                    $validator->errors()->add(
                        'dailies',
                        'Deve existir uma diaria para cada noite da hospedagem.'
                    );
                }

                if ($dailyTotal !== $this->toCents($data['total'])) {
                    $validator->errors()->add(
                        'total',
                        'O total da reserva deve corresponder a soma das diarias.'
                    );
                }
            },
        ];
    }

    private function toCents(string $value): int
    {
        return (int) str_replace('.', '', $value);
    }
}