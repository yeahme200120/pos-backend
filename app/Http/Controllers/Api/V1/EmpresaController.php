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
        'primary'   => '#1E293B',
        'secondary' => '#108981',
        'background'=> '#f3f4f6',
        'text'      => '#FFFFFF',
        'text_navbar' => '#FFFFFF',
        'menu_hover'  => '#2d3748',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    // -------------------------------------------------------------------
    // INDEX (sin cambios)
    // -------------------------------------------------------------------
    public function index(Request $request)
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json(['message' => 'No autorizado.'], 403);
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
                $query->where('activo', (bool) $validated['activo']);
            }

            $perPage = (int) ($validated['per_page'] ?? 20);
            $empresas = $query->orderBy('nombre', 'asc')->paginate($perPage);

            $this->registrarAuditoria(
                $request,
                'empresas.consultadas',
                'empresas',
                null,
                null,
                [
                    'search'    => $validated['search'] ?? null,
                    'activo'    => $validated['activo'] ?? null,
                    'pagina'    => $empresas->currentPage(),
                    'por_pagina'=> $empresas->perPage(),
                    'total'     => $empresas->total(),
                ]
            );

            return response()->json($empresas);
        } catch (Throwable $e) {
            Log::error('Error listando empresas.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al cargar empresas.'], 500);
        }
    }

    // -------------------------------------------------------------------
    // SHOW (sin cambios)
    // -------------------------------------------------------------------
    public function show($id, Request $request)
    {
        $user = $request->user();

        try {
            if ($user->rol === 'superadmin') {
                $empresa = Empresa::findOrFail($id);
            } else {
                $empresa = Empresa::query()->where('id', $user->empresa_id)->findOrFail($id);
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
            Log::error('Error consultando empresa.', [
                'empresa_id' => $id,
                'usuario_id' => $user?->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------
    // STORE (corregido: se asignan arrays en lugar de JSON string)
    // -------------------------------------------------------------------
    public function store(Request $request)
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'nombre'        => 'required|string|max:255',
            'direccion'     => 'nullable|string|max:500',
            'telefono'      => 'nullable|string|max:20',
            'email_contacto'=> 'nullable|email|max:255',
            'rfc'           => 'nullable|string|max:20',
            'razon_social'  => 'nullable|string|max:255',
            'leyenda_ticket'=> 'nullable|string|max:2000',
            'whatsapp_numero' => 'nullable|string|max:20',
            'colores'       => 'nullable',
            'configuracion' => 'nullable',
            'logo'          => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'logo_crop'     => 'nullable',
            'logo_crop.x'   => 'nullable|integer|min:0',
            'logo_crop.y'   => 'nullable|integer|min:0',
            'logo_crop.width'  => 'nullable|integer|min:1',
            'logo_crop.height' => 'nullable|integer|min:1',
            'activo'        => 'nullable|boolean',
        ]);

        $logoPath = null;

        try {
            $data = $request->only(self::CAMPOS_EMPRESA);
            $data['nombre'] = trim($data['nombre']);
            $data['activo'] = $request->has('activo') ? $request->boolean('activo') : true;

            // --- Colores (se asigna un array, Eloquent lo convertirá a JSON) ---
            if ($request->has('colores')) {
                $valorColores = $request->input('colores');
                if (!$this->estaVacioJson($valorColores)) {
                    $colores = $this->normalizarJsonArray($valorColores);
                    if ($colores === null) {
                        return response()->json(['message' => 'El campo colores debe contener un JSON válido.'], 422);
                    }
                    $data['colores'] = array_merge(self::COLORES_DEFAULT, $colores); // ARRAY
                }
            }

            // --- Configuración (se asigna un array) ---
            if ($request->has('configuracion')) {
                $valorConfiguracion = $request->input('configuracion');
                if (!$this->estaVacioJson($valorConfiguracion)) {
                    $configuracion = $this->normalizarJsonArray($valorConfiguracion);
                    if ($configuracion === null) {
                        return response()->json(['message' => 'El campo configuracion debe contener un JSON válido.'], 422);
                    }
                    $data['configuracion'] = $configuracion; // ARRAY
                }
            }

            // --- Logo ---
            if ($request->hasFile('logo')) {
                $logoPath = $this->procesarLogo(
                    $request->file('logo'),
                    $this->obtenerCropData($request)
                );
                $data['logo'] = $logoPath;
            }

            $empresa = DB::transaction(function () use ($data) {
                return Empresa::create($data);
            });

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
                    Storage::disk('public')->delete($logoPath);
                } catch (Throwable $cleanupException) {
                    Log::warning('No se pudo eliminar logo después de error.', [
                        'logo' => $logoPath,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            Log::error('Error creando empresa.', [
                'error'          => $e->getMessage(),
                'empresa_nombre' => $request->input('nombre'),
                'trace'          => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Error al crear empresa.'], 500);
        }
    }

    // -------------------------------------------------------------------
    // UPDATE (corregido: logo opcional, colores como array)
    // -------------------------------------------------------------------
    public function update(Request $request, $id)
    {
        $user = $request->user();

        try {
            if ($user->rol === 'superadmin') {
                $empresa = Empresa::findOrFail($id);
            } else {
                $empresa = Empresa::query()->where('id', $user->empresa_id)->findOrFail($id);
            }

            // Validación: el logo solo se valida si realmente es un archivo subido
            $rules = [
                'nombre'        => 'required|string|max:255',
                'direccion'     => 'nullable|string|max:500',
                'telefono'      => 'nullable|string|max:20',
                'email_contacto'=> 'nullable|email|max:255',
                'rfc'           => 'nullable|string|max:20',
                'razon_social'  => 'nullable|string|max:255',
                'leyenda_ticket'=> 'nullable|string|max:2000',
                'whatsapp_numero' => 'nullable|string|max:20',
                'colores'       => 'nullable',
                'configuracion' => 'nullable',
                'logo_crop'     => 'nullable',
                'logo_crop.x'   => 'nullable|integer|min:0',
                'logo_crop.y'   => 'nullable|integer|min:0',
                'logo_crop.width'  => 'nullable|integer|min:1',
                'logo_crop.height' => 'nullable|integer|min:1',
                'activo'        => 'nullable|boolean',
            ];

            if ($request->hasFile('logo')) {
                $rules['logo'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048';
            }

            $request->validate($rules);

            // Restricción para no superadmin con configuración no vacía
            if (
                $user->rol !== 'superadmin' &&
                $request->has('configuracion') &&
                !$this->estaVacioJson($request->input('configuracion'))
            ) {
                return response()->json([
                    'message' => 'No autorizado para modificar la configuración de la empresa.',
                ], 403);
            }

            $datosAntes   = $empresa->toArray();
            $logoAnterior = $empresa->logo;
            $nuevoLogoPath = null;

            $data = $request->only(self::CAMPOS_EMPRESA);

            if (isset($data['nombre'])) {
                $data['nombre'] = trim($data['nombre']);
            }

            if ($request->has('activo')) {
                $data['activo'] = $request->boolean('activo');
            }

            // --- Colores (se asigna array, no JSON string) ---
            if ($request->has('colores')) {
                $valorColores = $request->input('colores');
                if (!$this->estaVacioJson($valorColores)) {
                    $coloresNuevos = $this->normalizarJsonArray($valorColores);
                    if ($coloresNuevos === null) {
                        return response()->json(['message' => 'El campo colores debe contener un JSON válido.'], 422);
                    }
                    $coloresActuales = $this->normalizarJsonArray($empresa->colores) ?? [];
                    $data['colores'] = array_merge(self::COLORES_DEFAULT, $coloresActuales, $coloresNuevos); // ARRAY
                }
            }

            // --- Configuración (solo superadmin, se asigna array) ---
            if ($user->rol === 'superadmin' && $request->has('configuracion')) {
                $valorConfiguracion = $request->input('configuracion');
                if (!$this->estaVacioJson($valorConfiguracion)) {
                    $configuracion = $this->normalizarJsonArray($valorConfiguracion);
                    if ($configuracion === null) {
                        return response()->json(['message' => 'El campo configuracion debe contener un JSON válido.'], 422);
                    }
                    $data['configuracion'] = $configuracion; // ARRAY
                }
            }

            // --- Logo: solo si se sube un archivo ---
            if ($request->hasFile('logo')) {
                $nuevoLogoPath = $this->procesarLogo(
                    $request->file('logo'),
                    $this->obtenerCropData($request)
                );
                $data['logo'] = $nuevoLogoPath;
            }

            // Guardar
            DB::transaction(function () use ($empresa, $data) {
                $empresa->update($data);
            });

            $empresa->refresh();

            // Eliminar logo anterior si se subió uno nuevo
            if ($nuevoLogoPath && $logoAnterior && $logoAnterior !== $nuevoLogoPath) {
                try {
                    Storage::disk('public')->delete($logoAnterior);
                } catch (Throwable $cleanupException) {
                    Log::warning('No se pudo eliminar logo anterior.', [
                        'logo'       => $logoAnterior,
                        'empresa_id' => $empresa->id,
                        'error'      => $cleanupException->getMessage(),
                    ]);
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
            // Limpiar archivo nuevo si hubo error
            if (isset($nuevoLogoPath) && $nuevoLogoPath) {
                try {
                    Storage::disk('public')->delete($nuevoLogoPath);
                } catch (Throwable $cleanupException) {
                    Log::warning('No se pudo eliminar nuevo logo después de error.', [
                        'logo'       => $nuevoLogoPath,
                        'empresa_id' => $empresa->id ?? $id,
                        'error'      => $cleanupException->getMessage(),
                    ]);
                }
            }

            // Si es una excepción HTTP (ej. 404, 403) la relanzamos para que Laravel la maneje
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            // Registrar el error detallado en el log
            Log::error('Error actualizando empresa.', [
                'empresa_id' => $id,
                'usuario_id' => $user?->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            // En desarrollo puedes devolver el mensaje real, en producción el genérico
            $mensaje = config('app.debug') ? $e->getMessage() : 'Error al actualizar empresa.';
            return response()->json(['message' => $mensaje], 500);
        }
    }

    // -------------------------------------------------------------------
    // PROCESAR LOGO (sin cambios)
    // -------------------------------------------------------------------
    private function procesarLogo($file, array $cropData = []): string
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);

        if (isset($cropData['x'], $cropData['y'], $cropData['width'], $cropData['height'])) {
            $width  = (int) $cropData['width'];
            $height = (int) $cropData['height'];
            $x      = (int) $cropData['x'];
            $y      = (int) $cropData['y'];
            if ($width > 0 && $height > 0 && $x >= 0 && $y >= 0) {
                $image->crop($width, $height, $x, $y);
            }
        }

        $image->scale(width: 200, height: 200);
        $encoded = $image->toWebp(quality: 80);

        $filename = 'empresas/' . Str::uuid() . '.webp';
        Storage::disk('public')->put($filename, (string) $encoded);

        return $filename;
    }

    // -------------------------------------------------------------------
    // OBTENER CROP (sin cambios)
    // -------------------------------------------------------------------
    private function obtenerCropData(Request $request): array
    {
        $cropData = $request->input('logo_crop', []);
        if (is_string($cropData)) {
            $decoded = json_decode($cropData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $cropData = $decoded;
            } else {
                $cropData = [];
            }
        }
        return is_array($cropData) ? $cropData : [];
    }

    // -------------------------------------------------------------------
    // UTILIDADES (sin cambios)
    // -------------------------------------------------------------------
    private function estaVacioJson($value): bool
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        return false;
    }

    private function normalizarJsonArray($value): ?array
    {
        if (is_array($value)) return $value;
        if (!is_string($value)) return null;
        $value = trim($value);
        if ($value === '') return null;
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    // -------------------------------------------------------------------
    // LOGO (endpoints adicionales sin cambios)
    // -------------------------------------------------------------------
    public function logo(Request $request)
    {
        // ... (sin cambios, se mantiene igual)
        // Por brevedad no lo copio aquí, pero debe quedar como estaba.
    }

    public function uploadLogo(Request $request)
    {
        // ... (sin cambios)
    }

    public function deleteLogo(Request $request)
    {
        // ... (sin cambios)
    }

    public function destroy($id, Request $request)
    {
        // ... (sin cambios)
    }

    // -------------------------------------------------------------------
    // AUDITORÍA (sin cambios)
    // -------------------------------------------------------------------
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        if ($request->user()?->rol === 'superadmin') {
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
            Log::warning('No se pudo registrar auditoría.', [
                'accion'     => $accion,
                'tabla'      => $tabla,
                'registro_id'=> $registroId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}