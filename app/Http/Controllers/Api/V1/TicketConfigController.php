<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConfiguracionTicket;
use Illuminate\Validation\Rule;

class TicketConfigController extends Controller
{
    /**
     * Obtener la configuración del ticket de la empresa.
     */
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $config = ConfiguracionTicket::where('empresa_id', $empresaId)->first();

        if (!$config) {
            // Si no existe, crear una configuración por defecto
            $config = ConfiguracionTicket::create([
                'empresa_id' => $empresaId,
                'papel' => '58mm',
                'fuente' => 'Arial',
                'tamano_fuente' => 12,
                'alineacion' => 'izquierda',
                'mostrar_logo' => true,
                'mostrar_qr' => true,
                'qr_contenido' => 'https://miempresa.com',
                'campos' => json_encode([
                    ['nombre' => 'nombre_negocio', 'visible' => true, 'orden' => 1],
                    ['nombre' => 'direccion', 'visible' => true, 'orden' => 2],
                    ['nombre' => 'telefono', 'visible' => true, 'orden' => 3],
                    ['nombre' => 'fecha', 'visible' => true, 'orden' => 4],
                    ['nombre' => 'productos', 'visible' => true, 'orden' => 5],
                    ['nombre' => 'total', 'visible' => true, 'orden' => 6],
                ]),
                'cabecera' => '¡Gracias por su compra!',
                'pie_pagina' => 'Visítenos en www.miempresa.com',
            ]);
        }

        return response()->json($config);
    }

    /**
     * Actualizar la configuración del ticket.
     */
    public function update(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $validated = $request->validate([
            'papel' => ['nullable', Rule::in(['58mm', '80mm'])],
            'fuente' => 'nullable|string|max:50',
            'tamano_fuente' => 'nullable|integer|min:8|max:30',
            'alineacion' => ['nullable', Rule::in(['izquierda', 'centro', 'derecha'])],
            'mostrar_logo' => 'nullable|boolean',
            'mostrar_qr' => 'nullable|boolean',
            'qr_contenido' => 'nullable|string|max:255',
            'campos' => 'nullable|array',
            'cabecera' => 'nullable|string|max:255',
            'pie_pagina' => 'nullable|string|max:255',
        ]);

        $config = ConfiguracionTicket::where('empresa_id', $empresaId)->first();

        if (!$config) {
            return response()->json(['error' => 'Configuración no encontrada'], 404);
        }

        // Si se envían campos como array, codificarlos a JSON
        if (isset($validated['campos'])) {
            $validated['campos'] = json_encode($validated['campos']);
        }

        $config->update($validated);

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'config' => $config
        ]);
    }
}