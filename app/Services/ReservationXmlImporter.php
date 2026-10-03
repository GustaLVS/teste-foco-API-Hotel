<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;

class ReservationXmlImporter
{
    public function import(SimpleXMLElement $xml): array
    {
        $ids = [];

        foreach ($xml->Reserve as $item) {
            $ids[] = (string) $item['id'];
        }

        Validator::make(
            ['ids' => $ids],
            [
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            ]
        )->validate();

        $result = [
            'processed' => 0,
            'rejected' => 0,
            'messages' => [],
        ];

        foreach ($xml->Reserve as $item) {
            $externalId = (int) $item['id'];

            try {
                $data = $this->extractData($item);
                $data = $this->validateData($data);

                $this->saveReservation($data);

                $result['processed']++;
            } catch (ValidationException $exception) {
                $errors = $exception->errors();

                $message = implode(
                    ' ',
                    array_merge(...array_values($errors))
                );

                Log::warning('Reserva rejeitada na importacao XML.', [
                    'external_id' => $externalId,
                    'errors' => $errors,
                ]);

                $result['rejected']++;
                $result['messages'][] =
                    "Reserva {$externalId} rejeitada: {$message}";
            }
        }

        return $result;
    }

    private function extractData(SimpleXMLElement $item): array
    {
        $guests = [];
        $dailies = [];
        $payments = [];

        foreach ($item->Guests->Guest ?? [] as $guest) {
            $guests[] = [
                'name' => trim((string) $guest->Name),
                'last_name' => trim((string) $guest->LastName),
                'phone' => trim((string) $guest->Phone),
            ];
        }

        foreach ($item->Dailies->Daily ?? [] as $daily) {
            $dailies[] = [
                'date' => trim((string) $daily->Date),
                'value' => trim((string) $daily->Value),
            ];
        }

        foreach ($item->Payments->Payment ?? [] as $payment) {
            $payments[] = [
                'method' => trim((string) $payment->Method),
                'value' => trim((string) $payment->Value),
            ];
        }

        return [
            'external_id' => (string) $item['id'],
            'hotel_code' => (string) $item['hotelCode'],
            'room_code' => (string) $item['roomCode'],
            'check_in' => trim((string) $item->CheckIn),
            'check_out' => trim((string) $item->CheckOut),
            'total' => trim((string) $item->Total),
            'guests' => $guests,
            'dailies' => $dailies,
            'payments' => $payments,
        ];
    }

    private function validateData(array $data): array
    {
        $moneyRules = [
            'required',
            'regex:/^\d{1,10}(?:\.\d{1,2})?$/',
        ];

        $data = Validator::make($data, [
            'external_id' => ['required', 'integer', 'min:1'],
            'hotel_code' => ['required', 'integer', 'min:1'],
            'room_code' => ['required', 'integer', 'min:1'],

            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => [
                'required',
                'date_format:Y-m-d',
                'after:check_in',
            ],

            'total' => $moneyRules,

            'guests' => ['required', 'array', 'min:1'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.last_name' => ['required', 'string', 'max:255'],
            'guests.*.phone' => ['required', 'string', 'max:30'],

            'dailies' => ['required', 'array', 'min:1'],
            'dailies.*.date' => [
                'required',
                'date_format:Y-m-d',
                'distinct',
            ],
            'dailies.*.value' => $moneyRules,

            'payments' => ['present', 'array'],
            'payments.*.method' => [
                'required',
                'integer',
                'min:0',
                'max:65535',
            ],
            'payments.*.value' => $moneyRules,
        ])->validate();

        $dailyTotal = 0;

        foreach ($data['dailies'] as $index => $daily) {
            if (
                $daily['date'] < $data['check_in']
                || $daily['date'] >= $data['check_out']
            ) {
                throw ValidationException::withMessages([
                    "dailies.{$index}.date" =>
                        "Diaria {$daily['date']} fora do periodo "
                        . "{$data['check_in']} a {$data['check_out']}.",
                ]);
            }

            $dailyTotal += $this->toCents($daily['value']);
        }

        $checkIn = new DateTimeImmutable($data['check_in']);
        $checkOut = new DateTimeImmutable($data['check_out']);
        $nights = (int) $checkIn->diff($checkOut)->format('%a');

        if (count($data['dailies']) !== $nights) {
            throw ValidationException::withMessages([
                'dailies' =>
                    'Deve existir uma diaria para cada noite da hospedagem.',
            ]);
        }

        if ($dailyTotal !== $this->toCents($data['total'])) {
            throw ValidationException::withMessages([
                'total' =>
                    'O total da reserva deve corresponder a soma das diarias.',
            ]);
        }

        return $data;
    }

    private function toCents(string $value): int
    {
        [$whole, $decimal] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        return ((int) $whole * 100)
            + (int) str_pad($decimal, 2, '0');
    }

    private function saveReservation(array $data): void
    {
        DB::transaction(function () use ($data) {
            $room = Room::where(
                'external_id',
                (int) $data['room_code']
            )->lockForUpdate()->first();

            if ($room === null) {
                throw ValidationException::withMessages([
                    'room_code' =>
                        "Quarto {$data['room_code']} nao encontrado.",
                ]);
            }

            if (
                (int) $room->hotel->external_id
                !== (int) $data['hotel_code']
            ) {
                throw ValidationException::withMessages([
                    'hotel_code' =>
                        'O quarto nao pertence ao hotel informado no XML.',
                ]);
            }

            $reservation = Reservation::updateOrCreate(
                ['external_id' => (int) $data['external_id']],
                [
                    'room_id' => $room->id,
                    'check_in' => $data['check_in'],
                    'check_out' => $data['check_out'],
                    'total' => $data['total'],
                ]
            );

            $reservation->guests()->delete();
            $reservation->dailies()->delete();
            $reservation->payments()->delete();

            $reservation->guests()->createMany($data['guests']);
            $reservation->dailies()->createMany($data['dailies']);
            $reservation->payments()->createMany($data['payments']);
        });
    }
}