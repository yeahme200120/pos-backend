<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class EmpresaController extends Controller
{
    private const CAMPOS_EMPRESA = [
        'nombre',
        'direccion',
        'telefono',
        'email_contacto',
        'rfc',
        'razon_social',
        'leyenda_ticket',
        'whatsapp_numero',
        'activo',
    ];

    private const COLORES_DEFAULT = [
        'primary'     => '#1E293B',
        'secondary'   => '#108981',
        'background'  => '#f3f4f6',
        'text'        => '#FFFFFF',
        'text_navbar' => '#FFFFFF',
        'menu_hover'  => '#2d3748',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    // -------------------------------------------------------------------
    // INDEX
    // -------------------------------------------------------------------
    public function index(Request $request)
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json([
                'message' => 'No autorizado.',
            ], 403);
        }

        $validated = $request->validate([
            'search'   => 'nullable|string|max:255',
            'activo'   => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $query = Empresa::query();

            if (!empty($validated['search'])) {
                $search = trim($validated['search']);
                $like = '%' . $search . '%';

                $query->where(function ($q) use ($like) {
                    $q->where('nombre', 'LIKE', $like)
                        ->orWhere('rfc', 'LIKE', $like);
                });
            }

            if (array_key_exists('activo', $validated)) {
                $query->where(
                    'activo',
                    (bool) $validated['activo']
                );
            }

            $perPage = (int) (
                $validated['per_page'] ?? 20
            );

            $empresas = $query
                ->orderBy('nombre', 'asc')
                ->paginate($perPage);

            $this->registrarAuditoria(
                $request,
                'empresas.consultadas',
                'empresas',
                null,
                null,
                [
                    'search'     => $validated['search'] ?? null,
                    'activo'     => $validated['activo'] ?? null,
                    'pagina'     => $empresas->currentPage(),
                    'por_pagina' => $empresas->perPage(),
                    'total'      => $empresas->total(),
                ]
            );

            return response()->json($empresas);
        } catch (Throwable $e) {
            Log::error(
                'Error listando empresas.',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'message' => 'Error al cargar empresas.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------
    public function show($id, Request $request)
    {
        $user = $request->user();

        try {
            if ($user->rol === 'superadmin') {
                $empresa = Empresa::findOrFail($id);
            } else {
                $empresa = Empresa::query()
                    ->where('id', $user->empresa_id)
                    ->findOrFail($id);
            }

            $this->registrarAuditoria(
                $request,
                'empresa.consultada',
                'empresas',
                (int) $empresa->id,
                null,
                $empresa->toArray()
            );

            return response()->json($empresa);
        } catch (Throwable $e) {
            Log::error(
                'Error consultando empresa.',
                [
                    'empresa_id' => $id,
                    'usuario_id' => $user?->id,
                    'error'      => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    // -------------------------------------------------------------------
    // STORE
    // -------------------------------------------------------------------
    public function store(Request $request)
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json([
                'message' => 'No autorizado.',
            ], 403);
        }

        $request->validate([
            'nombre'          => 'required|string|max:255',
            'direccion'       => 'nullable|string|max:500',
            'telefono'        => 'nullable|string|max:20',
            'email_contacto'  => 'nullable|email|max:255',
            'rfc'             => 'nullable|string|max:20',
            'razon_social'    => 'nullable|string|max:255',
            'leyenda_ticket'  => 'nullable|string|max:2000',
            'whatsapp_numero' => 'nullable|string|max:20',
            'colores'         => 'nullable',
            'configuracion'   => 'nullable',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'logo_crop'       => 'nullable',
            'logo_crop.x'     => 'nullable|integer|min:0',
            'logo_crop.y'     => 'nullable|integer|min:0',
            'logo_crop.width' => 'nullable|integer|min:1',
            'logo_crop.height' => 'nullable|integer|min:1',
            'activo'          => 'nullable|boolean',
        ]);

        $logoPath = null;

        try {
            $data = $request->only(
                self::CAMPOS_EMPRESA
            );

            $data['nombre'] = trim(
                $data['nombre']
            );

            $data['activo'] = $request->has('activo')
                ? $request->boolean('activo')
                : true;

            // -----------------------------------------------------------
            // COLORES
            // -----------------------------------------------------------
            if ($request->has('colores')) {
                $valorColores = $request->input(
                    'colores'
                );

                if (!$this->estaVacioJson($valorColores)) {
                    $colores = $this->normalizarJsonArray(
                        $valorColores
                    );

                    if ($colores === null) {
                        return response()->json([
                            'message' =>
                            'El campo colores debe contener un JSON válido.',
                        ], 422);
                    }

                    $data['colores'] = array_merge(
                        self::COLORES_DEFAULT,
                        $colores
                    );
                }
            }

            // -----------------------------------------------------------
            // CONFIGURACIÓN
            // -----------------------------------------------------------
            if ($request->has('configuracion')) {
                $valorConfiguracion = $request->input(
                    'configuracion'
                );

                if (
                    !$this->estaVacioJson(
                        $valorConfiguracion
                    )
                ) {
                    $configuracion =
                        $this->normalizarJsonArray(
                            $valorConfiguracion
                        );

                    if ($configuracion === null) {
                        return response()->json([
                            'message' =>
                            'El campo configuracion debe contener un JSON válido.',
                        ], 422);
                    }

                    $data['configuracion'] = $configuracion;
                }
            }

            // -----------------------------------------------------------
            // LOGO
            // -----------------------------------------------------------
            if ($request->hasFile('logo')) {
                $logoPath = $this->procesarLogo(
                    $request->file('logo'),
                    $this->obtenerCropData($request)
                );

                $data['logo'] = $logoPath;
            }

            $empresa = DB::transaction(
                function () use ($data) {
                    return Empresa::create($data);
                }
            );

            $this->registrarAuditoria(
                $request,
                'empresa.creada',
                'empresas',
                (int) $empresa->id,
                null,
                $empresa->toArray()
            );

            return response()->json([
                'message' => 'Empresa creada correctamente.',
                'data'    => $empresa,
            ], 201);
        } catch (Throwable $e) {
            if ($logoPath) {
                try {
                    Storage::disk('public')->delete(
                        $logoPath
                    );
                } catch (Throwable $cleanupException) {
                    Log::warning(
                        'No se pudo eliminar logo después de error.',
                        [
                            'logo'  => $logoPath,
                            'error' => $cleanupException->getMessage(),
                        ]
                    );
                }
            }

            Log::error(
                'Error creando empresa.',
                [
                    'error'          => $e->getMessage(),
                    'empresa_nombre' => $request->input('nombre'),
                    'trace'          => $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'message' => 'Error al crear empresa.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------
    public function update(Request $request, $id)
    {
        $user = $request->user();

        try {
            if ($user->rol === 'superadmin') {
                $empresa = Empresa::findOrFail($id);
            } else {
                $empresa = Empresa::query()
                    ->where('id', $user->empresa_id)
                    ->findOrFail($id);
            }

            $rules = [
                'nombre'           => 'required|string|max:255',
                'direccion'        => 'nullable|string|max:500',
                'telefono'         => 'nullable|string|max:20',
                'email_contacto'   => 'nullable|email|max:255',
                'rfc'              => 'nullable|string|max:20',
                'razon_social'     => 'nullable|string|max:255',
                'leyenda_ticket'   => 'nullable|string|max:2000',
                'whatsapp_numero'  => 'nullable|string|max:20',
                'colores'          => 'nullable',
                'configuracion'    => 'nullable',
                'logo_crop'        => 'nullable',
                'logo_crop.x'      => 'nullable|integer|min:0',
                'logo_crop.y'      => 'nullable|integer|min:0',
                'logo_crop.width'  => 'nullable|integer|min:1',
                'logo_crop.height' => 'nullable|integer|min:1',
                'activo'           => 'nullable|boolean',
            ];

            if ($request->hasFile('logo')) {
                $rules['logo'] =
                    'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048';
            }

            $request->validate($rules);

            // -----------------------------------------------------------
            // RESTRICCIÓN DE CONFIGURACIÓN
            // -----------------------------------------------------------
            if (
                $user->rol !== 'superadmin' &&
                $request->has('configuracion') &&
                !$this->estaVacioJson(
                    $request->input('configuracion')
                )
            ) {
                return response()->json([
                    'message' =>
                    'No autorizado para modificar la configuración de la empresa.',
                ], 403);
            }

            $datosAntes = $empresa->toArray();
            $logoAnterior = $empresa->logo;
            $nuevoLogoPath = null;

            $data = $request->only(
                self::CAMPOS_EMPRESA
            );

            if (isset($data['nombre'])) {
                $data['nombre'] = trim(
                    $data['nombre']
                );
            }

            if ($request->has('activo')) {
                $data['activo'] =
                    $request->boolean('activo');
            }

            // -----------------------------------------------------------
            // COLORES
            // -----------------------------------------------------------
            if ($request->has('colores')) {
                $valorColores = $request->input(
                    'colores'
                );

                if (!$this->estaVacioJson($valorColores)) {
                    $coloresNuevos =
                        $this->normalizarJsonArray(
                            $valorColores
                        );

                    if ($coloresNuevos === null) {
                        return response()->json([
                            'message' =>
                            'El campo colores debe contener un JSON válido.',
                        ], 422);
                    }

                    $coloresActuales =
                        $this->normalizarJsonArray(
                            $empresa->colores
                        ) ?? [];

                    $data['colores'] = array_merge(
                        self::COLORES_DEFAULT,
                        $coloresActuales,
                        $coloresNuevos
                    );
                }
            }

            // -----------------------------------------------------------
            // CONFIGURACIÓN
            // -----------------------------------------------------------
            if (
                $user->rol === 'superadmin' &&
                $request->has('configuracion')
            ) {
                $valorConfiguracion =
                    $request->input(
                        'configuracion'
                    );

                if (
                    !$this->estaVacioJson(
                        $valorConfiguracion
                    )
                ) {
                    $configuracion =
                        $this->normalizarJsonArray(
                            $valorConfiguracion
                        );

                    if ($configuracion === null) {
                        return response()->json([
                            'message' =>
                            'El campo configuracion debe contener un JSON válido.',
                        ], 422);
                    }

                    $data['configuracion'] =
                        $configuracion;
                }
            }

            // -----------------------------------------------------------
            // LOGO
            // -----------------------------------------------------------
            if ($request->hasFile('logo')) {
                $nuevoLogoPath =
                    $this->procesarLogo(
                        $request->file('logo'),
                        $this->obtenerCropData($request)
                    );

                $data['logo'] = $nuevoLogoPath;
            }

            // -----------------------------------------------------------
            // GUARDAR
            // -----------------------------------------------------------
            DB::transaction(
                function () use ($empresa, $data) {
                    $empresa->update($data);
                }
            );

            $empresa->refresh();

            // -----------------------------------------------------------
            // ELIMINAR LOGO ANTERIOR
            // -----------------------------------------------------------
            if (
                $nuevoLogoPath &&
                $logoAnterior &&
                $logoAnterior !== $nuevoLogoPath
            ) {
                try {
                    Storage::disk('public')->delete(
                        $logoAnterior
                    );
                } catch (Throwable $cleanupException) {
                    Log::warning(
                        'No se pudo eliminar logo anterior.',
                        [
                            'logo'       => $logoAnterior,
                            'empresa_id' => $empresa->id,
                            'error'      => $cleanupException->getMessage(),
                        ]
                    );
                }
            }

            $this->registrarAuditoria(
                $request,
                'empresa.actualizada',
                'empresas',
                (int) $empresa->id,
                $datosAntes,
                $empresa->toArray()
            );

            return response()->json([
                'message' => 'Empresa actualizada correctamente.',
                'data'    => $empresa,
            ]);
        } catch (Throwable $e) {
            // -----------------------------------------------------------
            // LIMPIAR LOGO NUEVO SI HUBO ERROR
            // -----------------------------------------------------------
            if (
                isset($nuevoLogoPath) &&
                $nuevoLogoPath
            ) {
                try {
                    Storage::disk('public')->delete(
                        $nuevoLogoPath
                    );
                } catch (Throwable $cleanupException) {
                    Log::warning(
                        'No se pudo eliminar nuevo logo después de error.',
                        [
                            'logo'       => $nuevoLogoPath,
                            'empresa_id' => $empresa->id ?? $id,
                            'error'      => $cleanupException->getMessage(),
                        ]
                    );
                }
            }

            // -----------------------------------------------------------
            // EXCEPCIONES HTTP
            // -----------------------------------------------------------
            if (
                $e instanceof
                \Symfony\Component\HttpKernel\Exception\HttpException
            ) {
                throw $e;
            }

            Log::error(
                'Error actualizando empresa.',
                [
                    'empresa_id' => $id,
                    'usuario_id' => $user?->id,
                    'error'      => $e->getMessage(),
                    'trace'      => $e->getTraceAsString(),
                ]
            );

            $mensaje = config('app.debug')
                ? $e->getMessage()
                : 'Error al actualizar empresa.';

            return response()->json([
                'message' => $mensaje,
            ], 500);
        }
    }

    // -------------------------------------------------------------------
    // PROCESAR LOGO
    // -------------------------------------------------------------------
    private function procesarLogo(
        $file,
        array $cropData = []
    ): string {
        $manager = new ImageManager(
            new Driver()
        );

        $image = $manager->read($file);

        if (
            isset(
                $cropData['x'],
                $cropData['y'],
                $cropData['width'],
                $cropData['height']
            )
        ) {
            $width = (int) $cropData['width'];
            $height = (int) $cropData['height'];
            $x = (int) $cropData['x'];
            $y = (int) $cropData['y'];

            if (
                $width > 0 &&
                $height > 0 &&
                $x >= 0 &&
                $y >= 0
            ) {
                $image->crop(
                    $width,
                    $height,
                    $x,
                    $y
                );
            }
        }

        $image->scale(
            width: 200,
            height: 200
        );

        $encoded = $image->toWebp(
            quality: 80
        );

        $filename =
            'empresas/' .
            Str::uuid() .
            '.webp';

        Storage::disk('public')->put(
            $filename,
            (string) $encoded
        );

        return $filename;
    }

    // -------------------------------------------------------------------
    // OBTENER CROP
    // -------------------------------------------------------------------
    private function obtenerCropData(
        Request $request
    ): array {
        $cropData = $request->input(
            'logo_crop',
            []
        );

        if (is_string($cropData)) {
            $decoded = json_decode(
                $cropData,
                true
            );

            if (
                json_last_error() ===
                JSON_ERROR_NONE &&
                is_array($decoded)
            ) {
                $cropData = $decoded;
            } else {
                $cropData = [];
            }
        }

        return is_array($cropData)
            ? $cropData
            : [];
    }

    // -------------------------------------------------------------------
    // UTILIDADES
    // -------------------------------------------------------------------
    private function estaVacioJson(
        $value
    ): bool {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    private function normalizarJsonArray(
        $value
    ): ?array {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $decoded = json_decode(
            $value,
            true
        );

        if (
            json_last_error() !==
            JSON_ERROR_NONE ||
            !is_array($decoded)
        ) {
            return null;
        }

        return $decoded;
    }

    // -------------------------------------------------------------------
    // LOGO
    // -------------------------------------------------------------------

    /**
     * Obtener logo actual de la empresa autenticada.
     */
    public function logo(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'logo_url' => null,
                    'message' => 'No autenticado',
                ], 401);
            }

            $empresa = $user->empresa;

            if (
                !$empresa ||
                !$empresa->logo
            ) {
                return response()->json([
                    'logo_url' => null,
                    'message' => 'No hay logo configurado',
                ]);
            }

            $logoPath = $empresa->logo;

            /*
         * IMPORTANTE:
         * Usamos el mismo accessor utilizado
         * por uploadLogo().
         *
         * Así evitamos que:
         *
         * upload -> https://apis...
         *
         * GET    -> http://localhost...
         *
         * tengan comportamientos diferentes.
         */
            $logoUrl = $empresa->logo_url;

            Log::info(
                'Logo path: ' . $logoPath
            );

            Log::info(
                'Logo URL generated: ' . ($logoUrl ?? 'null')
            );

            return response()->json([
                'logo_url' => $logoUrl,
                'logo' => $logoPath,
                'message' => 'Logo encontrado',
            ]);
        } catch (\Exception $e) {
            Log::error(
                'Error obteniendo logo: ' .
                    $e->getMessage()
            );

            return response()->json([
                'logo_url' => null,
                'message' =>
                'Error al obtener logo: ' .
                    $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir logo directamente.
     */
    public function uploadLogo(
        Request $request
    ) {
        $request->validate([
            'logo' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048',
            ],
            'crop' => 'nullable|array',
            'crop.x' => 'nullable|integer|min:0',
            'crop.y' => 'nullable|integer|min:0',
            'crop.width' => 'nullable|integer|min:1',
            'crop.height' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        $empresa = $user->empresa;

        if (!$empresa) {
            return response()->json([
                'message' =>
                'No se encontró la empresa del usuario.',
            ], 404);
        }

        DB::beginTransaction();

        $nuevoLogoPath = null;

        try {
            $logoAnterior = $empresa->logo;

            $cropData = $request->input(
                'crop',
                []
            );

            if (is_string($cropData)) {
                $decoded = json_decode(
                    $cropData,
                    true
                );

                if (
                    json_last_error() ===
                    JSON_ERROR_NONE &&
                    is_array($decoded)
                ) {
                    $cropData = $decoded;
                } else {
                    $cropData = [];
                }
            }

            $nuevoLogoPath =
                $this->procesarLogo(
                    $request->file('logo'),
                    is_array($cropData)
                        ? $cropData
                        : []
                );

            $empresa->update([
                'logo' => $nuevoLogoPath,
            ]);

            DB::commit();

            // -----------------------------------------------------------
            // ELIMINAR LOGO ANTERIOR DESPUÉS DEL COMMIT
            // -----------------------------------------------------------
            if (
                $logoAnterior &&
                $logoAnterior !== $nuevoLogoPath
            ) {
                try {
                    Storage::disk('public')->delete(
                        $logoAnterior
                    );
                } catch (Throwable $cleanupException) {
                    Log::warning(
                        'No se pudo eliminar logo anterior después de actualizar.',
                        [
                            'logo'       => $logoAnterior,
                            'empresa_id' => $empresa->id,
                            'error'      => $cleanupException->getMessage(),
                        ]
                    );
                }
            }

            return response()->json([
                'message' =>
                'Logo actualizado correctamente',

                'logo_url' =>
                $empresa->getLogoUrlAttribute(),

                'logo' =>
                $empresa->logo,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($nuevoLogoPath) {
                try {
                    Storage::disk('public')->delete(
                        $nuevoLogoPath
                    );
                } catch (Throwable $cleanupException) {
                    Log::warning(
                        'No se pudo eliminar logo nuevo después de error.',
                        [
                            'logo'       => $nuevoLogoPath,
                            'empresa_id' => $empresa->id ?? null,
                            'error'      => $cleanupException->getMessage(),
                        ]
                    );
                }
            }

            Log::error(
                'Error subiendo logo: ' .
                    $e->getMessage()
            );

            return response()->json([
                'message' =>
                'Error al subir logo: ' .
                    $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar logo.
     */
    public function deleteLogo(
        Request $request
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        $empresa = $user->empresa;

        if (!$empresa) {
            return response()->json([
                'message' =>
                'No se encontró la empresa del usuario.',
            ], 404);
        }

        DB::beginTransaction();

        try {
            if ($empresa->logo) {
                $logoAnterior = $empresa->logo;

                Storage::disk('public')->delete(
                    $logoAnterior
                );

                $empresa->update([
                    'logo' => null,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' =>
                'Logo eliminado correctamente',
                'logo_url' => null,
                'logo'     => null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error(
                'Error eliminando logo: ' .
                    $e->getMessage()
            );

            return response()->json([
                'message' =>
                'Error al eliminar logo: ' .
                    $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------
    // DESTROY
    // -------------------------------------------------------------------
    public function destroy(
        $id,
        Request $request
    ) {
        // ... (sin cambios)
    }

    // -------------------------------------------------------------------
    // AUDITORÍA
    // -------------------------------------------------------------------
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        if (
            $request->user()?->rol ===
            'superadmin'
        ) {
            return;
        }

        try {
            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues
            );
        } catch (Throwable $e) {
            Log::warning(
                'No se pudo registrar auditoría.',
                [
                    'accion'      => $accion,
                    'tabla'       => $tabla,
                    'registro_id' => $registroId,
                    'error'       => $e->getMessage(),
                ]
            );
        }
    }
}
