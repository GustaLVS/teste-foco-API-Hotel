# Ambiente Docker

Este ambiente executa a API Laravel com PHP 8.2, MySQL 8.4 e um agendador opcional para importar os XMLs a cada hora. Foi preparado para desenvolvimento e avaliação local, usando `php artisan serve`.

## Requisitos

- Docker Engine com Docker Compose; no Windows, Docker Desktop com containers Linux.
- Porta local `8080` disponível.
- Arquivos `Dockerfile`, `.dockerignore`, `compose.yaml` e `.env.docker.example` na raiz do projeto.
- XMLs originais em `storage/app/import`: `hotels.xml`, `rooms.xml` e `reserves.xml`.

Execute os comandos deste documento na raiz do projeto.

## Serviços

| Serviço | Responsabilidade | Acesso |
|---|---|---|
| `app` | Executar a API Laravel | `http://127.0.0.1:8080` |
| `mysql` | Banco de dados do ambiente Docker | Host `mysql`, porta `3306`, dentro da rede do Compose |
| `scheduler` | Executar `php artisan schedule:work` | Ativado pelo perfil `scheduler` |

A aplicação aguarda o healthcheck do MySQL antes de iniciar. O MySQL não publica uma porta no computador; sua conexão é feita pela rede interna do Compose.

## Primeira execução

### 1. Criar o arquivo de ambiente

No PowerShell:

```powershell
Copy-Item .env.docker.example .env.docker
```

No Linux ou macOS:

```bash
cp .env.docker.example .env.docker
```

Este passo é necessário apenas na preparação inicial. Em execuções posteriores, mantenha o arquivo já configurado.

O Compose usa `.env.docker` para fornecer as variáveis ao container. O `.env` usado na instalação local do projeto é independente.

As credenciais de desenvolvimento de `.env.docker.example` correspondem às configuradas em `compose.yaml`:

| Variável da aplicação | Valor |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `mysql` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `foco_hotel_api` |
| `DB_USERNAME` | `foco` |
| `DB_PASSWORD` | `foco_dev_password` |

A senha do usuário `root` do MySQL é o exemplo `root_dev_password`. A aplicação utiliza o usuário `foco`.

O arquivo `.env.docker` é ignorado pelo Git; `.env.docker.example` contém os valores de exemplo que podem ser versionados.

### 2. Validar o Compose e construir a imagem

```bash
docker compose config --quiet
docker compose build app
```

A imagem é identificada como `foco-hotel-api:local`. O build instala as dependências do Composer, incluindo as necessárias para executar os testes.

### 3. Gerar a chave da aplicação

```bash
docker compose run --rm --no-deps app php artisan key:generate --show
```

Copie a linha completa que começa com `base64:` e coloque seu valor em `APP_KEY` no arquivo `.env.docker`:

```dotenv
APP_KEY=base64:CHAVE_GERADA_PELO_COMANDO
```

A expressão acima indica onde inserir a chave; substitua-a pelo valor gerado. Salve o arquivo antes de iniciar a aplicação.

### 4. Iniciar a aplicação e criar as tabelas

```bash
docker compose up -d app
docker compose exec app php artisan migrate
```

O serviço `mysql` será iniciado como dependência de `app`. As migrations criam as tabelas no banco do Docker.

### 5. Importar os XMLs

```bash
docker compose exec app php artisan hotel:import-xml
```

Com os XMLs originais, o resultado esperado é:

```text
Hoteis processados: 3
Quartos processados: 6
Reservas processadas: 5
Reservas rejeitadas: 1
Reserva 6 rejeitada: Diaria 2022-12-03 fora do periodo 2022-10-01 a 2022-10-04.
Importacao parcial. Consulte o log para os detalhes.
```

A reserva de código externo `6` contém uma diária fora do período. Sua rejeição é esperada, e os arquivos originais devem ser preservados. A importação parcial retorna código de saída `1`, mesmo tendo processado os registros válidos.

A importação pode ser repetida: os registros são identificados pelo `external_id`, conforme descrito em [Importação XML](importacao.md).

### 6. Gerar o Swagger

