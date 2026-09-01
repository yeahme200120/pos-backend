<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Empresa;
use App\Models\Venta;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Listar usuarios con paginación.
     */
    public function usuarios(Request $request)
    {
        Log::info('📋 Listando usuarios - Filtros:', $request->all());

        try {
            $users = User::with('empresa')
                ->when($request->search, function ($q, $search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('numero_usuario', 'like', "%{$search}%");
                })
                ->when($request->rol, function ($q, $rol) {
                    $q->where('rol', $rol);
                })
                ->when($request->activo !== null, function ($q) use ($request) {
                    $q->where('activo', $request->activo);
                })
                ->orderBy('id', 'desc')
                ->paginate(10);
            
            Log::info('✅ Usuarios listados: ' . $users->total() . ' registros');
            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('❌ Error al listar usuarios: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar usuarios'], 500);
        }
    }

    /**
     * Crear un nuevo usuario.
     */
    public function crearUsuario(Request $request)
    {
        Log::info('📝 Creando usuario - Datos:', $request->all());

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'telefono' => 'nullable|string|max:20',
            'rol' => 'required|in:superadmin,admin,cajero,vendedor',
            'empresa_id' => 'required|exists:empresas,id',
            'activo' => 'boolean',
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'telefono.max' => 'El teléfono no puede tener más de 20 caracteres.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol seleccionado no es válido.',
            'empresa_id.required' => 'La empresa es obligatoria.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ]);

        // ✅ Limpiar teléfono: solo números
        $telefono = $request->telefono ? preg_replace('/[^0-9]/', '', $request->telefono) : null;
        if ($telefono && strlen($telefono) !== 10) {
            $telefono = null;
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'telefono' => $telefono,
                'numero_usuario' => User::generarNumeroUsuario(),
                'empresa_id' => $request->empresa_id,
                'rol' => $request->rol,
                'activo' => $request->activo ?? true,
            ]);

            DB::commit();
            Log::info('✅ Usuario creado - ID: ' . $user->id);

            return response()->json([
                'message' => 'Usuario creado correctamente',
                'user' => $user->load('empresa')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error al crear usuario: ' . $e->getMessage());
            return response()->json(['message' => 'Error al crear usuario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar un usuario.
     */
    public function actualizarUsuario(Request $request, $id)
    {
        Log::info('✏️ Actualizando usuario ID: ' . $id);
        Log::info('📝 Datos recibidos:', $request->all());

        try {
            $user = User::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $id,
                'telefono' => 'nullable|string|max:20', // ✅ AGREGADO
                'rol' => 'required|in:superadmin,admin,cajero,vendedor',
                'empresa_id' => 'required|exists:empresas,id',
                'activo' => 'boolean',
                'licencia_tipo' => 'nullable|in:dia,semana,quincena,mes,bimestre,trimestre,semestre,anual,permanente',
                'licencia_fecha_inicio' => 'nullable|date',
                'licencia_fecha_fin' => 'nullable|date|after_or_equal:licencia_fecha_inicio',
            ], [
                'name.required' => 'El nombre es obligatorio.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'email.unique' => 'Este correo electrónico ya está registrado.',
                'telefono.max' => 'El teléfono no puede tener más de 20 caracteres.',
                'rol.required' => 'El rol es obligatorio.',
                'rol.in' => 'El rol seleccionado no es válido.',
                'empresa_id.required' => 'La empresa es obligatoria.',
                'empresa_id.exists' => 'La empresa seleccionada no existe.',
                'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
                'licencia_tipo.in' => 'El tipo de licencia no es válido.',
                'licencia_fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            ]);

            // ✅ Limpiar teléfono
            $telefono = $request->telefono ? preg_replace('/[^0-9]/', '', $request->telefono) : null;
            if ($telefono && strlen($telefono) !== 10) {
                $telefono = null;
            }

            // ✅ Construir array manualmente incluyendo telefono
            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'telefono' => $telefono, // ✅ AGREGADO
                'rol' => $request->rol,
                'empresa_id' => $request->empresa_id,
                'activo' => $request->activo ?? true,
                'licencia_tipo' => $request->licencia_tipo,
                'licencia_fecha_inicio' => $request->licencia_fecha_inicio,
                'licencia_fecha_fin' => $request->licencia_fecha_fin,
            ];
            
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            Log::info('📝 Datos a actualizar:', $data);

            DB::beginTransaction();
            $user->update($data);
            $user->refresh();
            DB::commit();

            Log::info('✅ Usuario actualizado - Teléfono: ' . $user->telefono);

            return response()->json([
                'message' => 'Usuario actualizado correctamente',
                'user' => $user->load('empresa')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error al actualizar usuario: ' . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar usuario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar un usuario.
     */
    public function eliminarUsuario($id)
    {
        Log::info('🗑️ Eliminando usuario ID: ' . $id);

        try {
            $user = User::findOrFail($id);

            if (auth()->id() === $user->id) {
                return response()->json(['message' => 'No puedes eliminar tu propio usuario'], 403);
            }

            DB::beginTransaction();
            $user->delete();
            DB::commit();

            Log::info('✅ Usuario eliminado - ID: ' . $id);
            return response()->json(['message' => 'Usuario eliminado correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error al eliminar usuario: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar usuario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Listar empresas.
     */
    public function empresas()
    {
        try {
            $empresas = Empresa::select('id', 'nombre', 'rfc', 'activo')->get();
            return response()->json($empresas);
        } catch (\Exception $e) {
            Log::error('❌ Error al listar empresas: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar empresas'], 500);
        }
    }

    /**
     * Obtener reportes de ventas.
     */
    public function reportes(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('❌ Error al generar reportes: ' . $e->getMessage());
            return response()->json(['message' => 'Error al generar reportes'], 500);
        }
    }

    /**
     * Exportar reportes a Excel.
     */
    public function exportarReportes(Request $request)
    {
        return response()->json(['message' => 'Exportación en desarrollo']);
    }

    /**
     * Actualizar configuración de la empresa.
     */
    public function configuracion(Request $request)
    {
        $empresa = $request->user()->empresa;
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        return response()->json([
            'nombre' => $empresa->nombre,
            'colores' => is_string($empresa->colores)
                ? json_decode($empresa->colores, true)
                : $empresa->colores,
        ]);
    }

    public function actualizarConfiguracion(Request $request)
    {
        $empresa = $request->user()->empresa;
        
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        $request->validate([
            'colores' => 'nullable|array',
            'colores.primary' => 'nullable|string',
            'colores.secondary' => 'nullable|string',
            'colores.background' => 'nullable|string',
            'colores.text' => 'nullable|string',
            'colores.navbar' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            if ($request->has('colores')) {
                $empresa->colores = json_encode($request->colores);
            }
            $empresa->save();
            DB::commit();

            return response()->json([
                'message' => 'Configuración actualizada correctamente',
                'colores' => json_decode($empresa->colores, true)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error al actualizar configuración: ' . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar configuración'], 500);
        }
    }
}
