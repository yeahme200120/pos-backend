<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cliente;
use App\Models\Empresa;

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
            ['nombre' => 'Cliente Genérico', 'email' => 'cliente@ejemplo.com', 'telefono' => '555-0001'],
            ['nombre' => 'María López', 'email' => 'maria@ejemplo.com', 'telefono' => '555-0002'],
            ['nombre' => 'Carlos Pérez', 'email' => 'carlos@ejemplo.com', 'telefono' => '555-0003'],
        ];

        foreach ($clientes as $cli) {
            Cliente::firstOrCreate(
                ['email' => $cli['email']],
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