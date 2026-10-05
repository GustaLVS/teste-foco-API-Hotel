# API Hotel — Foco

Projeto desenvolvido como **desafio técnico para uma vaga na FocoMultimídia**.

**Desenvolvido por Gustavo Alves Souza.**

API REST para gerenciamento de quartos e criação de reservas, com importação de hotéis, quartos e reservas a partir de arquivos XML.

O projeto utiliza Laravel 12 e MySQL, com documentação OpenAPI/Swagger, ambiente Docker Compose e testes automatizados.

## Funcionalidades

- Importação XML e atualização dos registros pela correspondência de `external_id`.
- Rejeição de reservas inconsistentes com registro dos motivos no log.
- Agendamento da importação a cada hora.
- CRUD de quartos com filtro por hotel e paginação.
- Criação transacional de reservas com hóspedes, diárias e pagamentos.
- Validação de datas, diárias, valores monetários e disponibilidade do quarto.
- Documentação interativa dos endpoints com Swagger.

## Tecnologias

| Componente | Tecnologia |
|---|---|
| Aplicação | PHP 8.2 e Laravel 12 |
| Banco da aplicação | MySQL |
| Ambiente Docker | PHP 8.2, MySQL 8.4 e Docker Compose |
| Testes | PHPUnit e SQLite em memória |
| Documentação da API | OpenAPI 3.0 e L5-Swagger |

## Obter o projeto

```bash
git clone https://github.com/GustaLVS/teste-foco-API-Hotel.git
cd teste-foco-API-Hotel
```

Escolha a instalação com Docker ou a instalação local. Execute os comandos na raiz do projeto.

## Instalação com Docker

É necessário ter Docker e Docker Compose disponíveis. No Windows, utilize Docker Desktop com containers Linux. A aplicação utiliza a porta local `8080`.

### 1. Preparar o ambiente

Para uma nova instalação, copie o arquivo de exemplo.

No PowerShell:

```powershell
Copy-Item .env.docker.example .env.docker
```

No Linux ou macOS:

```bash
cp .env.docker.example .env.docker
```

O arquivo `.env.docker` fornece as variáveis ao container e é ignorado pelo Git. As credenciais de desenvolvimento do exemplo correspondem às definidas em `compose.yaml`.

### 2. Construir a imagem e gerar a chave

```bash
docker compose config --quiet
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate --show
```

Copie a chave exibida pelo último comando, incluindo o prefixo `base64:`, e informe seu valor em `APP_KEY` no arquivo `.env.docker`. Salve o arquivo.

### 3. Iniciar a aplicação e criar as tabelas

```bash
docker compose up -d app
docker compose exec app php artisan migrate
```

O Compose inicia o MySQL como dependência da aplicação e aguarda seu healthcheck.

### 4. Importar os XMLs e gerar o Swagger

Execute cada comando separadamente:

```bash
docker compose exec app php artisan hotel:import-xml
docker compose exec app php artisan l5-swagger:generate
```

