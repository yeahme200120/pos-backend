<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    /**
     * Listar usuarios con paginación.
     *
     * superadmin:
     * - Puede consultar usuarios de todas las empresas.
     *
     * admin:
     * - Solo puede consultar usuarios de su propia empresa.
     */
    public function usuarios(Request $request)
    {
        try {
            $userActual = $request->user();

            if (!$userActual) {
                return response()->json([
                    'message' => 'Usuario no autenticado.',
                ], 401);
            }

            $request->validate([
                'search' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
                'rol' => [
                    'nullable',
                    'in:superadmin,admin,cajero,vendedor',
                ],
                'activo' => [
                    'nullable',
                    'boolean',
                ],
                'per_page' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100',
                ],
            ]);

            Log::info('📋 Listando usuarios', [
                'usuario_id' => $userActual->id,
                'empresa_id' => $userActual->empresa_id,
                'filtros' => $request->except([
                    'password',
                    'password_confirmation',
                    'token',
                ]),
            ]);

            $query = User::query()
                ->with('empresa');

            /*
             * Aislamiento por empresa.
             *
             * Únicamente superadmin puede consultar
             * usuarios de todas las empresas.
             */
            if ($userActual->rol !== 'superadmin') {
                $query->where(
                    'empresa_id',
                    $userActual->empresa_id
                );
            }

            if ($request->filled('search')) {
                $search = trim(
                    (string) $request->input('search')
                );

                $query->where(function ($q) use ($search) {
                    $q->where(
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

            if ($request->filled('rol')) {
                $query->where(
                    'rol',
                    $request->input('rol')
                );
            }

            if ($request->has('activo')) {
                $query->where(
                    'activo',
                    $request->boolean('activo')
                );
            }

            $perPage = (int) (
                $request->input('per_page', 10)
            );

            $users = $query
                ->orderByDesc('id')
                ->paginate($perPage)
                ->appends($request->query());

            Log::info(
                '✅ Usuarios listados: ' .
                $users->total() .
                ' registros'
            );

            app(AuditoriaService::class)->registrar(
                $request,
                'usuarios.consultados',
                'users',
                null,
                null,
                [
                    'total' => $users->total(),
                    'filtros' => $request->except([
                        'password',
                        'password_confirmation',
                        'token',
                    ]),
                ],
                $userActual->empresa_id,
                $userActual->id
            );

            return response()->json($users);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al listar usuarios: ' .
                $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'message' => 'Error al cargar usuarios',
            ], 500);
        }
    }

    /**
     * Crear un nuevo usuario.
     */
    public function crearUsuario(Request $request)
    {
        $userActual = $request->user();

        if (!$userActual) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:6',
                'max:255',
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
            ],
            'rol' => [
                'required',
                'in:superadmin,admin,cajero,vendedor',
            ],
            'empresa_id' => [
                'required',
                'integer',
                'exists:empresas,id',
            ],
            'activo' => [
                'sometimes',
                'boolean',
            ],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'password.max' => 'La contraseña no puede tener más de 255 caracteres.',
            'telefono.max' => 'El teléfono no puede tener más de 20 caracteres.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol seleccionado no es válido.',
            'empresa_id.required' => 'La empresa es obligatoria.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ]);

        /*
         * Un admin normal solo puede crear usuarios
         * dentro de su propia empresa.
         */
        $empresaId = (int) $request->input('empresa_id');

        if (
            $userActual->rol !== 'superadmin' &&
            (int) $userActual->empresa_id !== $empresaId
        ) {
            app(AuditoriaService::class)->registrar(
                $request,
                'usuario.creacion_rechazada',
                'users',
                null,
                null,
                [
                    'motivo' => 'empresa_no_autorizada',
                    'empresa_solicitada' => $empresaId,
                ],
                $userActual->empresa_id,
                $userActual->id
            );

            return response()->json([
                'message' => 'No tienes permiso para crear usuarios en otra empresa.',
            ], 403);
        }

        /*
         * Un administrador normal no debe poder crear
         * otro superadmin.
         */
        if (
            $userActual->rol !== 'superadmin' &&
            $request->input('rol') === 'superadmin'
        ) {
            return response()->json([
                'message' => 'No tienes permiso para crear un superadmin.',
            ], 403);
        }

        $telefono = $this->normalizarTelefono(
            $request->input('telefono')
        );

        try {
            $user = DB::transaction(
                function () use (
                    $request,
                    $empresaId,
                    $telefono
                ) {
                    return User::create([
                        'name' => trim(
                            (string) $request->input('name')
                        ),
                        'email' => strtolower(
                            trim(
                                (string) $request->input('email')
                            )
                        ),
                        'password' => Hash::make(
                            (string) $request->input('password')
                        ),
                        'telefono' => $telefono,
                        'numero_usuario' =>
                            User::generarNumeroUsuario(),
                        'empresa_id' => $empresaId,
                        'rol' => $request->input('rol'),
                        'activo' => $request->has('activo')
                            ? $request->boolean('activo')
                            : true,
                    ]);
                }
            );

            Log::info(
                '✅ Usuario creado - ID: ' .
                $user->id
            );

            app(AuditoriaService::class)->registrar(
                $request,
                'usuario.creado',
                'users',
                $user->id,
                null,
                $this->datosAuditoriaUsuario($user),
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'message' => 'Usuario creado correctamente',
                'user' => $user->load('empresa'),
            ], 201);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al crear usuario: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' => 'Error al crear usuario.',
            ], 500);
        }
    }

    /**
     * Actualizar un usuario.
     */
    public function actualizarUsuario(
        Request $request,
        $id
    ) {
        $userActual = $request->user();

        if (!$userActual) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $user = User::findOrFail($id);

            /*
             * Aislamiento por empresa.
             */
            if (
                $userActual->rol !== 'superadmin' &&
                (int) $user->empresa_id !==
                    (int) $userActual->empresa_id
            ) {
                return response()->json([
                    'message' => 'No tienes permiso para modificar este usuario.',
                ], 403);
            }

            $datosAntes = $this->datosAuditoriaUsuario($user);

            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    'unique:users,email,' . $user->id,
                ],
                'telefono' => [
                    'nullable',
                    'string',
                    'max:20',
                ],
                'rol' => [
                    'required',
                    'in:superadmin,admin,cajero,vendedor',
                ],
                'empresa_id' => [
                    'required',
                    'integer',
                    'exists:empresas,id',
                ],
                'activo' => [
                    'sometimes',
                    'boolean',
                ],
                'password' => [
                    'nullable',
                    'string',
                    'min:6',
                    'max:255',
                ],
                'licencia_tipo' => [
                    'nullable',
                    'in:dia,semana,quincena,mes,bimestre,trimestre,semestre,anual,permanente',
                ],
                'licencia_fecha_inicio' => [
                    'nullable',
                    'date',
                ],
                'licencia_fecha_fin' => [
                    'nullable',
                    'date',
                    'after_or_equal:licencia_fecha_inicio',
                ],
            ], [
                'name.required' => 'El nombre es obligatorio.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'email.unique' => 'Este correo electrónico ya está registrado.',
                'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
                'telefono.max' => 'El teléfono no puede tener más de 20 caracteres.',
                'rol.required' => 'El rol es obligatorio.',
                'rol.in' => 'El rol seleccionado no es válido.',
                'empresa_id.required' => 'La empresa es obligatoria.',
                'empresa_id.exists' => 'La empresa seleccionada no existe.',
                'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
                'licencia_tipo.in' => 'El tipo de licencia no es válido.',
                'licencia_fecha_fin.after_or_equal' =>
                    'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            ]);

            $empresaId = (int) $request->input('empresa_id');

            if (
                $userActual->rol !== 'superadmin' &&
                $empresaId !== (int) $userActual->empresa_id
            ) {
                return response()->json([
                    'message' => 'No tienes permiso para asignar otra empresa.',
                ], 403);
            }

            if (
                $userActual->rol !== 'superadmin' &&
                $request->input('rol') === 'superadmin'
            ) {
                return response()->json([
                    'message' => 'No tienes permiso para asignar el rol superadmin.',
                ], 403);
            }

            /*
             * Un admin no puede convertir/modificar a otro
             * usuario superadmin.
             */
            if (
                $userActual->rol !== 'superadmin' &&
                $user->rol === 'superadmin'
            ) {
                return response()->json([
                    'message' => 'No tienes permiso para modificar un superadmin.',
                ], 403);
            }

            /*
             * Evitar que un usuario se quite a sí mismo
             * permisos críticos accidentalmente.
             */
            if (
                $user->id === $userActual->id &&
                $request->input('rol') === 'superadmin' &&
                $userActual->rol !== 'superadmin'
            ) {
                return response()->json([
                    'message' => 'No puedes asignarte permisos de superadmin.',
                ], 403);
            }

            $data = [
                'name' => trim(
                    (string) $request->input('name')
                ),
                'email' => strtolower(
                    trim(
                        (string) $request->input('email')
                    )
                ),
                'telefono' => $this->normalizarTelefono(
                    $request->input('telefono')
                ),
                'rol' => $request->input('rol'),
                'empresa_id' => $empresaId,
                'activo' => $request->has('activo')
                    ? $request->boolean('activo')
                    : $user->activo,
                'licencia_tipo' =>
                    $request->input('licencia_tipo'),
                'licencia_fecha_inicio' =>
                    $request->input('licencia_fecha_inicio'),
                'licencia_fecha_fin' =>
                    $request->input('licencia_fecha_fin'),
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make(
                    (string) $request->input('password')
                );
            }

            $updatedUser = DB::transaction(
                function () use (
                    $user,
                    $data
                ) {
                    $user->update($data);

                    return $user->fresh([
                        'empresa',
                    ]);
                }
            );

            /*
             * Si se desactiva el usuario o se cambia la
             * contraseña, revocamos sus tokens.
             */
            if (
                !$this->isUserActive($updatedUser) ||
                array_key_exists('password', $data)
            ) {
                $updatedUser->tokens()->delete();
            }

            app(AuditoriaService::class)->registrar(
                $request,
                'usuario.actualizado',
                'users',
                $updatedUser->id,
                $datosAntes,
                $this->datosAuditoriaUsuario($updatedUser),
                $updatedUser->empresa_id,
                $userActual->id
            );

            return response()->json([
                'message' =>
                    'Usuario actualizado correctamente',
                'user' => $updatedUser,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al actualizar usuario: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al actualizar usuario.',
            ], 500);
        }
    }

    /**
     * Eliminar un usuario.
     */
    public function eliminarUsuario(
        Request $request,
        $id
    ) {
        $userActual = $request->user();

        if (!$userActual) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $user = User::findOrFail($id);

            if ($userActual->id === $user->id) {
                return response()->json([
                    'message' =>
                        'No puedes eliminar tu propio usuario.',
                ], 403);
            }

            if (
                $userActual->rol !== 'superadmin' &&
                (int) $user->empresa_id !==
                    (int) $userActual->empresa_id
            ) {
                return response()->json([
                    'message' =>
                        'No tienes permiso para eliminar este usuario.',
                ], 403);
            }

            if (
                $userActual->rol !== 'superadmin' &&
                $user->rol === 'superadmin'
            ) {
                return response()->json([
                    'message' =>
                        'No tienes permiso para eliminar un superadmin.',
                ], 403);
            }

            $datosAntes =
                $this->datosAuditoriaUsuario($user);

            DB::transaction(
                function () use ($user) {
                    $user->tokens()->delete();
                    $user->delete();
                }
            );

            app(AuditoriaService::class)->registrar(
                $request,
                'usuario.eliminado',
                'users',
                $user->id,
                $datosAntes,
                null,
                $user->empresa_id,
                $userActual->id
            );

            Log::info(
                '✅ Usuario eliminado - ID: ' .
                $user->id
            );

            return response()->json([
                'message' =>
                    'Usuario eliminado correctamente',
            ]);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al eliminar usuario: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al eliminar usuario.',
            ], 500);
        }
    }

    /**
     * Listar empresas.
     */
    public function empresas(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $query = Empresa::query()
                ->select([
                    'id',
                    'nombre',
                    'rfc',
                    'activo',
                ]);

            /*
             * superadmin puede ver todas.
             * admin solamente su empresa.
             */
            if ($user->rol !== 'superadmin') {
                $query->whereKey(
                    $user->empresa_id
                );
            }

            $empresas = $query
                ->orderBy('nombre')
                ->get();

            app(AuditoriaService::class)->registrar(
                $request,
                'empresas.consultadas',
                'empresas',
                null,
                null,
                [
                    'total' => $empresas->count(),
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json($empresas);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al listar empresas: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al cargar empresas',
            ], 500);
        }
    }

    /**
     * Obtener reportes de ventas.
     */
    public function reportes(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $request->validate([
            'fecha_desde' => [
                'nullable',
                'date',
            ],
            'fecha_hasta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_desde',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        try {
            $query = Venta::query()
                ->with([
                    'usuario',
                    'cliente',
                ]);

            /*
             * Aislamiento por empresa.
             *
             * La consulta utiliza la empresa del usuario
             * autenticado siempre que la columna exista.
             */
            $query->where(
                'empresa_id',
                $user->empresa_id
            );

            if ($request->filled('fecha_desde')) {
                $query->whereDate(
                    'fecha',
                    '>=',
                    $request->input('fecha_desde')
                );
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate(
                    'fecha',
                    '<=',
                    $request->input('fecha_hasta')
                );
            }

            $query->orderByDesc('fecha');

            $totalVentas = (clone $query)->sum('total');

            $numeroTickets = (clone $query)->count();

            $ticketPromedio = $numeroTickets > 0
                ? round(
                    (float) $totalVentas /
                    $numeroTickets,
                    2
                )
                : 0;

            $ventas = $query
                ->paginate(
                    (int) $request->input(
                        'per_page',
                        20
                    )
                )
                ->appends(
                    $request->query()
                );

            app(AuditoriaService::class)->registrar(
                $request,
                'reportes.ventas.consultados',
                'ventas',
                null,
                null,
                [
                    'fecha_desde' =>
                        $request->input('fecha_desde'),
                    'fecha_hasta' =>
                        $request->input('fecha_hasta'),
                    'numero_tickets' =>
                        $numeroTickets,
                    'total_ventas' =>
                        $totalVentas,
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'data' => $ventas->items(),
                'total_ventas' =>
                    round((float) $totalVentas, 2),
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al generar reportes: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al generar reportes',
            ], 500);
        }
    }

    /**
     * Exportar reportes.
     */
    public function exportarReportes(
        Request $request
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $request->validate([
            'fecha_desde' => [
                'nullable',
                'date',
            ],
            'fecha_hasta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_desde',
            ],
        ]);

        app(AuditoriaService::class)->registrar(
            $request,
            'reportes.ventas.exportacion_solicitada',
            'ventas',
            null,
            null,
            [
                'fecha_desde' =>
                    $request->input('fecha_desde'),
                'fecha_hasta' =>
                    $request->input('fecha_hasta'),
                'estado' => 'en_desarrollo',
            ],
            $user->empresa_id,
            $user->id
        );

        return response()->json([
            'message' =>
                'Exportación en desarrollo',
        ]);
    }

    /**
     * Obtener configuración de la empresa.
     */
    public function configuracion(
        Request $request
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresa = $user->empresa;

        if (!$empresa) {
            return response()->json([
                'error' => 'Empresa no encontrada',
            ], 404);
        }

        app(AuditoriaService::class)->registrar(
            $request,
            'empresa.configuracion.consultada',
            'empresas',
            $empresa->id,
            null,
            null,
            $empresa->id,
            $user->id
        );

        return response()->json([
            'nombre' => $empresa->nombre,
            'colores' => $this->decodificarJson(
                $empresa->colores
            ),
        ]);
    }

    /**
     * Actualizar configuración de la empresa.
     */
    public function actualizarConfiguracion(
        Request $request
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresa = $user->empresa;

        if (!$empresa) {
            return response()->json([
                'error' => 'Empresa no encontrada',
            ], 404);
        }

        /*
         * La configuración solamente puede ser modificada
         * por admin o superadmin.
         */
        if (
            !in_array(
                $user->rol,
                ['admin', 'superadmin'],
                true
            )
        ) {
            return response()->json([
                'message' =>
                    'No tienes permiso para modificar la configuración.',
            ], 403);
        }

        $request->validate([
            'colores' => [
                'nullable',
                'array',
            ],
            'colores.primary' => [
                'nullable',
                'string',
                'max:30',
            ],
            'colores.secondary' => [
                'nullable',
                'string',
                'max:30',
            ],
            'colores.background' => [
                'nullable',
                'string',
                'max:30',
            ],
            'colores.text' => [
                'nullable',
                'string',
                'max:30',
            ],
            'colores.navbar' => [
                'nullable',
                'string',
                'max:30',
            ],
        ]);

        $datosAntes = $empresa->toArray();

        try {
            DB::transaction(
                function () use (
                    $request,
                    $empresa
                ) {
                    if ($request->has('colores')) {
                        $empresa->colores =
                            json_encode(
                                $request->input(
                                    'colores'
                                ),
                                JSON_UNESCAPED_UNICODE
                            );
                    }

                    $empresa->save();
                }
            );

            $empresa->refresh();

            app(AuditoriaService::class)->registrar(
                $request,
                'empresa.configuracion.actualizada',
                'empresas',
                $empresa->id,
                $datosAntes,
                $empresa->toArray(),
                $empresa->id,
                $user->id
            );

            return response()->json([
                'message' =>
                    'Configuración actualizada correctamente',
                'colores' => $this->decodificarJson(
                    $empresa->colores
                ),
            ]);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al actualizar configuración: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al actualizar configuración',
            ], 500);
        }
    }

    /**
     * Normalizar teléfono.
     */
    private function normalizarTelefono(
        mixed $telefono
    ): ?string {
        if (
            $telefono === null ||
            trim((string) $telefono) === ''
        ) {
            return null;
        }

        $telefono = preg_replace(
            '/[^0-9]/',
            '',
            (string) $telefono
        );

        if (
            $telefono === null ||
            strlen($telefono) !== 10
        ) {
            return null;
        }

        return $telefono;
    }

    /**
     * Datos seguros para auditoría.
     *
     * Nunca guardar password.
     */
    private function datosAuditoriaUsuario(
        User $user
    ): array {
        return $user->only([
            'id',
            'name',
            'email',
            'telefono',
            'numero_usuario',
            'empresa_id',
            'rol',
            'activo',
            'licencia_tipo',
            'licencia_fecha_inicio',
            'licencia_fecha_fin',
        ]);
    }

    /**
     * Decodificar JSON de forma segura.
     */
    private function decodificarJson(
        mixed $value
    ): mixed {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode(
            $value,
            true
        );

        return json_last_error() === JSON_ERROR_NONE
            ? $decoded
            : $value;
    }

    /**
     * Determinar si el usuario está activo.
     */
    private function isUserActive(
        User $user
    ): bool {
        $value = $user->activo;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(
                    trim($value)
                ),
                [
                    '1',
                    'true',
                    'si',
                    'yes',
                ],
                true
            );
        }

        return false;
    }
}