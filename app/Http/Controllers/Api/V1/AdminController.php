<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

use function Illuminate\Log\log;

class AdminController extends Controller
{
    public function __construct(
        protected AuditoriaService $auditoria
    ) {}

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function usuarioAutenticado(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User
            ? $usuario
            : null;
    }

    private function respuestaNoAutenticado()
    {
        return response()->json([
            'message' => 'No autenticado.',
        ], 401);
    }

    private function puedeAdministrarEmpresa(
        User $usuario,
        ?int $empresaId = null
    ): bool {
        if ($usuario->rol === 'superadmin') {
            return true;
        }

        if (!$usuario->empresa_id) {
            return false;
        }

        if ($empresaId === null) {
            return true;
        }

        return (int) $usuario->empresa_id === $empresaId;
    }

    /**
     * Registrar auditoría sin permitir que una falla
     * de auditoría rompa la operación principal.
     */
    private function auditar(
        Request $request,
        string $accion,
        string $tabla,
        $registroId,
        $datosAntes = null,
        $datosDespues = null,
        ?int $empresaId = null,
        ?int $usuarioId = null
    ): void {
        try {
            $usuario = $this->usuarioAutenticado();

            $empresaId ??= $usuario?->empresa_id;
            $usuarioId ??= $usuario?->id;

            $this->auditoria->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues,
                $empresaId,
                $usuarioId
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría en AdminController.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Respuesta estándar para errores de validación.
     */
    private function respuestaValidacion(
        Request $request,
        ValidationException $e,
        string $accion,
        string $tabla,
        $registroId = null,
        ?int $empresaId = null
    ) {
        $errores = $e->errors();

        $this->auditar(
            $request,
            $accion,
            $tabla,
            $registroId,
            null,
            [
                'errores' => $errores,
            ],
            $empresaId
        );

        return response()->json([
            'message' => 'Error de validación.',
            'errors' => $errores,
        ], 422);
    }

    /**
     * Generar una respuesta muy detallada para errores internos.
     *
     * IMPORTANTE:
     * No incluye contraseñas ni datos sensibles del request.
     */
    private function respuestaErrorInterno(
        Request $request,
        Throwable $e,
        string $mensaje,
        string $accion,
        string $tabla,
        $registroId = null,
        ?int $empresaId = null,
        ?int $usuarioId = null,
        array $contexto = []
    ) {
        $exceptionClass = get_class($e);

        $esDatabase = $e instanceof QueryException;

        $codigo = $e->getCode();

        $detalle = [
            'tipo' => $esDatabase
                ? 'database_error'
                : 'application_error',

            'exception' => $exceptionClass,

            'code' => $codigo,

            'message' => $e->getMessage(),

            'file' => $e->getFile(),

            'line' => $e->getLine(),
        ];

        /*
         * QueryException contiene información adicional
         * extremadamente útil para detectar columnas,
         * constraints, tablas, etc.
         */
        if ($e instanceof QueryException) {
            $detalle['sql_state'] =
                $e->errorInfo[0] ?? null;

            $detalle['driver_code'] =
                $e->errorInfo[1] ?? null;

            $detalle['driver_message'] =
                $e->errorInfo[2] ?? null;
        }

        $contextoError = [
            'accion' => $accion,
            'tabla' => $tabla,
            'registro_id' => $registroId,
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ];

        if (!empty($contexto)) {
            $contextoError['contexto'] = $contexto;
        }

        /*
         * Registrar absolutamente todos los detalles en Laravel.
         */
        Log::error(
            $mensaje,
            [
                'exception' => $exceptionClass,
                'code' => $codigo,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),

                'sql_state' =>
                    $e instanceof QueryException
                    ? ($e->errorInfo[0] ?? null)
                    : null,

                'driver_code' =>
                    $e instanceof QueryException
                    ? ($e->errorInfo[1] ?? null)
                    : null,

                'driver_message' =>
                    $e instanceof QueryException
                    ? ($e->errorInfo[2] ?? null)
                    : null,

                'contexto' => $contextoError,
            ]
        );

        /*
         * La auditoría también se intenta registrar,
         * pero nunca debe romper la respuesta.
         */
        $this->auditar(
            $request,
            $accion . '_error',
            $tabla,
            $registroId,
            null,
            [
                'tipo' => $detalle['tipo'],
                'exception' => $exceptionClass,
                'code' => $codigo,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
            $empresaId,
            $usuarioId
        );

        return response()->json([
            'message' => $mensaje,

            'error' => $detalle,

            'context' => $contextoError,
        ], 500);
    }

    private function validarId($id): ?int
    {
        if (
            !is_numeric($id) ||
            (int) $id <= 0 ||
            (string) (int) $id != (string) $id
        ) {
            return null;
        }

        return (int) $id;
    }

    private function datosUsuario(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'telefono' => $usuario->telefono,
            'numero_usuario' => $usuario->numero_usuario,
            'empresa_id' => $usuario->empresa_id,
            'rol' => $usuario->rol,
            'activo' => $usuario->activo,
        ];
    }

    private function datosEmpresa(Empresa $empresa): array
    {
        return [
            'id' => $empresa->id,
            'nombre' => $empresa->nombre,
            'rfc' => $empresa->rfc,
            'activo' => $empresa->activo,
            'licencia_tipo' => $empresa->licencia_tipo,
            'licencia_fecha_inicio' =>
                $empresa->licencia_fecha_inicio,
            'licencia_fecha_fin' =>
                $empresa->licencia_fecha_fin,
            'licencia_activa' =>
                $empresa->licencia_activa,
        ];
    }

    private function respuestaNoEncontrado(string $mensaje)
    {
        return response()->json([
            'message' => $mensaje,
        ], 404);
    }

    private function respuestaNoAutorizado(string $mensaje)
    {
        return response()->json([
            'message' => $mensaje,
        ], 403);
    }

    private function datosLicencia(Empresa $empresa): array
    {
        return [
            'empresa_id' => $empresa->id,
            'empresa' => $empresa->nombre,
            'licencia_tipo' => $empresa->licencia_tipo,
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
    }

    /*
    |--------------------------------------------------------------------------
    | USUARIOS
    |--------------------------------------------------------------------------
    */

    public function usuarios(Request $request)
    {
        $usuario = $this->usuarioAutenticado();

        if (!$usuario) {
            return $this->respuestaNoAutenticado();
        }

        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'search' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'rol' => [
                        'nullable',
                        'string',
                        Rule::in([
                            'superadmin',
                            'admin',
                            'vendedor',
                            'cajero',
                        ]),
                    ],

                    'activo' => [
                        'nullable',
                        'boolean',
                    ],

                    'empresa_id' => [
                        'nullable',
                        'integer',
                        'min:1',
                    ],

                    'per_page' => [
                        'nullable',
                        'integer',
                        'min:1',
                        'max:100',
                    ],
                ],
                [
                    'search.string' =>
                        'El texto de búsqueda no es válido.',

                    'search.max' =>
                        'El texto de búsqueda es demasiado largo.',

                    'rol.in' =>
                        'El rol seleccionado no es válido.',

                    'activo.boolean' =>
                        'El estado activo no es válido.',

                    'empresa_id.integer' =>
                        'La empresa indicada no es válida.',

                    'empresa_id.min' =>
                        'La empresa indicada no es válida.',

                    'per_page.integer' =>
                        'La cantidad de registros por página no es válida.',

                    'per_page.max' =>
                        'No se pueden solicitar más de 100 registros por página.',
                ]
            );

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $empresaFiltro = $request->filled('empresa_id')
                ? (int) $request->empresa_id
                : null;

            $query = User::query()
                ->with([
                    'empresa:id,nombre,rfc,activo',
                ])
                ->select([
                    'id',
                    'name',
                    'email',
                    'telefono',
                    'numero_usuario',
                    'empresa_id',
                    'rol',
                    'activo',
                    'created_at',
                    'updated_at',
                ]);

            if ($usuario->rol !== 'superadmin') {
                if (!$usuario->empresa_id) {
                    return $this->respuestaNoAutorizado(
                        'El usuario no tiene una empresa asignada.'
                    );
                }

                $query->where(
                    'empresa_id',
                    $usuario->empresa_id
                );

                $empresaFiltro =
                    $usuario->empresa_id;
            }

            if ($empresaFiltro !== null) {
                $query->where(
                    'empresa_id',
                    $empresaFiltro
                );
            }

            $query
                ->when(
                    $request->filled('search'),
                    function ($q) use ($request) {
                        $search =
                            trim($request->search);

                        $q->where(function ($query) use ($search) {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'numero_usuario',
                                    'like',
                                    '%' . $search . '%'
                                );
                        });
                    }
                )
                ->when(
                    $request->filled('rol'),
                    fn($q) => $q->where(
                        'rol',
                        $request->rol
                    )
                )
                ->when(
                    $request->has('activo'),
                    fn($q) => $q->where(
                        'activo',
                        $request->boolean('activo')
                    )
                )
                ->orderByDesc('id');

