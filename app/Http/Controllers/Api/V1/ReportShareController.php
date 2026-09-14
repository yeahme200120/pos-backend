<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReportShareController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria
    ) {
    }

    /**
     * Compartir reporte diario por correo.
     */
    public function dailyShare(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            $this->registrarAuditoriaError(
                $request,
                'compartir_reporte_error',
                null,
                null,
                [
                    'tipo' => 'diario',
                    'error' => 'REPORT_UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'message' => 'Usuario no autenticado.',
                'error' => 'REPORT_UNAUTHENTICATED',
            ], 401);
        }

        if (!in_array($user->rol, ['admin', 'superadmin'], true)) {
            $this->registrarAuditoriaError(
                $request,
                'compartir_reporte_error',
                (int) ($user->empresa_id ?: 0) ?: null,
                (int) $user->id,
                [
                    'tipo' => 'diario',
                    'error' => 'REPORT_UNAUTHORIZED',
                    'rol' => $user->rol,
                ]
            );

            return response()->json([
                'message' => 'No tienes permisos para compartir reportes.',
                'error' => 'REPORT_UNAUTHORIZED',
            ], 403);
        }

        if (!$user->empresa_id || !$user->empresa) {
            $this->registrarAuditoriaError(
                $request,
                'compartir_reporte_error',
                null,
                (int) $user->id,
                [
                    'tipo' => 'diario',
                    'error' => 'REPORT_EMPRESA_NO_ASOCIADA',
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'error' => 'REPORT_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        /*
         * La validación conserva el comportamiento estándar de Laravel:
         * los errores de validación continúan respondiendo HTTP 422.
         */
        try {
            $data = $request->validate([
                'destinatario' => [
                    'required',
                    'email:rfc,dns',
                    'max:255',
                ],

                'fecha' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],

                'formato' => [
                    'nullable',
                    'in:pdf',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'compartir_reporte_error',
                $empresaId,
                $usuarioId,
                [
                    'tipo' => 'diario',
                    'error' => 'REPORT_VALIDATION_ERROR',
                    'campos' => array_keys($e->errors()),
                ]
            );

            throw $e;
        }

        $fecha = $data['fecha'] ?? now()->toDateString();
        $formato = $data['formato'] ?? 'pdf';

        try {
            /*
             * Usar rango de fechas en lugar de whereDate() permite
             * aprovechar mejor un índice sobre la columna fecha.
             */
            $inicio = Carbon::createFromFormat(
                'Y-m-d',
                $fecha
            )->startOfDay();

            $fin = Carbon::createFromFormat(
                'Y-m-d',
                $fecha
            )->endOfDay();

            $ventas = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicio, $fin])
                ->where('estado', 'pagado')
                ->orderBy('fecha', 'asc')
                ->get();

            $total = $ventas->sum(
                static fn ($venta) => (float) $venta->total
            );

            $html = '<h1>Reporte diario POS</h1>';

            $html .= '<p>Fecha: '
                . e($fecha)
                . '</p>';

            $html .= '<p>Ventas: '
                . $ventas->count()
                . '</p>';

            $html .= '<p>Total: $'
                . number_format($total, 2)
                . '</p>';

            $html .= '<table width="100%" border="1" cellspacing="0" cellpadding="5">';

            $html .= '<tr>';
            $html .= '<th>Folio</th>';
            $html .= '<th>Fecha</th>';
            $html .= '<th>Total</th>';
            $html .= '</tr>';

            foreach ($ventas as $venta) {
                $fechaVenta = $venta->fecha instanceof Carbon
                    ? $venta->fecha->format('Y-m-d H:i:s')
                    : (string) $venta->fecha;

                $html .= '<tr>';

                $html .= '<td>'
                    . e((string) $venta->folio)
                    . '</td>';

                $html .= '<td>'
                    . e($fechaVenta)
                    . '</td>';

                $html .= '<td>$'
                    . number_format((float) $venta->total, 2)
                    . '</td>';

                $html .= '</tr>';
            }

            $html .= '</table>';

            /*
             * Generación del PDF.
             */
            $pdf = Pdf::loadHTML($html)->output();

            /*
             * Envío del reporte.
             */
            Mail::raw(
                "Reporte diario {$fecha}. "
                . "Ventas: {$ventas->count()}. "
                . "Total: "
                . number_format($total, 2),
                function ($mail) use ($data, $fecha, $pdf) {
                    $mail
                        ->to($data['destinatario'])
                        ->subject("Reporte diario POS {$fecha}")
                        ->attachData(
                            $pdf,
                            "reporte-diario-{$fecha}.pdf",
                            [
                                'mime' => 'application/pdf',
                            ]
                        );
                }
            );

            /*
             * La auditoría es posterior a la operación principal.
             * Si falla, no convierte un envío exitoso en HTTP 500.
             */
            $this->registrarAuditoria(
                $request,
                'compartir_reporte',
                'reportes',
                null,
                null,
                [
                    'tipo' => 'diario',
                    'fecha' => $fecha,
                    'formato' => $formato,
                    'destinatario' => $this->mascararCorreo(
                        $data['destinatario']
                    ),
                    'ventas' => $ventas->count(),
                    'total' => round((float) $total, 2),
                ],
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'Reporte enviado por correo.',
                'fecha' => $fecha,
                'ventas' => $ventas->count(),
                'total' => round((float) $total, 2),
            ]);
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'compartir_reporte_error',
                $empresaId,
                $usuarioId,
                [
                    'tipo' => 'diario',
                    'fecha' => $fecha,
                    'formato' => $formato,
                    'destinatario' => $this->mascararCorreo(
                        $data['destinatario']
                    ),
                    'error' => 'REPORT_DB_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error de base de datos al compartir reporte diario.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'fecha' => $fecha,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'No fue posible consultar la información del reporte.',
                'error' => 'REPORT_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'compartir_reporte_error',
                $empresaId,
                $usuarioId,
                [
                    'tipo' => 'diario',
                    'fecha' => $fecha,
                    'formato' => $formato,
                    'destinatario' => isset($data['destinatario'])
                        ? $this->mascararCorreo($data['destinatario'])
                        : null,
                    'error' => 'REPORT_SEND_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error al compartir reporte diario.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'fecha' => $fecha,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'No fue posible enviar el reporte.',
                'error' => 'REPORT_SEND_ERROR',
            ], 500);
        }
    }

    /**
     * Registrar auditoría de forma segura.
     *
     * La auditoría nunca debe romper la operación principal.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaId,
        ?int $usuarioId
    ): void {
        /*
         * Se conserva el comportamiento original:
         * las acciones realizadas por superadmin no se registran.
         */
        if ($request->user()?->rol === 'superadmin') {
            return;
        }

        try {
            $datosAuditoria = array_merge(
                $datosDespues ?? [],
                [
                    /*
                     * El contexto viene del servidor y no del cliente.
                     */
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                ]
            );

            $this->auditoria->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosAuditoria,
                $empresaId,
                $usuarioId
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de reporte.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );
        }
    }

    /**
     * Registrar errores de auditoría sin afectar la respuesta principal.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        ?int $empresaId,
        ?int $usuarioId,
        array $datos
    ): void {
        $this->registrarAuditoria(
            $request,
            $accion,
            'reportes',
            null,
            null,
            $datos,
            $empresaId,
            $usuarioId
        );
    }

    /**
     * Evitar almacenar el correo completo en auditoría.
     */
    private function mascararCorreo(string $correo): string
    {
        $partes = explode('@', $correo, 2);

        if (count($partes) !== 2) {
            return '***';
        }

        $usuario = $partes[0];
        $dominio = $partes[1];

        if ($usuario === '') {
            return '***@' . $dominio;
        }

        if (mb_strlen($usuario) === 1) {
            return '*@' . $dominio;
        }

        return mb_substr($usuario, 0, 1)
            . str_repeat(
                '*',
                max(1, mb_strlen($usuario) - 1)
            )
            . '@'
            . $dominio;
    }
}
