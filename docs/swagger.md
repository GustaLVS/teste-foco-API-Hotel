# Documentação OpenAPI/Swagger

## Instalação e arquivos

Pré-requisito: pacote `darkaonline/l5-swagger` instalado pelo Composer.
Os arquivos usam atributos nativos do PHP e são compatíveis com as versões 9 e 10 do L5-Swagger.

Se ainda não instalou/publicou a configuração, execute na pasta do projeto:

```powershell
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

Copie a pasta `app/OpenApi` deste pacote para a pasta `app` do projeto.
Copie este documento para `docs/swagger.md`.

## Configuração

No arquivo `config/l5-swagger.php`, dentro de `documentations > default > api`, altere o título:

```php
'title' => 'API de quartos e reservas',
```

Dentro de `documentations > default > paths`, substitua somente a lista `annotations` por:

```php
'annotations' => [
    base_path('app/OpenApi'),
],
```

A especificação usa OpenAPI 3.0.0, que é o padrão do pacote. Se você já definiu a variável `L5_SWAGGER_OPEN_API_SPEC_VERSION`, use `3.0.0` para estes arquivos.

## Geração e acesso

Execute um comando por vez, na pasta do projeto:

```powershell
composer dump-autoload
php artisan config:clear
php artisan l5-swagger:generate
```

Confira as rotas:

```powershell
php artisan route:list --path=api/documentation
```

Inicie o servidor local e mantenha esse terminal aberto:

```powershell
php artisan serve
```

Abra http://127.0.0.1:8000/api/documentation no navegador.
O arquivo JSON gerado, na configuração padrão, fica em `storage/api-docs/api-docs.json`.

O servidor OpenAPI é `/api`, relativo ao servidor que hospeda a documentação.
As operações usam `/rooms` e `/reservations`, formando as URLs `/api/rooms` e `/api/reservations`.

## Endpoints documentados

| Método | Endpoint | Respostas documentadas |
|---|---|---|
| GET | /api/rooms | 200, 422 |
| POST | /api/rooms | 201, 422 |
| GET | /api/rooms/{id} | 200, 404 |
| PUT | /api/rooms/{id} | 200, 404, 409, 422 |
| PATCH | /api/rooms/{id} | 200, 404, 409, 422 |
| DELETE | /api/rooms/{id} | 200, 404, 409 |
| POST | /api/reservations | 201, 409, 422 |

## Exemplos e regras

- IDs recebidos são os IDs internos do banco. Ajuste os exemplos aos registros existentes.
- `external_id` é reservado à importação XML. Na criação de reservas, enviar esse campo causa erro 422.
- Valores monetários de reservas devem ser strings como `"150.00"`.
- Uma diária corresponde a cada noite; o checkout não recebe diária.
- A soma das diárias deve ser igual ao total da reserva.
- Pagamentos são opcionais e podem ser parciais.
- Reservas sobrepostas no mesmo quarto são rejeitadas com 409.
- Uma entrada no checkout anterior e uma saída no checkin seguinte são permitidas.
- Repetir o exemplo de uma reserva já cadastrada retorna conflito; escolha outro período disponível.
- O cadastro imediato de um quarto pode omitir `external_id` na resposta, embora o banco armazene nulo.

O botão **Try it out** envia requisições reais à API. POST, PUT, PATCH e DELETE gravam alterações no banco usado pelo servidor.

## Verificação

Após copiar e configurar os arquivos:

1. Execute `php artisan l5-swagger:generate` e confira se não há erros.
2. Abra a interface e confira os grupos Quartos e Reservas, com sete operações.
3. Use Try it out no GET /api/rooms e confira o HTTP 200 com a lista paginada.
4. Em outro terminal, execute `php artisan test` para verificar a suíte da aplicação.

Sempre gere novamente a documentação depois de alterar os atributos em `app/OpenApi`.

Os arquivos deste pacote definem a documentação. A geração pelo L5-Swagger e a conferência da interface devem ser realizadas no projeto local.

## Referências

- [Instalação do L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger/wiki/Installation-%26-Configuration).
- [Exemplos de atributos do L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger/wiki/Examples).
- [Especificação OpenAPI 3.0](https://spec.openapis.org/oas/v3.0.3.html).
