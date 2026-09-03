<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Verificar empresas
        |--------------------------------------------------------------------------
        */

        $empresa1 = Empresa::find(1);
        $empresa2 = Empresa::find(2);
        $empresa3 = Empresa::find(3);

        if (!$empresa1 || !$empresa2 || !$empresa3) {
            $this->command->error(
                '❌ No existen las 3 empresas requeridas.'
            );

            $this->command->error(
                'Ejecuta primero: php artisan db:seed --class=EmpresaSeeder'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Contraseña única para todos los usuarios de prueba
        |--------------------------------------------------------------------------
        */

        $password = Hash::make('prueba2026');

        /*
        |--------------------------------------------------------------------------
        | Usuario 1 - SUPERADMIN
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'id' => 1,
            ],
            [
                'name' => 'Ivan Alejandro Hernandez',
                'email' => 'yeahme200120@gmail.com',
                'password' => $password,
                'telefono' => '555-1234',
                'numero_usuario' => 1000000001,
                'empresa_id' => $empresa1->id,
                'rol' => 'superadmin',
                'activo' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Usuario 2 - VENDEDOR
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'id' => 2,
            ],
            [
                'name' => 'Vendedor Juan',
                'email' => 'vendedor@pos.com',
                'password' => $password,
                'telefono' => '555-5678',
                'numero_usuario' => 1000000002,
                'empresa_id' => $empresa1->id,
                'rol' => 'vendedor',
                'activo' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Usuario 3 - VENDEDOR
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'id' => 3,
            ],
            [
                'name' => 'Jose Paz Duran Martinez',
                'email' => 'josepaz@gmail.com',
                'password' => $password,
                'telefono' => '7355298275',
                'numero_usuario' => 1000000003,
                'empresa_id' => $empresa2->id,
                'rol' => 'vendedor',
                'activo' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Usuario 4 - VENDEDOR
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'id' => 4,
            ],
            [
                'name' => 'Jose Paz Duran Martinez',
                'email' => 'josepazd@gmail.com',
                'password' => $password,
                'telefono' => '7355298275',
                'numero_usuario' => 1000000004,
                'empresa_id' => $empresa2->id,
                'rol' => 'vendedor',
                'activo' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Usuario 5 - VENDEDOR
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'id' => 5,
            ],
            [
                'name' => 'Daniel Rivera',
                'email' => 'daniel@gmail.com',
                'password' => $password,
                'telefono' => '7351732085',
                'numero_usuario' => 1000000005,
                'empresa_id' => $empresa2->id,
                'rol' => 'vendedor',
                'activo' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Usuario 6 - VENDEDOR
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'id' => 6,
            ],
            [
                'name' => 'Venderor',
                'email' => 'elviejon@gmail.com',
                'password' => $password,
                'telefono' => null,
                'numero_usuario' => 1000000006,
                'empresa_id' => $empresa3->id,
                'rol' => 'vendedor',
                'activo' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Mensajes
        |--------------------------------------------------------------------------
        */

        $this->command->info('');
        $this->command->info('✅ Usuarios de prueba creados/actualizados correctamente.');
        $this->command->info('');
        $this->command->info('🔐 Contraseña de TODOS los usuarios: prueba2026');
        $this->command->info('');

        $this->command->info(
            '👤 Superadmin: yeahme200120@gmail.com'
        );

        $this->command->info(
            '👤 Vendedor: vendedor@pos.com'
        );

        $this->command->info(
            '👤 Vendedor: josepaz@gmail.com'
        );

        $this->command->info(
            '👤 Vendedor: josepazd@gmail.com'
        );

        $this->command->info(
            '👤 Vendedor: daniel@gmail.com'
        );

        $this->command->info(
            '👤 Vendedor: elviejon@gmail.com'
        );

        $this->command->info('');
        $this->command->info('🏢 Empresa 1: Mi Empresa Prueba');
        $this->command->info('🏢 Empresa 2: Empresa prueba');
        $this->command->info('🏢 Empresa 3: Antojitos el Viejon');
        $this->command->info('');
    }
}