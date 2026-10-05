<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_reserva_com_relacionamentos_e_pagamento_parcial(): void
    {
        $room = $this->createRoom();
        $payload = $this->validPayload($room);

        $response = $this->postJson('/api/reservations', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.external_id', null)
            ->assertJsonPath('data.room_id', $room->id)
            ->assertJsonPath('data.total', '300.00')
            ->assertJsonPath('data.check_in', '2026-11-10')
            ->assertJsonPath('data.check_out', '2026-11-12')
            ->assertJsonPath('data.room.hotel.id', $room->hotel_id)
            ->assertJsonCount(1, 'data.guests')
            ->assertJsonCount(2, 'data.dailies')
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.value', '100.00');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_guests', 1);
        $this->assertDatabaseCount('reservation_dailies', 2);
        $this->assertDatabaseCount('reservation_payments', 1);

        $reservationId = $response->json('data.id');

        $this->assertDatabaseHas('reservation_guests', [
            'reservation_id' => $reservationId,
            'name' => 'Hospede',
            'last_name' => 'Teste',
            'phone' => '77999999999',
        ]);

        $this->assertDatabaseHas('reservation_payments', [
            'reservation_id' => $reservationId,
            'method' => 1,
            'value' => 100,
        ]);

        $reservation = Reservation::with('dailies')
            ->findOrFail($reservationId);

        $this->assertSame($room->id, $reservation->room_id);
        $this->assertSame('300.00', $reservation->total);
        $this->assertNull($reservation->external_id);

        $this->assertEqualsCanonicalizing(
            ['2026-11-10', '2026-11-11'],
            $reservation->dailies
                ->map(fn ($daily) => $daily->date->format('Y-m-d'))
                ->all()
        );

        foreach ($reservation->dailies as $daily) {
            $this->assertSame('150.00', $daily->value);
        }
    }

    public function test_cadastra_reserva_sem_informar_pagamentos(): void
    {
        $room = $this->createRoom();
        $payload = $this->validPayload($room);

        unset($payload['payments']);

        $this->postJson('/api/reservations', $payload)
            ->assertCreated()
            ->assertJsonCount(0, 'data.payments');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_guests', 1);
        $this->assertDatabaseCount('reservation_dailies', 2);
        $this->assertDatabaseCount('reservation_payments', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_rejeita_dados_invalidos_sem_gravar_reserva(
        array $changes,
        array $errors
    ): void {
        $room = $this->createRoom();
        $payload = $this->validPayload($room);

        foreach ($changes as $field => $value) {
            Arr::set($payload, $field, $value);
        }

        $this->postJson('/api/reservations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errors);

        $this->assertNoReservationRecords();
    }

    public static function invalidPayloads(): array
    {
        return [
            'quarto inexistente' => [
                ['room_id' => 999999],
                ['room_id'],
            ],
            'saida igual a entrada' => [
                ['check_out' => '2026-11-10'],
                ['check_out'],
            ],
            'sem hospedes' => [
                ['guests' => []],
                ['guests'],
            ],
            'hospede sem telefone' => [
                ['guests.0.phone' => null],
                ['guests.0.phone'],
            ],
            'diaria na data do checkout' => [
                ['dailies.1.date' => '2026-11-12'],
                ['dailies.1.date'],
            ],
            'diaria duplicada' => [
                ['dailies.1.date' => '2026-11-10'],
                ['dailies.0.date', 'dailies.1.date'],
            ],
            'diaria faltando' => [
                [
                    'dailies' => [
                        ['date' => '2026-11-10', 'value' => '150.00'],
                    ],
                ],
                ['dailies'],
            ],
            'total diferente da soma' => [
                ['total' => '999.00'],
                ['total'],
            ],
            'total sem casas decimais' => [
                ['total' => '300'],
                ['total'],
            ],
            'valor monetario enviado como numero' => [
                ['dailies.0.value' => 150],
                ['dailies.0.value'],
            ],
            'valor de diaria negativo' => [
                ['dailies.0.value' => '-150.00'],
                ['dailies.0.value'],
            ],
            'metodo de pagamento fora do limite' => [
                ['payments.0.method' => 65536],
                ['payments.0.method'],
            ],
            'codigo externo enviado pela API' => [
                ['external_id' => 123],
                ['external_id'],
            ],
        ];
    }

    public function test_desfaz_cadastro_inteiro_se_pagamento_falhar(): void
    {
        $room = $this->createRoom();
        $payload = $this->validPayload($room);

        // Provoca uma falha no banco após cadastrar a reserva,
        // os hóspedes e as diárias. O campo method não aceita null.
        $payload['payments'][0]['method'] = null;

        $this->expectException(QueryException::class);

        try {
            app(ReservationService::class)->create($payload);
        } finally {
            $this->assertNoReservationRecords();

            $this->assertDatabaseHas('rooms', [
                'id' => $room->id,
            ]);
        }
    }

    private function assertNoReservationRecords(): void
    {
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_guests', 0);
        $this->assertDatabaseCount('reservation_dailies', 0);
        $this->assertDatabaseCount('reservation_payments', 0);
    }

    private function createRoom(): Room
    {
        $hotel = Hotel::create([
            'name' => 'Hotel de teste',
        ]);

        return Room::create([
            'hotel_id' => $hotel->id,
            'name' => 'Quarto de teste',
        ]);
    }

    private function validPayload(Room $room): array
    {
        return [
            'room_id' => $room->id,
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-12',
            'total' => '300.00',
            'guests' => [
                [
                    'name' => 'Hospede',
                    'last_name' => 'Teste',
                    'phone' => '77999999999',
                ],
            ],
            'dailies' => [
                [
                    'date' => '2026-11-10',
                    'value' => '150.00',
                ],
                [
                    'date' => '2026-11-11',
                    'value' => '150.00',
                ],
            ],
            'payments' => [
                [
                    'method' => 1,
                    'value' => '100.00',
                ],
            ],
        ];
    }
}