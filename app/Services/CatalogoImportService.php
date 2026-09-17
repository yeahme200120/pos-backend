<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\Empresa;
use App\Models\FormaPago;
use App\Models\Impuesto;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Importa catálogos desde Excel (base64).
 *
 * No toca la app Flutter. Los datos insertados/actualizados aquí
 * serán enviados a la app en el próximo `syncPull`.
 */
class CatalogoImportService
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @return array {
     *   tipo_catalogo: string,
     *   empresa_id: int,
     *   total: int,
     *   insertados: int,
     *   actualizados: int,
     *   rechazados: int,
     *   detalle: [
     *     insertados: array,
     *     rechazados: array,
     *   ]
     * }
     */
    public function importarDesdeBase64(
        Request $request,
        int $usuarioId,
        string $tipoCatalogo,
        string $base64,
        string $nombreArchivo
    ): array {
        $usuario = User::query()->find($usuarioId);

        if (!$usuario) {
            throw new RuntimeException('El usuario indicado no existe.');
        }

        $empresaId = (int) $usuario->empresa_id;

        if ($empresaId <= 0) {
            throw new RuntimeException('El usuario no tiene una empresa asociada.');
        }

        $empresa = Empresa::query()->find($empresaId);

        if (!$empresa) {
            throw new RuntimeException('La empresa no existe.');
        }

        // Limpiar prefijo data URI si existe.
        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $binario = base64_decode($base64, true);

        if ($binario === false || strlen($binario) === 0) {
            throw new RuntimeException('El archivo base64 no es válido.');
        }

        // Guardar en temp.
        $tmp = tempnam(sys_get_temp_dir(), 'import_');

        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal.');
        }

        file_put_contents($tmp, $binario);

        try {
            $hoja = IOFactory::load($tmp)->getActiveSheet();
        } catch (Throwable $e) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo leer el Excel: ' . $e->getMessage());
        }

        @unlink($tmp);

        $filas = $hoja->toArray(null, true, true, false);

        if (count($filas) < 2) {
            throw new RuntimeException('El archivo no contiene filas de datos.');
        }

        $encabezados = array_map(
            fn ($v) => strtolower(trim((string) $v)),
            $filas[0]
        );

        $insertados = [];
        $actualizados = [];
        $rechazados = [];

        DB::beginTransaction();

        try {
            foreach (array_slice($filas, 1) as $idx => $fila) {
                $numeroFila = $idx + 2;

                // Saltar filas completamente vacías.
                if (count(array_filter($fila, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $datos = $this->mapearFila($encabezados, $fila);

                try {
                    $res = $this->procesarFila($empresaId, $tipoCatalogo, $datos);

                    if ($res['accion'] === 'insertado') {
                        $insertados[] = [
                            'fila' => $numeroFila,
                            'id' => $res['id'],
                            'identificador' => $res['identificador'],
                            'datos' => $datos,
                        ];
                    } else {
                        $actualizados[] = [
                            'fila' => $numeroFila,
                            'id' => $res['id'],
                            'identificador' => $res['identificador'],
                            'datos' => $datos,
                        ];
                    }
                } catch (Throwable $e) {
                    $rechazados[] = [
                        'fila' => $numeroFila,
                        'campo_error' => $e instanceof RuntimeException ? $e->getMessage() : 'desconocido',
                        'mensaje' => $e->getMessage(),
                        'datos_fila' => $datos,
                    ];
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error procesando importación.', [
                'tipo_catalogo' => $tipoCatalogo,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('No se pudo completar la importación.');
        }

        $resultado = [
            'tipo_catalogo' => $tipoCatalogo,
            'empresa_id' => $empresaId,
            'total' => count($insertados) + count($actualizados) + count($rechazados),
            'insertados' => count($insertados),
            'actualizados' => count($actualizados),
            'rechazados' => count($rechazados),
            'detalle' => [
                'insertados' => $insertados,
                'actualizados' => $actualizados,
                'rechazados' => $rechazados,
            ],
        ];

        // Auditoría.
        try {
            $this->auditoriaService->registrar(
                $request,
                'catalogo.importado_excel',
                'catalogos',
                null,
                null,
                [
                    'tipo_catalogo' => $tipoCatalogo,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'archivo' => $nombreArchivo,
                    'insertados' => $resultado['insertados'],
                    'actualizados' => $resultado['actualizados'],
                    'rechazados' => $resultado['rechazados'],
                ],
                $empresaId,
                $request->user()?->id
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo auditar la importación.', [
                'error' => $e->getMessage(),
            ]);
        }

        return $resultado;
    }

    /**
     * Mapea una fila a un array asociativo usando encabezados.
     */
    private function mapearFila(array $encabezados, array $fila): array
    {
        $datos = [];

        foreach ($encabezados as $i => $col) {
            if ($col === '') {
                continue;
            }

            $valor = $fila[$i] ?? null;

            if (is_string($valor)) {
                $valor = trim($valor);
            }

            $datos[$col] = $valor;
        }

        return $datos;
    }

    /**
     * Procesa una fila individual y hace upsert según el tipo de catálogo.
     */
    private function procesarFila(int $empresaId, string $tipo, array $datos): array
    {
        return match ($tipo) {
            'productos' => $this->procesarProducto($empresaId, $datos),
            'clientes' => $this->procesarCliente($empresaId, $datos),
            'categorias' => $this->procesarCategoria($empresaId, $datos),
            'impuestos' => $this->procesarImpuesto($empresaId, $datos),
            'formas_pago' => $this->procesarFormaPago($empresaId, $datos),
            'unidades_medida' => $this->procesarUnidadMedida($empresaId, $datos),
            'promociones' => $this->procesarPromocion($empresaId, $datos),
            'cupones' => $this->procesarCupon($empresaId, $datos),
            default => throw new RuntimeException('tipo_catalogo'),
        };
    }

    // ============================================================
    // PROCESADORES POR CATÁLOGO
    // ============================================================

    private function procesarProducto(int $empresaId, array $d): array
    {
        $codigo = $this->obligatorio($d, 'codigo');
        $nombre = $this->obligatorio($d, 'nombre');
        $precio = $this->numericoObligatorio($d, 'precio');

        $stock = $this->numericoOpcional($d, 'stock', 0.0);
        $descripcion = $this->textoOpcional($d, 'descripcion');
        $inventariable = $this->booleanoOpcional($d, 'is_inventariable', true);

        $categoriaId = null;
        $categoriaRef = $this->textoOpcional($d, 'categoria');

        if ($categoriaRef) {
            $categoriaId = $this->buscarCategoria($empresaId, $categoriaRef);

            if ($categoriaId === null) {
                throw new RuntimeException('categoria');
            }
        }

        $existente = Producto::withTrashed()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first();

        if ($existente) {
            $existente->restore();
            $existente->fill([
                'nombre' => $nombre,
                'precio' => $precio,
                'stock' => $stock,
                'descripcion' => $descripcion,
                'categoria_id' => $categoriaId,
                'is_inventariable' => $inventariable,
            ])->save();

            return [
                'accion' => 'actualizado',
                'id' => $existente->id,
                'identificador' => $codigo,
            ];
        }

        $nuevo = Producto::create([
            'empresa_id' => $empresaId,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'precio' => $precio,
            'stock' => $stock,
            'descripcion' => $descripcion,
            'categoria_id' => $categoriaId,
            'is_inventariable' => $inventariable,
            'activo' => true,
        ]);

        return [
            'accion' => 'insertado',
            'id' => $nuevo->id,
            'identificador' => $codigo,
        ];
    }

    private function procesarCliente(int $empresaId, array $d): array
    {
        $nombre = $this->obligatorio($d, 'nombre');
        $email = $this->textoOpcional($d, 'email');
        $telefono = $this->textoOpcional($d, 'telefono');
        $rfc = $this->textoOpcional($d, 'rfc');

        $existente = $email
            ? Cliente::withTrashed()->where('empresa_id', $empresaId)->where('email', $email)->first()
            : null;

        if ($existente) {
            $existente->restore();
            $existente->fill([
                'nombre' => $nombre,
                'telefono' => $telefono,
                'rfc' => $rfc,
            ])->save();

            return ['accion' => 'actualizado', 'id' => $existente->id, 'identificador' => $email];
        }

        $nuevo = Cliente::create([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'rfc' => $rfc,
            'activo' => true,
        ]);

        return ['accion' => 'insertado', 'id' => $nuevo->id, 'identificador' => $email ?? $nombre];
    }

    private function procesarCategoria(int $empresaId, array $d): array
    {
        $nombre = $this->obligatorio($d, 'nombre');
        $codigo = $this->textoOpcional($d, 'codigo');

        $existente = $codigo
            ? Categoria::withTrashed()->where('empresa_id', $empresaId)->where('codigo', $codigo)->first()
            : Categoria::withTrashed()->where('empresa_id', $empresaId)->where('nombre', $nombre)->first();

        if ($existente) {
            $existente->restore();
            $existente->fill(['nombre' => $nombre, 'codigo' => $codigo])->save();

            return ['accion' => 'actualizado', 'id' => $existente->id, 'identificador' => $codigo ?? $nombre];
        }

        $nuevo = Categoria::create([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activo' => true,
        ]);

        return ['accion' => 'insertado', 'id' => $nuevo->id, 'identificador' => $codigo ?? $nombre];
    }

    private function procesarImpuesto(int $empresaId, array $d): array
    {
        $nombre = $this->obligatorio($d, 'nombre');
        $codigo = $this->textoOpcional($d, 'codigo');
        $rate = $this->numericoObligatorio($d, 'rate');

        $existente = $codigo
            ? Impuesto::withTrashed()->where('empresa_id', $empresaId)->where('codigo', $codigo)->first()
            : null;

        if ($existente) {
            $existente->restore();
            $existente->fill(['nombre' => $nombre, 'rate' => $rate])->save();

            return ['accion' => 'actualizado', 'id' => $existente->id, 'identificador' => $codigo ?? $nombre];
        }

        $nuevo = Impuesto::create([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'codigo' => $codigo,
            'rate' => $rate,
            'activo' => true,
        ]);

        return ['accion' => 'insertado', 'id' => $nuevo->id, 'identificador' => $codigo ?? $nombre];
    }

    private function procesarFormaPago(int $empresaId, array $d): array
    {
        return $this->procesarCatalogoSimple(FormaPago::class, $empresaId, $d);
    }

    private function procesarUnidadMedida(int $empresaId, array $d): array
    {
        return $this->procesarCatalogoSimple(UnidadMedida::class, $empresaId, $d);
    }

    private function procesarPromocion(int $empresaId, array $d): array
    {
        return $this->procesarCatalogoSimple(Promocion::class, $empresaId, $d);
    }

    private function procesarCupon(int $empresaId, array $d): array
    {
        return $this->procesarCatalogoSimple(Cupon::class, $empresaId, $d);
    }

    private function procesarCatalogoSimple(string $modelClass, int $empresaId, array $d): array
    {
        $nombre = $this->obligatorio($d, 'nombre');
        $codigo = $this->textoOpcional($d, 'codigo');
        $activo = $this->booleanoOpcional($d, 'activo', true);

        $existente = $codigo
            ? $modelClass::withTrashed()->where('empresa_id', $empresaId)->where('codigo', $codigo)->first()
            : $modelClass::withTrashed()->where('empresa_id', $empresaId)->where('nombre', $nombre)->first();

        if ($existente) {
            $existente->restore();
            $existente->fill([
                'nombre' => $nombre,
                'codigo' => $codigo,
                'activo' => $activo,
            ])->save();

            return ['accion' => 'actualizado', 'id' => $existente->id, 'identificador' => $codigo ?? $nombre];
        }

        $nuevo = $modelClass::create([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activo' => $activo,
        ]);

        return ['accion' => 'insertado', 'id' => $nuevo->id, 'identificador' => $codigo ?? $nombre];
    }

    // ============================================================
    // HELPERS DE VALIDACIÓN
    // ============================================================

    private function obligatorio(array $d, string $campo): string
    {
        $valor = trim((string) ($d[$campo] ?? ''));

        if ($valor === '') {
            throw new RuntimeException($campo);
        }

        return $valor;
    }

    private function textoOpcional(array $d, string $campo): ?string
    {
        $valor = trim((string) ($d[$campo] ?? ''));

        return $valor === '' ? null : $valor;
    }

    private function numericoObligatorio(array $d, string $campo): float
    {
        $valor = $d[$campo] ?? null;

        if ($valor === null || $valor === '') {
            throw new RuntimeException($campo);
        }

        if (!is_numeric($valor)) {
            throw new RuntimeException($campo);
        }

        return (float) $valor;
    }

    private function numericoOpcional(array $d, string $campo, float $default = 0): float
    {
        $valor = $d[$campo] ?? null;

        if ($valor === null || $valor === '') {
            return $default;
        }

        if (!is_numeric($valor)) {
            throw new RuntimeException($campo);
        }

        return (float) $valor;
    }

    private function booleanoOpcional(array $d, string $campo, bool $default = false): bool
    {
        $valor = $d[$campo] ?? null;

        if ($valor === null || $valor === '') {
            return $default;
        }

        if (is_bool($valor)) {
            return $valor;
        }

        $texto = strtolower(trim((string) $valor));

        return in_array($texto, ['1', 'true', 'si', 'sí', 'yes'], true);
    }

    /**
     * Busca la categoría por código primero, si no por nombre.
     */
    private function buscarCategoria(int $empresaId, string $ref): ?int
    {
        $porCodigo = Categoria::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $ref)
            ->first();

        if ($porCodigo) {
            return $porCodigo->id;
        }

        $porNombre = Categoria::query()
            ->where('empresa_id', $empresaId)
            ->where('nombre', $ref)
            ->first();

        return $porNombre?->id;
    }
}