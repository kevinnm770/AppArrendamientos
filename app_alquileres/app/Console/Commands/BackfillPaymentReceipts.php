<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Extrae de una sola vez los "comprobantes de pago" que hoy viven mezclados en
 * invoices/invoice_items (identificados por no tener invoice_electronic_details) hacia
 * sus tablas propias (payment_receipts/payment_receipt_items), y borra el original —
 * es una migración de datos de un solo uso, no una sincronización recurrente.
 */
class BackfillPaymentReceipts extends Command
{
    protected $signature = 'payment-receipts:backfill {--dry-run : Muestra qué se migraría sin guardar cambios}';

    protected $description = 'Migra los comprobantes de pago simples de invoices/invoice_items a payment_receipts/payment_receipt_items';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $invoices = Invoice::with('items')
            ->doesntHave('electronicDetail')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No hay comprobantes de pago pendientes de migrar.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Migrando {$invoices->count()} comprobante(s)...");

        // Contador en memoria de "REC-{lessor}-{año}" -> siguiente número. El comando
        // corre en un solo proceso, así que no hace falta lockForUpdate como en
        // PaymentReceiptController::nextReceiptNumber() (pensado para uso concurrente).
        $sequences = [];

        DB::transaction(function () use ($invoices, $dryRun, &$sequences) {
            foreach ($invoices as $invoice) {
                $year = $invoice->date->year;
                $key = "{$invoice->lessor_id}-{$year}";

                if (!isset($sequences[$key])) {
                    $prefix = "REC-{$year}-";
                    $last = PaymentReceipt::where('lessor_id', $invoice->lessor_id)
                        ->where('receipt_number', 'like', $prefix.'%')
                        ->max('receipt_number');

                    $sequences[$key] = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
                }

                $receiptNumber = "REC-{$year}-".str_pad((string) $sequences[$key], 6, '0', STR_PAD_LEFT);
                $sequences[$key]++;

                $this->line(" - Invoice #{$invoice->id} ({$invoice->invoice_number}) -> {$receiptNumber}, ".$invoice->items->count().' línea(s)');

                if ($dryRun) {
                    continue;
                }

                $receipt = PaymentReceipt::create([
                    'agreement_id' => $invoice->agreement_id,
                    'lessor_id' => $invoice->lessor_id,
                    'roomer_id' => $invoice->roomer_id,
                    'receipt_number' => $receiptNumber,
                    'date' => $invoice->date,
                    'currency' => $invoice->currency,
                    'payment_methods' => $invoice->payment_methods ?? [],
                    'payment_method_other_description' => $invoice->payment_method_other_description,
                    'reference_code' => $invoice->reference_code,
                    'notes' => $invoice->notes,
                    'total' => $invoice->total,
                    'created_by_user_id' => $invoice->created_by_user_id,
                    'updated_by_user_id' => $invoice->updated_by_user_id,
                ]);

                // Preserva la fecha real de creación (afecta la ventana de 24h de
                // PaymentReceipt::canEditOrDelete()): sin esto, todo comprobante migrado
                // quedaría editable de nuevo por 24 horas a partir de hoy.
                $receipt->forceFill([
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                ])->save();

                foreach ($invoice->items as $item) {
                    PaymentReceiptItem::create([
                        'payment_receipt_id' => $receipt->id,
                        'concept' => $item->concept,
                        'is_return' => $item->is_return,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                        'balance_pending' => $item->balance_pending,
                        'file_payment_id' => $item->file_payment_id,
                        'position' => $item->position,
                    ]);
                }

                // Cascade delete se encarga de sus invoice_items (ver create_invoice_items_table).
                $invoice->delete();
            }

            if ($dryRun) {
                // Nada que deshacer explícitamente: en dry-run no se creó/borró nada
                // dentro de la transacción, así que dejarla completar es inofensivo.
                $this->info('[dry-run] No se guardó ningún cambio.');
            }
        });

        if (!$dryRun) {
            $this->info('Migración completada.');
        }

        return self::SUCCESS;
    }
}
