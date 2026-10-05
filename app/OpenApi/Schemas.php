<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Hotel',
    type: 'object',
    required: ['id', 'name'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 2,
        ),
        new OA\Property(
            property: 'external_id',
            type: 'integer',
            nullable: true,
            readOnly: true,
            description: 'Código de origem do XML; pode ser nulo.',
            example: 1,
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Hotel Foco Prime',
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
    ],
)]

#[OA\Schema(
    schema: 'Room',
    type: 'object',
    required: ['id', 'hotel_id', 'name', 'hotel'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 2,
        ),
        new OA\Property(
            property: 'external_id',
            type: 'integer',
            nullable: true,
            readOnly: true,
            description: 'Código do XML; nulo para quartos criados pela API. Pode não aparecer na resposta imediata do cadastro.',
            example: 1,
        ),
        new OA\Property(
            property: 'hotel_id',
            type: 'integer',
            minimum: 1,
            example: 2,
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Room 1 Hotel 1',
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
        new OA\Property(
            property: 'hotel',
            ref: '#/components/schemas/Hotel',
        ),
    ],
)]

#[OA\Schema(
    schema: 'RoomWriteRequest',
    type: 'object',
    required: ['hotel_id', 'name'],
    properties: [
        new OA\Property(
            property: 'hotel_id',
            type: 'integer',
            minimum: 1,
            description: 'ID interno de um hotel existente.',
            example: 2,
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 1,
            maxLength: 255,
            example: 'Quarto de teste do Swagger',
        ),
    ],
    example: ['hotel_id' => 2, 'name' => 'Quarto de teste do Swagger'],
)]

#[OA\Schema(
    schema: 'RoomPatchRequest',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'hotel_id',
            type: 'integer',
            minimum: 1,
            description: 'ID interno de um hotel existente.',
            example: 2,
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 1,
            maxLength: 255,
            example: 'Quarto de teste do Swagger',
        ),
    ],
    description: 'Campos opcionais. Se enviados, devem respeitar as regras do cadastro. Um corpo vazio também é aceito.',
    example: ['name' => 'Quarto atualizado'],
)]

#[OA\Schema(
    schema: 'RoomResponse',
    type: 'object',
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/Room',
        ),
    ],
)]

#[OA\Schema(
    schema: 'RoomWriteResponse',
    type: 'object',
    required: ['message', 'data'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Quarto cadastrado com sucesso.',
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/Room',
        ),
    ],
)]

#[OA\Schema(
    schema: 'PaginationLink',
    type: 'object',
    required: ['url', 'label', 'active'],
    properties: [
        new OA\Property(
            property: 'url',
            type: 'string',
            nullable: true,
            example: 'http://127.0.0.1:8000/api/rooms?page=1',
        ),
        new OA\Property(
            property: 'label',
            type: 'string',
            example: '1',
        ),
        new OA\Property(
            property: 'page',
            type: 'integer',
            nullable: true,
            example: 1,
        ),
        new OA\Property(
            property: 'active',
            type: 'boolean',
            example: true,
        ),
    ],
)]

#[OA\Schema(
    schema: 'RoomPage',
    type: 'object',
    required: ['current_page', 'data', 'per_page', 'total'],
    properties: [
        new OA\Property(
            property: 'current_page',
            type: 'integer',
            minimum: 1,
            example: 1,
        ),
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/Room',
            ),
        ),
        new OA\Property(
            property: 'first_page_url',
            type: 'string',
            example: 'http://127.0.0.1:8000/api/rooms?page=1',
        ),
        new OA\Property(
            property: 'from',
            type: 'integer',
            nullable: true,
            example: 1,
        ),
        new OA\Property(
            property: 'last_page',
            type: 'integer',
            minimum: 1,
            example: 1,
        ),
        new OA\Property(
            property: 'last_page_url',
            type: 'string',
            example: 'http://127.0.0.1:8000/api/rooms?page=1',
        ),
        new OA\Property(
            property: 'links',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/PaginationLink',
            ),
        ),
        new OA\Property(
            property: 'next_page_url',
            type: 'string',
            nullable: true,
        ),
        new OA\Property(
            property: 'path',
            type: 'string',
            example: 'http://127.0.0.1:8000/api/rooms',
        ),
        new OA\Property(
            property: 'per_page',
            type: 'integer',
            minimum: 1,
            maximum: 100,
            example: 15,
        ),
        new OA\Property(
            property: 'prev_page_url',
            type: 'string',
            nullable: true,
        ),
        new OA\Property(
            property: 'to',
            type: 'integer',
            nullable: true,
            example: 6,
        ),
        new OA\Property(
            property: 'total',
            type: 'integer',
            minimum: 0,
            example: 6,
        ),
    ],
)]

