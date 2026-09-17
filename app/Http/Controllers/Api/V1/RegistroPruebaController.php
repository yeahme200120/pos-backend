<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\RegistroPrueba;
use App\Services\AuditoriaService;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class RegistroPruebaController extends Controller
{
    private const TIPOS_LICENCIA = [
        'dia',
        'semana',
        'quincena',
        'mes',
        'bimestre',
        'trimestre',
        'semestre',
        'anual',
        'permanente',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly LicenseService $licenseService
    ) {}

    /**
     * Listar registros de prueba con su empresa asociada.
     */
    public function index(Request $request): JsonResponse
    {
        $auth = $this->ensureSuperAdmin($request);

        if ($auth) {
            return $auth;
        }

        $search = trim((string) $request->query('search', ''));

        $query = RegistroPrueba::query()
            ->with([
                'empresa:id,nombre,logo,rfc,telefono,email_contacto,activo,licencia_tipo,licencia_fecha_inicio,licencia_fecha_fin,licencia_activa',
                'user:id,name,email,numero_usuario',
            ])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('empresa_nombre', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mac_address', 'like', "%{$search}%");
            });
        }

        $registros = $query->get()->map(function (RegistroPrueba $r) {
            $empresa = $r->empresa;

            return [
                'id' => $r->id,
                'estado_registro' => $r->estado,
                'email' => $r->email,
                'ip' => $r->ip,
                'mac_address' => $r->mac_address,
                'empresa_nombre' => $r->empresa_nombre,
                'empresa_id' => $r->empresa_id,
                'user_id' => $r->user_id,
                'created_at' => $r->created_at?->toISOString(),

                'empresa' => $empresa ? [
                    'id' => $empresa->id,
                    'nombre' => $empresa->nombre,
                    'logo_url' => $empresa->logo_url,
                    'rfc' => $empresa->rfc,
                    'telefono' => $empresa->telefono,
                    'email_contacto' => $empresa->email_contacto,
                    'activo' => (bool) $empresa->activo,
                    'licencia_tipo' => $empresa->licencia_tipo,
                    'licencia_fecha_inicio' => $empresa->licencia_fecha_inicio?->toISOString(),
                    'licencia_fecha_fin' => $empresa->licencia_fecha_fin?->toISOString(),
                    'licencia_activa' => (bool) $empresa->licencia_activa,
                ] : null,

                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                    'numero_usuario' => $r->user->numero_usuario,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $registros,
        ]);
    }

    /**
     * Convertir una prueba a real (individual).
     */
    public function convertirReal(Request $request, int $id): JsonResponse
    {
        $auth = $this->ensureSuperAdmin($request);

        if ($auth) {
            return $auth;
        }

        $data = $request->validate([
            'licencia_tipo' => ['required', 'string', Rule::in(self::TIPOS_LICENCIA)],
            'licencia_fecha_inicio' => ['required', 'date'],
            'licencia_fecha_fin' => ['required', 'date', 'after:licencia_fecha_inicio'],
        ]);

        return $this->procesarConversion($request, $id, $data, 'licencia.convertida_a_real');
    }

    /**
     * Convertir varias pruebas a real (lote).
     */
    public function convertirRealLote(Request $request): JsonResponse
    {
        $auth = $this->ensureSuperAdmin($request);

        if ($auth) {
            return $auth;
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:registros_prueba,id'],
            'licencia_tipo' => ['required', 'string', Rule::in(self::TIPOS_LICENCIA)],
            'licencia_fecha_inicio' => ['required', 'date'],
            'licencia_fecha_fin' => ['required', 'date', 'after:licencia_fecha_inicio'],
        ]);

        $ids = $data['ids'];
        unset($data['ids']);

        $exitosos = [];
        $fallidos = [];

        DB::beginTransaction();

        try {
            foreach ($ids as $id) {
                try {
                    $this->procesarConversion($request, (int) $id, $data, 'licencia.convertida_a_real_lote', false);
                    $exitosos[] = (int) $id;
                } catch (Throwable $e) {
                    $fallidos[] = [
                        'id' => (int) $id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al convertir en lote.',
                'error' => 'BULK_CONVERT_ERROR',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'exitosos' => $exitosos,
            'fallidos' => $fallidos,
        ]);
    }

    /**
     * Lógica compartida de conversión.
     */
    private function procesarConversion(
        Request $request,
        int $registroId,
        array $datos,
        string $accionAuditoria,
        bool $transaccionIndividual = true
    ): JsonResponse {
        $registro = RegistroPrueba::with('empresa')->find($registroId);

        if (!$registro) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de prueba no encontrado.',
                'error' => 'PRUEBA_NOT_FOUND',
            ], 404);
        }

        $empresa = $registro->empresa;

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El registro no tiene empresa asociada.',
                'error' => 'PRUEBA_WITHOUT_EMPRESA',
            ], 404);
        }

        $runner = function () use ($empresa, $datos, $request, $registro, $accionAuditoria) {
            $resultado = $this->licenseService->aplicarLicencia($empresa, $datos);

            $this->auditoriaService->registrar(
                $request,
                $accionAuditoria,
                'empresas',
                $empresa->id,
                $resultado['datos_antes'],
                array_merge($resultado['datos_despues'], [
                    'registro_prueba_id' => $registro->id,
                    'estado' => $resultado['estado'],
                ]),
                $empresa->id,
                $request->user()?->id
            );

            return $resultado;
        };

        try {
            $resultado = $transaccionIndividual
                ? DB::transaction($runner)
                : $runner();

            return response()->json([
                'success' => true,
                'message' => 'Cuenta convertida a real correctamente.',
                'data' => [
                    'empresa_id' => $empresa->id,
                    'licencia_tipo' => $resultado['empresa']->licencia_tipo,
                    'licencia_fecha_inicio' => $resultado['empresa']->licencia_fecha_inicio?->toISOString(),
                    'licencia_fecha_fin' => $resultado['empresa']->licencia_fecha_fin?->toISOString(),
                    'licencia_activa' => (bool) $resultado['empresa']->licencia_activa,
                    'estado' => $resultado['estado'],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Error al convertir prueba a real.', [
                'registro_id' => $registroId,
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible convertir la cuenta.',
                'error' => 'CONVERT_ERROR',
            ], 500);
        }
    }

    private function ensureSuperAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización.',
                'error' => 'SUPERADMIN_REQUIRED',
            ], 403);
        }

        return null;
    }
}