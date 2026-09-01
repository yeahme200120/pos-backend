<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (
            !in_array(
                $user->rol,
                ['admin', 'superadmin'],
                true
            )
        ) {
            abort(403, 'No tienes permisos para compartir reportes.');
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        /*
         * La validación debe ejecutarse fuera del try/catch principal
         * para que Laravel responda correctamente con HTTP 422.
         */
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

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        $fecha = $data['fecha'] ?? now()->toDateString();
        $formato = $data['formato'] ?? 'pdf';

        try {
            $ventas = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha', $fecha)
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
                $fechaVenta = $venta->fecha instanceof \Carbon\Carbon
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

            $pdf = Pdf::loadHTML($html)->output();

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
             * La auditoría se realiza después de completar la operación.
             * Si falla, NO debe convertir una operación exitosa en HTTP 500.
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
        } catch (Throwable $e) {
            /*
             * Nunca guardar el mensaje técnico completo de la excepción
             * dentro de la auditoría.
             */
            $this->registrarAuditoria(
                $request,
                'compartir_reporte_error',
                'reportes',
                null,
                null,
                [
                    'tipo' => 'diario',
                    'fecha' => $fecha,
                    'formato' => $formato,
                    'destinatario' => isset($data['destinatario'])
                        ? $this->mascararCorreo($data['destinatario'])
                        : null,
                    'error_tipo' => get_class($e),
                ],
                $empresaId,
                $usuarioId
            );

            Log::error(
                'Error al compartir reporte diario',
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
            ], 500);
        }
    }

    /**
     * Registrar auditoría de forma segura.
     *
     * La auditoría no debe romper la operación principal.
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
         * No registrar acciones realizadas por superadmin.
         */
        if ($request->user()?->rol === 'superadmin') {
            return;
        }

        try {
            $this->auditoria->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues,
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
                ]
            );
        }
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