#[OA\Schema(
    schema: 'GuestRequest',
    type: 'object',
    required: ['name', 'last_name', 'phone'],
    properties: [
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 1,
            maxLength: 255,
            example: 'Hospede',
        ),
        new OA\Property(
            property: 'last_name',
            type: 'string',
            minLength: 1,
            maxLength: 255,
            example: 'Teste Swagger',
        ),
        new OA\Property(
            property: 'phone',
            type: 'string',
            minLength: 1,
            maxLength: 30,
            example: '77999999999',
        ),
    ],
    additionalProperties: false,
)]

#[OA\Schema(
    schema: 'DailyRequest',
    type: 'object',
    required: ['date', 'value'],
    properties: [
        new OA\Property(
            property: 'date',
            type: 'string',
            format: 'date',
            example: '2026-12-10',
        ),
        new OA\Property(
            property: 'value',
            type: 'string',
            pattern: '^[0-9]{1,10}\\.[0-9]{2}$',
            minLength: 4,
            maxLength: 13,
            description: 'Valor monetário não negativo como texto, com ponto e duas casas decimais.',
            example: '150.00',
        ),
    ],
    additionalProperties: false,
)]

#[OA\Schema(
    schema: 'PaymentRequest',
    type: 'object',
    required: ['method', 'value'],
    properties: [
        new OA\Property(
            property: 'method',
            type: 'integer',
            minimum: 0,
            maximum: 65535,
            description: 'Código do método de pagamento.',
            example: 1,
        ),
        new OA\Property(
            property: 'value',
            type: 'string',
            pattern: '^[0-9]{1,10}\\.[0-9]{2}$',
            minLength: 4,
            maxLength: 13,
            description: 'Valor monetário não negativo como texto, com ponto e duas casas decimais.',
            example: '100.00',
        ),
    ],
    additionalProperties: false,
)]

#[OA\Schema(
    schema: 'ReservationGuest',
    type: 'object',
    required: ['id', 'reservation_id', 'name', 'last_name', 'phone'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 1,
        ),
        new OA\Property(
            property: 'reservation_id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 1,
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 1,
            maxLength: 255,
            example: 'Hospede',
        ),
        new OA\Property(
            property: 'last_name',
            type: 'string',
            minLength: 1,
            maxLength: 255,
            example: 'Teste Swagger',
        ),
        new OA\Property(
            property: 'phone',
            type: 'string',
            minLength: 1,
            maxLength: 30,
            example: '77999999999',
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
    ],
)]

#[OA\Schema(
    schema: 'ReservationDaily',
    type: 'object',
    required: ['id', 'reservation_id', 'date', 'value'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 1,
        ),
        new OA\Property(
            property: 'reservation_id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 1,
        ),
        new OA\Property(
            property: 'date',
            type: 'string',
            format: 'date',
            example: '2026-12-10',
        ),
        new OA\Property(
            property: 'value',
            type: 'string',
            pattern: '^[0-9]{1,10}\\.[0-9]{2}$',
            minLength: 4,
            maxLength: 13,
            description: 'Valor monetário não negativo como texto, com ponto e duas casas decimais.',
            example: '150.00',
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
    ],
)]

#[OA\Schema(
    schema: 'ReservationPayment',
    type: 'object',
    required: ['id', 'reservation_id', 'method', 'value'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 1,
        ),
        new OA\Property(
            property: 'reservation_id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 1,
        ),
        new OA\Property(
            property: 'method',
            type: 'integer',
            minimum: 0,
            maximum: 65535,
            description: 'Código do método de pagamento.',
            example: 1,
        ),
        new OA\Property(
            property: 'value',
            type: 'string',
            pattern: '^[0-9]{1,10}\\.[0-9]{2}$',
            minLength: 4,
            maxLength: 13,
            description: 'Valor monetário não negativo como texto, com ponto e duas casas decimais.',
            example: '100.00',
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
    ],
)]

