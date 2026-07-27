<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCabysCatalog extends Command
{
    /**
     * Espera un CSV con columnas: codigo,descripcion,tarifa (tarifa opcional, default 13).
     * Descargar el catálogo oficial desde Hacienda y guardarlo en storage/ antes de correr esto.
     */
    protected $signature = 'cabys:import {path : Ruta al CSV del catálogo CABYS}';

    protected $description = 'Importa/actualiza el catálogo de códigos CABYS usado para armar líneas de factura electrónica';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!is_readable($path)) {
            $this->error("No se pudo leer el archivo: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            $this->error('El archivo está vacío.');
            fclose($handle);

            return self::FAILURE;
        }

        $count = 0;
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $batch[] = [
                'code' => trim($row[0]),
                'description' => trim($row[1]),
                'tax_rate' => isset($row[2]) && $row[2] !== '' ? (float) $row[2] : 13,
            ];

            if (count($batch) >= 500) {
                $this->upsertBatch($batch);
                $count += count($batch);
                $batch = [];
                $this->output->write('.');
            }
        }

        if ($batch !== []) {
            $this->upsertBatch($batch);
            $count += count($batch);
        }

        fclose($handle);

        $this->newLine();
        $this->info("Catálogo CABYS importado: {$count} códigos procesados.");

        return self::SUCCESS;
    }

    protected function upsertBatch(array $batch): void
    {
        DB::table('cabys_codes')->upsert(
            $batch,
            ['code'],
            ['description', 'tax_rate']
        );
    }
}
