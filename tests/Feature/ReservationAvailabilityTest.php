<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('conflictingPeriods')]
    public function test_rejeita_sobreposicao_sem_alterar_reserva_existente(
        string $checkIn,
        string $checkOut
    ): void {
        $room = $this->createRoom();
        $existing = $this->createExistingReservation($room);

        $this->postJson(
            '/api/reservations',
            $this->payload($room, $checkIn, $checkOut)
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'O quarto ja possui uma reserva que se sobrepoe ao periodo informado.'
            );

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_guests', 1);
        $this->assertDatabaseCount('reservation_dailies', 2);
        $this->assertDatabaseCount('reservation_payments', 0);

        $existing->refresh();

        $this->assertSame($room->id, $existing->room_id);
        $this->assertSame('2026-11-10', $existing->check_in->format('Y-m-d'));
        $this->assertSame('2026-11-12', $existing->check_out->format('Y-m-d'));
        $this->assertSame('300.00', $existing->total);

        $this->assertSame(1, $existing->guests()->count());
        $this->assertSame(2, $existing->dailies()->count());
    }

    public static function conflictingPeriods(): array
    {
        return [
            'periodo identico' => [
                '2026-11-10', '2026-11-12',
            ],
            'entrada dentro da reserva' => [
                '2026-11-11', '2026-11-13',
            ],
            'saida dentro da reserva' => [
                '2026-11-09', '2026-11-11',
            ],
            'periodo contido' => [
                '2026-11-10', '2026-11-11',
            ],
            'periodo envolvendo a reserva' => [
                '2026-11-09', '2026-11-13',
            ],
        ];
    }

    #[DataProvider('availablePeriods')]
    public function test_permite_periodo_sem_sobreposicao(
        string $checkIn,
        string $checkOut
    ): void {
        $room = $this->createRoom();
        $existing = $this->createExistingReservation($room);
        $payload = $this->payload($room, $checkIn, $checkOut);

        $response = $this->postJson('/api/reservations', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.room_id', $room->id)
            ->assertJsonPath('data.check_in', $checkIn)
            ->assertJsonPath('data.check_out', $checkOut);

        $this->assertDatabaseCount('reservations', 2);
        $this->assertDatabaseCount('reservation_guests', 2);
        $this->assertDatabaseCount(
            'reservation_dailies',
            2 + count($payload['dailies'])
        );
        $this->assertDatabaseCount('reservation_payments', 0);

        $this->assertNotSame(
            $existing->id,
            $response->json('data.id')
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $existing->id,
            'room_id' => $room->id,
        ]);
    }

    public static function availablePeriods(): array
    {
        return [
            'entrada no checkout anterior' => [
                '2026-11-12', '2026-11-14',
            ],
            'saida no checkin seguinte' => [
                '2026-11-08', '2026-11-10',
            ],
            'periodo anterior com intervalo' => [
                '2026-11-06', '2026-11-08',
            ],
            'periodo posterior com intervalo' => [
                '2026-11-14', '2026-11-16',
            ],
        ];
    }

    public function test_permite_mesmo_periodo_em_outro_quarto(): void
    {
        $firstRoom = $this->createRoom();
        $this->createExistingReservation($firstRoom);

        // Outro quarto do mesmo hotel.
        $secondRoom = Room::create([
            'hotel_id' => $firstRoom->hotel_id,
            'name' => 'Segundo quarto',
        ]);

        $this->postJson(
            '/api/reservations',
            $this->payload($secondRoom, '2026-11-10', '2026-11-12')
        )
            ->assertCreated()
            ->assertJsonPath('data.room_id', $secondRoom->id);

        $this->assertDatabaseCount('reservations', 2);
        $this->assertDatabaseCount('reservation_guests', 2);
        $this->assertDatabaseCount('reservation_dailies', 4);

        $this->assertSame(1, $firstRoom->reservations()->count());
        $this->assertSame(1, $secondRoom->reservations()->count());
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

    private function createExistingReservation(Room $room): Reservation
    {
        return app(ReservationService::class)->create(
            $this->payload($room, '2026-11-10', '2026-11-12')
        );
    }

    private function payload(
        Room $room,
        string $checkIn,
        string $checkOut
    ): array {
        $start = new DateTimeImmutable($checkIn);
        $end = new DateTimeImmutable($checkOut);
        $dailies = [];

        for ($date = $start; $date < $end; $date = $date->modify('+1 day')) {
            $dailies[] = [
                'date' => $date->format('Y-m-d'),
                'value' => '150.00',
            ];
        }

        return [
            'room_id' => $room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total' => (count($dailies) * 150) . '.00',
            'guests' => [
                [
                    'name' => 'Hospede',
                    'last_name' => 'Teste disponibilidade',
                    'phone' => '77999999999',
                ],
            ],
            'dailies' => $dailies,
            'payments' => [],
        ];
    }
}