#[OA\Schema(
    schema: 'ReservationWriteRequest',
    type: 'object',
    required: ['room_id', 'check_in', 'check_out', 'total', 'guests', 'dailies'],
    properties: [
        new OA\Property(
            property: 'room_id',
            type: 'integer',
            minimum: 1,
            description: 'ID interno de um quarto existente, não o external_id.',
            example: 2,
        ),
        new OA\Property(
            property: 'check_in',
            type: 'string',
            format: 'date',
            example: '2026-12-10',
        ),
        new OA\Property(
            property: 'check_out',
            type: 'string',
            format: 'date',
            description: 'Deve ser posterior ao checkin; esse dia não recebe diária.',
            example: '2026-12-12',
        ),
        new OA\Property(
            property: 'total',
            type: 'string',
            pattern: '^[0-9]{1,10}\\.[0-9]{2}$',
            minLength: 4,
            maxLength: 13,
            description: 'Valor monetário não negativo como texto, com ponto e duas casas decimais.',
            example: '300.00',
        ),
        new OA\Property(
            property: 'guests',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/GuestRequest',
            ),
            minItems: 1,
        ),
        new OA\Property(
            property: 'dailies',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/DailyRequest',
            ),
            minItems: 1,
            description: 'Uma diária por noite; datas distintas, incluindo checkin e excluindo checkout. A soma dos valores deve ser igual ao total.',
        ),
        new OA\Property(
            property: 'payments',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/PaymentRequest',
            ),
            description: 'Opcional. Pode ser uma lista vazia. Pagamentos parciais são aceitos.',
        ),
    ],
    description: 'external_id é reservado ao XML e será rejeitado se enviado. O total deve ser igual à soma das diárias. Os nomes e telefones não podem conter apenas espaços. A disponibilidade é validada antes do cadastro.',
    example: ['room_id' => 2, 'check_in' => '2026-12-10', 'check_out' => '2026-12-12', 'total' => '300.00', 'guests' => [['name' => 'Hospede', 'last_name' => 'Teste Swagger', 'phone' => '77999999999']], 'dailies' => [['date' => '2026-12-10', 'value' => '150.00'], ['date' => '2026-12-11', 'value' => '150.00']], 'payments' => [['method' => 1, 'value' => '100.00']]],
)]

#[OA\Schema(
    schema: 'Reservation',
    type: 'object',
    required: ['id', 'room_id', 'check_in', 'check_out', 'total', 'room', 'guests', 'dailies', 'payments'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            minimum: 1,
            readOnly: true,
            example: 1,
        ),
        new OA\Property(
            property: 'external_id',
            type: 'integer',
            nullable: true,
            readOnly: true,
            description: 'Código do XML; nulo para reservas criadas pela API.',
        ),
        new OA\Property(
            property: 'room_id',
            type: 'integer',
            minimum: 1,
            example: 2,
        ),
        new OA\Property(
            property: 'check_in',
            type: 'string',
            format: 'date',
            example: '2026-12-10',
        ),
        new OA\Property(
            property: 'check_out',
            type: 'string',
            format: 'date',
            example: '2026-12-12',
        ),
        new OA\Property(
            property: 'total',
            type: 'string',
            pattern: '^[0-9]{1,10}\\.[0-9]{2}$',
            minLength: 4,
            maxLength: 13,
            description: 'Valor monetário não negativo como texto, com ponto e duas casas decimais.',
            example: '300.00',
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            readOnly: true,
            example: '2026-10-05T13:00:00.000000Z',
        ),
        new OA\Property(
            property: 'room',
            ref: '#/components/schemas/Room',
        ),
        new OA\Property(
            property: 'guests',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ReservationGuest',
            ),
        ),
        new OA\Property(
            property: 'dailies',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ReservationDaily',
            ),
        ),
        new OA\Property(
            property: 'payments',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ReservationPayment',
            ),
        ),
    ],
)]

#[OA\Schema(
    schema: 'ReservationWriteResponse',
    type: 'object',
    required: ['message', 'data'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Reserva cadastrada com sucesso.',
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/Reservation',
        ),
    ],
)]

#[OA\Schema(
    schema: 'MessageResponse',
    type: 'object',
    required: ['message'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Operação concluída.',
        ),
    ],
)]

#[OA\Schema(
    schema: 'ErrorResponse',
    type: 'object',
    required: ['message'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Mensagem de erro.',
        ),
    ],
    description: 'Em ambiente de desenvolvimento, podem existir informações adicionais de depuração.',
)]

#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'The name field is required.',
        ),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(
                    type: 'string',
                ),
            ),
            example: ['name' => ['The name field is required.']],
        ),
    ],
)]
final class Schemas
{
}
