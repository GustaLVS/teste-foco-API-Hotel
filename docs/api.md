# API de quartos e reservas

## Endereço local

http://127.0.0.1:8000/api

Envie os cabeçalhos:

- `Accept: application/json`.
- `Content-Type: application/json` nas requisições com corpo JSON.

Os endpoints utilizam os IDs internos do banco. `external_id` identifica o
código de origem do XML e não é preenchido pela API.

## Endpoints

| Método | Endpoint | Operação | HTTP de sucesso |
|---|---|---|---|
| GET | /api/rooms | Listar quartos | 200 |
| POST | /api/rooms | Cadastrar quarto | 201 |
| GET | /api/rooms/{id} | Consultar quarto | 200 |
| PUT | /api/rooms/{id} | Atualizar nome e hotel | 200 |
| PATCH | /api/rooms/{id} | Atualizar parcialmente | 200 |
| DELETE | /api/rooms/{id} | Excluir quarto | 200 |
| POST | /api/reservations | Cadastrar reserva | 201 |

## Quartos

### Listagem

`GET /api/rooms`

A resposta contém `data` com os quartos e informações de paginação.
Cada quarto inclui os dados de seu hotel.

Parâmetros opcionais:

| Parâmetro | Regra |
|---|---|
| hotel_id | ID interno de um hotel existente |
| per_page | Inteiro de 1 a 100; padrão 15 |
| page | Inteiro maior ou igual a 1 |

Exemplo:

```http
GET /api/rooms?hotel_id=2&per_page=5&page=1
```

### Cadastro

`POST /api/rooms`

```json
{
  "hotel_id": 2,
  "name": "Quarto de teste da API"
}
```

Substitua `hotel_id` pelo ID de um hotel existente no seu banco.

Campos obrigatórios:

- `hotel_id`: inteiro positivo correspondente a um hotel existente.
- `name`: texto não vazio com até 255 caracteres.

A resposta contém `message` e `data` com o quarto cadastrado e seu hotel.
O registro é criado com `external_id` nulo no banco.

### Atualização

`PUT /api/rooms/{id}` exige `hotel_id` e `name`.

`PATCH /api/rooms/{id}` aceita somente os campos que serão alterados.
Os campos enviados devem respeitar as mesmas regras do cadastro.

Exemplo de PATCH:

```json
{
  "name": "Quarto atualizado"
}
```

A resposta contém `message` e `data` com o quarto atualizado e seu hotel.

### Consulta e exclusão

`GET /api/rooms/{id}` retorna o quarto e seu hotel dentro de `data`.

`DELETE /api/rooms/{id}` retorna uma mensagem de confirmação em JSON.

### Regras de negócio

- Um quarto com reservas não pode ser excluído.
- Um quarto com reservas não pode ser transferido para outro hotel.
- O nome de um quarto com reservas pode ser atualizado.
- A API utiliza somente os campos validados para cadastrar e atualizar.

## Reservas

### Criação

`POST /api/reservations`

Cria uma reserva com hóspedes, diárias e pagamentos opcionais.

Exemplo de corpo JSON:

```json
{
  "room_id": 2,
  "check_in": "2026-11-10",
  "check_out": "2026-11-12",
  "total": "300.00",
  "guests": [
    {
      "name": "Hospede",
      "last_name": "Teste API",
      "phone": "77999999999"
    }
  ],
  "dailies": [
    {"date": "2026-11-10", "value": "150.00"},
    {"date": "2026-11-11", "value": "150.00"}
  ],
  "payments": [
    {"method": 1, "value": "100.00"}
  ]
}
```

Substitua `room_id` pelo ID de um quarto existente e escolha um período
disponível. Se a reserva desse exemplo já estiver cadastrada, repetir a
requisição retornará `409 Conflict`.

A resposta de sucesso contém `message` e `data` com a reserva, o quarto e seu
hotel, os hóspedes, as diárias e os pagamentos. O registro é criado com
`external_id` nulo no banco.

### Validação

- `room_id` deve ser um inteiro positivo correspondente a um quarto existente.
- As datas devem seguir o formato `YYYY-MM-DD`.
- `check_out` deve ser posterior a `check_in`.
- É obrigatório informar pelo menos um hóspede.
- Cada hóspede deve conter `name`, `last_name` e `phone` como textos não vazios.
- `name` e `last_name` aceitam até 255 caracteres; `phone`, até 30.
- Deve existir uma diária para cada noite da hospedagem, sem datas repetidas.
- As datas das diárias incluem o checkin e excluem o checkout.
- `total` deve corresponder à soma dos valores das diárias.
- Os valores monetários devem ser strings não negativas, com ponto decimal,
  duas casas decimais e até dez dígitos antes do ponto, como `"150.00"`.
