<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mesa;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MesaController extends Controller
{
    protected AuditoriaService $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /**
     * Registrar auditoría de forma segura.
     *
     * IMPORTANTE:
     * - Superadmin también se audita.
     * - usuario_id = actor real.
     * - empresa_id = empresa afectada.
     * - Los errores de auditoría no rompen la operación.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaIdAfectada = null
    ): void {
        try {
            $usuario = $request->user();

            if (!$usuario) {
                return;
            }

            $empresaId = $empresaIdAfectada ?? $usuario->empresa_id;

            $this->auditoria->registrar(
                $request,
                $accion,
                'mesas',
                $registroId,
                $datosAntes,
                $datosDespues,
                $empresaId,
                $usuario->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de mesa.',
                [
                    'accion' => $accion,
                    'mesa_id' => $registroId,
                    'empresa_id_afectada' => $empresaIdAfectada,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id_actor' =>
                        $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Validar que el usuario tenga empresa.
     */
    private function obtenerEmpresaId(Request $request): ?int
    {
        $usuario = $request->user();

        if (!$usuario) {
            return null;
        }

        return $usuario->empresa_id
            ? (int) $usuario->empresa_id
            : null;
    }

    /**
     * Validar módulo de mesas.
     *
     * Las mesas son independientes de las cajas.
     */
    private function validarModulo(Request $request): ?int
    {
        $usuario = $request->user();

        if (!$usuario) {
            abort(
                401,
                'Usuario no autenticado.'
            );
        }

        $empresa = $usuario->empresa;

        if (!$empresa) {
            $this->registrarAuditoria(
                $request,
                'mesa.operacion_rechazada',
                null,
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null
            );

            abort(
                403,
                'El usuario no tiene una empresa asociada.'
            );
        }

        if (!$empresa->usaMesas()) {
            $this->registrarAuditoria(
                $request,
                'mesa.operacion_rechazada',
                null,
                null,
                [
                    'motivo' => 'mesas_no_activas',
                ],
                $empresa->id
            );

            abort(
                422,
                'Las mesas no están activas para esta empresa.'
            );
        }

        return (int) $empresa->id;
    }

    /**
     * Validar datos y registrar auditoría si fallan.
     */
    private function validarRequest(
        Request $request,
        array $rules,
        array $messages = []
    ): array {
        $validator = Validator::make(
            $request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {
            $usuario = $request->user();

            if ($usuario) {
                $this->registrarAuditoria(
                    $request,
                    'mesa.validacion_rechazada',
                    null,
                    null,
                    [
                        'errores' =>
                            $validator->errors()->toArray(),
                    ],
                    $usuario->empresa_id
                );
            }

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Listar mesas.
     */
    public function index(Request $request)
    {
        $empresaId = $this->validarModulo($request);
        $usuario = $request->user();

        try {
            /*
             * Aislamiento obligatorio por empresa.
             */
            $mesas = Mesa::query()
                ->where('empresa_id', $empresaId)
                ->orderBy('nombre')
                ->get();

            $this->registrarAuditoria(
                $request,
                'mesas.consultadas',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'total' => $mesas->count(),
                ],
                $empresaId
            );

            return response()->json([
                'success' => true,
                'data' => $mesas,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al listar mesas.',
                [
                    'usuario_id' => $usuario->id,
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'mesas.consulta_error',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible obtener las mesas.',
            ], 500);
        }
    }

    /**
     * Crear mesa.
     */
    public function store(Request $request)
    {
        $empresaId = $this->validarModulo($request);
        $usuario = $request->user();

        try {
            $validated = $this->validarRequest(
                $request,
                [
                    'nombre' => [
                        'required',
                        'string',
                        'max:80',
                    ],

                    'capacidad' => [
                        'nullable',
                        'integer',
                        'min:1',
                        'max:1000',
                    ],

                    'notas' => [
                        'nullable',
                        'string',
                        'max:500',
                    ],
                ],
                [
                    'nombre.required' =>
                        'El nombre de la mesa es obligatorio.',
                    'nombre.max' =>
                        'El nombre de la mesa no puede superar 80 caracteres.',
                    'capacidad.integer' =>
                        'La capacidad debe ser un número entero.',
                    'capacidad.min' =>
                        'La capacidad debe ser de al menos 1 persona.',
                    'capacidad.max' =>
                        'La capacidad no puede superar 1000 personas.',
                    'notas.max' =>
                        'Las notas no pueden superar 500 caracteres.',
                ]
            );

            $validated['nombre'] =
                trim($validated['nombre']);

            if (isset($validated['notas'])) {
                $validated['notas'] =
                    trim($validated['notas']);
            }

            /*
             * Validación explícita de nombre único POR EMPRESA.
             */
            $existe = Mesa::query()
                ->where('empresa_id', $empresaId)
                ->whereRaw(
                    'LOWER(nombre) = LOWER(?)',
                    [$validated['nombre']]
                )
                ->exists();

            if ($existe) {
                $this->registrarAuditoria(
                    $request,
                    'mesa.creacion_rechazada',
                    null,
                    null,
                    [
                        'motivo' => 'nombre_duplicado',
                        'nombre' => $validated['nombre'],
                    ],
                    $empresaId
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Ya existe una mesa con ese nombre en esta empresa.',
                    'errors' => [
                        'nombre' => [
                            'Ya existe una mesa con ese nombre en esta empresa.',
                        ],
                    ],
                ], 422);
            }

            $mesa = DB::transaction(
                function () use (
                    $validated,
                    $empresaId
                ) {
                    return Mesa::create([
                        'empresa_id' => $empresaId,
                        'nombre' => $validated['nombre'],
                        'capacidad' =>
                            $validated['capacidad'] ?? null,
                        'notas' =>
                            $validated['notas'] ?? null,
                    ]);
                }
            );

            $this->registrarAuditoria(
                $request,
                'mesa.creada',
                (int) $mesa->id,
                null,
                $mesa->toArray(),
                $empresaId
            );

            return response()->json([
                'success' => true,
                'message' => 'Mesa creada correctamente.',
                'data' => $mesa,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Los datos de la mesa no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error al crear mesa.',
                [
                    'usuario_id' => $usuario->id,
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'mesa.creacion_error',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible crear la mesa.',
            ], 500);
        }
    }

    /**
     * Actualizar mesa.
     */
    public function update(
        Request $request,
        int $id
    ) {
        $empresaId = $this->validarModulo($request);
        $usuario = $request->user();

        try {
            $validated = $this->validarRequest(
                $request,
                [
                    'nombre' => [
                        'sometimes',
                        'required',
                        'string',
                        'max:80',
                    ],

                    'capacidad' => [
                        'sometimes',
                        'nullable',
                        'integer',
                        'min:1',
                        'max:1000',
                    ],

                    'activo' => [
                        'sometimes',
                        'boolean',
                    ],

                    'notas' => [
                        'sometimes',
                        'nullable',
                        'string',
                        'max:500',
                    ],
                ],
                [
                    'nombre.required' =>
                        'El nombre de la mesa es obligatorio.',
                    'nombre.max' =>
                        'El nombre de la mesa no puede superar 80 caracteres.',
                    'capacidad.integer' =>
                        'La capacidad debe ser un número entero.',
                    'capacidad.min' =>
                        'La capacidad debe ser de al menos 1 persona.',
                    'capacidad.max' =>
                        'La capacidad no puede superar 1000 personas.',
                    'activo.boolean' =>
                        'El estado activo de la mesa no es válido.',
                    'notas.max' =>
                        'Las notas no pueden superar 500 caracteres.',
                ]
            );

            if (array_key_exists('nombre', $validated)) {
                $validated['nombre'] =
                    trim($validated['nombre']);

                $existe = Mesa::query()
                    ->where('empresa_id', $empresaId)
                    ->where('id', '<>', $id)
                    ->whereRaw(
                        'LOWER(nombre) = LOWER(?)',
                        [$validated['nombre']]
                    )
                    ->exists();

                if ($existe) {
                    $this->registrarAuditoria(
                        $request,
                        'mesa.actualizacion_rechazada',
                        $id,
                        null,
                        [
                            'motivo' => 'nombre_duplicado',
                            'nombre' => $validated['nombre'],
                        ],
                        $empresaId
                    );

                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Ya existe otra mesa con ese nombre en esta empresa.',
                        'errors' => [
                            'nombre' => [
                                'Ya existe otra mesa con ese nombre en esta empresa.',
                            ],
                        ],
                    ], 422);
                }
            }

            if (array_key_exists('notas', $validated)) {
                $validated['notas'] =
                    $validated['notas'] !== null
                        ? trim($validated['notas'])
                        : null;
            }

            /*
             * La empresa SIEMPRE se aplica antes del ID.
             */
            $mesa = Mesa::query()
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->first();

            if (!$mesa) {
                $this->registrarAuditoria(
                    $request,
                    'mesa.actualizacion_rechazada',
                    $id,
                    null,
                    [
                        'motivo' =>
                            'mesa_no_existe_o_no_pertenece_empresa',
                    ],
                    $empresaId
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'La mesa no existe o no pertenece a tu empresa.',
                ], 404);
            }

            $datosAntes = $mesa->toArray();

            DB::transaction(
                function () use (
                    $mesa,
                    $validated
                ) {
                    $mesa->update($validated);
                }
            );

            $mesa->refresh();

            $this->registrarAuditoria(
                $request,
                'mesa.actualizada',
                (int) $mesa->id,
                $datosAntes,
                $mesa->toArray(),
                $empresaId
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Mesa actualizada correctamente.',
                'data' => $mesa,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Los datos de la mesa no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error al actualizar mesa.',
                [
                    'mesa_id' => $id,
                    'usuario_id' => $usuario->id,
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'mesa.actualizacion_error',
                $id,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible actualizar la mesa.',
            ], 500);
        }
    }
}