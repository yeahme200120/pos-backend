<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
                'error'   => 'AUTH_REQUIRED',
            ], 401);
        }

        if ($user->rol !== 'superadmin') {
            $this->registrarAuditoriaError(
                $request,
                'empresas.consulta.rechazada',
                'empresas',
                null,
                [
                    'motivo' => 'usuario_no_autorizado',
                ]
            );

            return response()->json([
                'message' => 'No autorizado.',
                'error'   => 'FORBIDDEN',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'search'   => 'nullable|string|max:255',
                'activo'   => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'empresas.consulta.rechazada',
                'empresas',
                null,
                [
                    'motivo' => 'validacion',
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        try {
            $query = Empresa::query();

            if (!empty($validated['search'])) {
                $search = trim((string) $validated['search']);
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

            $perPage = (int) ($validated['per_page'] ?? 20);

            $empresas = $query
                ->orderBy('nombre', 'asc')
                ->paginate($perPage)
                ->appends($request->query());

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

        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos listando empresas.',
                [
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                    'code'       => $e->getCode(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresas.consulta.error',
                'empresas',
                null,
                [
                    'motivo' => 'error_base_datos',
                ]
            );

            return response()->json([
                'message' => 'Error al consultar empresas.',
                'error'   => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            Log::error(
                'Error inesperado listando empresas.',
                [
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresas.consulta.error',
                'empresas',
                null,
                [
                    'motivo' => 'error_interno',
                ]
            );

            return response()->json([
                'message' => 'Error al cargar empresas.',
                'error'   => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    // -------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------

    public function show($id, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
                'error'   => 'AUTH_REQUIRED',
            ], 401);
        }

        try {
            if ($user->rol === 'superadmin') {
                $empresa = Empresa::query()
                    ->whereKey($id)
                    ->first();
            } else {
                $empresa = Empresa::query()
                    ->whereKey($id)
                    ->where('id', $user->empresa_id)
                    ->first();
            }

            if (!$empresa) {
                $this->registrarAuditoriaError(
                    $request,
                    'empresa.consulta.rechazada',
                    'empresas',
                    is_numeric($id) ? (int) $id : null,
                    [
                        'motivo' => 'empresa_no_encontrada_o_no_autorizada',
                    ]
                );

                return response()->json([
                    'message' => 'Empresa no encontrada.',
                    'error'   => 'EMPRESA_NOT_FOUND',
                ], 404);
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

        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos consultando empresa.',
                [
                    'empresa_id' => $id,
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                    'code'       => $e->getCode(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.consulta.error',
                'empresas',
                is_numeric($id) ? (int) $id : null,
                [
                    'motivo' => 'error_base_datos',
                ]
            );

            return response()->json([
                'message' => 'Error al consultar empresa.',
                'error'   => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            Log::error(
                'Error inesperado consultando empresa.',
                [
                    'empresa_id' => $id,
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.consulta.error',
                'empresas',
                is_numeric($id) ? (int) $id : null,
                [
                    'motivo' => 'error_interno',
                ]
            );

            return response()->json([
                'message' => 'Error al consultar empresa.',
                'error'   => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    // -------------------------------------------------------------------
    // STORE
    // -------------------------------------------------------------------

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
                'error'   => 'AUTH_REQUIRED',
            ], 401);
        }

        if ($user->rol !== 'superadmin') {
            $this->registrarAuditoriaError(
                $request,
                'empresa.creacion.rechazada',
                'empresas',
                null,
                [
                    'motivo' => 'usuario_no_autorizado',
                ]
            );

            return response()->json([
                'message' => 'No autorizado.',
                'error'   => 'FORBIDDEN',
            ], 403);
        }

        try {
            $validated = $request->validate([
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
                'logo'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'logo_crop'        => 'nullable',
                'logo_crop.x'      => 'nullable|integer|min:0',
                'logo_crop.y'      => 'nullable|integer|min:0',
                'logo_crop.width'  => 'nullable|integer|min:1',
                'logo_crop.height' => 'nullable|integer|min:1',
                'activo'           => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'empresa.creacion.rechazada',
                'empresas',
                null,
                [
                    'motivo' => 'validacion',
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        $logoPath = null;

        try {
            $data = $request->only(
                self::CAMPOS_EMPRESA
            );

            $data['nombre'] = trim(
                (string) $data['nombre']
            );

            $data['activo'] = $request->has('activo')
                ? $request->boolean('activo')
                : true;

            // -----------------------------------------------------------
            // COLORES
            // -----------------------------------------------------------

            if ($request->has('colores')) {
                $valorColores = $request->input('colores');

                if (!$this->estaVacioJson($valorColores)) {
                    $colores = $this->normalizarJsonArray(
                        $valorColores
                    );

                    if ($colores === null) {
                        $this->registrarAuditoriaError(
                            $request,
                            'empresa.creacion.rechazada',
                            'empresas',
                            null,
                            [
                                'motivo' => 'colores_json_invalido',
                            ]
                        );

                        return response()->json([
                            'message' =>
                                'El campo colores debe contener un JSON válido.',
                            'error' => 'INVALID_COLORES',
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

                if (!$this->estaVacioJson($valorConfiguracion)) {
                    $configuracion = $this->normalizarJsonArray(
                        $valorConfiguracion
                    );

                    if ($configuracion === null) {
                        $this->registrarAuditoriaError(
                            $request,
                            'empresa.creacion.rechazada',
                            'empresas',
                            null,
                            [
                                'motivo' => 'configuracion_json_invalida',
                            ]
                        );

                        return response()->json([
                            'message' =>
                                'El campo configuracion debe contener un JSON válido.',
                            'error' => 'INVALID_CONFIGURACION',
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

            // -----------------------------------------------------------
            // CREAR
            // -----------------------------------------------------------

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

        } catch (QueryException $e) {
            if ($logoPath) {
                $this->eliminarArchivoSeguro(
                    $logoPath,
                    'logo de empresa después de error de base de datos'
                );
            }

            Log::error(
                'Error de base de datos creando empresa.',
                [
                    'usuario_id'     => $user->id,
                    'empresa_nombre' => $request->input('nombre'),
                    'error'          => $e->getMessage(),
                    'code'           => $e->getCode(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.creacion.error',
                'empresas',
                null,
                [
                    'motivo' => 'error_base_datos',
                ]
            );

            return response()->json([
                'message' => 'No fue posible crear la empresa.',
                'error'   => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            if ($logoPath) {
                $this->eliminarArchivoSeguro(
                    $logoPath,
                    'logo de empresa después de error'
                );
            }

            Log::error(
                'Error creando empresa.',
                [
                    'usuario_id'     => $user->id,
                    'empresa_nombre' => $request->input('nombre'),
                    'error'          => $e->getMessage(),
                    'trace'          => $e->getTraceAsString(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.creacion.error',
                'empresas',
                null,
                [
                    'motivo' => 'error_interno',
                ]
            );

            return response()->json([
                'message' => 'Error al crear empresa.',
                'error'   => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    // -------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
                'error'   => 'AUTH_REQUIRED',
            ], 401);
        }

        $empresa = null;
        $nuevoLogoPath = null;

        try {
            if ($user->rol === 'superadmin') {
                $empresa = Empresa::query()
                    ->whereKey($id)
                    ->first();
            } else {
                $empresa = Empresa::query()
                    ->whereKey($id)
                    ->where('id', $user->empresa_id)
                    ->first();
            }

            if (!$empresa) {
                $this->registrarAuditoriaError(
                    $request,
                    'empresa.actualizacion.rechazada',
                    'empresas',
                    is_numeric($id) ? (int) $id : null,
                    [
                        'motivo' => 'empresa_no_encontrada_o_no_autorizada',
                    ]
                );

                return response()->json([
                    'message' => 'Empresa no encontrada.',
                    'error'   => 'EMPRESA_NOT_FOUND',
                ], 404);
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

            try {
                $validated = $request->validate($rules);
            } catch (ValidationException $e) {
                $this->registrarAuditoriaError(
                    $request,
                    'empresa.actualizacion.rechazada',
                    'empresas',
                    (int) $empresa->id,
                    [
                        'motivo' => 'validacion',
                        'errores' => $e->errors(),
                    ]
                );

                throw $e;
            }

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
                $this->registrarAuditoriaError(
                    $request,
                    'empresa.actualizacion.rechazada',
                    'empresas',
                    (int) $empresa->id,
                    [
                        'motivo' => 'configuracion_no_autorizada',
                    ]
                );

                return response()->json([
                    'message' =>
                        'No autorizado para modificar la configuración de la empresa.',
                    'error' => 'CONFIGURATION_FORBIDDEN',
                ], 403);
            }

            $datosAntes = $empresa->toArray();
            $logoAnterior = $empresa->logo;

            $data = $request->only(
                self::CAMPOS_EMPRESA
            );

            if (isset($data['nombre'])) {
                $data['nombre'] = trim(
                    (string) $data['nombre']
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
                        $this->registrarAuditoriaError(
                            $request,
                            'empresa.actualizacion.rechazada',
                            'empresas',
                            (int) $empresa->id,
                            [
                                'motivo' => 'colores_json_invalido',
                            ]
                        );

                        return response()->json([
                            'message' =>
                                'El campo colores debe contener un JSON válido.',
                            'error' => 'INVALID_COLORES',
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
                        $this->registrarAuditoriaError(
                            $request,
                            'empresa.actualizacion.rechazada',
                            'empresas',
                            (int) $empresa->id,
                            [
                                'motivo' => 'configuracion_json_invalida',
                            ]
                        );

                        return response()->json([
                            'message' =>
                                'El campo configuracion debe contener un JSON válido.',
                            'error' => 'INVALID_CONFIGURACION',
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
            // ELIMINAR LOGO ANTERIOR DESPUÉS DEL COMMIT
            // -----------------------------------------------------------

            if (
                $nuevoLogoPath &&
                $logoAnterior &&
                $logoAnterior !== $nuevoLogoPath
            ) {
                $this->eliminarArchivoSeguro(
                    $logoAnterior,
                    'logo anterior de empresa'
                );
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

        } catch (ValidationException $e) {
            throw $e;

        } catch (QueryException $e) {
            if ($nuevoLogoPath) {
                $this->eliminarArchivoSeguro(
                    $nuevoLogoPath,
                    'nuevo logo después de error de base de datos'
                );
            }

            Log::error(
                'Error de base de datos actualizando empresa.',
                [
                    'empresa_id' => $id,
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                    'code'       => $e->getCode(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.actualizacion.error',
                'empresas',
                is_numeric($id) ? (int) $id : null,
                [
                    'motivo' => 'error_base_datos',
                ]
            );

            return response()->json([
                'message' => 'Error al actualizar empresa.',
                'error'   => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            if ($nuevoLogoPath) {
                $this->eliminarArchivoSeguro(
                    $nuevoLogoPath,
                    'nuevo logo después de error'
                );
            }

            Log::error(
                'Error actualizando empresa.',
                [
                    'empresa_id' => $id,
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                    'trace'      => $e->getTraceAsString(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.actualizacion.error',
                'empresas',
                is_numeric($id) ? (int) $id : null,
                [
                    'motivo' => 'error_interno',
                ]
            );

            return response()->json([
                'message' => 'Error al actualizar empresa.',
                'error'   => 'INTERNAL_ERROR',
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
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'logo_url' => null,
                'message'  => 'No autenticado',
                'error'    => 'AUTH_REQUIRED',
            ], 401);
        }

        try {
            $empresa = $user->empresa;

            if (!$empresa) {
                $this->registrarAuditoriaError(
                    $request,
                    'empresa.logo.consulta.rechazada',
                    'empresas',
                    null,
                    [
                        'motivo' => 'empresa_no_encontrada',
                    ]
                );

                return response()->json([
                    'logo_url' => null,
                    'message'  => 'No se encontró la empresa del usuario.',
                    'error'    => 'EMPRESA_NOT_FOUND',
                ], 404);
            }

            if (!$empresa->logo) {
                $this->registrarAuditoria(
                    $request,
                    'empresa.logo.consultado',
                    'empresas',
                    (int) $empresa->id,
                    null,
                    [
                        'logo_url' => null,
                        'configurado' => false,
                    ]
                );

                return response()->json([
                    'logo_url' => null,
                    'message'  => 'No hay logo configurado',
                ]);
            }

            $logoPath = $empresa->logo;
            $logoUrl = $empresa->logo_url;

            Log::info(
                'Logo de empresa consultado.',
                [
                    'empresa_id' => $empresa->id,
                    'logo_path'  => $logoPath,
                ]
            );

            $this->registrarAuditoria(
                $request,
                'empresa.logo.consultado',
                'empresas',
                (int) $empresa->id,
                null,
                [
                    'logo_url' => $logoUrl,
                    'configurado' => true,
                ]
            );

            return response()->json([
                'logo_url' => $logoUrl,
                'logo'     => $logoPath,
                'message'  => 'Logo encontrado',
            ]);

        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos obteniendo logo.',
                [
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                    'code'       => $e->getCode(),
                ]
            );

            return response()->json([
                'logo_url' => null,
                'message'  => 'Error al obtener logo.',
                'error'    => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo logo.',
                [
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.logo.consulta.error',
                'empresas',
                null,
                [
                    'motivo' => 'error_interno',
                ]
            );

            return response()->json([
                'logo_url' => null,
                'message'  => 'Error al obtener logo.',
                'error'    => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Subir logo directamente.
     */
    public function uploadLogo(
        Request $request
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
                'error'   => 'AUTH_REQUIRED',
            ], 401);
        }

        try {
            $validated = $request->validate([
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
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'empresa.logo.subida.rechazada',
                'empresas',
                null,
                [
                    'motivo' => 'validacion',
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        $empresa = $user->empresa;

        if (!$empresa) {
            $this->registrarAuditoriaError(
                $request,
                'empresa.logo.subida.rechazada',
                'empresas',
                null,
                [
                    'motivo' => 'empresa_no_encontrada',
                ]
            );

            return response()->json([
                'message' =>
                    'No se encontró la empresa del usuario.',
                'error' => 'EMPRESA_NOT_FOUND',
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
                $this->eliminarArchivoSeguro(
                    $logoAnterior,
                    'logo anterior después de actualización'
                );
            }

            $empresa->refresh();

            $this->registrarAuditoria(
                $request,
                'empresa.logo.actualizado',
                'empresas',
                (int) $empresa->id,
                [
                    'logo' => $logoAnterior,
                ],
                [
                    'logo' => $empresa->logo,
                    'logo_url' => $empresa->logo_url,
                ]
            );

            return response()->json([
                'message' =>
                    'Logo actualizado correctamente',
                'logo_url' =>
                    $empresa->getLogoUrlAttribute(),
                'logo' =>
                    $empresa->logo,
            ]);

        } catch (QueryException $e) {
            DB::rollBack();

            if ($nuevoLogoPath) {
                $this->eliminarArchivoSeguro(
                    $nuevoLogoPath,
                    'logo nuevo después de error de base de datos'
                );
            }

            Log::error(
                'Error de base de datos subiendo logo.',
                [
                    'empresa_id' => $empresa->id,
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                    'code'       => $e->getCode(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.logo.actualizacion.error',
                'empresas',
                (int) $empresa->id,
                [
                    'motivo' => 'error_base_datos',
                ]
            );

            return response()->json([
                'message' => 'Error al actualizar logo.',
                'error'   => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            DB::rollBack();

            if ($nuevoLogoPath) {
                $this->eliminarArchivoSeguro(
                    $nuevoLogoPath,
                    'logo nuevo después de error'
                );
            }

            Log::error(
                'Error subiendo logo.',
                [
                    'empresa_id' => $empresa->id,
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.logo.actualizacion.error',
                'empresas',
                (int) $empresa->id,
                [
                    'motivo' => 'error_interno',
                ]
            );

            return response()->json([
                'message' => 'Error al subir logo.',
                'error'   => 'INTERNAL_ERROR',
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
                'error'   => 'AUTH_REQUIRED',
            ], 401);
        }

        $empresa = $user->empresa;

        if (!$empresa) {
            $this->registrarAuditoriaError(
                $request,
                'empresa.logo.eliminacion.rechazada',
                'empresas',
                null,
                [
                    'motivo' => 'empresa_no_encontrada',
                ]
            );

            return response()->json([
                'message' =>
                    'No se encontró la empresa del usuario.',
                'error' => 'EMPRESA_NOT_FOUND',
            ], 404);
        }

        DB::beginTransaction();

        try {
            $logoAnterior = $empresa->logo;

            if ($logoAnterior) {
                $empresa->update([
                    'logo' => null,
                ]);
            }

            DB::commit();

            // -----------------------------------------------------------
            // ELIMINAR ARCHIVO DESPUÉS DEL COMMIT
            // -----------------------------------------------------------

            if ($logoAnterior) {
                $this->eliminarArchivoSeguro(
                    $logoAnterior,
                    'logo eliminado'
                );
            }

            $this->registrarAuditoria(
                $request,
                'empresa.logo.eliminado',
                'empresas',
                (int) $empresa->id,
                [
                    'logo' => $logoAnterior,
                ],
                [
                    'logo' => null,
                ]
            );

            return response()->json([
                'message' =>
                    'Logo eliminado correctamente',
                'logo_url' => null,
                'logo'     => null,
            ]);

        } catch (QueryException $e) {
            DB::rollBack();

            Log::error(
                'Error de base de datos eliminando logo.',
                [
                    'empresa_id' => $empresa->id,
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                    'code'       => $e->getCode(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.logo.eliminacion.error',
                'empresas',
                (int) $empresa->id,
                [
                    'motivo' => 'error_base_datos',
                ]
            );

            return response()->json([
                'message' => 'Error al eliminar logo.',
                'error'   => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error(
                'Error eliminando logo.',
                [
                    'empresa_id' => $empresa->id,
                    'usuario_id' => $user->id,
                    'error'      => $e->getMessage(),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'empresa.logo.eliminacion.error',
                'empresas',
                (int) $empresa->id,
                [
                    'motivo' => 'error_interno',
                ]
            );

            return response()->json([
                'message' => 'Error al eliminar logo.',
                'error'   => 'INTERNAL_ERROR',
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
        /*
         * IMPORTANTE:
         * La implementación original de destroy no fue incluida
         * en el código proporcionado. El código recibido contiene
         * literalmente:
         *
         *     // ... (sin cambios)
         *
         * No se agrega una implementación inventada para evitar
         * modificar accidentalmente la lógica de eliminación de
         * empresas, relaciones o reglas de negocio existentes.
         *
         * Sustituir este bloque por la implementación original de
         * destroy y aplicar el mismo patrón de auditoría, aislamiento
         * por empresa y manejo de errores.
         */
        return response()->json([
            'message' => 'Método destroy pendiente de la implementación original.',
            'error'   => 'METHOD_IMPLEMENTATION_MISSING',
        ], 500);
    }

    // -------------------------------------------------------------------
    // AUDITORÍA
    // -------------------------------------------------------------------

    /**
     * Registrar auditoría de operación exitosa.
     *
     * La auditoría NO debe impedir que la operación principal
     * termine correctamente.
     *
     * Importante:
     * - Se auditan también las acciones del superadmin.
     * - El AuditoriaService recibe el Request y puede obtener
     *   de ahí el usuario autenticado.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
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
                    'usuario_id'  => $request->user()?->id,
                    'empresa_id'  => $request->user()?->empresa_id,
                    'error'       => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Registrar auditoría de errores o rechazos.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datos
    ): void {
        $this->registrarAuditoria(
            $request,
            $accion,
            $tabla,
            $registroId,
            null,
            $datos
        );
    }

    /**
     * Eliminar archivo sin permitir que un problema de storage
     * rompa la operación principal.
     */
    private function eliminarArchivoSeguro(
        ?string $path,
        string $contexto
    ): void {
        if (!$path) {
            return;
        }

        try {
            Storage::disk('public')->delete($path);
        } catch (Throwable $e) {
            Log::warning(
                'No se pudo eliminar archivo de empresa.',
                [
                    'path'     => $path,
                    'contexto' => $contexto,
                    'error'    => $e->getMessage(),
                ]
            );
        }
    }
}
