<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_quartos_com_filtro_e_paginacao(): void
    {
        $hotel = Hotel::create(['name' => 'Hotel de teste']);

        $firstRoom = $this->createRoom($hotel);
        $this->createRoom($hotel);

        // Quarto de outro hotel, que deve ficar fora do resultado.
        $this->createRoom();

        $this->getJson(
            "/api/rooms?hotel_id={$hotel->id}&per_page=1&page=1"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('data.0.id', $firstRoom->id)
            ->assertJsonPath('data.0.hotel.id', $hotel->id);
    }

    public function test_cadastra_quarto(): void
    {
        $hotel = Hotel::create(['name' => 'Hotel de teste']);

        $response = $this->postJson('/api/rooms', [
            'hotel_id' => $hotel->id,
            'name' => 'Quarto novo',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Quarto novo')
            ->assertJsonPath('data.hotel_id', $hotel->id)
            ->assertJsonPath('data.hotel.id', $hotel->id);

        $this->assertDatabaseCount('rooms', 1);

        $this->assertDatabaseHas('rooms', [
            'id' => $response->json('data.id'),
            'hotel_id' => $hotel->id,
            'name' => 'Quarto novo',
            'external_id' => null,
        ]);
    }

    public function test_rejeita_nome_vazio_e_hotel_inexistente(): void
    {
        $this->postJson('/api/rooms', [
            'hotel_id' => 999999,
            'name' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hotel_id', 'name']);

        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_consulta_quarto_com_seu_hotel(): void
    {
        $room = $this->createRoom();

        $this->getJson("/api/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $room->id)
            ->assertJsonPath('data.name', $room->name)
            ->assertJsonPath('data.hotel.id', $room->hotel_id);
    }

    public function test_consulta_quarto_inexistente_retorna_json_404(): void
    {
        $this->getJson('/api/rooms/999999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_patch_altera_nome_e_preserva_hotel(): void
    {
        $room = $this->createRoom();

        $this->patchJson("/api/rooms/{$room->id}", [
            'name' => 'Quarto atualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Quarto atualizado')
            ->assertJsonPath('data.hotel_id', $room->hotel_id);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Quarto atualizado',
            'hotel_id' => $room->hotel_id,
        ]);
    }

    public function test_put_exige_nome_e_hotel(): void
    {
        $room = $this->createRoom();

        $this->putJson("/api/rooms/{$room->id}", [
            'name' => 'Outro nome',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hotel_id']);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => $room->name,
            'hotel_id' => $room->hotel_id,
        ]);
    }

    public function test_put_altera_nome_e_hotel_de_quarto_sem_reservas(): void
    {
        $room = $this->createRoom();
        $otherHotel = Hotel::create(['name' => 'Outro hotel']);

        $this->putJson("/api/rooms/{$room->id}", [
            'hotel_id' => $otherHotel->id,
            'name' => 'Quarto transferido',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Quarto transferido')
            ->assertJsonPath('data.hotel.id', $otherHotel->id);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'hotel_id' => $otherHotel->id,
            'name' => 'Quarto transferido',
        ]);
    }

    public function test_exclui_quarto_sem_reservas(): void
    {
        $room = $this->createRoom();

        $this->deleteJson("/api/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('rooms', [
            'id' => $room->id,
        ]);
    }

    public function test_nao_exclui_quarto_com_reservas(): void
    {
        $room = $this->createRoom();
        $reservation = $this->createReservation($room);

        $this->deleteJson("/api/rooms/{$room->id}")
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'room_id' => $room->id,
        ]);
    }

    public function test_nao_transfere_quarto_com_reservas(): void
    {
        $room = $this->createRoom();
        $this->createReservation($room);

        $otherHotel = Hotel::create(['name' => 'Outro hotel']);

        $this->patchJson("/api/rooms/{$room->id}", [
            'hotel_id' => $otherHotel->id,
        ])
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'hotel_id' => $room->hotel_id,
            'name' => $room->name,
        ]);
    }

    public function test_permite_renomear_quarto_com_reservas(): void
    {
        $room = $this->createRoom();
        $reservation = $this->createReservation($room);

        $this->patchJson("/api/rooms/{$room->id}", [
            'name' => 'Novo nome permitido',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo nome permitido');

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'hotel_id' => $room->hotel_id,
            'name' => 'Novo nome permitido',
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'room_id' => $room->id,
        ]);
    }

    private function createRoom(?Hotel $hotel = null): Room
    {
        $hotel ??= Hotel::create([
            'name' => 'Hotel de teste',
        ]);

        return Room::create([
            'hotel_id' => $hotel->id,
            'name' => 'Quarto de teste',
        ]);
    }

    private function createReservation(Room $room): Reservation
    {
        $reservation = $room->reservations()->create([
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-11',
            'total' => '150.00',
        ]);

        $reservation->guests()->create([
            'name' => 'Hospede',
            'last_name' => 'Teste',
            'phone' => '77999999999',
        ]);

        $reservation->dailies()->create([
            'date' => '2026-11-10',
            'value' => '150.00',
        ]);

        return $reservation;
    }
}