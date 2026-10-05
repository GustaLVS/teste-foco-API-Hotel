<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

final class ReservationEndpoints
{
    #[OA\Post(
        path: '/reservations',
        operationId: 'createReservation',
        summary: 'Cadastrar reserva',
        description: 'Cria reserva, hóspedes, diárias e pagamentos na mesma transação. O checkout não conta como noite. Pagamentos são opcionais e podem ser parciais. Há conflito quando uma reserva do mesmo quarto começa antes da nova saída e termina depois da nova entrada. É permitido entrar no checkout anterior ou sair no checkin seguinte. Repetir o cadastro do mesmo período no mesmo quarto retorna 409.',
        tags: ['Reservas'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/ReservationWriteRequest',
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Reserva cadastrada com todos os relacionamentos.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ReservationWriteResponse',
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'Já existe reserva sobreposta no mesmo quarto.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: ['message' => 'O quarto ja possui uma reserva que se sobrepoe ao periodo informado.'],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Dados inválidos, campos obrigatórios ausentes ou ID relacionado inexistente.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationError',
                ),
            ),
        ],
    )]
    public function createReservation(): void
    {
    }

}