            $perPage = min(
                max(
                    (int) $request->input(
                        'per_page',
                        10
                    ),
                    1
                ),
                100
            );

            $users = $query->paginate($perPage);

            $this->auditar(
                $request,
                'listar_usuarios',
                'users',
                null,
                null,
                [
                    'total' =>
                        $users->total(),

                    'pagina' =>
                        $users->currentPage(),

                    'per_page' =>
                        $users->perPage(),
                ],
                $usuario->rol === 'superadmin'
                    ? $empresaFiltro
                    : $usuario->empresa_id
            );

            return response()->json($users);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion(
                $request,
                $e,
                'listar_usuarios_validacion_rechazada',
                'users',
                null,
                $usuario->empresa_id
            );
        } catch (Throwable $e) {
            return $this->respuestaErrorInterno(
                $request,
                $e,
                'Error al cargar usuarios.',
                'listar_usuarios',
                'users',
                null,
                $usuario->empresa_id,
                $usuario->id
            );
        }
    }

/**
 * Crear usuario.
 */
public function crearUsuario(Request $request)
{
    $usuarioActual =
        $this->usuarioAutenticado();

    if (!$usuarioActual) {
        return $this->respuestaNoAutenticado();
    }

    try {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email'),
                ],

                'password' => [
                    'required',
                    'string',
                    'min:6',
                    'max:255',
                ],

                /*
                 * Confirmación obligatoria al crear.
                 */
                'password_confirmation' => [
                    'required',
                    'same:password',
                ],

                'telefono' => [
                    'nullable',
                    'digits:10',
                ],

                'rol' => [
                    'required',
                    Rule::in([
                        'superadmin',
                        'admin',
                        'vendedor',
                        'cajero',
                    ]),
                ],

                'empresa_id' => [
                    'required',
                    'integer',
                    'min:1',
                    'exists:empresas,id',
                ],

                'activo' => [
                    'sometimes',
                    'boolean',
                ],
            ],
            [
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

                'email.max' =>
                    'El correo electrónico es demasiado largo.',

                'email.unique' =>
                    'Este correo electrónico ya está registrado.',

                'password.required' =>
                    'La contraseña es obligatoria.',

                'password.min' =>
                    'La contraseña debe tener al menos 6 caracteres.',

                'password.max' =>
                    'La contraseña es demasiado larga.',

                'password_confirmation.required' =>
                    'La confirmación de contraseña es obligatoria.',

                'password_confirmation.same' =>
                    'La confirmación de contraseña no coincide con la contraseña.',

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
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $empresaId =
            (int) $validated['empresa_id'];

        if (
            !$this->puedeAdministrarEmpresa(
                $usuarioActual,
                $empresaId
            )
        ) {
            $this->auditar(
                $request,
                'crear_usuario_no_autorizado',
                'users',
                null,
                null,
                [
                    'empresa_id_solicitada' =>
                        $empresaId,
                ],
                $usuarioActual->empresa_id
            );

            return $this->respuestaNoAutorizado(
                'No tienes permiso para crear usuarios en esta empresa.'
            );
        }

        if (
            $validated['rol'] === 'superadmin' &&
            $usuarioActual->rol !== 'superadmin'
        ) {
            $this->auditar(
                $request,
                'crear_usuario_rol_no_autorizado',
                'users',
                null,
                null,
                [
                    'rol_solicitado' =>
                        'superadmin',
                ],
                $usuarioActual->empresa_id
            );

            return $this->respuestaNoAutorizado(
                'No tienes permiso para crear usuarios superadmin.'
            );
        }

        /*
         * La confirmación ya fue validada pero NO se
         * utiliza para crear el registro.
         */
        $user = DB::transaction(
            function () use (
                $validated,
                $empresaId
            ) {
                $empresa = Empresa::query()
                    ->lockForUpdate()
                    ->find($empresaId);

                if (!$empresa) {
                    throw new ModelNotFoundException(
                        'La empresa seleccionada no existe.'
                    );
                }

                /*
                 * Obtener el siguiente ID y pasarlo al
                 * generador de numero_usuario.
                 */
                $nextId =
                    User::withTrashed()->max('id') + 1;

                $numeroUsuario =
                    User::generarNumeroUsuario($nextId);

                return User::create([
                    'name' =>
                        trim($validated['name']),

                    'email' =>
                        strtolower(
                            trim($validated['email'])
                        ),

                    'password' =>
                        Hash::make(
                            $validated['password']
                        ),

                    'telefono' =>
                        $validated['telefono'] ?? null,

                    'numero_usuario' =>
                        $numeroUsuario,

                    'empresa_id' =>
                        $empresa->id,

                    'rol' =>
                        $validated['rol'],

                    'activo' =>
                        $validated['activo'] ?? true,
                ]);
            }
        );

        $user->load([
            'empresa:id,nombre,rfc,activo',
        ]);

        $this->auditar(
            $request,
            'crear_usuario',
            'users',
            $user->id,
            null,
            $this->datosUsuario($user),
            $user->empresa_id
        );

        Log::info(
            'Usuario creado correctamente.',
            [
                'usuario_id' =>
                    $user->id,

                'empresa_id' =>
                    $user->empresa_id,

                'creado_por' =>
                    $usuarioActual->id,
            ]
        );

        return response()->json([
            'message' =>
                'Usuario creado correctamente.',

            'user' =>
                $user,
        ], 201);
    } catch (ValidationException $e) {
        return $this->respuestaValidacion(
            $request,
            $e,
            'crear_usuario_validacion_rechazada',
            'users',
            null,
            $usuarioActual->empresa_id
        );
    } catch (ModelNotFoundException $e) {
        return response()->json([
            'message' =>
                'Empresa no encontrada.',

            'error' => [
                'tipo' =>
                    'model_not_found',

                'exception' =>
                    get_class($e),

                'code' =>
                    $e->getCode(),

                'message' =>
                    $e->getMessage(),

                'file' =>
                    $e->getFile(),

                'line' =>
                    $e->getLine(),
            ],

            'context' => [
                'accion' =>
                    'crear_usuario',

                'empresa_id' =>
                    $usuarioActual->empresa_id,

                'usuario_id' =>
                    $usuarioActual->id,
            ],
        ], 404);
    } catch (Throwable $e) {
        log($e);

        return $this->respuestaErrorInterno(
            $request,
            $e,
            'Error al crear usuario.',
            'crear_usuario',
            'users',
            null,
            $usuarioActual->empresa_id,
            $usuarioActual->id,
            [
                'empresa_id_solicitada' =>
                    $request->input('empresa_id'),

                'rol_solicitado' =>
                    $request->input('rol'),

                'email_solicitado' =>
                    $request->input('email'),

                'password_recibida' =>
                    $request->has('password'),

                'password_confirmation_recibida' =>
                    $request->has(
                        'password_confirmation'
                    ),
            ]
        );
    }
}


    /**
     * Actualizar usuario.
     */
    public function actualizarUsuario(
        Request $request,
        $id
    ) {
        $usuarioActual =
            $this->usuarioAutenticado();

        if (!$usuarioActual) {
            return $this->respuestaNoAutenticado();
        }

        $userId = $this->validarId($id);

        if ($userId === null) {
            return response()->json([
                'message' =>
                    'El ID del usuario no es válido.',
            ], 422);
        }

        try {
            $userQuery = User::query();

            if ($usuarioActual->rol !== 'superadmin') {
                $userQuery->where(
                    'empresa_id',
                    $usuarioActual->empresa_id
                );
            }

            $user = $userQuery->find($userId);

            if (!$user) {
                $this->auditar(
                    $request,
                    'actualizar_usuario_no_encontrado',
                    'users',
                    $userId,
                    null,
                    null,
                    $usuarioActual->empresa_id
                );

                return $this->respuestaNoEncontrado(
                    'Usuario no encontrado.'
                );
            }

            $validator = Validator::make(
                $request->all(),
                [
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                    ],

                    'email' => [
                        'required',
                        'email',
                        'max:255',
                        Rule::unique('users', 'email')
                            ->ignore($user->id),
                    ],

                    'password' => [
                        'nullable',
                        'string',
                        'min:6',
                        'max:255',
                    ],

                    /*
                     * Solo obligatoria cuando se está
                     * enviando una nueva contraseña.
                     */
                    'password_confirmation' => [
                        'nullable',
                        'required_with:password',
                        'same:password',
                    ],

                    'telefono' => [
                        'nullable',
                        'digits:10',
                    ],

                    'rol' => [
                        'required',
                        Rule::in([
                            'superadmin',
                            'admin',
                            'vendedor',
                            'cajero',
                        ]),
                    ],

                    'empresa_id' => [
                        'required',
                        'integer',
                        'min:1',
                        'exists:empresas,id',
                    ],

                    'activo' => [
                        'sometimes',
                        'boolean',
                    ],
                ],
                [
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

                    'password.min' =>
                        'La contraseña debe tener al menos 6 caracteres.',

                    'password_confirmation.required_with' =>
                        'Debes confirmar la nueva contraseña.',

                    'password_confirmation.same' =>
                        'La confirmación de contraseña no coincide con la contraseña.',

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
                ]
            );

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated =
                $validator->validated();

            $nuevaEmpresaId =
                (int) $validated['empresa_id'];

            if (
                !$this->puedeAdministrarEmpresa(
                    $usuarioActual,
                    $nuevaEmpresaId
                )
            ) {
                $this->auditar(
                    $request,
                    'actualizar_usuario_empresa_no_autorizada',
                    'users',
                    $user->id,
                    $this->datosUsuario($user),
                    [
                        'empresa_id_solicitada' =>
                            $nuevaEmpresaId,
                    ],
                    $usuarioActual->empresa_id
                );

                return $this->respuestaNoAutorizado(
                    'No tienes permiso para asignar usuarios a esta empresa.'
                );
            }

            if (
                $validated['rol'] === 'superadmin' &&
                $usuarioActual->rol !== 'superadmin'
            ) {
                return $this->respuestaNoAutorizado(
                    'No tienes permiso para asignar el rol superadmin.'
                );
            }

            $datosAntes =
                $this->datosUsuario($user);

            $user = DB::transaction(
                function () use (
                    $userId,
                    $validated,
                    $nuevaEmpresaId,
                    $usuarioActual
                ) {
                    $query = User::query()
                        ->lockForUpdate();

                    if ($usuarioActual->rol !== 'superadmin') {
                        $query->where(
                            'empresa_id',
                            $usuarioActual->empresa_id
                        );
                    }

                    $user = $query->find($userId);

                    if (!$user) {
                        throw new ModelNotFoundException(
                            'Usuario no encontrado.'
                        );
                    }

                    $empresa = Empresa::query()
                        ->lockForUpdate()
                        ->find($nuevaEmpresaId);

                    if (!$empresa) {
                        throw new ModelNotFoundException(
                            'Empresa no encontrada.'
                        );
                    }

                    $data = [
                        'name' =>
                            trim($validated['name']),

                        'email' =>
                            strtolower(
                                trim($validated['email'])
                            ),

                        'telefono' =>
                            $validated['telefono'] ?? null,

                        'rol' =>
                            $validated['rol'],

                        'empresa_id' =>
                            $empresa->id,

                        'activo' =>
                            $validated['activo']
                            ?? $user->activo,
                    ];

                    if (
                        !empty($validated['password'])
                    ) {
                        $data['password'] =
                            Hash::make(
                                $validated['password']
                            );
                    }

                    $user->update($data);

                    return $user->fresh();
                }
            );

            $user->load([
                'empresa:id,nombre,rfc,activo',
            ]);

            $this->auditar(
                $request,
                'actualizar_usuario',
                'users',
                $user->id,
                $datosAntes,
                $this->datosUsuario($user),
                $user->empresa_id
            );

            Log::info(
                'Usuario actualizado correctamente.',
                [
                    'usuario_id' =>
                        $user->id,

                    'empresa_id' =>
                        $user->empresa_id,

                    'actualizado_por' =>
                        $usuarioActual->id,
                ]
            );

            return response()->json([
                'message' =>
                    'Usuario actualizado correctamente.',

                'user' =>
                    $user,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion(
                $request,
                $e,
                'actualizar_usuario_validacion_rechazada',
                'users',
                $userId,
                $usuarioActual->empresa_id
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' =>
                    'Usuario o empresa no encontrada.',

                'error' => [
                    'tipo' =>
                        'model_not_found',

                    'exception' =>
                        get_class($e),

                    'code' =>
                        $e->getCode(),

                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ],
            ], 404);
        } catch (Throwable $e) {
            return $this->respuestaErrorInterno(
                $request,
                $e,
                'Error al actualizar usuario.',
                'actualizar_usuario',
                'users',
                $userId,
                $usuarioActual->empresa_id,
                $usuarioActual->id
            );
        }
    }

    /**
     * Eliminar usuario.
     */
    public function eliminarUsuario($id)
    {
        $request = request();

        $usuarioActual =
            $this->usuarioAutenticado();

        if (!$usuarioActual) {
            return $this->respuestaNoAutenticado();
        }

        $userId = $this->validarId($id);

        if ($userId === null) {
            return response()->json([
                'message' =>
                    'El ID del usuario no es válido.',
            ], 422);
        }

        try {
            if (
                (int) $usuarioActual->id === $userId
            ) {
                $this->auditar(
                    $request,
                    'eliminar_usuario_rechazado',
                    'users',
                    $userId,
                    null,
                    [
                        'motivo' =>
                            'Intento de eliminar usuario propio.',
                    ],
                    $usuarioActual->empresa_id
                );

                return response()->json([
                    'message' =>
                        'No puedes eliminar tu propio usuario.',
                ], 403);
            }

            $user = DB::transaction(
                function () use (
                    $userId,
                    $usuarioActual
                ) {
                    $query = User::query()
                        ->lockForUpdate();

                    if ($usuarioActual->rol !== 'superadmin') {
                        $query->where(
                            'empresa_id',
                            $usuarioActual->empresa_id
                        );
                    }

                    $user = $query->find($userId);

                    if (!$user) {
                        throw new ModelNotFoundException(
                            'Usuario no encontrado.'
                        );
                    }

                    if (
                        $user->rol === 'superadmin' &&
                        $usuarioActual->rol !== 'superadmin'
                    ) {
                        throw new \RuntimeException(
                            'No tienes permiso para eliminar este usuario.'
                        );
                    }

                    $datosAntes =
                        $this->datosUsuario($user);

                    $user->delete();

                    return [
                        'user' => $user,
                        'datosAntes' => $datosAntes,
                    ];
                }
            );

            $user =
                $user['user'];

            $datosAntes =
                $user['datosAntes'];

            $this->auditar(
                $request,
                'eliminar_usuario',
                'users',
                $user->id,
                $datosAntes,
                null,
                $user->empresa_id
            );

            Log::info(
                'Usuario eliminado correctamente.',
                [
                    'usuario_id' =>
                        $user->id,

                    'empresa_id' =>
                        $user->empresa_id,

                    'eliminado_por' =>
                        $usuarioActual->id,
                ]
            );

            return response()->json([
                'message' =>
                    'Usuario eliminado correctamente.',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' =>
                    'Usuario no encontrado.',

                'error' => [
                    'tipo' =>
                        'model_not_found',

                    'exception' =>
                        get_class($e),

                    'code' =>
                        $e->getCode(),

                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ],
            ], 404);
        } catch (Throwable $e) {
            return $this->respuestaErrorInterno(
                $request,
                $e,
                'Error al eliminar usuario.',
                'eliminar_usuario',
                'users',
                $userId,
                $usuarioActual->empresa_id,
                $usuarioActual->id
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EMPRESAS
    |--------------------------------------------------------------------------
    */

    public function empresas(Request $request)
    {
        $usuarioActual =
            $this->usuarioAutenticado();

        if (!$usuarioActual) {
            return $this->respuestaNoAutenticado();
        }

        try {
            $query = Empresa::query()
                ->select([
                    'id',
                    'nombre',
                    'rfc',
                    'activo',
                    'licencia_tipo',
                    'licencia_fecha_inicio',
                    'licencia_fecha_fin',
                    'licencia_activa',
                ])
                ->orderBy('nombre');

            if ($usuarioActual->rol !== 'superadmin') {
                if (!$usuarioActual->empresa_id) {
                    return $this->respuestaNoAutorizado(
                        'El usuario no tiene una empresa asignada.'
                    );
                }

                $query->where(
                    'id',
                    $usuarioActual->empresa_id
                );
            }

            $empresas = $query
                ->get()
                ->map(
                    fn(Empresa $empresa) => [
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
                    ]
                )
                ->values();

            $this->auditar(
                $request,
                'listar_empresas',
                'empresas',
                null,
                null,
                [
                    'total' =>
                        $empresas->count(),
                ],
                $usuarioActual->rol === 'superadmin'
                    ? null
                    : $usuarioActual->empresa_id
            );

            return response()->json(
                $empresas
            );
        } catch (Throwable $e) {
            return $this->respuestaErrorInterno(
                $request,
                $e,
                'Error al cargar empresas.',
                'listar_empresas',
                'empresas',
                null,
                $usuarioActual->empresa_id,
                $usuarioActual->id
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LICENCIA
    |--------------------------------------------------------------------------
    */

    public function obtenerLicenciaEmpresa(
        Request $request,
        $empresaId
    ) {
        $usuarioActual =
            $this->usuarioAutenticado();

        if (!$usuarioActual) {
            return $this->respuestaNoAutenticado();
        }

        $id = $this->validarId($empresaId);

        if ($id === null) {
            return response()->json([
                'message' =>
                    'El ID de la empresa no es válido.',
            ], 422);
        }

        try {
            if (
                !$this->puedeAdministrarEmpresa(
                    $usuarioActual,
                    $id
                )
            ) {
                return $this->respuestaNoAutorizado(
                    'No tienes permiso para consultar esta empresa.'
                );
            }

            $empresa =
                Empresa::query()->find($id);

            if (!$empresa) {
                return $this->respuestaNoEncontrado(
                    'Empresa no encontrada.'
                );
            }

            $licencia =
                $this->datosLicencia($empresa);

            $this->auditar(
                $request,
                'consultar_licencia_empresa',
                'empresas',
                $empresa->id,
                null,
                [
                    'licencia_tipo' =>
                        $empresa->licencia_tipo,

                    'licencia_activa' =>
                        $empresa->licencia_activa,
                ],
                $empresa->id
            );

            return response()->json(
                $licencia
            );
        } catch (Throwable $e) {
            return $this->respuestaErrorInterno(
                $request,
                $e,
                'Error al obtener licencia.',
                'consultar_licencia_empresa',
                'empresas',
                $id,
                $usuarioActual->empresa_id,
                $usuarioActual->id
            );
        }
    }

    public function actualizarLicenciaEmpresa(
        Request $request,
        $empresaId
    ) {
        $usuarioActual =
            $this->usuarioAutenticado();

        if (!$usuarioActual) {
            return $this->respuestaNoAutenticado();
        }

        $id = $this->validarId($empresaId);

        if ($id === null) {
            return response()->json([
                'message' =>
                    'El ID de la empresa no es válido.',
            ], 422);
        }

        try {
            if (
                !$this->puedeAdministrarEmpresa(
                    $usuarioActual,
                    $id
                )
            ) {
                return $this->respuestaNoAutorizado(
                    'No tienes permiso para modificar esta empresa.'
                );
            }

            $validator = Validator::make(
                $request->all(),
                [
                    'licencia_tipo' => [
                        'required',
                        Rule::in([
                            'dia',
                            'semana',
                            'quincena',
                            'mes',
                            'bimestre',
                            'trimestre',
                            'semestre',
                            'anual',
                            'permanente',
                        ]),
                    ],

                    'licencia_fecha_inicio' => [
                        'required',
                        'date',
                    ],

                    'licencia_fecha_fin' => [
                        'nullable',
                        'date',
                        'after_or_equal:licencia_fecha_inicio',
                    ],

                    'licencia_activa' => [
                        'required',
                        'boolean',
                    ],
                ],
                [
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
                ]
            );

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated =
                $validator->validated();

            $resultado = DB::transaction(
                function () use (
                    $id,
                    $validated
                ) {
                    $empresa = Empresa::query()
                        ->lockForUpdate()
                        ->find($id);

                    if (!$empresa) {
                        throw new ModelNotFoundException(
                            'Empresa no encontrada.'
                        );
                    }

                    $datosAntes =
                        $this->datosEmpresa($empresa);

                    $fechaFin =
                        $validated['licencia_tipo']
                            === 'permanente'
                        ? null
                        : (
                            $validated['licencia_fecha_fin']
                            ?? null
                        );

                    $empresa->update([
                        'licencia_tipo' =>
                            $validated['licencia_tipo'],

                        'licencia_fecha_inicio' =>
                            $validated['licencia_fecha_inicio'],

                        'licencia_fecha_fin' =>
                            $fechaFin,

                        'licencia_activa' =>
                            (bool) $validated['licencia_activa'],
                    ]);

                    $empresa->refresh();

                    return [
                        'empresa' =>
                            $empresa,

                        'datosAntes' =>
                            $datosAntes,
                    ];
                }
            );

            /** @var Empresa $empresa */
            $empresa =
                $resultado['empresa'];

            $this->auditar(
                $request,
                'actualizar_licencia_empresa',
                'empresas',
                $empresa->id,
                $resultado['datosAntes'],
                $this->datosEmpresa($empresa),
                $empresa->id
            );

            Log::info(
                'Licencia actualizada correctamente.',
                [
                    'empresa_id' =>
                        $empresa->id,

                    'actualizado_por' =>
                        $usuarioActual->id,
                ]
            );

            return response()->json([
                'message' =>
                    'Licencia actualizada correctamente.',

                'licencia' =>
                    $this->datosLicencia($empresa),
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion(
                $request,
                $e,
                'actualizar_licencia_validacion_rechazada',
                'empresas',
                $id,
                $id
            );
        } catch (ModelNotFoundException $e) {
            return $this->respuestaNoEncontrado(
                'Empresa no encontrada.'
            );
        } catch (Throwable $e) {
            return $this->respuestaErrorInterno(
                $request,
                $e,
                'Error al actualizar licencia.',
                'actualizar_licencia_empresa',
                'empresas',
                $id,
                $usuarioActual->empresa_id,
                $usuarioActual->id
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REPORTES
    |--------------------------------------------------------------------------
    */

    public function reportes(
        Request $request
    ) {
        $usuarioActual =
            $this->usuarioAutenticado();

        if (!$usuarioActual) {
            return $this->respuestaNoAutenticado();
        }

        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'fecha_desde' => [
                        'nullable',
                        'date',
                    ],

                    'fecha_hasta' => [
                        'nullable',
                        'date',
                        'after_or_equal:fecha_desde',
                    ],

                    'estado' => [
                        'nullable',
                        Rule::in([
                            'pendiente',
                            'pagado',
                            'cancelado',
                        ]),
                    ],

                    'per_page' => [
                        'nullable',
                        'integer',
                        'min:1',
                        'max:100',
                    ],
                ],
                [
                    'fecha_desde.date' =>
                        'La fecha inicial no es válida.',

                    'fecha_hasta.date' =>
                        'La fecha final no es válida.',

                    'fecha_hasta.after_or_equal' =>
                        'La fecha final debe ser igual o posterior a la fecha inicial.',

                    'estado.in' =>
                        'El estado seleccionado no es válido.',

                    'per_page.max' =>
                        'No se pueden solicitar más de 100 registros por página.',
                ]
            );

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $query = Venta::query();

            if ($usuarioActual->rol !== 'superadmin') {
                if (!$usuarioActual->empresa_id) {
                    return $this->respuestaNoAutorizado(
                        'El usuario no tiene una empresa asignada.'
                    );
                }

                $query->where(
                    'empresa_id',
                    $usuarioActual->empresa_id
                );
            }

            if ($request->filled('fecha_desde')) {
                $fechaDesde =
                    \Carbon\Carbon::parse(
                        $request->fecha_desde
                    )->startOfDay();

                $query->where(
                    'fecha',
                    '>=',
                    $fechaDesde
                );
            }

            if ($request->filled('fecha_hasta')) {
                $fechaHasta =
                    \Carbon\Carbon::parse(
                        $request->fecha_hasta
                    )
                    ->addDay()
                    ->startOfDay();

                $query->where(
                    'fecha',
                    '<',
                    $fechaHasta
                );
            }

            if ($request->filled('estado')) {
                $query->where(
                    'estado',
                    $request->estado
                );
            }

            $resumen =
                (clone $query)
                    ->selectRaw(
                        'COUNT(*) as numero_tickets, COALESCE(SUM(total), 0) as total_ventas'
                    )
                    ->first();

            $totalVentas =
                (float) (
                    $resumen->total_ventas ?? 0
                );

            $numeroTickets =
                (int) (
                    $resumen->numero_tickets ?? 0
                );

            $ticketPromedio =
                $numeroTickets > 0
                    ? round(
                        $totalVentas /
                        $numeroTickets,
                        2
                    )
                    : 0;
            // ---------------------------------------------------------------------
// PRODUCTOS MÁS VENDIDOS
// ---------------------------------------------------------------------

$productosMasVendidos = DetalleVenta::query()
    ->selectRaw('
        producto_id,
        SUM(cantidad) as cantidad_vendida,
        SUM(subtotal) as total_vendido
    ')
    ->whereHas('venta', function ($ventaQuery) use ($request, $usuarioActual) {

        // Misma restricción por empresa del reporte principal
        if ($usuarioActual->rol !== 'superadmin') {
            $ventaQuery->where('empresa_id', $usuarioActual->empresa_id);
        }

        // Misma fecha desde
        if ($request->filled('fecha_desde')) {
            $fechaDesde = \Carbon\Carbon::parse(
                $request->fecha_desde
            )->startOfDay();

            $ventaQuery->where('fecha', '>=', $fechaDesde);
        }

        // Misma fecha hasta
        if ($request->filled('fecha_hasta')) {
            $fechaHasta = \Carbon\Carbon::parse(
                $request->fecha_hasta
            )->addDay()->startOfDay();

            $ventaQuery->where('fecha', '<', $fechaHasta);
        }

        // Mismo estado
        if ($request->filled('estado')) {
            $ventaQuery->where('estado', $request->estado);
        }
    })
    ->with('producto:id,nombre,codigo')
    ->groupBy('producto_id')
    ->orderByDesc('cantidad_vendida')
    ->limit(10)
    ->get()
    ->map(function ($detalle) {
        return [
            'producto_id' => $detalle->producto_id,
            'codigo' => $detalle->producto?->codigo,
            'nombre' => $detalle->producto?->nombre ?? 'Producto eliminado',
            'cantidad_vendida' => (float) $detalle->cantidad_vendida,
            'total_vendido' => (float) $detalle->total_vendido,
        ];
    })
    ->values();
            $perPage = min(
                max(
                    (int) $request->input(
                        'per_page',
                        20
                    ),
                    1
                ),
                100
            );

            $ventas =
                (clone $query)
                    ->with([
                        'usuario:id,name,numero_usuario',
                        'cliente:id,nombre,telefono',
                    ])
                    ->orderByDesc('fecha')
                    ->paginate($perPage);
            $totalProductos = (float) DetalleVenta::query()
    ->whereHas('venta', function ($ventaQuery) use ($request, $usuarioActual) {

        if ($usuarioActual->rol !== 'superadmin') {
            $ventaQuery->where('empresa_id', $usuarioActual->empresa_id);
        }

        if ($request->filled('fecha_desde')) {
            $fechaDesde = \Carbon\Carbon::parse(
                $request->fecha_desde
            )->startOfDay();

            $ventaQuery->where('fecha', '>=', $fechaDesde);
        }

        if ($request->filled('fecha_hasta')) {
            $fechaHasta = \Carbon\Carbon::parse(
                $request->fecha_hasta
            )->addDay()->startOfDay();

            $ventaQuery->where('fecha', '<', $fechaHasta);
        }

        if ($request->filled('estado')) {
            $ventaQuery->where('estado', $request->estado);
        }
    })
    ->sum('cantidad');
            $this->auditar(
                $request,
                'consultar_reportes_ventas',
                'ventas',
                null,
                null,
                [
                    'total_ventas' =>
                        $totalVentas,

                    'numero_tickets' =>
                        $numeroTickets,
                ],
                $usuarioActual->rol === 'superadmin'
                    ? null
                    : $usuarioActual->empresa_id
            );

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
                'total_productos' => $totalProductos,
'productos_mas_vendidos' => $productosMasVendidos,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion(
                $request,
                $e,
                'consultar_reportes_validacion_rechazada',
                'ventas',
                null,
                $usuarioActual->empresa_id
            );
        } catch (Throwable $e) {
            return $this->respuestaErrorInterno(
                $request,
                $e,
                'Error al generar reportes.',
                'consultar_reportes_ventas',
                'ventas',
                null,
                $usuarioActual->empresa_id,
                $usuarioActual->id
            );
        }
    }

    public function exportarReportes(
        Request $request
    ) {
        $usuarioActual =
            $this->usuarioAutenticado();

        if (!$usuarioActual) {
            return $this->respuestaNoAutenticado();
        }

        $this->auditar(
            $request,
            'exportar_reportes',
            'ventas',
            null,
            null,
            [
                'estado' =>
                    'en_desarrollo',
            ],
            $usuarioActual->empresa_id
        );

        return response()->json([
            'message' =>
                'Exportación en desarrollo.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN
    |--------------------------------------------------------------------------
    */

    public function actualizarConfiguracion(
        Request $request
    ) {
        $usuarioActual =
            $this->usuarioAutenticado();

        if (!$usuarioActual) {
            return $this->respuestaNoAutenticado();
        }

        try {
            if (!$usuarioActual->empresa_id) {
                return $this->respuestaNoAutorizado(
                    'El usuario no tiene una empresa asignada.'
                );
            }

            $validator = Validator::make(
                $request->all(),
                [
                    'colores' =>
                        'nullable|array',

                    'colores.primary' =>
                        'nullable|string|max:20',

                    'colores.secondary' =>
                        'nullable|string|max:20',

                    'colores.background' =>
                        'nullable|string|max:20',

                    'colores.text' =>
                        'nullable|string|max:20',

                    'colores.navbar' =>
                        'nullable|string|max:20',
                ]
            );

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $empresa = Empresa::query()
                ->where(
                    'id',
                    $usuarioActual->empresa_id
                )
                ->lockForUpdate()
                ->first();

            if (!$empresa) {
                return $this->respuestaNoEncontrado(
                    'Empresa no encontrada.'
                );
            }

            $datosAntes =
                $this->datosEmpresa($empresa);

            $empresa = DB::transaction(
                function () use (
                    $empresa,
                    $request
                ) {
                    if ($request->has('colores')) {
                        $empresa->colores =
                            $request->input('colores');
                    }

                    $empresa->save();

                    return $empresa->fresh();
                }
            );

            $this->auditar(
                $request,
                'actualizar_configuracion_empresa',
                'empresas',
                $empresa->id,
                $datosAntes,
                [
                    'colores' =>
                        $empresa->colores,
                ],
                $empresa->id
            );

            return response()->json([
                'message' =>
                    'Configuración actualizada correctamente.',

                'colores' =>
                    $empresa->colores,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion(
                $request,
                $e,
                'actualizar_configuracion_validacion_rechazada',
                'empresas',
                $usuarioActual->empresa_id,
                $usuarioActual->empresa_id
            );
        } catch (Throwable $e) {
            return $this->respuestaErrorInterno(
                $request,
                $e,
                'Error al actualizar configuración.',
                'actualizar_configuracion_empresa',
                'empresas',
                $usuarioActual->empresa_id,
                $usuarioActual->empresa_id,
                $usuarioActual->id
            );
        }
    }
}
