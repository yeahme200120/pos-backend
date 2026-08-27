<?php
// database/seeders/ProductoSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use App\Models\Empresa;
use App\Models\UnidadMedida;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ No hay empresa. Ejecuta EmpresaSeeder primero.');
            return;
        }

        $unidades = UnidadMedida::where('empresa_id', $empresa->id)->get()->keyBy('nombre');

        if ($unidades->isEmpty()) {
            $this->command->error('❌ No hay unidades de medida. Ejecuta UnidadMedidaSeeder primero.');
            return;
        }

        $productos = [
            [
                'codigo' => 'PROD001',
                'nombre' => 'Café Americano',
                'descripcion' => 'Café americano recién hecho',
                'precio' => 45.00,
                'costo' => 15.00, // ✅ Usando 'costo' que está en la migración
                'impuesto' => 16.00,
                'stock' => 100,
                'stock_minimo' => 10,
                'unidad_nombre' => 'Pieza'
            ],
            [
                'codigo' => 'PROD002',
                'nombre' => 'Café Latte',
                'descripcion' => 'Café latte con leche espumada',
                'precio' => 55.00,
                'costo' => 20.00,
                'impuesto' => 16.00,
                'stock' => 80,
                'stock_minimo' => 8,
                'unidad_nombre' => 'Pieza'
            ],
            [
                'codigo' => 'PROD003',
                'nombre' => 'Café Mocha',
                'descripcion' => 'Café mocha con chocolate',
                'precio' => 60.00,
                'costo' => 22.00,
                'impuesto' => 16.00,
                'stock' => 70,
                'stock_minimo' => 7,
                'unidad_nombre' => 'Pieza'
            ],
            [
                'codigo' => 'PROD004',
                'nombre' => 'Panqueque',
                'descripcion' => 'Panqueque con miel de maple',
                'precio' => 60.00,
                'costo' => 25.00,
                'impuesto' => 16.00,
                'stock' => 40,
                'stock_minimo' => 5,
                'unidad_nombre' => 'Pieza'
            ],
            [
                'codigo' => 'PROD005',
                'nombre' => 'Té Verde',
                'descripcion' => 'Té verde natural',
                'precio' => 35.00,
                'costo' => 10.00,
                'impuesto' => 16.00,
                'stock' => 120,
                'stock_minimo' => 15,
                'unidad_nombre' => 'Pieza'
            ],
            [
                'codigo' => 'PROD006',
                'nombre' => 'Pollo Entero',
                'descripcion' => 'Pollo entero fresco',
                'precio' => 120.00,
                'costo' => 70.00,
                'impuesto' => 16.00,
                'stock' => 30,
                'stock_minimo' => 5,
                'unidad_nombre' => 'Pollo Entero'
            ],
            [
                'codigo' => 'PROD007',
                'nombre' => 'Pollo en Kilo',
                'descripcion' => 'Pollo en piezas por kilo',
                'precio' => 80.00,
                'costo' => 45.00,
                'impuesto' => 16.00,
                'stock' => 50,
                'stock_minimo' => 10,
                'unidad_nombre' => 'Kilogramo'
            ],
        ];

        foreach ($productos as $prodData) {
            $unidadId = null;
            if (isset($prodData['unidad_nombre']) && isset($unidades[$prodData['unidad_nombre']])) {
                $unidadId = $unidades[$prodData['unidad_nombre']]->id;
            } else {
                $this->command->warn("⚠️ Unidad '{$prodData['unidad_nombre']}' no encontrada para '{$prodData['nombre']}'");
            }

            Producto::firstOrCreate(
                ['codigo' => $prodData['codigo']],
                [
                    'empresa_id' => $empresa->id,
                    'nombre' => $prodData['nombre'],
                    'descripcion' => $prodData['descripcion'],
                    'precio' => $prodData['precio'],
                    'costo' => $prodData['costo'], // ✅ Usando 'costo'
                    'impuesto' => $prodData['impuesto'],
                    'stock' => $prodData['stock'],
                    'stock_minimo' => $prodData['stock_minimo'],
                    'unidad_medida_id' => $unidadId,
                    // ❌ ELIMINADO: 'precio_por_unidad' => $prodData['precio'],
                    'activo' => true,
                ]
            );
        }

        $this->command->info('✅ Productos creados con unidades asignadas.');
    }
}