- `payments` pode ser omitido ou enviado como uma lista vazia.
- Cada pagamento informado deve conter `method` e `value`.
- `method` deve ser um inteiro de 0 a 65535.
- Pagamentos podem ser parciais; sua soma não precisa ser igual ao total.
- `external_id` é reservado à importação XML e não deve ser enviado.

A reserva e seus hóspedes, diárias e pagamentos são gravados na mesma
transação. Um conflito de disponibilidade é verificado antes dos cadastros.

### Disponibilidade do quarto

Na criação pela API, uma reserva é rejeitada quando existe outra reserva do
mesmo quarto que começa antes da nova saída e termina depois da nova entrada.

A condição de sobreposição é:

```text
check_in existente < check_out solicitado
E
check_out existente > check_in solicitado
```

É permitido:

- Entrar no dia do checkout da reserva anterior.
- Sair no dia do checkin da reserva seguinte.
- Reservar outro quarto no mesmo período.

A verificação e o cadastro são realizados dentro de uma transação, após
bloquear o registro do quarto com `lockForUpdate()`.

Respostas do cadastro de reservas:

| HTTP | Situação |
|---|---|
| 201 | Reserva cadastrada |
| 409 | O quarto já possui uma reserva sobreposta ao período solicitado |
| 422 | Dados inválidos ou campos obrigatórios ausentes |

Mensagem de conflito:

```json
{
  "message": "O quarto ja possui uma reserva que se sobrepoe ao periodo informado."
}
```

## Erros

| HTTP | Situação |
|---|---|
| 404 | Quarto não encontrado na consulta, atualização ou exclusão |
| 409 | Exclusão ou troca de hotel bloqueada por reservas existentes; ou período de reserva indisponível para o quarto |
| 422 | Dados inválidos ou campos obrigatórios ausentes |

Os erros da API são apresentados em JSON. Informar um `hotel_id` ou `room_id`
inexistente no corpo de uma requisição retorna `422` por falha de validação.

## Relação com a importação

A importação atualiza os registros identificados pelo `external_id`.
Alterações feitas pela API em quartos importados podem ser substituídas
pelos dados do XML em uma nova execução.

Quartos e reservas criados pela API possuem `external_id` nulo e não são
atualizados pela correspondência dos códigos de origem dos XMLs.

## Verificação realizada

### Quartos

Foram testados manualmente:

- Listagem, cadastro, consulta, atualização por PATCH e exclusão.
- Rejeição de nome vazio e hotel inexistente: `422`.
- Consulta de quarto excluído: `404`.
- Bloqueio de exclusão e troca de hotel de quarto com reservas: `409`.
- Rejeição de PUT sem os campos obrigatórios: `422`.

### Validação das reservas

Foram testados manualmente:

- Criação válida com hóspedes, diárias e pagamento parcial: `201`.
- Saída igual à entrada: `422`.
- Diária fora do período: `422`.
- Diária duplicada: `422`.
- Total diferente da soma das diárias: `422`.

### Disponibilidade das reservas

| Cenário | HTTP verificado |
|---|---|
| Período idêntico | 409 |
| Entrada dentro de uma reserva existente | 409 |
| Saída dentro de uma reserva existente | 409 |
| Período contido em outra reserva | 409 |
| Período envolvendo outra reserva | 409 |
| Entrada no checkout anterior | 201 |
| Saída no checkin seguinte | 201 |
| Mesmo período em outro quarto | 201 |

## Testes automatizados

A suíte completa foi executada com 48 testes aprovados.

Os testes específicos da aplicação estão distribuídos assim:

| Arquivo | Testes |
|---|---:|
| RoomApiTest | 12 |
| ReservationApiTest | 16 |
| ReservationAvailabilityTest | 10 |
| ImportHotelXmlTest | 8 |

### Execução

Na pasta do projeto:

```bash
php artisan test
```

Para executar um grupo específico:

```bash
php artisan test --filter=RoomApiTest
php artisan test --filter=ReservationApiTest
php artisan test --filter=ReservationAvailabilityTest
php artisan test --filter=ImportHotelXmlTest
```

### Ambiente

- Banco SQLite em memória, configurado no phpunit.xml.
- Dados de teste isolados com RefreshDatabase.
- Importação testada com cópias temporárias dos XMLs.
- Verificação das respostas HTTP e dos registros no banco.
- Verificação do rollback quando a gravação de um pagamento falha.
- Verificação da repetição da importação sem duplicatas.
- Verificação da preservação de uma reserva existente quando seu XML é rejeitado.


## Documentação OpenAPI/Swagger

Interface local: http://127.0.0.1:8000/api/documentation

- [Configuração e geração do Swagger](swagger.md)
- [Especificação OpenAPI em JSON](openapi.json)

Verificações realizadas:
- Interface Swagger carregada.
- GET de quartos executado pela interface com HTTP 200.
- Suíte completa executada após a instalação: 48 testes aprovados.