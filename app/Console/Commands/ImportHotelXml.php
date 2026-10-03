<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class ImportHotelXml extends Command
{
    protected $signature = 'hotel:import-xml
        {--path= : Caminho da pasta que contem os XMLs}';

    protected $description = 'Importa hoteis e quartos dos arquivos XML';

    public function handle(): int
    {
        $directory = $this->option('path') ?: storage_path('app/import');
        $directory = rtrim($directory, '/\\');

        try {
            $hotelsXml = $this->readXml(
                $directory . '/hotels.xml',
                'Hotels'
            );

            $roomsXml = $this->readXml(
                $directory . '/rooms.xml',
                'Rooms'
            );

            $counts = DB::transaction(function () use (
                $hotelsXml,
                $roomsXml
            ) {
                return [
                    'hotels' => $this->importHotels($hotelsXml),
                    'rooms' => $this->importRooms($roomsXml),
                ];
            });

            $this->info('Importacao de hoteis e quartos concluida.');
            $this->line("Hoteis processados: {$counts['hotels']}");
            $this->line("Quartos processados: {$counts['rooms']}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Falha na importacao dos XMLs de hoteis e quartos.', [
                'message' => $exception->getMessage(),
            ]);

            $this->error('Importacao interrompida: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function readXml(string $path, string $root): SimpleXMLElement
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                "Arquivo nao encontrado ou sem permissao de leitura: {$path}"
            );
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Nao foi possivel ler: {$path}");
        }

        if (stripos($content, '<!DOCTYPE') !== false) {
            throw new RuntimeException(
                "Declaracoes DOCTYPE nao sao permitidas: {$path}"
            );
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $content,
                SimpleXMLElement::class,
                LIBXML_NONET
            );

            if ($xml === false) {
                throw new RuntimeException("XML malformado: {$path}");
            }

            if ($xml->getName() !== $root) {
                throw new RuntimeException(
                    "Elemento raiz incorreto em {$path}. Esperado: {$root}"
                );
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function importHotels(SimpleXMLElement $xml): int
    {
        if (count($xml->Hotel) === 0) {
            throw new RuntimeException('hotels.xml nao possui hoteis.');
        }

        $count = 0;
        $seen = [];

        foreach ($xml->Hotel as $item) {
            $data = Validator::make([
                'external_id' => (string) $item['id'],
                'name' => trim((string) $item->Name),
            ], [
                'external_id' => ['required', 'integer', 'min:1'],
                'name' => ['required', 'string', 'max:255'],
            ])->validate();

            $externalId = (int) $data['external_id'];

            if (isset($seen[$externalId])) {
                throw new RuntimeException(
                    "Hotel com codigo repetido no XML: {$externalId}"
                );
            }

            $seen[$externalId] = true;

            Hotel::updateOrCreate(
                ['external_id' => $externalId],
                ['name' => $data['name']]
            );

            $count++;
        }

        return $count;
    }

    private function importRooms(SimpleXMLElement $xml): int
    {
        if (count($xml->Room) === 0) {
            throw new RuntimeException('rooms.xml nao possui quartos.');
        }

        $count = 0;
        $seen = [];

        foreach ($xml->Room as $item) {
            $data = Validator::make([
                'external_id' => (string) $item['id'],
                'hotel_code' => (string) $item['hotelCode'],
                'name' => trim((string) $item->Name),
            ], [
                'external_id' => ['required', 'integer', 'min:1'],
                'hotel_code' => ['required', 'integer', 'min:1'],
                'name' => ['required', 'string', 'max:255'],
            ])->validate();

            $externalId = (int) $data['external_id'];

            if (isset($seen[$externalId])) {
                throw new RuntimeException(
                    "Quarto com codigo repetido no XML: {$externalId}"
                );
            }

            $seen[$externalId] = true;

            $hotel = Hotel::where(
                'external_id',
                (int) $data['hotel_code']
            )->first();

            if ($hotel === null) {
                throw new RuntimeException(
                    "Quarto {$externalId}: hotel {$data['hotel_code']} nao encontrado."
                );
            }

            Room::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'hotel_id' => $hotel->id,
                    'name' => $data['name'],
                ]
            );

            $count++;
        }

        return $count;
    }
}