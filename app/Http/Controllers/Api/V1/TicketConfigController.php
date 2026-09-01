<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionTicket;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class TicketConfigController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria
    ) {
    }

    /**
     * Obtener la configuración del ticket.
     *
     * Si no existe, se crea automáticamente.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        try {
            $config = ConfiguracionTicket::where(
                'empresa_id',
                $empresaId
            )->first();

            if (!$config) {
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
                        [
                            'nombre' => 'nombre_negocio',
                            'visible' => true,
                            'orden' => 1,
                        ],
                        [
                            'nombre' => 'direccion',
                            'visible' => true,
                            'orden' => 2,
                        ],
                        [
                            'nombre' => 'telefono',
                            'visible' => true,
                            'orden' => 3,
                        ],
                        [
                            'nombre' => 'fecha',
                            'visible' => true,
                            'orden' => 4,
                        ],
                        [
                            'nombre' => 'productos',
                            'visible' => true,
                            'orden' => 5,
                        ],
                        [
                            'nombre' => 'total',
                            'visible' => true,
                            'orden' => 6,
                        ],
                    ]),
                    'cabecera' => '¡Gracias por su compra!',
                    'pie_pagina' => 'Visítenos en www.miempresa.com',
                ]);

                $this->registrarAuditoria(
                    $request,
                    'crear_por_defecto',
                    'configuracion_tickets',
                    $config->id,
                    null,
                    $config->toArray(),
                    $empresaId,
                    $usuarioId
                );
            }

            return response()->json($config);
        } catch (Throwable $e) {
            Log::error(
                'Error al obtener configuración del ticket',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al cargar la configuración del ticket.',
            ], 500);
        }
    }

    /**
     * Actualizar configuración del ticket.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        /*
         * Validación fuera del try/catch para conservar HTTP 422.
         */
        $validated = $request->validate([
            'papel' => [
                'nullable',
                Rule::in([
                    '58mm',
                    '80mm',
                ]),
            ],

            'fuente' => [
                'nullable',
                'string',
                'max:50',
            ],

            'tamano_fuente' => [
                'nullable',
                'integer',
                'min:8',
                'max:30',
            ],

            'alineacion' => [
                'nullable',
                Rule::in([
                    'izquierda',
                    'centro',
                    'derecha',
                ]),
            ],

            'mostrar_logo' => [
                'nullable',
                'boolean',
            ],

            'mostrar_qr' => [
                'nullable',
                'boolean',
            ],

            'qr_contenido' => [
                'nullable',
                'string',
                'max:255',
            ],

            'campos' => [
                'nullable',
                'array',
            ],

            'cabecera' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pie_pagina' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        try {
            $config = ConfiguracionTicket::where(
                'empresa_id',
                $empresaId
            )->first();

            if (!$config) {
                $config = ConfiguracionTicket::create([
                    'empresa_id' => $empresaId,
                    'papel' => $validated['papel'] ?? '58mm',
                    'fuente' => $validated['fuente'] ?? 'Arial',
                    'tamano_fuente' => $validated['tamano_fuente'] ?? 12,
                    'alineacion' => $validated['alineacion'] ?? 'izquierda',
                    'mostrar_logo' => array_key_exists(
                        'mostrar_logo',
                        $validated
                    )
                        ? $validated['mostrar_logo']
                        : true,
                    'mostrar_qr' => array_key_exists(
                        'mostrar_qr',
                        $validated
                    )
                        ? $validated['mostrar_qr']
                        : true,
                    'qr_contenido' => $validated['qr_contenido']
                        ?? 'https://miempresa.com',
                    'campos' => json_encode(
                        $validated['campos'] ?? []
                    ),
                    'cabecera' => $validated['cabecera']
                        ?? '¡Gracias por su compra!',
                    'pie_pagina' => $validated['pie_pagina']
                        ?? 'Visítenos en www.miempresa.com',
                ]);

                $this->registrarAuditoria(
                    $request,
                    'crear',
                    'configuracion_tickets',
                    $config->id,
                    null,
                    $config->toArray(),
                    $empresaId,
                    $usuarioId
                );
            } else {
                $datosAntes = $config->toArray();

                $datosActualizar = $validated;

                if (array_key_exists('campos', $datosActualizar)) {
                    $datosActualizar['campos'] = json_encode(
                        $datosActualizar['campos']
                    );
                }

                $config->update($datosActualizar);
                $config->refresh();

                $this->registrarAuditoria(
                    $request,
                    'actualizar',
                    'configuracion_tickets',
                    $config->id,
                    $datosAntes,
                    $config->toArray(),
                    $empresaId,
                    $usuarioId
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada correctamente',
                'config' => $config,
            ]);
        } catch (Throwable $e) {
            $this->registrarAuditoria(
                $request,
                'actualizar_error',
                'configuracion_tickets',
                isset($config) ? $config->id : null,
                isset($config) ? $config->toArray() : null,
                [
                    'error_tipo' => get_class($e),
                ],
                $empresaId,
                $usuarioId
            );

            Log::error(
                'Error actualizando ticket config',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar configuración',
            ], 500);
        }
    }

    /**
     * Registrar auditoría sin permitir que falle la operación principal.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaId,
        ?int $usuarioId
    ): void {
        /*
         * No registrar acciones de superadmin.
         */
        if ($request->user()?->rol === 'superadmin') {
            return;
        }

        try {
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
                'No fue posible registrar auditoría de configuración de ticket.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}