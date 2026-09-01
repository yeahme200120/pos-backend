<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mesa;
use Illuminate\Http\Request;

class MesaController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->empresa?->usaMesas() && $request->user()->empresa?->usaCajas(), 422, 'Las mesas no están activas para esta empresa.');
        return response()->json(['success' => true, 'data' => Mesa::where('empresa_id', $request->user()->empresa_id)->orderBy('nombre')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->empresa?->usaMesas() && $request->user()->empresa?->usaCajas(), 422, 'Las mesas no están activas para esta empresa.');
        $request->validate(['nombre' => 'required|string|max:80', 'capacidad' => 'nullable|integer|min:1', 'notas' => 'nullable|string|max:500']);
        $mesa = Mesa::create($request->only('nombre', 'capacidad', 'notas') + ['empresa_id' => $request->user()->empresa_id]);

        return response()->json(['success' => true, 'data' => $mesa], 201);
    }

    public function update(Request $request, $id)
    {
        abort_unless($request->user()->empresa?->usaMesas() && $request->user()->empresa?->usaCajas(), 422, 'Las mesas no están activas para esta empresa.');
        $request->validate(['nombre' => 'sometimes|required|string|max:80', 'capacidad' => 'nullable|integer|min:1', 'activo' => 'nullable|boolean', 'notas' => 'nullable|string|max:500']);
        $mesa = Mesa::where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
        $mesa->update($request->only('nombre', 'capacidad', 'activo', 'notas'));

        return response()->json(['success' => true, 'data' => $mesa->fresh()]);
    }
}