**A importação dos XMLs originais é parcial e retorna código de saída 1.** São processados 3 hotéis, 6 quartos e 5 reservas; uma reserva é rejeitada pela diária fora do período. Consulte [Dados de entrada e importação](#dados-de-entrada-e-importação).

### 5. Acessar a API e executar os testes

| Recurso | Endereço no Docker |
|---|---|
| Listagem de quartos | [http://127.0.0.1:8080/api/rooms](http://127.0.0.1:8080/api/rooms) |
| Swagger | [http://127.0.0.1:8080/api/documentation](http://127.0.0.1:8080/api/documentation) |

```bash
docker compose exec app php artisan test
```

Resultado validado: **48 testes aprovados**.

### 6. Ativar a importação agendada

Depois de preparar o banco:

```bash
docker compose --profile scheduler up -d scheduler
docker compose exec app php artisan schedule:list
docker compose --profile scheduler ps
```

A tarefa `hotel:import-xml` está configurada para o início de cada hora, com `withoutOverlapping()`. O serviço `scheduler` precisa permanecer em execução.

### Uso posterior

Para iniciar novamente todos os serviços:

```bash
docker compose --profile scheduler up -d
```

Após alterar o código PHP, reconstrua a imagem e atualize os serviços:

```bash
docker compose build app
docker compose --profile scheduler up -d app scheduler
```

Para encerrar:

```bash
docker compose --profile scheduler down
```

Os dados persistem nos volumes do Docker. Os XMLs são montados somente para leitura, e a aplicação publica apenas um endereço local.

Veja os detalhes de configuração, persistência e logs em [docs/docker.md](docs/docker.md).

## Instalação local sem Docker

Para uma nova instalação, prepare:

- PHP 8.2 ou superior compatível com as dependências do projeto.
- Composer 2.
- MySQL e um banco destinado à aplicação.
- Extensões PHP necessárias ao Laravel e às dependências; o importador requer SimpleXML e a conexão MySQL requer `pdo_mysql`.
- `pdo_sqlite` para os testes em memória.

Instale as dependências:

```bash
composer install
```

Copie `.env.example` para `.env` usando `Copy-Item` no PowerShell ou `cp` no Linux/macOS.

Configure a conexão com seu banco no `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=foco_hotel_api
DB_USERNAME=SEU_USUARIO
DB_PASSWORD=SUA_SENHA
```

Substitua usuário e senha pelos valores da sua instalação. O banco deve existir antes das migrations.

Execute um comando por vez:

```bash
php artisan key:generate
php artisan config:clear
php artisan migrate
php artisan hotel:import-xml
php artisan l5-swagger:generate
php artisan serve
```

A importação parcial esperada também retorna código de saída `1` neste ambiente. Continue com a geração do Swagger após conferir o resultado.

O servidor mantém o terminal ocupado. Use outro terminal para os demais comandos.

| Recurso | Endereço local |
|---|---|
| Listagem de quartos | [http://127.0.0.1:8000/api/rooms](http://127.0.0.1:8000/api/rooms) |
| Swagger | [http://127.0.0.1:8000/api/documentation](http://127.0.0.1:8000/api/documentation) |

Para manter o agendador ativo durante o desenvolvimento, execute em outro terminal:

```bash
php artisan schedule:work
```

A configuração do agendamento e a alternativa com cron estão descritas em [docs/importacao.md](docs/importacao.md).

## Endpoints

Envie `Accept: application/json`. Para requisições com corpo JSON, envie também `Content-Type: application/json`.

| Método | Endpoint | Operação | HTTP de sucesso |
|---|---|---|---|
| GET | `/api/rooms` | Listar quartos | 200 |
| POST | `/api/rooms` | Cadastrar quarto | 201 |
| GET | `/api/rooms/{id}` | Consultar quarto | 200 |
| PUT | `/api/rooms/{id}` | Atualizar nome e hotel | 200 |
| PATCH | `/api/rooms/{id}` | Atualizar parcialmente | 200 |
| DELETE | `/api/rooms/{id}` | Excluir quarto | 200 |
| POST | `/api/reservations` | Criar reserva com relacionamentos | 201 |

A listagem aceita os parâmetros opcionais `hotel_id`, `per_page` e `page`.

Exemplo no Docker:

```text
GET http://127.0.0.1:8080/api/rooms?per_page=5&page=1
```

Os endpoints utilizam os **IDs internos do banco**. Consulte a listagem para obter os IDs do ambiente em uso. O campo `external_id` identifica a origem no XML e é controlado pela importação.

Os corpos das requisições, exemplos de respostas e parâmetros estão em [docs/api.md](docs/api.md) e na interface Swagger.

## Regras de negócio

- Quartos com reservas não podem ser excluídos nem transferidos para outro hotel.
- O nome de um quarto com reservas pode ser atualizado.
- PUT de quarto exige `hotel_id` e `name`; PATCH valida os campos enviados.
- A saída da reserva deve ser posterior à entrada.
- Uma reserva exige pelo menos um hóspede e uma diária para cada noite.
- As datas das diárias incluem o check-in e excluem o check-out, sem repetições.
- Valores monetários devem ser enviados como strings com duas casas decimais.
- O total da reserva deve corresponder à soma das diárias.
- Pagamentos são opcionais e podem ser parciais.
- Na criação de reservas pela API, períodos sobrepostos no mesmo quarto são bloqueados.
- Uma nova reserva pode começar na data do checkout anterior.
- Reservas em quartos diferentes podem ter o mesmo período.

O cadastro de uma reserva e de seus hóspedes, diárias e pagamentos ocorre em uma transação.

| HTTP de erro | Situação |
|---|---|
| 404 | Quarto não encontrado |
| 409 | Operação de quarto bloqueada por reservas ou conflito de disponibilidade |
| 422 | Dados inválidos ou campos obrigatórios ausentes |

Os erros dos endpoints da API são apresentados em JSON.

## Dados de entrada e importação

Os arquivos originais ficam em:

```text
storage/app/import/hotels.xml
storage/app/import/rooms.xml
storage/app/import/reserves.xml
```

A importação utiliza os códigos externos para relacionar hotéis, quartos e reservas. Uma nova execução atualiza os registros correspondentes pela associação de `external_id`.

No arquivo `reserves.xml`, a reserva de código externo `6` tem período de `2022-10-01` a `2022-10-04`, mas contém uma diária em `2022-12-03`. Essa reserva é rejeitada inteira, enquanto as demais reservas válidas são processadas.

| Resultado com os XMLs originais | Quantidade |
|---|---|
| Hotéis processados | 3 |
| Quartos processados | 6 |
| Reservas processadas | 5 |
| Reservas rejeitadas | 1 |

Os arquivos originais são preservados. A inconsistência é registrada no log e informada na saída do comando.

Registros criados pela API possuem `external_id` nulo. Alterações pela API em quartos importados podem ser substituídas pelos dados do XML na próxima importação.

Para usar outra pasta de entrada:

```bash
php artisan hotel:import-xml --path=CAMINHO_DA_PASTA
```

No Docker, o caminho deve existir dentro do container. O caminho padrão já corresponde à pasta de importação montada pelo Compose.

Mais detalhes: [docs/importacao.md](docs/importacao.md).

## Testes automatizados

No ambiente local:

```bash
php artisan config:clear
php artisan test
```

No Docker:

```bash
docker compose exec app php artisan test
```

| Arquivo | Testes | Cobertura principal |
|---|---|---|
| `RoomApiTest.php` | 12 | CRUD, filtros, paginação, validações e restrições por reservas |
| `ReservationApiTest.php` | 16 | Cadastro, relacionamentos, valores e rollback |
| `ReservationAvailabilityTest.php` | 10 | Sobreposição, períodos adjacentes e quartos distintos |
| `ImportHotelXmlTest.php` | 8 | Importação parcial, repetição, códigos externos e XMLs inválidos |

Os 46 testes específicos do projeto, somados aos 2 testes de exemplo do Laravel, resultam em **48 testes aprovados**. A suíte foi validada no ambiente local e no Docker.

O `phpunit.xml` configura SQLite em memória e uma chave exclusiva dos testes. As configurações principais são definidas em `<env>` e `<server>` para assegurar o ambiente de teste dentro do container.

## Documentação

- [API de quartos e reservas](docs/api.md)
- [Importação XML e agendamento](docs/importacao.md)
- [Swagger e geração da especificação](docs/swagger.md)
- [Especificação OpenAPI em JSON](docs/openapi.json)
- [Ambiente Docker](docs/docker.md)
