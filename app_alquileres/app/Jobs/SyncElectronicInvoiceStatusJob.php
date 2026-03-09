<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceElectronicDetail;
use App\Services\CostaRicaElectronicInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use RuntimeException;

class SyncElectronicInvoiceStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $invoiceId,
        public bool $manual = false,
    ) {
    }

    public function handle(CostaRicaElectronicInvoiceService $service): void
    {
        $invoice = Invoice::with('electronicDetail')->find($this->invoiceId);

        if (!$invoice || !$invoice->electronicDetail) {
            return;
        }

        $detail = $invoice->electronicDetail;

        if (in_array($detail->electronic_status, [InvoiceElectronicDetail::STATE_ACCEPTED, InvoiceElectronicDetail::STATE_REJECTED], true)) {
            return;
        }

        try {
            $response = $service->getVoucherStatus($detail);
            $payload = $response['payload'] ?? [];
            $status = strtolower((string) (
                Arr::get($payload, 'ind-estado')
                ?? Arr::get($payload, 'resp.ind-estado')
                ?? Arr::get($payload, 'status')
                ?? Arr::get($payload, 'estado')
                ?? 'pending'
            ));
            $message = (string) (
                Arr::get($payload, 'respuesta-xml')
                ?? Arr::get($payload, 'message')
                ?? Arr::get($payload, 'mensaje')
                ?? 'Estado consultado en proveedor.'
            );

            $detail->refresh();
            $detail->forceFill(['status_checked_at' => now()])->save();

            if (in_array($status, ['accepted', 'aceptado'], true)) {
                $detail->transitionTo(InvoiceElectronicDetail::STATE_ACCEPTED, $message);

                return;
            }

            if (in_array($status, ['rejected', 'rechazado'], true)) {
                $detail->transitionTo(InvoiceElectronicDetail::STATE_REJECTED, $message);

                return;
            }

            $detail->transitionTo(InvoiceElectronicDetail::STATE_SENT, $message);

            SyncElectronicInvoiceStatusJob::dispatch($invoice->id)->delay(now()->addMinute());
        } catch (RuntimeException $exception) {
            $detail->refresh();
            $detail->forceFill([
                'error_code' => $detail->error_code ?? 'SYNC_ERROR',
                'status_checked_at' => now(),
            ])->save();
            $detail->transitionTo(InvoiceElectronicDetail::STATE_ERROR, 'Error al sincronizar estado: ' . $exception->getMessage());

            if (!$this->manual) {
                throw $exception;
            }
        }
    }
}
