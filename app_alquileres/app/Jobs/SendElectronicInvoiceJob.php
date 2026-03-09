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
use RuntimeException;

class SendElectronicInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $invoiceId,
        public bool $isRetry = false,
    ) {
    }

    public function handle(CostaRicaElectronicInvoiceService $service): void
    {
        $invoice = Invoice::with('electronicDetail')->find($this->invoiceId);

        if (!$invoice || !$invoice->electronicDetail) {
            return;
        }

        $detail = $invoice->electronicDetail;

        $detail->transitionTo(InvoiceElectronicDetail::STATE_QUEUED, $this->isRetry
            ? 'Factura electrónica encolada para reintento de envío.'
            : 'Factura electrónica encolada para envío.');

        try {
            $service->sendVoucher($invoice);

            $detail->refresh();
            $detail->transitionTo(InvoiceElectronicDetail::STATE_SENT, 'Comprobante enviado a proveedor electrónico.');

            SyncElectronicInvoiceStatusJob::dispatch($invoice->id)->delay(now()->addSeconds(20));
        } catch (RuntimeException $exception) {
            $detail->refresh();
            $detail->forceFill([
                'error_code' => $detail->error_code ?? 'SEND_ERROR',
                'status_checked_at' => now(),
            ])->save();
            $detail->transitionTo(InvoiceElectronicDetail::STATE_ERROR, 'Error al enviar: ' . $exception->getMessage());

            throw $exception;
        }
    }
}
