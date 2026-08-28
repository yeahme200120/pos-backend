<?php
// app/Http/Controllers/Api/V1/EmpresaController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class EmpresaController extends Controller
{
    /**
     * Listar empresas
     */
    public function index(Request $request)
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $query = Empresa::query();

        if ($request->search) {
            $query->where('nombre', 'LIKE', "%{$request->search}%")
                ->orWhere('rfc', 'LIKE', "%{$request->search}%");
        }

        if ($request->activo !== null) {
            $query->where('activo', $request->activo);
        }

        $empresas = $query->orderBy('nombre', 'asc')
            ->paginate($request->per_page ?? 20);

        return response()->json($empresas);
    }

    /**
     * Mostrar una empresa
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        if ($user->rol === 'superadmin') {
            $empresa = Empresa::findOrFail($id);
        } else {
            $empresa = Empresa::where('id', $user->empresa_id)->findOrFail($id);
        }

        return response()->json($empresa);
    }

    public function store(Request $request)
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string|max:20',
            'email_contacto' => 'nullable|email|max:255',
            'rfc' => 'nullable|string|max:20',
            'razon_social' => 'nullable|string|max:255',
            'leyenda_ticket' => 'nullable|string',
            'whatsapp_numero' => 'nullable|string|max:20',
            'colores' => 'nullable',
            'configuracion' => 'nullable',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'activo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            Log::info('=== INICIO STORE EMPRESA ===', [
                'request_all' => $request->all(),
                'colores_raw' => $request->input('colores'),
                'colores_type' => gettype($request->input('colores')),
            ]);

            $data = $request->except(['logo', 'colores', 'configuracion']);

            // ✅ Procesar colores
            if ($request->has('colores')) {
                $colores = $request->input('colores');

                if (is_string($colores)) {
                    $coloresDecodificados = json_decode($colores, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($coloresDecodificados)) {
                        $colores = $coloresDecodificados;
                        Log::info('Colores decodificados de string JSON:', $colores);
                    }
                }

                if (is_array($colores)) {
                    $coloresCompletos = array_merge([
                        'primary' => '#1E293B',
                        'secondary' => '#108981',
                        'background' => '#f3f4f6',
                        'text' => '#FFFFFF',
                        'text_navbar' => '#FFFFFF',
                        'menu_hover' => '#2d3748'
                    ], $colores);

                    $data['colores'] = json_encode($coloresCompletos);
                    Log::info('Colores procesados para guardar:', $coloresCompletos);
                }
            }

            // ✅ Procesar configuración
            if ($request->has('configuracion')) {
                $config = $request->input('configuracion');

                if (is_string($config)) {
                    $configDecodificado = json_decode($config, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($configDecodificado)) {
                        $config = $configDecodificado;
                    }
                }

                if (is_array($config)) {
                    $data['configuracion'] = json_encode($config);
                }
            }

            // ✅ Procesar activo
            if ($request->has('activo')) {
                $activo = $request->input('activo');
                if (is_string($activo)) {
                    $activo = in_array(strtolower($activo), ['1', 'true', 'on', 'yes']);
                }
                $data['activo'] = (bool) $activo;
            } else {
                $data['activo'] = true;
            }

            // ✅ Procesar logo
            if ($request->hasFile('logo')) {
                $cropData = $request->input('logo_crop', []);
                if (is_string($cropData)) {
                    $cropData = json_decode($cropData, true) ?? [];
                }
                $logoPath = $this->procesarLogo($request->file('logo'), $cropData);
                $data['logo'] = $logoPath;
                Log::info('Logo guardado:', ['logo' => $logoPath]);
            }

            Log::info('Datos finales para crear:', $data);

            $empresa = Empresa::create($data);

            Log::info('Empresa creada exitosamente:', [
                'empresa_id' => $empresa->id,
                'colores_guardados' => $empresa->colores,
                'activo' => $empresa->activo
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Empresa creada correctamente',
                'data' => $empresa
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando empresa: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error al crear empresa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar empresa
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user->rol === 'superadmin') {
            $empresa = Empresa::findOrFail($id);
        } else {
            $empresa = Empresa::where('id', $user->empresa_id)->findOrFail($id);
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string|max:20',
            'email_contacto' => 'nullable|email|max:255',
            'rfc' => 'nullable|string|max:20',
            'razon_social' => 'nullable|string|max:255',
            'leyenda_ticket' => 'nullable|string',
            'whatsapp_numero' => 'nullable|string|max:20',
            'colores' => 'nullable',
            'configuracion' => 'nullable',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'activo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Log para debugging
            Log::info('=== INICIO UPDATE EMPRESA ===', [
                'empresa_id' => $id,
                'request_all' => $request->all(),
                'colores_raw' => $request->input('colores'),
                'colores_type' => gettype($request->input('colores')),
            ]);

            // Crear array de datos a actualizar
            $data = $request->except(['logo', 'colores', 'configuracion']);

            // ✅ Procesar colores
            if ($request->has('colores')) {
                $colores = $request->input('colores');

                // Si es string JSON, decodificar
                if (is_string($colores)) {
                    $coloresDecodificados = json_decode($colores, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($coloresDecodificados)) {
                        $colores = $coloresDecodificados;
                        Log::info('Colores decodificados de string JSON:', $colores);
                    } else {
                        Log::warning('Error decodificando colores JSON:', [
                            'error' => json_last_error_msg(),
                            'colores_string' => $colores
                        ]);
                    }
                }

                // Si es array, guardar como JSON
                if (is_array($colores)) {
                    // Asegurar que tenga las claves necesarias
                    $coloresCompletos = array_merge([
                        'primary' => '#1E293B',
                        'secondary' => '#108981',
                        'background' => '#f3f4f6',
                        'text' => '#FFFFFF',
                        'text_navbar' => '#FFFFFF',
                        'menu_hover' => '#2d3748'
                    ], $colores);

                    $data['colores'] = json_encode($coloresCompletos);
                    Log::info('Colores procesados para guardar:', $coloresCompletos);
                } else {
                    Log::warning('Colores no es array después de procesar:', [
                        'type' => gettype($colores),
                        'value' => $colores
                    ]);
                }
            }

            // ✅ Procesar configuración
            if ($request->has('configuracion')) {
                $config = $request->input('configuracion');

                if (is_string($config)) {
                    $configDecodificado = json_decode($config, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($configDecodificado)) {
                        $config = $configDecodificado;
                        Log::info('Configuración decodificada de string JSON');
                    }
                }

                if (is_array($config)) {
                    $data['configuracion'] = json_encode($config);
                    Log::info('Configuración procesada para guardar');
                }
            }

            // ✅ Procesar activo
            if ($request->has('activo')) {
                // Convertir a boolean correctamente
                $activo = $request->input('activo');
                if (is_string($activo)) {
                    $activo = in_array(strtolower($activo), ['1', 'true', 'on', 'yes']);
                }
                $data['activo'] = (bool) $activo;
                Log::info('Activo procesado:', ['activo' => $data['activo']]);
            }

            // ✅ Procesar logo con opciones de recorte
            if ($request->hasFile('logo')) {
                Log::info('Procesando logo nuevo');

                // Eliminar logo anterior
                if ($empresa->logo) {
                    Storage::disk('public')->delete($empresa->logo);
                    Log::info('Logo anterior eliminado:', ['logo' => $empresa->logo]);
                }

                $cropData = $request->input('logo_crop', []);
                if (is_string($cropData)) {
                    $cropData = json_decode($cropData, true) ?? [];
                }

                $logoPath = $this->procesarLogo($request->file('logo'), $cropData);
                $data['logo'] = $logoPath;
                Log::info('Nuevo logo guardado:', ['logo' => $logoPath]);
            }

            // Log de datos finales
            Log::info('Datos finales para actualizar:', $data);

            // Actualizar empresa
            $empresa->update($data);

            // Recargar empresa para obtener datos actualizados
            $empresa->refresh();

            Log::info('Empresa actualizada exitosamente:', [
                'empresa_id' => $empresa->id,
                'colores_guardados' => $empresa->colores,
                'activo' => $empresa->activo
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Empresa actualizada correctamente',
                'data' => $empresa
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando empresa: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error al actualizar empresa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar logo con opciones de recorte
     */
    private function procesarLogo($file, $cropData = [])
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file);

            // ✅ Aplicar recorte si se especifica
            if (!empty($cropData) && isset($cropData['x']) && isset($cropData['y']) && isset($cropData['width']) && isset($cropData['height'])) {
                $image->crop(
                    intval($cropData['width']),
                    intval($cropData['height']),
                    intval($cropData['x']),
                    intval($cropData['y'])
                );
            }

            // Redimensionar a 200x200 manteniendo proporción
            $image->scale(width: 200, height: 200);

            // Convertir a WebP para mejor optimización
            $encoded = $image->toWebp(quality: 80);

            // Generar nombre único
            $filename = 'empresas/' . Str::uuid() . '.webp';

            // Guardar en storage
            Storage::disk('public')->put($filename, (string) $encoded);

            return $filename;
        } catch (\Exception $e) {
            Log::error('Error procesando logo: ' . $e->getMessage());
            throw $e;
        }
    }

    public function logo(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'logo_url' => null,
                    'message' => 'No autenticado'
                ], 401);
            }

            $empresa = $user->empresa;

            if (!$empresa || !$empresa->logo) {
                return response()->json([
                    'logo_url' => null,
                    'message' => 'No hay logo configurado'
                ]);
            }

            // Construir URL manualmente
            $logoPath = $empresa->logo;
            $logoUrl = null;

            // Verificar si el archivo existe en storage
            if (Storage::disk('public')->exists($logoPath)) {
                $logoUrl = Storage::disk('public')->url($logoPath);
            } else {
                // Construir URL manualmente
                $baseUrl = rtrim(config('app.url'), '/');
                $logoUrl = $baseUrl . '/storage/' . ltrim($logoPath, '/');
            }

            Log::info('Logo path: ' . $logoPath);
            Log::info('Logo URL generated: ' . $logoUrl);

            return response()->json([
                'logo_url' => $logoUrl,
                'logo' => $logoPath,
                'message' => 'Logo encontrado'
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo logo: ' . $e->getMessage());
            return response()->json([
                'logo_url' => null,
                'message' => 'Error al obtener logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir logo directamente
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'crop' => 'nullable|array',
            'crop.x' => 'nullable|integer',
            'crop.y' => 'nullable|integer',
            'crop.width' => 'nullable|integer',
            'crop.height' => 'nullable|integer',
        ]);

        $empresa = $request->user()->empresa;

        DB::beginTransaction();
        try {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }

            $cropData = $request->input('crop', []);
            $logoPath = $this->procesarLogo($request->file('logo'), $cropData);
            $empresa->update(['logo' => $logoPath]);

            DB::commit();

            return response()->json([
                'message' => 'Logo actualizado correctamente',
                'logo_url' => $empresa->getLogoUrlAttribute()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error subiendo logo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al subir logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar logo
     */
    public function deleteLogo(Request $request)
    {
        $empresa = $request->user()->empresa;

        DB::beginTransaction();
        try {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
                $empresa->update(['logo' => null]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Logo eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error eliminando logo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar empresa
     */
    public function destroy($id, Request $request)
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $empresa = Empresa::findOrFail($id);

        if ($empresa->users()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la empresa porque tiene usuarios asociados'
            ], 422);
        }

        DB::beginTransaction();
        try {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }

            $empresa->delete();

            DB::commit();

            return response()->json([
                'message' => 'Empresa eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error eliminando empresa: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar empresa: ' . $e->getMessage()
            ], 500);
        }
    }
}
