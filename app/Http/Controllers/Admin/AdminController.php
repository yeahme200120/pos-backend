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
        Log::info(
            '📋 Listando usuarios - Filtros:',
            $request->all()
        );

        try {

            $users = User::with('empresa')
                ->when(
                    $request->search,
                    function ($q, $search) {

                        $q->where(function ($query) use ($search) {

                            $query
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'numero_usuario',
                                    'like',
                                    "%{$search}%"
                                );
                        });
                    }
                )
                ->when(
                    $request->rol,
                    function ($q, $rol) {
                        $q->where(
                            'rol',
                            $rol
                        );
                    }
                )
                ->when(
                    $request->activo !== null,
                    function ($q) use ($request) {
                        $q->where(
                            'activo',
                            $request->activo
                        );
                    }
                )
                ->orderBy(
                    'id',
                    'desc'
                )
                ->paginate(10);

            Log::info(
                '✅ Usuarios listados: ' .
                $users->total() .
                ' registros'
            );

            return response()->json(
                $users
            );

        } catch (\Exception $e) {

            Log::error(
                '❌ Error al listar usuarios: ' .
                $e->getMessage(),
                [
                    'trace' =>
                        $e->getTraceAsString()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al cargar usuarios'
            ], 500);
        }
    }

    /**
     * Crear usuario.
     */
    public function crearUsuario(Request $request)
    {
        Log::info(
            '📝 Creando usuario - Datos:',
            $request->all()
        );

        $request->validate([
            'name' =>
                'required|string|max:255',

            'email' =>
                'required|email|unique:users,email',

            'password' =>
                'required|min:6',

            'telefono' =>
                'nullable|numeric|digits:10',

            'rol' =>
                'required|in:superadmin,admin,vendedor,cajero',

            'empresa_id' =>
                'required|exists:empresas,id',

            'activo' =>
                'boolean',
        ], [

            'name.required' =>
                'El nombre es obligatorio.',

            'name.string' =>
                'El nombre debe ser un texto válido.',

            'name.max' =>
                'El nombre no puede tener más de 255 caracteres.',

            'email.required' =>
                'El correo electrónico es obligatorio.',

            'email.email' =>
                'Ingresa un correo electrónico válido.',

            'email.unique' =>
                'Este correo electrónico ya está registrado.',

            'password.required' =>
                'La contraseña es obligatoria.',

            'password.min' =>
                'La contraseña debe tener al menos 6 caracteres.',

            'telefono.numeric' =>
                'El teléfono debe ser un número válido.',

            'telefono.digits' =>
                'El teléfono debe tener exactamente 10 dígitos.',

            'rol.required' =>
                'El rol es obligatorio.',

            'rol.in' =>
                'El rol seleccionado no es válido.',

            'empresa_id.required' =>
                'La empresa es obligatoria.',

            'empresa_id.exists' =>
                'La empresa seleccionada no existe.',

            'activo.boolean' =>
                'El estado activo debe ser verdadero o falso.',
        ]);

        DB::beginTransaction();

        try {

            $user = User::create([
                'name' =>
                    $request->name,

                'email' =>
                    $request->email,

                'password' =>
                    Hash::make(
                        $request->password
                    ),

                'telefono' =>
                    $request->telefono,

                'numero_usuario' =>
                    User::generarNumeroUsuario(),

                'empresa_id' =>
                    $request->empresa_id,

                'rol' =>
                    $request->rol,

                'activo' =>
                    $request->activo ?? true,
            ]);

            DB::commit();

            Log::info(
                '✅ Usuario creado correctamente',
                [
                    'id' => $user->id,
                    'email' => $user->email
                ]
            );

            return response()->json([
                'message' =>
                    'Usuario creado correctamente',

                'user' =>
                    $user->load('empresa')
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error(
                '❌ Error al crear usuario: ' .
                $e->getMessage(),
                [
                    'data' =>
                        $request->all(),

                    'trace' =>
                        $e->getTraceAsString()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al crear usuario: ' .
                    $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar usuario.
     *
     * La licencia NO se actualiza aquí.
     * La licencia pertenece a la empresa.
     */
    public function actualizarUsuario(
        Request $request,
        $id
    ) {
        Log::info(
            '✏️ Actualizando usuario ID: ' .
            $id
        );

        Log::info(
            '📝 Datos recibidos:',
            $request->all()
        );

        try {

            $user = User::findOrFail($id);

            Log::info(
                '👤 Usuario encontrado:',
                $user->toArray()
            );

            $request->validate([
                'name' =>
                    'required|string|max:255',

                'email' =>
                    'required|email|unique:users,email,' .
                    $id,

                'telefono' =>
                    'nullable|numeric|digits:10',

                'rol' =>
                    'required|in:superadmin,admin,vendedor,cajero',

                'empresa_id' =>
                    'required|exists:empresas,id',

                'activo' =>
                    'boolean',
            ], [

                'name.required' =>
                    'El nombre es obligatorio.',

                'name.string' =>
                    'El nombre debe ser un texto válido.',

                'name.max' =>
                    'El nombre no puede tener más de 255 caracteres.',

                'email.required' =>
                    'El correo electrónico es obligatorio.',

                'email.email' =>
                    'Ingresa un correo electrónico válido.',

                'email.unique' =>
                    'Este correo electrónico ya está registrado.',

                'telefono.numeric' =>
                    'El teléfono debe ser un número válido.',

                'telefono.digits' =>
                    'El teléfono debe tener exactamente 10 dígitos.',

                'rol.required' =>
                    'El rol es obligatorio.',

                'rol.in' =>
                    'El rol seleccionado no es válido.',

                'empresa_id.required' =>
                    'La empresa es obligatoria.',

                'empresa_id.exists' =>
                    'La empresa seleccionada no existe.',

                'activo.boolean' =>
                    'El estado activo debe ser verdadero o falso.',
            ]);

            $data = $request->only([
                'name',
                'email',
                'telefono',
                'rol',
                'empresa_id',
                'activo',
            ]);

            if ($request->filled('password')) {

                $request->validate([
                    'password' =>
                        'min:6',
                ], [
                    'password.min' =>
                        'La contraseña debe tener al menos 6 caracteres.',
                ]);

                $data['password'] =
                    Hash::make(
                        $request->password
                    );
            }

            DB::beginTransaction();

            $user->update(
                $data
            );

            $user->refresh();

            DB::commit();

            Log::info(
                '✅ Usuario actualizado correctamente',
                [
                    'id' => $user->id
                ]
            );

            return response()->json([
                'message' =>
                    'Usuario actualizado correctamente',

                'user' =>
                    $user->load('empresa')
            ]);

        } catch (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e
        ) {

            DB::rollBack();

            return response()->json([
                'message' =>
                    'Usuario no encontrado'
            ], 404);

        } catch (
            \Illuminate\Validation\ValidationException $e
        ) {

            DB::rollBack();

            return response()->json([
                'message' =>
                    'Error de validación',

                'errors' =>
                    $e->errors()
            ], 422);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error(
                '❌ Error al actualizar usuario',
                [
                    'user_id' =>
                        $id,

                    'data' =>
                        $request->all(),

                    'error' =>
                        $e->getMessage(),

                    'trace' =>
                        $e->getTraceAsString()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al actualizar usuario: ' .
                    $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar usuario.
     */
    public function eliminarUsuario($id)
    {
        Log::info(
            '🗑️ Eliminando usuario ID: ' .
            $id
        );

        try {

            $user = User::findOrFail($id);

            if (
                auth()->id() === $user->id
            ) {

                return response()->json([
                    'message' =>
                        'No puedes eliminar tu propio usuario'
                ], 403);
            }

            DB::beginTransaction();

            $user->delete();

            DB::commit();

            Log::info(
                '✅ Usuario eliminado correctamente',
                [
                    'id' => $id,
                    'email' => $user->email
                ]
            );

            return response()->json([
                'message' =>
                    'Usuario eliminado correctamente'
            ]);

        } catch (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e
        ) {

            DB::rollBack();

            return response()->json([
                'message' =>
                    'Usuario no encontrado'
            ], 404);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error(
                '❌ Error al eliminar usuario',
                [
                    'user_id' =>
                        $id,

                    'error' =>
                        $e->getMessage(),

                    'trace' =>
                        $e->getTraceAsString()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al eliminar usuario'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EMPRESAS
    |--------------------------------------------------------------------------
    */

    /**
     * Listar empresas incluyendo información de licencia.
     */
    public function empresas()
    {
        Log::info(
            '📋 Listando empresas'
        );

        try {

            $empresas = Empresa::select([
                'id',
                'nombre',
                'rfc',
                'activo',
                'licencia_tipo',
                'licencia_fecha_inicio',
                'licencia_fecha_fin',
                'licencia_activa',
            ])
            ->orderBy(
                'nombre'
            )
            ->get()
            ->map(function ($empresa) {

                return [
                    'id' =>
                        $empresa->id,

                    'nombre' =>
                        $empresa->nombre,

                    'rfc' =>
                        $empresa->rfc,

                    'activo' =>
                        $empresa->activo,

                    'licencia_tipo' =>
                        $empresa->licencia_tipo,

                    'licencia_fecha_inicio' =>
                        $empresa->licencia_fecha_inicio,

                    'licencia_fecha_fin' =>
                        $empresa->licencia_fecha_fin,

                    'licencia_activa' =>
                        $empresa->licencia_activa,

                    'licencia_vigente' =>
                        $empresa->tieneLicenciaActiva(),

                    'licencia_vencida' =>
                        $empresa->licenciaVencida(),

                    'licencia_pendiente' =>
                        $empresa->licenciaPendiente(),

                    'dias_restantes' =>
                        $empresa->diasLicenciaRestantes(),

                    'estado_licencia' =>
                        $empresa->estadoLicencia(),
                ];
            });

            return response()->json(
                $empresas
            );

        } catch (\Exception $e) {

            Log::error(
                '❌ Error al listar empresas',
                [
                    'error' =>
                        $e->getMessage(),

                    'trace' =>
                        $e->getTraceAsString()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al cargar empresas'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LICENCIA
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener licencia de una empresa.
     */
    public function obtenerLicenciaEmpresa(
        $empresaId
    ) {
        try {

            $empresa =
                Empresa::findOrFail(
                    $empresaId
                );

            return response()->json([
                'empresa_id' =>
                    $empresa->id,

                'empresa' =>
                    $empresa->nombre,

                'licencia_tipo' =>
                    $empresa->licencia_tipo,

                'licencia_fecha_inicio' =>
                    $empresa->licencia_fecha_inicio,

                'licencia_fecha_fin' =>
                    $empresa->licencia_fecha_fin,

                'licencia_activa' =>
                    $empresa->licencia_activa,

                'licencia_vigente' =>
                    $empresa->tieneLicenciaActiva(),

                'licencia_vencida' =>
                    $empresa->licenciaVencida(),

                'licencia_pendiente' =>
                    $empresa->licenciaPendiente(),

                'dias_restantes' =>
                    $empresa->diasLicenciaRestantes(),

                'estado_licencia' =>
                    $empresa->estadoLicencia(),
            ]);

        } catch (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e
        ) {

            return response()->json([
                'message' =>
                    'Empresa no encontrada'
            ], 404);

        } catch (\Exception $e) {

            Log::error(
                '❌ Error obteniendo licencia',
                [
                    'empresa_id' =>
                        $empresaId,

                    'error' =>
                        $e->getMessage()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al obtener licencia'
            ], 500);
        }
    }

    /**
     * Actualizar licencia de una empresa.
     */
    public function actualizarLicenciaEmpresa(
        Request $request,
        $empresaId
    ) {
        Log::info(
            '🔐 Actualizando licencia de empresa',
            [
                'empresa_id' =>
                    $empresaId,

                'datos' =>
                    $request->all()
            ]
        );

        $request->validate([
            'licencia_tipo' => [
                'required',
                'in:dia,semana,quincena,mes,bimestre,trimestre,semestre,anual,permanente'
            ],

            'licencia_fecha_inicio' => [
                'required',
                'date'
            ],

            'licencia_fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:licencia_fecha_inicio'
            ],

            'licencia_activa' => [
                'required',
                'boolean'
            ],
        ], [

            'licencia_tipo.required' =>
                'El tipo de licencia es obligatorio.',

            'licencia_tipo.in' =>
                'El tipo de licencia seleccionado no es válido.',

            'licencia_fecha_inicio.required' =>
                'La fecha de inicio es obligatoria.',

            'licencia_fecha_inicio.date' =>
                'La fecha de inicio no es válida.',

            'licencia_fecha_fin.date' =>
                'La fecha de fin no es válida.',

            'licencia_fecha_fin.after_or_equal' =>
                'La fecha de fin debe ser igual o posterior a la fecha de inicio.',

            'licencia_activa.required' =>
                'Debe indicar si la licencia está activa.',

            'licencia_activa.boolean' =>
                'El estado de licencia no es válido.',
        ]);

        DB::beginTransaction();

        try {

            $empresa =
                Empresa::findOrFail(
                    $empresaId
                );

            $fechaInicio =
                $request->licencia_fecha_inicio;

            $fechaFin =
                $request->licencia_tipo === 'permanente'
                    ? null
                    : $request->licencia_fecha_fin;

            $empresa->update([
                'licencia_tipo' =>
                    $request->licencia_tipo,

                'licencia_fecha_inicio' =>
                    $fechaInicio,

                'licencia_fecha_fin' =>
                    $fechaFin,

                'licencia_activa' =>
                    $request->boolean(
                        'licencia_activa'
                    ),
            ]);

            $empresa->refresh();

            DB::commit();

            Log::info(
                '✅ Licencia actualizada correctamente',
                [
                    'empresa_id' =>
                        $empresa->id,

                    'tipo' =>
                        $empresa->licencia_tipo,

                    'fecha_inicio' =>
                        $empresa->licencia_fecha_inicio,

                    'fecha_fin' =>
                        $empresa->licencia_fecha_fin,

                    'activa' =>
                        $empresa->licencia_activa,
                ]
            );

            return response()->json([
                'message' =>
                    'Licencia actualizada correctamente',

                'licencia' => [
                    'empresa_id' =>
                        $empresa->id,

                    'empresa' =>
                        $empresa->nombre,

                    'licencia_tipo' =>
                        $empresa->licencia_tipo,

                    'licencia_fecha_inicio' =>
                        $empresa->licencia_fecha_inicio,

                    'licencia_fecha_fin' =>
                        $empresa->licencia_fecha_fin,

                    'licencia_activa' =>
                        $empresa->licencia_activa,

                    'licencia_vigente' =>
                        $empresa->tieneLicenciaActiva(),

                    'licencia_vencida' =>
                        $empresa->licenciaVencida(),

                    'licencia_pendiente' =>
                        $empresa->licenciaPendiente(),

                    'dias_restantes' =>
                        $empresa->diasLicenciaRestantes(),

                    'estado_licencia' =>
                        $empresa->estadoLicencia(),
                ]
            ]);

        } catch (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e
        ) {

            DB::rollBack();

            return response()->json([
                'message' =>
                    'Empresa no encontrada'
            ], 404);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error(
                '❌ Error actualizando licencia',
                [
                    'empresa_id' =>
                        $empresaId,

                    'error' =>
                        $e->getMessage(),

                    'trace' =>
                        $e->getTraceAsString()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al actualizar licencia: ' .
                    $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REPORTES
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener reportes de ventas.
     */
    public function reportes(
        Request $request
    ) {
        Log::info(
            '📊 Generando reportes',
            $request->all()
        );

        try {

            $query = Venta::with([
                'usuario',
                'cliente'
            ]);

            if ($request->fecha_desde) {

                $query->whereDate(
                    'fecha',
                    '>=',
                    $request->fecha_desde
                );
            }

            if ($request->fecha_hasta) {

                $query->whereDate(
                    'fecha',
                    '<=',
                    $request->fecha_hasta
                );
            }

            if ($request->estado) {

                $query->where(
                    'estado',
                    $request->estado
                );
            }

            $ventas = $query
                ->orderBy(
                    'fecha',
                    'desc'
                )
                ->paginate(20);

            $totalVentas =
                (clone $query)->sum(
                    'total'
                );

            $numeroTickets =
                (clone $query)->count();

            $ticketPromedio =
                $numeroTickets > 0
                    ? $totalVentas / $numeroTickets
                    : 0;

            return response()->json([
                'data' =>
                    $ventas->items(),

                'total_ventas' =>
                    $totalVentas,

                'numero_tickets' =>
                    $numeroTickets,

                'ticket_promedio' =>
                    $ticketPromedio,

                'current_page' =>
                    $ventas->currentPage(),

                'last_page' =>
                    $ventas->lastPage(),

                'per_page' =>
                    $ventas->perPage(),

                'total' =>
                    $ventas->total(),
            ]);

        } catch (\Exception $e) {

            Log::error(
                '❌ Error al generar reportes',
                [
                    'error' =>
                        $e->getMessage(),

                    'trace' =>
                        $e->getTraceAsString()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al generar reportes'
            ], 500);
        }
    }

    /**
     * Exportar reportes.
     */
    public function exportarReportes(
        Request $request
    ) {
        try {

            return response()->json([
                'message' =>
                    'Exportación en desarrollo'
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' =>
                    'Error al exportar reportes'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN
    |--------------------------------------------------------------------------
    */

    /**
     * Actualizar configuración de empresa.
     */
    public function actualizarConfiguracion(
        Request $request
    ) {
        Log::info(
            '⚙️ Actualizando configuración de empresa',
            $request->all()
        );

        $empresa =
            $request->user()->empresa;

        if (!$empresa) {

            return response()->json([
                'message' =>
                    'Empresa no encontrada'
            ], 404);
        }

        $request->validate([
            'colores' =>
                'nullable|array',

            'colores.primary' =>
                'nullable|string',

            'colores.secondary' =>
                'nullable|string',

            'colores.background' =>
                'nullable|string',

            'colores.text' =>
                'nullable|string',

            'colores.navbar' =>
                'nullable|string',
        ]);

        DB::beginTransaction();

        try {

            if ($request->has('colores')) {

                $empresa->colores =
                    $request->colores;
            }

            $empresa->save();

            DB::commit();

            return response()->json([
                'message' =>
                    'Configuración actualizada correctamente',

                'colores' =>
                    $empresa->colores
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error(
                '❌ Error al actualizar configuración',
                [
                    'empresa_id' =>
                        $empresa->id,

                    'error' =>
                        $e->getMessage()
                ]
            );

            return response()->json([
                'message' =>
                    'Error al actualizar configuración'
            ], 500);
        }
    }
}
