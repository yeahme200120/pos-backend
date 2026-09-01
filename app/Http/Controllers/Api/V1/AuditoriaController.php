<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LogAuditoria;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuditoriaController extends Controller
{
    /**
     * Listar registros de auditoría.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $request->validate([
            'usuario_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'accion' => [
                'nullable',
                'string',
                'max:100',
            ],
            'tabla' => [
                'nullable',
                'string',
                'max:100',
            ],
            'fecha_desde' => [
                'nullable',
                'date',
            ],
            'fecha_hasta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_desde',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        try {
            $empresaId = (int) $user->empresa_id;

            $query = LogAuditoria::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->with('usuario');

            if ($request->filled('usuario_id')) {
                $query->where(
                    'usuario_id',
                    (int) $request->input('usuario_id')
                );
            }

            if ($request->filled('accion')) {
                $accion = trim(
                    (string) $request->input('accion')
                );

                $query->where(
                    'accion',
                    'LIKE',
                    "%{$accion}%"
                );
            }

            if ($request->filled('tabla')) {
                $query->where(
                    'tabla',
                    $request->input('tabla')
                );
            }

            if ($request->filled('fecha_desde')) {
                $query->whereDate(
                    'created_at',
                    '>=',
                    $request->input('fecha_desde')
                );
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate(
                    'created_at',
                    '<=',
                    $request->input('fecha_hasta')
                );
            }

            $perPage = (int) $request->input(
                'per_page',
                50
            );

            $logs = $query
                ->orderByDesc('created_at')
                ->paginate($perPage)
                ->appends($request->query());

            /*
             * Resumen.
             */
            $baseQuery = LogAuditoria::query()
                ->where(
                    'empresa_id',
                    $empresaId
                );

            $resumen = [
                'total' =>
                    (clone $baseQuery)->count(),

                'hoy' =>
                    (clone $baseQuery)
                        ->whereDate(
                            'created_at',
                            now()->toDateString()
                        )
                        ->count(),

                'acciones' =>
                    (clone $baseQuery)
                        ->select(
                            'accion',
                            DB::raw(
                                'COUNT(*) as total'
                            )
                        )
                        ->groupBy('accion')
                        ->orderByDesc('total')
                        ->get(),
            ];

            app(AuditoriaService::class)->registrar(
                $request,
                'auditoria.consultada',
                'logs_auditoria',
                null,
                null,
                [
                    'usuario_id' =>
                        $request->input('usuario_id'),
                    'accion' =>
                        $request->input('accion'),
                    'tabla' =>
                        $request->input('tabla'),
                    'fecha_desde' =>
                        $request->input('fecha_desde'),
                    'fecha_hasta' =>
                        $request->input('fecha_hasta'),
                    'per_page' =>
                        $perPage,
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'data' => $logs,
                'resumen' => $resumen,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al consultar auditoría: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al consultar auditoría.',
            ], 500);
        }
    }

    /**
     * Mostrar detalle de un registro de auditoría.
     */
    public function show(
        $id,
        Request $request
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $empresaId = (int) $user->empresa_id;

            $log = LogAuditoria::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->with('usuario')
                ->findOrFail($id);

            app(AuditoriaService::class)->registrar(
                $request,
                'auditoria.detalle.consultado',
                'logs_auditoria',
                (int) $log->id,
                null,
                [
                    'registro_consultado' =>
                        $log->id,
                    'accion_original' =>
                        $log->accion,
                    'tabla_original' =>
                        $log->tabla,
                    'registro_id_original' =>
                        $log->registro_id,
                ],
                $empresaId,
                $user->id
            );

            return response()->json($log);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al consultar detalle de auditoría: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Registro de auditoría no encontrado.',
            ], 404);
        }
    }

    /**
     * Exportar auditoría a CSV.
     */
    public function exportar(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $request->validate([
            'fecha_desde' => [
                'nullable',
                'date',
            ],
            'fecha_hasta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_desde',
            ],
        ]);

        try {
            $empresaId = (int) $user->empresa_id;

            $query = LogAuditoria::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->with('usuario');

            if ($request->filled('fecha_desde')) {
                $query->whereDate(
                    'created_at',
                    '>=',
                    $request->input('fecha_desde')
                );
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate(
                    'created_at',
                    '<=',
                    $request->input('fecha_hasta')
                );
            }

            $logs = $query
                ->orderByDesc('created_at')
                ->get();

            $filename =
                'auditoria_' .
                now()->format(
                    'Y-m-d_H-i-s'
                ) .
                '.csv';

            $directory = 'exports';

            if (
                !Storage::disk('public')
                    ->exists($directory)
            ) {
                Storage::disk('public')
                    ->makeDirectory($directory);
            }

            $relativePath =
                $directory .
                '/' .
                $filename;

            $absolutePath =
                Storage::disk('public')
                    ->path($relativePath);

            $file = fopen(
                $absolutePath,
                'wb'
            );

            if ($file === false) {
                throw new \RuntimeException(
                    'No fue posible crear el archivo CSV.'
                );
            }

            /*
             * BOM UTF-8 para Excel.
             */
            fwrite(
                $file,
                "\xEF\xBB\xBF"
            );

            fputcsv(
                $file,
                [
                    'ID',
                    'Usuario',
                    'Acción',
                    'Tabla',
                    'Registro ID',
                    'Datos Antes',
                    'Datos Después',
                    'IP',
                    'Fecha',
                ]
            );

            foreach ($logs as $log) {
                fputcsv(
                    $file,
                    [
                        $log->id,
                        $log->usuario?->name ?? 'N/A',
                        $log->accion,
                        $log->tabla,
                        $log->registro_id,
                        $this->jsonParaCsv(
                            $log->datos_antes
                        ),
                        $this->jsonParaCsv(
                            $log->datos_despues
                        ),
                        $log->ip ?? '',
                        $log->created_at
                            ? $log->created_at
                                ->format(
                                    'd/m/Y H:i:s'
                                )
                            : '',
                    ]
                );
            }

            fclose($file);

            app(AuditoriaService::class)->registrar(
                $request,
                'auditoria.exportada',
                'logs_auditoria',
                null,
                null,
                [
                    'fecha_desde' =>
                        $request->input('fecha_desde'),
                    'fecha_hasta' =>
                        $request->input('fecha_hasta'),
                    'registros_exportados' =>
                        $logs->count(),
                    'archivo' =>
                        $filename,
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'message' =>
                    'Exportación completada',
                'url' =>
                    asset(
                        'storage/exports/' .
                        $filename
                    ),
                'filename' =>
                    $filename,
                'registros_exportados' =>
                    $logs->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al exportar auditoría: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al exportar auditoría.',
            ], 500);
        }
    }

    /**
     * Convertir datos de auditoría a texto CSV.
     */
    private function jsonParaCsv(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            $decoded = json_decode(
                $value,
                true
            );

            if (
                json_last_error() ===
                JSON_ERROR_NONE
            ) {
                return json_encode(
                    $decoded,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );
            }

            return $value;
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?: '';
    }
}