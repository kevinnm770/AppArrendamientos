# Contrato unificado: `invoice_electronic_details`

Este módulo usa un único contrato de columnas para el detalle electrónico de factura.

## Nombres y tipos

| Columna | Tipo | Requerido | Descripción |
|---|---|---:|---|
| `invoice_id` | `foreignId` único | Sí | Relación 1:1 con `invoices`. |
| `hacienda_key` | `string(50)` nullable | No | Clave electrónica de Hacienda. |
| `hacienda_consecutive` | `string(50)` nullable | No | Consecutivo del comprobante electrónico. |
| `emisor_nit` | `string(20)` | Sí | Identificación del emisor. |
| `emisor_name` | `string(255)` | Sí | Nombre legal del emisor. |
| `receptor_nit` | `string(20)` | Sí | Identificación del receptor. |
| `receptor_name` | `string(255)` | Sí | Nombre legal del receptor. |
| `electronic_status` | `enum(pending,sent,accepted,rejected)` | Sí | Estado electrónico del comprobante. |
| `sent_at` | `timestamp` nullable | No | Fecha/hora de envío al proveedor/Hacienda. |
| `accepted_at` | `timestamp` nullable | No | Fecha/hora de aceptación. |
| `rejected_at` | `timestamp` nullable | No | Fecha/hora de rechazo. |
| `xml_content` | `longText` nullable | No | XML generado o firmado. |
| `xml_hash` | `string(100)` nullable | No | Hash del XML para control de integridad. |
| `ptec_response` | `json` nullable | No | Respuesta del proveedor/API tributaria. |
| `created_at`, `updated_at` | `timestamps` | Sí | Auditoría base de Eloquent. |

## Estados oficiales

- `pending`: pendiente de envío o procesamiento inicial.
- `sent`: enviado al proveedor/Hacienda.
- `accepted`: aceptado por Hacienda.
- `rejected`: rechazado por Hacienda.

## Fechas oficiales de flujo

- `sent_at`: se completa cuando pasa a `sent`.
- `accepted_at`: se completa cuando pasa a `accepted`.
- `rejected_at`: se completa cuando pasa a `rejected`.

> No se deben usar columnas legacy como `hacienda_status`, `electronic_key`, `consecutive_number`, `sent_to_hacienda_at` o `hacienda_response_at` en código nuevo.
