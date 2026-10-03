# Importação dos XMLs

## Arquivos

Os exemplos originais estão em storage/app/import:

- hotels.xml
- rooms.xml
- reserves.xml

## Execução manual

```bash
php artisan hotel:import-xml
```

Para utilizar outra pasta:

```bash
php artisan hotel:import-xml --path="/caminho/dos/xmls"
```

A repetição atualiza registros pelos códigos de origem sem acumular
registros filhos. Cada reserva é gravada em uma transação.

A reserva 6 é rejeitada pela diária fora do período. Com os exemplos
originais, são importados 3 hotéis, 6 quartos e 5 reservas.

O código de saída é 0 quando não há rejeições e 1 quando há uma
falha ou importação parcial. Os detalhes ficam no log da aplicação.

## Agendamento

A tarefa está definida em routes/console.php para executar no início
de cada hora, com proteção contra sobreposição entre execuções
agendadas. Sua saída fica em storage/logs/xml-import.log.

Para conferir e testar:

```bash
php artisan schedule:list
php artisan schedule:test
```

## Desenvolvimento local

```bash
php artisan schedule:work
```

Esse processo mantém o scheduler ativo enquanto o terminal estiver
aberto. Encerre com Ctrl+C.

## CRON em servidor Linux

Configure o CRON para chamar o scheduler a cada minuto:

```cron
* * * * * cd "/caminho/do/projeto" && /usr/bin/php artisan schedule:run >> "/caminho/do/projeto/storage/logs/scheduler.log" 2>&1
```

Substitua os caminhos pelos valores reais do servidor, incluindo
o executável PHP. O CRON verifica a agenda a cada minuto, enquanto
o Laravel executa a importação somente no horário configurado.

O usuário do CRON precisa de acesso ao banco, leitura dos XMLs e
permissão de escrita em storage e bootstrap/cache.