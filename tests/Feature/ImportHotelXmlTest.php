<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use SimpleXMLElement;
use Tests\TestCase;

class ImportHotelXmlTest extends TestCase
{
    use RefreshDatabase;

    private ?string $xmlDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Captura os logs para verificá-los durante os testes.
        Log::spy();

        $this->xmlDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'hotel-xml-test-'
            . Str::uuid();

        File::makeDirectory($this->xmlDirectory, 0755, true);

        foreach (['hotels.xml', 'rooms.xml', 'reserves.xml'] as $file) {
            $source = storage_path('app/import/' . $file);

            $this->assertFileExists($source);

            $this->assertTrue(
                File::copy($source, $this->xmlPath($file))
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if (
                $this->xmlDirectory !== null
                && File::isDirectory($this->xmlDirectory)
            ) {
                File::deleteDirectory($this->xmlDirectory);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_importa_dados_validos_e_rejeita_reserva_inconsistente(): void
    {
        $this->runImport(1);

        $this->assertOriginalImportCounts();

        $this->assertSame(
            [1, 2, 3, 4, 5],
            Reservation::orderBy('external_id')
                ->pluck('external_id')
                ->all()
        );

        $this->assertDatabaseMissing('reservations', [
            'external_id' => 6,
        ]);

        Log::shouldHaveReceived('warning')
            ->atLeast()
            ->once();
    }

    public function test_repetir_importacao_nao_duplica_registros(): void
    {
        $this->runImport(1);

        $hotelIds = Hotel::orderBy('external_id')
            ->pluck('id', 'external_id')
            ->all();

        $roomIds = Room::orderBy('external_id')
            ->pluck('id', 'external_id')
            ->all();

        $reservationIds = Reservation::orderBy('external_id')
            ->pluck('id', 'external_id')
            ->all();

        $this->runImport(1);

        $this->assertOriginalImportCounts();

        $this->assertSame(
            $hotelIds,
            Hotel::orderBy('external_id')
                ->pluck('id', 'external_id')
                ->all()
        );

        $this->assertSame(
            $roomIds,
            Room::orderBy('external_id')
                ->pluck('id', 'external_id')
                ->all()
        );

        $this->assertSame(
            $reservationIds,
            Reservation::orderBy('external_id')
                ->pluck('id', 'external_id')
                ->all()
        );
    }

    public function test_relaciona_codigos_externos_aos_ids_internos(): void
    {
        // Faz os IDs internos dos registros importados serem
        // diferentes dos códigos 1, 2, 3... dos XMLs.
        $hotel = new Hotel();
        $hotel->id = 100;
        $hotel->name = 'Hotel criado pela API';
        $hotel->save();

        $room = new Room();
        $room->id = 100;
        $room->hotel_id = $hotel->id;
        $room->name = 'Quarto criado pela API';
        $room->save();

        $this->runImport(1);

        $importedHotel = Hotel::where('external_id', 1)
            ->firstOrFail();

        $importedRoom = Room::where('external_id', 1)
            ->firstOrFail();

        $importedReservation = Reservation::where('external_id', 1)
            ->firstOrFail();

        $this->assertNotSame(1, $importedHotel->id);
        $this->assertNotSame(1, $importedRoom->id);

        $this->assertSame(
            $importedHotel->id,
            $importedRoom->hotel_id
        );

        $this->assertSame(
            $importedRoom->id,
            $importedReservation->room_id
        );

        $this->assertDatabaseCount('hotels', 4);
        $this->assertDatabaseCount('rooms', 7);
        $this->assertDatabaseCount('reservations', 5);

        $this->assertDatabaseHas('hotels', [
            'id' => 100,
            'external_id' => null,
            'name' => 'Hotel criado pela API',
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => 100,
            'external_id' => null,
            'hotel_id' => 100,
            'name' => 'Quarto criado pela API',
        ]);
    }

    public function test_arquivo_ausente_impede_importacao(): void
    {
        File::delete($this->xmlPath('reserves.xml'));

        $this->runImport(1);

        $this->assertNoImportedRecords();
    }

    #[DataProvider('invalidXmlDocuments')]
    public function test_xml_invalido_impede_importacao(string $xml): void
    {
        File::put($this->xmlPath('hotels.xml'), $xml);

        $this->runImport(1);

        $this->assertNoImportedRecords();
    }

    public static function invalidXmlDocuments(): array
    {
        return [
            'XML malformado' => [
                '<Hotels><Hotel>',
            ],
            'elemento raiz incorreto' => [
                '<WrongRoot/>',
            ],
            'DOCTYPE proibido' => [
                '<?xml version="1.0"?>'
                . '<!DOCTYPE Hotels [<!ENTITY example "test">]>'
                . '<Hotels/>',
            ],
        ];
    }

    public function test_reserva_rejeitada_preserva_cadastro_anterior(): void
    {
        $path = $this->xmlPath('reserves.xml');
        $originalXml = File::get($path);

        // Corrige somente a cópia temporária para cadastrar
        // a reserva 6 antes de testar sua preservação.
        $xml = simplexml_load_string($originalXml);

        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        $nodes = $xml->xpath('Reserve[@id="6"]');

        $this->assertCount(1, $nodes);

        $changed = false;

        foreach ($nodes[0]->Dailies->Daily as $daily) {
            if ((string) $daily->Date === '2022-12-03') {
                $daily->Date = '2022-10-03';
                $changed = true;
            }
        }

        $this->assertTrue($changed);

        $correctedXml = $xml->asXML();

        $this->assertIsString($correctedXml);

        File::put($path, $correctedXml);

        // Com a data corrigida, todas as seis reservas são válidas.
        $this->runImport(0);

        $this->assertDatabaseCount('reservations', 6);
        $this->assertDatabaseCount('reservation_guests', 6);
        $this->assertDatabaseCount('reservation_dailies', 18);
        $this->assertDatabaseCount('reservation_payments', 1);

        $before = $this->reservationSixSnapshot();

        // Restaura a inconsistência na cópia temporária.
        File::put($path, $originalXml);

        $this->runImport(1);

        $this->assertDatabaseCount('reservations', 6);
        $this->assertDatabaseCount('reservation_guests', 6);
        $this->assertDatabaseCount('reservation_dailies', 18);
        $this->assertDatabaseCount('reservation_payments', 1);

        $this->assertSame(
            $before,
            $this->reservationSixSnapshot()
        );
    }

    private function runImport(int $expectedCode): void
    {
        $this->artisan('hotel:import-xml', [
            '--path' => $this->xmlDirectory,
        ])
            ->assertExitCode($expectedCode)
            ->run();
    }

    private function xmlPath(string $file): string
    {
        return $this->xmlDirectory
            . DIRECTORY_SEPARATOR
            . $file;
    }

    private function assertOriginalImportCounts(): void
    {
        $this->assertDatabaseCount('hotels', 3);
        $this->assertDatabaseCount('rooms', 6);
        $this->assertDatabaseCount('reservations', 5);
        $this->assertDatabaseCount('reservation_guests', 5);
        $this->assertDatabaseCount('reservation_dailies', 15);
        $this->assertDatabaseCount('reservation_payments', 1);
    }

    private function assertNoImportedRecords(): void
    {
        foreach ([
            'hotels',
            'rooms',
            'reservations',
            'reservation_guests',
            'reservation_dailies',
            'reservation_payments',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    private function reservationSixSnapshot(): array
    {
        return Reservation::where('external_id', 6)
            ->with(['guests', 'dailies', 'payments'])
            ->firstOrFail()
            ->toArray();
    }
}