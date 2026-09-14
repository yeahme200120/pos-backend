<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LogAuditoria;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class AuditoriaController extends Controller
{
    /**
     * Listar auditoría.
     *
     * Usuario normal:
     *   solamente ve su empresa.
     *
     * Superadmin:
     *   puede consultar toda la auditoría o filtrar por empresa.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        $esSuperAdmin = app(AuditoriaService::class)
            ->esSuperAdmin($user);

        $request->validate([
            'empresa_id' => [
                'nullable',
                'integer',
                'exists:empresas,id',
            ],

            'usuario_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(function ($query) use ($user, $esSuperAdmin) {
                        if (!$esSuperAdmin) {
                            $query->where(
                                'empresa_id',
                                $user->empresa_id
                            );
                        }
                    }),
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
            $query = LogAuditoria::query()
                ->with([
                    'usuario:id,name,email,empresa_id,rol',
                ]);

            /*
             * Aislamiento por empresa.
             */
            if ($esSuperAdmin) {
                if ($request->filled('empresa_id')) {
                    $query->where(
                        'empresa_id',
                        (int) $request->input('empresa_id')
                    );
                }
            } else {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    return response()->json([
                        'message' => 'El usuario no tiene una empresa válida.',
                    ], 403);
                }

                $query->where('empresa_id', $empresaId);
            }

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

                if ($accion !== '') {
                    $query->where(
                        'accion',
                        'LIKE',
                        '%' . $accion . '%'
                    );
                }
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
                ->orderByDesc('id')
                ->paginate($perPage)
                ->appends($request->query());

            /*
             * Resumen.
             */
            $baseQuery = LogAuditoria::query();

            if ($esSuperAdmin) {
                if ($request->filled('empresa_id')) {
                    $baseQuery->where(
                        'empresa_id',
                        (int) $request->input('empresa_id')
                    );
                }
            } else {
                $baseQuery->where(
                    'empresa_id',
                    (int) $user->empresa_id
                );
            }

            $resumen = [
                'total' => (clone $baseQuery)->count(),

                'hoy' => (clone $baseQuery)
                    ->whereDate(
                        'created_at',
                        now()->toDateString()
                    )
                    ->count(),

                'acciones' => (clone $baseQuery)
                    ->select(
                        'accion',
                        DB::raw('COUNT(*) as total')
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
                    'empresa_id_filtro' =>
                        $request->input('empresa_id'),

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

                    'per_page' => $perPage,

                    'consulta_global' =>
                        $esSuperAdmin &&
                        !$request->filled('empresa_id'),
                ],
                $request->filled('empresa_id')
                    ? (int) $request->input('empresa_id')
                    : ($esSuperAdmin
                        ? null
                        : (int) $user->empresa_id),
                (int) $user->id
            );

            return response()->json([
                'data' => $logs,
                'resumen' => $resumen,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al consultar auditoría.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al consultar auditoría.',
            ], 500);
        }
    }

    /**
     * Mostrar un registro de auditoría.
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        $esSuperAdmin = app(AuditoriaService::class)
            ->esSuperAdmin($user);

        try {
            $query = LogAuditoria::query()
                ->with([
                    'usuario:id,name,email,empresa_id,rol',
                ])
                ->whereKey($id);

            /*
             * Usuario normal:
             * únicamente su empresa.
             *
             * Superadmin:
             * puede consultar cualquier auditoría.
             */
            if (!$esSuperAdmin) {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    return response()->json([
                        'message' =>
                            'El usuario no tiene una empresa válida.',
                    ], 403);
                }

                $query->where('empresa_id', $empresaId);
            }

            $log = $query->first();

            if (!$log) {
                return response()->json([
                    'message' => 'Registro de auditoría no encontrado.',
                ], 404);
            }

            app(AuditoriaService::class)->registrar(
                $request,
                'auditoria.detalle.consultado',
                'logs_auditoria',
                (int) $log->id,
                null,
                [
                    'registro_consultado' =>
                        (int) $log->id,

                    'accion_original' =>
                        $log->accion,

                    'tabla_original' =>
                        $log->tabla,

                    'registro_id_original' =>
                        $log->registro_id,
                ],
                $log->empresa_id !== null
                    ? (int) $log->empresa_id
                    : null,
                (int) $user->id
            );

            return response()->json($log);
        } catch (Throwable $e) {
            Log::error(
                'Error al consultar detalle de auditoría.',
                [
                    'usuario_id' => $user->id,
                    'registro_id' => $id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' =>
                    'Error al consultar el registro de auditoría.',
            ], 500);
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
                'message' => 'No autenticado.',
            ], 401);
        }

        $esSuperAdmin = app(AuditoriaService::class)
            ->esSuperAdmin($user);

        $request->validate([
            'empresa_id' => [
                'nullable',
                'integer',
                'exists:empresas,id',
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
        ]);

        $file = null;

        try {
            $empresaFiltro = null;

            if ($esSuperAdmin) {
                if ($request->filled('empresa_id')) {
                    $empresaFiltro = (int) $request->input(
                        'empresa_id'
                    );
                }
            } else {
                $empresaFiltro = (int) $user->empresa_id;

                if ($empresaFiltro <= 0) {
                    return response()->json([
                        'message' =>
                            'El usuario no tiene una empresa válida.',
                    ], 403);
                }
            }

            $query = LogAuditoria::query()
                ->with([
                    'usuario:id,name,email,empresa_id,rol',
                ]);

            if ($empresaFiltro !== null) {
                $query->where(
                    'empresa_id',
                    $empresaFiltro
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

            $filename =
                'auditoria_' .
                ($empresaFiltro ?? 'global') .
                '_' .
                now()->format('Y-m-d_H-i-s') .
                '_' .
                uniqid() .
                '.csv';

            $directory = 'exports';

            $disk = Storage::disk('public');

            if (!$disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }

            $relativePath =
                $directory . '/' . $filename;

            $absolutePath =
                $disk->path($relativePath);

            $file = fopen($absolutePath, 'wb');

            if ($file === false) {
                throw new \RuntimeException(
                    'No fue posible crear el archivo CSV.'
                );
            }

            /*
             * BOM para Excel.
             */
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID',
                'Empresa ID',
                'Usuario ID',
                'Usuario',
                'Rol',
                'Acción',
                'Tabla',
                'Registro ID',
                'Datos Antes',
                'Datos Después',
                'IP',
                'Fecha',
            ]);

            $registrosExportados = 0;

            $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->chunk(
                    500,
                    function ($logs) use (
                        $file,
                        &$registrosExportados
                    ) {
                        foreach ($logs as $log) {
                            fputcsv($file, [
                                $this->valorCsv(
                                    $log->id
                                ),

                                $this->valorCsv(
                                    $log->empresa_id
                                ),

                                $this->valorCsv(
                                    $log->usuario_id
                                ),

                                $this->valorCsv(
                                    $log->usuario?->name ?? 'N/A'
                                ),

                                $this->valorCsv(
                                    $log->usuario?->rol ?? 'N/A'
                                ),

                                $this->valorCsv(
                                    $log->accion
                                ),

                                $this->valorCsv(
                                    $log->tabla
                                ),

                                $this->valorCsv(
                                    $log->registro_id
                                ),

                                $this->valorCsv(
                                    $this->jsonParaCsv(
                                        $log->datos_antes
                                    )
                                ),

                                $this->valorCsv(
                                    $this->jsonParaCsv(
                                        $log->datos_despues
                                    )
                                ),

                                $this->valorCsv(
                                    $log->ip ?? ''
                                ),

                                $this->valorCsv(
                                    $log->created_at
                                        ? $log->created_at
                                            ->format(
                                                'd/m/Y H:i:s'
                                            )
                                        : ''
                                ),
                            ]);

                            $registrosExportados++;
                        }
                    }
                );

            fclose($file);
            $file = null;

            app(AuditoriaService::class)->registrar(
                $request,
                'auditoria.exportada',
                'logs_auditoria',
                null,
                null,
                [
                    'empresa_id_filtro' =>
                        $empresaFiltro,

                    'fecha_desde' =>
                        $request->input('fecha_desde'),

                    'fecha_hasta' =>
                        $request->input('fecha_hasta'),

                    'registros_exportados' =>
                        $registrosExportados,

                    'archivo' =>
                        $filename,

                    'consulta_global' =>
                        $esSuperAdmin &&
                        $empresaFiltro === null,
                ],
                $empresaFiltro,
                (int) $user->id
            );

            return response()->json([
                'message' =>
                    'Exportación completada.',

                'url' =>
                    asset(
                        'storage/exports/' . $filename
                    ),

                'filename' =>
                    $filename,

                'registros_exportados' =>
                    $registrosExportados,
            ]);
        } catch (Throwable $e) {
            if (is_resource($file)) {
                fclose($file);
            }

            Log::error(
                'Error al exportar auditoría.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' =>
                    'Error al exportar auditoría.',
            ], 500);
        }
    }

    /**
     * Convertir datos a texto para CSV.
     */
    private function jsonParaCsv(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            return $json !== false
                ? $json
                : '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Protección básica contra CSV/Excel Formula Injection.
     */
    private function valorCsv(mixed $value): string
    {
        $value = $this->jsonParaCsv($value);

        if ($value === '') {
            return '';
        }

        $primerCaracter = $value[0];

        if (in_array(
            $primerCaracter,
            ['=', '+', '-', '@'],
            true
        )) {
            return "'" . $value;
        }

        return $value;
    }
}