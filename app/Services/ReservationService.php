<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function create(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $room = Room::query()
                ->whereKey($data['room_id'])
                ->lockForUpdate()
                ->first();

            if ($room === null) {
                throw ValidationException::withMessages([
                    'room_id' => 'O quarto informado nao existe.',
                ]);
            }

            $hasConflict = $room->reservations()
                ->where('check_in', '<', $data['check_out'])
                ->where('check_out', '>', $data['check_in'])
                ->exists();

            if ($hasConflict) {
                abort(
                    409,
                    'O quarto ja possui uma reserva que se sobrepoe ao periodo informado.'
                );
            }

            $reservation = $room->reservations()->create([
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'total' => $data['total'],
            ]);

            $reservation->guests()->createMany($data['guests']);
            $reservation->dailies()->createMany($data['dailies']);
            $reservation->payments()->createMany($data['payments'] ?? []);

            return $reservation->refresh()->load([
                'room.hotel',
                'guests',
                'dailies',
                'payments',
            ]);
        });
    }
}