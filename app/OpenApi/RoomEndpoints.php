<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

final class RoomEndpoints
{
    #[OA\Get(
        path: '/rooms',
        operationId: 'listRooms',
        summary: 'Listar quartos',
        description: 'Lista quartos com seus hotéis, em ordem de ID e com paginação. hotel_id deve corresponder a um hotel existente.',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'hotel_id',
                in: 'query',
                description: 'Filtra pelo ID interno de um hotel existente.',
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    example: 2,
                ),
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Quantidade de quartos por página.',
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    maximum: 100,
                    default: 15,
                    example: 15,
                ),
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Página solicitada.',
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    default: 1,
                    example: 1,
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista paginada de quartos.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/RoomPage',
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
    public function listRooms(): void
    {
    }

    #[OA\Post(
        path: '/rooms',
        operationId: 'createRoom',
        summary: 'Cadastrar quarto',
        description: 'Cria um quarto com external_id nulo no banco. O campo external_id pode não aparecer na resposta imediata do cadastro.',
        tags: ['Quartos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/RoomWriteRequest',
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Quarto cadastrado.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/RoomWriteResponse',
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
    public function createRoom(): void
    {
    }

    #[OA\Get(
        path: '/rooms/{id}',
        operationId: 'showRoom',
        summary: 'Consultar quarto',
        description: 'Retorna o quarto e seu hotel.',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID interno do quarto.',
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    example: 2,
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto encontrado.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/RoomResponse',
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Quarto não encontrado.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: ['message' => 'Quarto não encontrado.'],
                ),
            ),
        ],
    )]
    public function showRoom(): void
    {
    }

    #[OA\Put(
        path: '/rooms/{id}',
        operationId: 'replaceRoom',
        summary: 'Atualizar nome e hotel',
        description: 'Exige hotel_id e name. Um quarto com reservas pode ser renomeado, mas não transferido para outro hotel.',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID interno do quarto.',
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    example: 2,
                ),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/RoomWriteRequest',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto atualizado.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/RoomWriteResponse',
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Quarto não encontrado.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: ['message' => 'Quarto não encontrado.'],
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'Quarto com reservas: exclusão ou transferência de hotel bloqueada.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: ['message' => 'Nao e permitido alterar o hotel de um quarto com reservas.'],
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
    public function replaceRoom(): void
    {
    }

    #[OA\Patch(
        path: '/rooms/{id}',
        operationId: 'patchRoom',
        summary: 'Atualizar parcialmente',
        description: 'hotel_id e name são opcionais, mas não podem ser vazios quando enviados. Um quarto com reservas não pode ser transferido para outro hotel.',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID interno do quarto.',
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    example: 2,
                ),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                ref: '#/components/schemas/RoomPatchRequest',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto atualizado.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/RoomWriteResponse',
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Quarto não encontrado.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: ['message' => 'Quarto não encontrado.'],
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'Quarto com reservas: exclusão ou transferência de hotel bloqueada.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: ['message' => 'Nao e permitido alterar o hotel de um quarto com reservas.'],
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
    public function patchRoom(): void
    {
    }

    #[OA\Delete(
        path: '/rooms/{id}',
        operationId: 'deleteRoom',
        summary: 'Excluir quarto',
        description: 'Exclui somente quartos sem reservas.',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID interno do quarto.',
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    example: 2,
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto excluído.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/MessageResponse',
                    example: ['message' => 'Quarto excluido com sucesso.'],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Quarto não encontrado.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: ['message' => 'Quarto não encontrado.'],
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'Quarto com reservas não pode ser excluído.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: ['message' => 'Nao e permitido excluir um quarto com reservas.'],
                ),
            ),
        ],
    )]
    public function deleteRoom(): void
    {
    }

}
