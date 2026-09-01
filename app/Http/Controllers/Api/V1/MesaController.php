<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mesa;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        try {
            $usuario = $request->user();

            $this->auditoria->registrar(
                $request,
                $accion,
                'mesas',
                $registroId,
                $datosAntes,
                $datosDespues,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning('No fue posible registrar auditoría de mesa', [
                'accion' => $accion,
                'mesa_id' => $registroId,
                'usuario_id' => $request->user()?->id,
                'empresa_id' => $request->user()?->empresa_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validar que la empresa tenga habilitados
     * los módulos de mesas y cajas.
     */
    private function validarModulo(Request $request): void
    {
        $empresa = $request->user()?->empresa;

        abort_unless(
            $empresa,
            403,
            'El usuario no tiene una empresa asociada.'
        );

        abort_unless(
            $empresa->usaMesas() && $empresa->usaCajas(),
            422,
            'Las mesas no están activas para esta empresa.'
        );
    }

    /**
     * Listar mesas.
     */
    public function index(Request $request)
    {
        $this->validarModulo($request);

        $usuario = $request->user();
        $empresaId = $usuario->empresa_id;

        try {
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
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $mesas,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al listar mesas', [
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las mesas.',
            ], 500);
        }
    }

    /**
     * Crear mesa.
     */
    public function store(Request $request)
    {
        $this->validarModulo($request);

        $usuario = $request->user();
        $empresaId = $usuario->empresa_id;

        $validated = $request->validate([
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
        ]);

        $validated['nombre'] = trim($validated['nombre']);

        if (isset($validated['notas'])) {
            $validated['notas'] = trim($validated['notas']);
        }

        try {
            $mesa = DB::transaction(function () use (
                $validated,
                $empresaId
            ) {
                return Mesa::create([
                    'empresa_id' => $empresaId,
                    'nombre' => $validated['nombre'],
                    'capacidad' => $validated['capacidad'] ?? null,
                    'notas' => $validated['notas'] ?? null,
                ]);
            });

            $this->registrarAuditoria(
                $request,
                'mesa.creada',
                (int) $mesa->id,
                null,
                $mesa->toArray()
            );

            return response()->json([
                'success' => true,
                'data' => $mesa,
            ], 201);
        } catch (Throwable $e) {
            Log::error('Error al crear mesa', [
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible crear la mesa.',
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
        $this->validarModulo($request);

        $usuario = $request->user();
        $empresaId = $usuario->empresa_id;

        $validated = $request->validate([
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
        ]);

        if (array_key_exists('nombre', $validated)) {
            $validated['nombre'] = trim(
                $validated['nombre']
            );
        }

        if (array_key_exists('notas', $validated)) {
            $validated['notas'] = $validated['notas'] !== null
                ? trim($validated['notas'])
                : null;
        }

        try {
            $mesa = Mesa::query()
                ->where('empresa_id', $empresaId)
                ->find($id);

            if (!$mesa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesa no encontrada.',
                ], 404);
            }

            $datosAntes = $mesa->toArray();

            DB::transaction(function () use (
                $mesa,
                $validated
            ) {
                $mesa->update($validated);
            });

            $mesa->refresh();

            $this->registrarAuditoria(
                $request,
                'mesa.actualizada',
                (int) $mesa->id,
                $datosAntes,
                $mesa->toArray()
            );

            return response()->json([
                'success' => true,
                'data' => $mesa,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al actualizar mesa', [
                'mesa_id' => $id,
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible actualizar la mesa.',
            ], 500);
        }
    }
}