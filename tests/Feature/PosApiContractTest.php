<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosApiContractTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'nombre' => 'Mi Negocio',
            'activo' => true,
        ]);

        $this->user = User::create([
            'name' => 'Usuario Demo',
            'email' => 'demo@empresa.test',
            'password' => Hash::make('password123'),
            'telefono' => '5551234567',
            'empresa_id' => $this->empresa->id,
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    public function test_catalogo_includes_tombstones_for_deleted_records(): void
    {
        $producto = Producto::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'PROD-001',
            'nombre' => 'Producto demo',
            'precio' => 100,
            'costo' => 70,
            'impuesto' => 16,
            'stock' => 10,
            'activo' => true,
        ]);

        $categoria = Categoria::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Bebidas',
            'descripcion' => 'Para bebidas',
            'activo' => true,
        ]);

        $producto->delete();
        $categoria->delete();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/catalogos?desde=2020-01-01T00:00:00Z');

        $response->assertOk();
        $this->assertArrayHasKey('tombstones', $response->json());
        $this->assertArrayHasKey('productos', $response->json('tombstones'));
        $this->assertArrayHasKey('categorias', $response->json('tombstones'));
        $this->assertSame($producto->id, $response->json('tombstones.productos.0.id'));
        $this->assertSame($categoria->id, $response->json('tombstones.categorias.0.id'));
    }

    public function test_sync_offline_is_idempotent_for_repeated_uuid(): void
    {
        $producto = Producto::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'PROD-SYNC',
            'nombre' => 'Producto sync',
            'precio' => 150,
            'costo' => 100,
            'impuesto' => 0,
            'stock' => 10,
            'activo' => true,
        ]);

        $payload = [
            'ventas' => [[
                'uuid_local' => 'uuid-123',
                'fecha_venta' => '2026-08-28',
                'productos' => [[
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio_unitario' => 150,
                    'descuento' => 0,
                ]],
                'pagos' => [[
                    'forma_pago' => 'efectivo',
                    'monto' => 150,
                    'referencia' => null,
                    'cambio' => 0,
                ]],
            ]],
        ];

        $first = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync/offline', $payload);

        $first->assertOk();
        $this->assertFalse((bool) $first->json('procesadas.0.idempotente'));
        $this->assertNotNull($first->json('procesadas.0.folio'));

        $second = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync/offline', $payload);

        $second->assertOk();
        $this->assertTrue((bool) $second->json('procesadas.0.idempotente'));
        $this->assertSame($first->json('procesadas.0.venta_id'), $second->json('procesadas.0.venta_id'));
        $this->assertSame($first->json('procesadas.0.folio'), $second->json('procesadas.0.folio'));
    }
}
