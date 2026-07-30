<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCrLocations extends Command
{
    /**
     * Espera un CSV con columnas: provincia_codigo,provincia_nombre,canton_codigo,
     * canton_nombre,distrito_codigo,distrito_nombre (sin encabezado, o con encabezado —
     * la primera fila se descarta siempre). Los códigos deben coincidir con el catálogo
     * oficial que usa Hacienda (Provincia 1 dígito, Cantón 2 dígitos, Distrito 2 dígitos).
     */
    protected $signature = 'locations:import {path : Ruta al CSV del catálogo de provincia/cantón/distrito}';

    protected $description = 'Importa/actualiza el catálogo de cantones y distritos de Costa Rica para los selects encadenados del perfil del arrendador';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!is_readable($path)) {
            $this->error("No se pudo leer el archivo: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle); // descarta encabezado

        $cantons = 0;
        $districts = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 6) {
                continue;
            }

            [$provinceCode, $provinceName, $cantonCode, $cantonName, $districtCode, $districtName] = array_map('trim', $row);

            if (!DB::table('cr_provinces')->where('code', $provinceCode)->exists()) {
                DB::table('cr_provinces')->insert(['code' => $provinceCode, 'name' => $provinceName]);
            }

            DB::table('cr_cantons')->upsert(
                [['province_code' => $provinceCode, 'code' => $cantonCode, 'name' => $cantonName]],
                ['province_code', 'code'],
                ['name']
            );
            $cantons++;

            DB::table('cr_districts')->upsert(
                [['province_code' => $provinceCode, 'canton_code' => $cantonCode, 'code' => $districtCode, 'name' => $districtName]],
                ['province_code', 'canton_code', 'code'],
                ['name']
            );
            $districts++;
        }

        fclose($handle);

        $this->info("Catálogo de ubicaciones importado: {$cantons} filas de cantón, {$districts} filas de distrito procesadas.");

        return self::SUCCESS;
    }
}
