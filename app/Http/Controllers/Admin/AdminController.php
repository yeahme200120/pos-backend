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
            Log::error('❌ Error al listar usuarios: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al cargar usuarios'
            ], 500);
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
            'telefono' => 'nullable|numeric|digits:10', // ✅ NÚMERICO, 10 DÍGITOS
            'rol' => 'required|in:superadmin,admin,vendedor',
            'empresa_id' => 'required|exists:empresas,id',
            'activo' => 'boolean',
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser un texto válido.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',

            'telefono.numeric' => 'El teléfono debe ser un número válido.',
            'telefono.digits' => 'El teléfono debe tener exactamente 10 dígitos.',

            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol seleccionado no es válido. Opciones: superadmin, admin, vendedor.',

            'empresa_id.required' => 'La empresa es obligatoria.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',

            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'telefono' => $request->telefono,
                'numero_usuario' => User::generarNumeroUsuario(),
                'empresa_id' => $request->empresa_id,
                'rol' => $request->rol,
                'activo' => $request->activo ?? true,
            ]);

            DB::commit();

            Log::info('✅ Usuario creado correctamente - ID: ' . $user->id . ', Email: ' . $user->email);

            return response()->json([
                'message' => 'Usuario creado correctamente',
                'user' => $user->load('empresa')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error al crear usuario: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al crear usuario: ' . $e->getMessage()
            ], 500);
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
            Log::info('👤 Usuario encontrado:', $user->toArray());

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $id,
                'telefono' => 'numeric|digits:10', // ✅ NÚMERICO, 10 DÍGITOS
                'rol' => 'required|in:superadmin,admin,vendedor',
                'empresa_id' => 'required|exists:empresas,id',
                'activo' => 'boolean',
                'licencia_tipo' => 'nullable|in:dia,semana,quincena,mes,bimestre,trimestre,semestre,anual,permanente',
                'licencia_fecha_inicio' => 'nullable|date',
                'licencia_fecha_fin' => 'nullable|date|after_or_equal:licencia_fecha_inicio',
            ], [
                'name.required' => 'El nombre es obligatorio.',
                'name.string' => 'El nombre debe ser un texto válido.',
                'name.max' => 'El nombre no puede tener más de 255 caracteres.',

                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'email.unique' => 'Este correo electrónico ya está registrado.',

                'telefono.numeric' => 'El teléfono debe ser un número válido.',
                'telefono.digits' => 'El teléfono debe tener exactamente 10 dígitos.',

                'rol.required' => 'El rol es obligatorio.',
                'rol.in' => 'El rol seleccionado no es válido. Opciones: superadmin, admin, vendedor.',

                'empresa_id.required' => 'La empresa es obligatoria.',
                'empresa_id.exists' => 'La empresa seleccionada no existe.',

                'activo.boolean' => 'El estado activo debe ser verdadero o falso.',

                'licencia_tipo.in' => 'El tipo de licencia no es válido.',
                'licencia_fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
                'licencia_fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
                'licencia_fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            ]);

            Log::info('✅ Validación pasada correctamente');

            $data = $request->only([
                'name',
                'email',
                'telefono',
                'rol',
                'empresa_id',
                'activo',
                'licencia_tipo',
                'licencia_fecha_inicio',
                'licencia_fecha_fin'
            ]);

            if ($request->filled('password')) {
                $request->validate([
                    'password' => 'min:6',
                ], [
                    'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
                ]);
                $data['password'] = Hash::make($request->password);
            }

            Log::info('📝 Datos a actualizar:', $data);

            DB::beginTransaction();
            $user->update($data);
            $user->refresh();
            DB::commit();

            Log::info('✅ Usuario actualizado correctamente - ID: ' . $user->id);

            return response()->json([
                'message' => 'Usuario actualizado correctamente',
                'user' => $user->load('empresa')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ Usuario no encontrado - ID: ' . $id);
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Error de validación:', $e->errors());
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error al actualizar usuario: ' . $e->getMessage(), [
                'user_id' => $id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ], 500);
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
            Log::info('👤 Usuario encontrado:', $user->toArray());

            // Evitar eliminar el propio usuario
            if (auth()->id() === $user->id) {
                Log::warning('⚠️ Intento de eliminar propio usuario - ID: ' . $id);
                return response()->json([
                    'message' => 'No puedes eliminar tu propio usuario'
                ], 403);
            }

            DB::beginTransaction();
            $user->delete();
            DB::commit();

            Log::info('✅ Usuario eliminado correctamente - ID: ' . $id . ', Email: ' . $user->email);

            return response()->json([
                'message' => 'Usuario eliminado correctamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ Usuario no encontrado - ID: ' . $id);
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error al eliminar usuario: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al eliminar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar empresas.
     */
    public function empresas()
    {
        Log::info('📋 Listando empresas');

        try {
            $empresas = Empresa::select('id', 'nombre', 'rfc', 'activo')->get();
            Log::info('✅ Empresas listadas: ' . $empresas->count() . ' registros');
            return response()->json($empresas);
        } catch (\Exception $e) {
            Log::error('❌ Error al listar empresas: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al cargar empresas'
            ], 500);
        }
    }

    /**
     * Obtener reportes de ventas.
     */
    public function reportes(Request $request)
    {
        Log::info('📊 Generando reportes - Filtros:', $request->all());

        try {
            $query = Venta::with(['usuario', 'cliente']);

            if ($request->fecha_desde) {
                $query->whereDate('fecha', '>=', $request->fecha_desde);
                Log::info('📅 Fecha desde: ' . $request->fecha_desde);
            }
            if ($request->fecha_hasta) {
                $query->whereDate('fecha', '<=', $request->fecha_hasta);
                Log::info('📅 Fecha hasta: ' . $request->fecha_hasta);
            }
            if ($request->estado) {
                $query->where('estado', $request->estado);
                Log::info('📌 Estado: ' . $request->estado);
            }

            $ventas = $query->orderBy('fecha', 'desc')->paginate(20);

            $totalVentas = $query->sum('total');
            $numeroTickets = $query->count();
            $ticketPromedio = $numeroTickets > 0 ? $totalVentas / $numeroTickets : 0;

            Log::info('✅ Reporte generado - Ventas: ' . $numeroTickets . ', Total: $' . $totalVentas);

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
            Log::error('❌ Error al generar reportes: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al generar reportes'
            ], 500);
        }
    }

    /**
     * Exportar reportes a Excel.
     */
    public function exportarReportes(Request $request)
    {
        Log::info('📤 Exportando reportes - Filtros:', $request->all());

        try {
            // Implementar exportación a Excel con Maatwebsite
            // return Excel::download(new VentasExport($request->fecha_desde, $request->fecha_hasta), 'ventas.xlsx');

            Log::info('✅ Exportación en desarrollo');
            return response()->json([
                'message' => 'Exportación en desarrollo'
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error al exportar reportes: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al exportar reportes'
            ], 500);
        }
    }

    /**
     * Actualizar configuración de la empresa.
     */
    public function actualizarConfiguracion(Request $request)
    {
        Log::info('⚙️ Actualizando configuración de empresa - Datos:', $request->all());

        $empresa = $request->user()->empresa;

        if (!$empresa) {
            Log::error('❌ Empresa no encontrada para usuario: ' . $request->user()->id);
            return response()->json([
                'message' => 'Empresa no encontrada'
            ], 404);
        }

        Log::info('🏢 Empresa encontrada - ID: ' . $empresa->id . ', Nombre: ' . $empresa->nombre);

        $request->validate([
            'colores' => 'nullable|array',
            'colores.primary' => 'nullable|string',
            'colores.secondary' => 'nullable|string',
            'colores.background' => 'nullable|string',
            'colores.text' => 'nullable|string',
            'colores.navbar' => 'nullable|string',
        ], [
            'colores.array' => 'Los colores deben ser un arreglo válido.',
            'colores.primary.string' => 'El color principal debe ser un texto válido.',
            'colores.secondary.string' => 'El color secundario debe ser un texto válido.',
            'colores.background.string' => 'El color de fondo debe ser un texto válido.',
            'colores.text.string' => 'El color de texto debe ser un texto válido.',
            'colores.navbar.string' => 'El color de la barra debe ser un texto válido.',
        ]);

        DB::beginTransaction();
        try {
            if ($request->has('colores')) {
                $empresa->colores = json_encode($request->colores);
                Log::info('🎨 Colores actualizados:', $request->colores);
            }

            $empresa->save();

            DB::commit();

            Log::info('✅ Configuración actualizada para empresa: ' . $empresa->id);

            return response()->json([
                'message' => 'Configuración actualizada correctamente',
                'colores' => json_decode($empresa->colores, true)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error al actualizar configuración: ' . $e->getMessage(), [
                'empresa_id' => $empresa->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al actualizar configuración: ' . $e->getMessage()
            ], 500);
        }
    }
}
