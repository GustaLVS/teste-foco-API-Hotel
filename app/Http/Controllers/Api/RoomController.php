<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hotel_id' => [
                'sometimes',
                'integer',
                'min:1',
                'exists:hotels,id',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'between:1,100',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ]);

        $query = Room::query()->with('hotel');

        if (isset($data['hotel_id'])) {
            $query->where('hotel_id', $data['hotel_id']);
        }

        $rooms = $query
            ->orderBy('id')
            ->paginate((int) ($data['per_page'] ?? 15))
            ->withQueryString();

        return response()->json($rooms);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return response()->json([
            'message' => 'Quarto cadastrado com sucesso.',
            'data' => $room->load('hotel'),
        ], 201);
    }

    public function show(Room $room): JsonResponse
    {
        return response()->json([
            'data' => $room->load('hotel'),
        ]);
    }

    public function update(
        UpdateRoomRequest $request,
        Room $room
    ): JsonResponse {
        $data = $request->validated();

        return DB::transaction(function () use ($room, $data) {
            $room = Room::query()
                ->whereKey($room->id)
                ->lockForUpdate()
                ->firstOrFail();

            $changingHotel = isset($data['hotel_id'])
                && (int) $data['hotel_id'] !== (int) $room->hotel_id;

            if (
                $changingHotel
                && $room->reservations()->exists()
            ) {
                return response()->json([
                    'message' =>
                        'Nao e permitido alterar o hotel de um quarto com reservas.',
                ], 409);
            }

            $room->update($data);

            return response()->json([
                'message' => 'Quarto atualizado com sucesso.',
                'data' => $room->load('hotel'),
            ]);
        });
    }

    public function destroy(Room $room): JsonResponse
    {
        return DB::transaction(function () use ($room) {
            $room = Room::query()
                ->whereKey($room->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($room->reservations()->exists()) {
                return response()->json([
                    'message' =>
                        'Nao e permitido excluir um quarto com reservas.',
                ], 409);
            }

            $room->delete();

            return response()->json([
                'message' => 'Quarto excluido com sucesso.',
            ]);
        });
    }
}