```bash
docker compose exec app php artisan l5-swagger:generate
```

Acesse:

- [Listagem de quartos](http://127.0.0.1:8080/api/rooms)
- [Documentação Swagger](http://127.0.0.1:8080/api/documentation)

Após a importação inicial, a listagem deve apresentar `total: 6`. Os IDs internos desse banco podem ser diferentes dos IDs da instalação local; consulte a listagem antes de enviar requisições que usam `room_id` ou `hotel_id`.

### 7. Executar os testes

```bash
docker compose exec app php artisan test
```

Resultado validado: **48 testes aprovados**.

Os testes usam SQLite em memória. O `phpunit.xml` define uma chave exclusiva para testes e as configurações de ambiente, banco, cache e sessão. As configurações principais também são definidas com elementos `<server>` dentro de `<php>`, garantindo que os valores de teste sejam usados quando o PHP recebe variáveis do container em `$_SERVER`.

O Dockerfile cria um `.env` vazio dentro da imagem com `RUN touch .env`. Isso permite que o carregador de ambiente leia o arquivo sem gerar avisos de arquivo ausente. As configurações da aplicação em execução continuam sendo fornecidas pelo Compose.

## Importação agendada

Após preparar o banco, inicie o agendador:

```bash
docker compose --profile scheduler up -d scheduler
```

Confira a tarefa e os serviços:

```bash
docker compose exec app php artisan schedule:list
docker compose --profile scheduler ps
```

A expressão `0 * * * *` indica execução no início de cada hora. O agendamento usa `withoutOverlapping()` e grava a saída em `storage/logs/xml-import.log`.

A tarefa só será executada enquanto o serviço `scheduler` estiver rodando. A primeira execução automática depende do próximo horário agendado.

Consulte a saída do agendador:

```bash
docker compose logs --tail=50 scheduler
```

Após uma execução agendada, consulte o log da importação:

```bash
docker compose exec app tail -n 50 storage/logs/xml-import.log
```

Com os XMLs originais, a importação agendada também terá resultado parcial por causa da reserva inválida.

## Persistência e arquivos

| Recurso | Armazenamento |
|---|---|
| Banco MySQL | Volume `mysql-data` |
| Logs e arquivos de execução do Laravel | Volume `app-storage`, compartilhado entre `app` e `scheduler` |
| XMLs de entrada | Pasta `storage/app/import` do projeto, montada somente para leitura |
| Código e dependências PHP | Imagem construída pelo Dockerfile |

A montagem dos XMLs permite atualizar os arquivos de entrada no computador e disponibilizá-los aos serviços. O código PHP é copiado para a imagem e exige reconstrução quando alterado.

Os arquivos gerados em `storage/api-docs` ficam no armazenamento do container. A especificação versionada do projeto é [docs/openapi.json](openapi.json).

## Comandos de uso

### Iniciar novamente todos os serviços

```bash
docker compose --profile scheduler up -d
```

### Aplicar alterações no código

```bash
docker compose build app
docker compose --profile scheduler up -d app scheduler
```

Depois de alterações nas anotações OpenAPI, gere novamente o Swagger:

```bash
docker compose exec app php artisan l5-swagger:generate
```

### Ver o estado e os logs

```bash
docker compose --profile scheduler ps
docker compose logs --tail=50 app
docker compose exec app tail -n 50 storage/logs/laravel.log
```

### Encerrar os serviços

```bash
docker compose --profile scheduler down
```

Esse comando remove os containers e a rede do Compose, preservando os volumes e os dados armazenados neles.

## Verificações realizadas

- Construção da imagem da aplicação.
- MySQL disponível com healthcheck aprovado.
- Execução das migrations.
- Listagem da API no endereço do Docker.
- Suíte completa com 48 testes aprovados após os ajustes de configuração.
- Serviço `scheduler` iniciado.
- Tarefa horária `hotel:import-xml` confirmada em `schedule:list`.

## Documentação relacionada

- [API de quartos e reservas](api.md)
- [Importação XML](importacao.md)
- [Configuração do Swagger](swagger.md)

