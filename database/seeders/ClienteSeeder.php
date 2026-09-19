<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ No hay empresa. Ejecuta EmpresaSeeder primero.');
            return;
        }

        $clientes = [
            [
                'nombre' => 'Cliente Genérico',
                'email' => 'cliente@ejemplo.com',
                'telefono' => '555-0001',
                'rfc' => null,
                'tipo' => 'particular',
            ],
            [
                'nombre' => 'María López',
                'email' => 'maria@ejemplo.com',
                'telefono' => '555-0002',
                'rfc' => null,
                'tipo' => 'particular',
            ],
            [
                'nombre' => 'Carlos Pérez',
                'email' => 'carlos@ejemplo.com',
                'telefono' => '555-0003',
                'rfc' => null,
                'tipo' => 'particular',
            ],
        ];

        foreach ($clientes as $cli) {
            Cliente::firstOrCreate(
                [
                    'email' => $cli['email'],
                    'empresa_id' => $empresa->id,
                ],
                array_merge($cli, [
                    'empresa_id' => $empresa->id,
                    'direccion' => 'Dirección de ' . $cli['nombre'],
                    'activo' => true,
                ])
            );
        }

        $this->command->info('✅ Clientes creados.');
    }
}