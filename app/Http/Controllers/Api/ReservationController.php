<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function store(
        StoreReservationRequest $request,
        ReservationService $service
    ): JsonResponse {
        $reservation = $service->create($request->validated());

        return response()->json([
            'message' => 'Reserva cadastrada com sucesso.',
            'data' => $reservation,
        ], 201);
    }
}