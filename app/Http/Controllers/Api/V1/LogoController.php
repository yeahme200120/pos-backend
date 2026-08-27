<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Empresa;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class LogoController extends Controller
{
    /**
     * Obtener la URL del logo de la empresa.
     */
    public function show(Request $request)
    {
        $empresa = $request->user()->empresa;

        return response()->json([
            'logo_url' => $empresa->logo_url,
        ]);
    }

    /**
     * Subir y procesar el logo de la empresa.
     */
    public function upload(Request $request)
    {
        $empresa = $request->user()->empresa;

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // 2MB máximo
        ]);

        $file = $request->file('logo');

        // Procesar imagen con Intervention Image
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);

        // Redimensionar a un máximo de 300x200 manteniendo la proporción
        $image->scale(width: 300, height: 200);

        // Convertir a formato webp para optimizar (opcional)
        // $image->toWebp(quality: 80);

        // Generar nombre único
        $filename = 'logos/' . $empresa->id . '/' . uniqid() . '.' . $file->getClientOriginalExtension();

        // Guardar en storage/app/public
        Storage::disk('public')->put($filename, (string) $image->encode());

        // Eliminar logo anterior si existe
        if ($empresa->logo) {
            Storage::disk('public')->delete($empresa->logo);
        }

        // Actualizar empresa con la ruta del nuevo logo
        $empresa->update(['logo' => $filename]);

        return response()->json([
            'message' => 'Logo actualizado correctamente',
            'logo_url' => $empresa->logo_url,
        ]);
    }

    /**
     * Eliminar el logo de la empresa.
     */
    public function destroy(Request $request)
    {
        $empresa = $request->user()->empresa;

        if ($empresa->logo) {
            Storage::disk('public')->delete($empresa->logo);
            $empresa->update(['logo' => null]);
        }

        return response()->json(['message' => 'Logo eliminado correctamente']);
    }
}