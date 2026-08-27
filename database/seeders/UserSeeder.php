<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Empresa;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ No hay empresa. Ejecuta EmpresaSeeder primero.');
            return;
        }

        User::firstOrCreate(
            ['email' => 'yeahme200120@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('yesy2001'),
                'telefono' => '555-1234',
                'numero_usuario' => User::generarNumeroUsuario(),
                'empresa_id' => $empresa->id,
                'rol' => 'superadmin',
                'licencia_tipo' => 'permanente',
                'licencia_fecha_inicio' => now(),
                'licencia_fecha_fin' => null,
                'activo' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'vendedor@pos.com'],
            [
                'name' => 'Vendedor Juan',
                'password' => Hash::make('password123'),
                'telefono' => '555-5678',
                'numero_usuario' => User::generarNumeroUsuario(),
                'empresa_id' => $empresa->id,
                'rol' => 'vendedor',
                'licencia_tipo' => 'mes',
                'licencia_fecha_inicio' => now(),
                'licencia_fecha_fin' => now()->addDays(30),
                'activo' => true,
            ]
        );

        $this->command->info('✅ Usuarios creados.');
        $this->command->info("👤 Superadmin: yeahme200120@gmail.com / yesy2001");
        $this->command->info("👤 Vendedor: vendedor@pos.com / password123");
    }
}
