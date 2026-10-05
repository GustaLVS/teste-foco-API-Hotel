<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API de quartos e reservas',
    description: 'CRUD de quartos e criação de reservas. Todos os IDs recebidos são internos ao banco. external_id é exclusivo da importação XML. Envie Accept: application/json e Content-Type: application/json nas requisições com corpo. Escolha IDs existentes e datas disponíveis nos exemplos.',
)]
#[OA\Server(
    url: '/api',
    description: 'API no mesmo servidor da documentação',
)]
#[OA\Tag(
    name: 'Quartos',
    description: 'Cadastro, consulta, atualização e exclusão de quartos.',
)]
#[OA\Tag(
    name: 'Reservas',
    description: 'Criação de reservas com hóspedes, diárias e pagamentos opcionais.',
)]
final class ApiDocumentation
{
}
