<?php
// database/seeders/CatalogSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Impuesto;
use App\Models\FormaPago;

class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $empresaId = 1; // Asumiendo que existe la empresa con ID 1

        // Productos
        Producto::create([
            'empresa_id' => $empresaId,
            'codigo' => 'PROD001',
            'nombre' => 'Café Americano',
            'descripcion' => 'Café negro recién hecho',
            'precio' => 45.00,
            'costo' => 15.00,
            'impuesto' => 16,
            'stock' => 100,
            'stock_minimo' => 10,
            'activo' => true,
        ]);

        Producto::create([
            'empresa_id' => $empresaId,
            'codigo' => 'PROD002',
            'nombre' => 'Panqueque con miel',
            'descripcion' => 'Panqueque esponjoso con miel de maple',
            'precio' => 60.00,
            'costo' => 25.00,
            'impuesto' => 16,
            'stock' => 50,
            'stock_minimo' => 5,
            'activo' => true,
        ]);

        // Clientes
        Cliente::create([
            'empresa_id' => $empresaId,
            'nombre' => 'Cliente Genérico',
            'email' => 'cliente@test.com',
            'telefono' => '555-0000',
            'direccion' => 'Calle Principal 123',
            'activo' => true,
        ]);

        // Impuestos
        Impuesto::create([
            'empresa_id' => $empresaId,
            'nombre' => 'IVA General',
            'porcentaje' => 16,
            'activo' => true,
        ]);

        // Formas de pago
        $formas = ['Efectivo', 'Tarjeta de Crédito', 'Tarjeta de Débito', 'Transferencia', 'Mercado Pago'];
        foreach ($formas as $nombre) {
            FormaPago::create([
                'empresa_id' => $empresaId,
                'nombre' => $nombre,
                'activo' => true,
            ]);
        }
    }
}