<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Empresa;
use App\Models\Venta;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Listar usuarios con paginación.
     */
    public function usuarios(Request $request)
    {
        $users = User::with('empresa')
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('numero_usuario', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10);
        
        return response()->json($users);
    }

    /**
     * Crear un nuevo usuario.
     */
    public function crearUsuario(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'rol' => 'required|in:superadmin,admin,vendedor',
            'empresa_id' => 'required|exists:empresas,id',
            'activo' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'numero_usuario' => User::generarNumeroUsuario(),
            'empresa_id' => $request->empresa_id,
            'rol' => $request->rol,
            'activo' => $request->activo ?? true,
        ]);

        return response()->json(['message' => 'Usuario creado correctamente', 'user' => $user]);
    }

    /**
     * Actualizar un usuario.
     */
    public function actualizarUsuario(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'rol' => 'required|in:superadmin,admin,vendedor',
            'empresa_id' => 'required|exists:empresas,id',
            'activo' => 'boolean',
            'licencia_tipo' => 'nullable|in:dia,semana,quincena,mes,bimestre,trimestre,semestre,anual,permanente',
            'licencia_fecha_inicio' => 'nullable|date',
            'licencia_fecha_fin' => 'nullable|date|after_or_equal:licencia_fecha_inicio',
        ]);

        $data = $request->only(['name', 'email', 'rol', 'empresa_id', 'activo', 'licencia_tipo', 'licencia_fecha_inicio', 'licencia_fecha_fin']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json(['message' => 'Usuario actualizado correctamente', 'user' => $user]);
    }

    /**
     * Eliminar un usuario.
     */
    public function eliminarUsuario($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    /**
     * Listar empresas.
     */
    public function empresas()
    {
        $empresas = Empresa::all();
        return response()->json($empresas);
    }

    /**
     * Obtener reportes de ventas.
     */
    public function reportes(Request $request)
    {
        $query = Venta::with(['usuario', 'cliente']);

        if ($request->fecha_desde) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }
        if ($request->fecha_hasta) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $ventas = $query->orderBy('fecha', 'desc')->paginate(20);
        
        $totalVentas = $query->sum('total');
        $numeroTickets = $query->count();
        $ticketPromedio = $numeroTickets > 0 ? $totalVentas / $numeroTickets : 0;

        return response()->json([
            'data' => $ventas->items(),
            'total_ventas' => $totalVentas,
            'numero_tickets' => $numeroTickets,
            'ticket_promedio' => $ticketPromedio,
            'current_page' => $ventas->currentPage(),
            'last_page' => $ventas->lastPage(),
            'per_page' => $ventas->perPage(),
            'total' => $ventas->total(),
        ]);
    }

    /**
     * Exportar reportes a Excel.
     */
    public function exportarReportes(Request $request)
    {
        // Implementar exportación a Excel con Maatwebsite
        // return Excel::download(new VentasExport($request->fecha_desde, $request->fecha_hasta), 'ventas.xlsx');
        return response()->json(['message' => 'Exportación en desarrollo']);
    }

    /**
     * Actualizar configuración de la empresa.
     */
    public function actualizarConfiguracion(Request $request)
    {
        $empresa = $request->user()->empresa;
        
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        if ($request->has('colores')) {
            $empresa->colores = json_encode($request->colores);
        }

        $empresa->save();

        return response()->json(['message' => 'Configuración actualizada correctamente']);
    }
}