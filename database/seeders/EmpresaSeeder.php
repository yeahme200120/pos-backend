<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $fechaInicio = Carbon::now();
        $fechaFin = $fechaInicio->copy()->addMonth();

        $empresas = [
            [
                'id' => 1,
                'nombre' => 'Mi Empresa Prueba',
                'logo' => 'empresas/f31574e2-0309-446f-81c0-cfcd312af653.webp',

                'colores' => [
                    'primary' => '#022c6e',
                    'secondary' => '#108981',
                    'background' => '#F8FAFC',
                    'text' => '#0F172A',
                    'accent' => '#3B82F6',
                ],

                'configuracion' => [],

                'direccion' => 'Calle Falsa 123, Ciudad',
                'telefono' => '555-1234',
                'email_contacto' => 'admin@miempresa.com',

                'rfc' => 'ABCD123456XYZ',
                'razon_social' => null,

                'leyenda_ticket' => null,

                'whatsapp_numero' => null,
                'whatsapp_mensaje_default' => null,

                'activo' => true,

                // LICENCIA
                'licencia_tipo' => 'mes',
                'licencia_fecha_inicio' => $fechaInicio,
                'licencia_fecha_fin' => $fechaFin,
                'licencia_activa' => true,
                'licencia_ultima_validacion' => $fechaInicio,
            ],

            [
                'id' => 2,
                'nombre' => 'Empresa prueba',
                'logo' => 'empresas/b09817a9-b009-4aa4-81e7-c1a1a05e457f.webp',

                'colores' => [
                    'primary' => '#1E293B',
                    'secondary' => '#108981',
                    'background' => '#F8FAFC',
                    'text' => '#0F172A',
                    'accent' => '#3B82F6',
                ],

                'configuracion' => [],

                'direccion' => 'prueba',
                'telefono' => '9879879877',
                'email_contacto' => 'empresaprueba@gmail.com',

                'rfc' => 'PRUEBAXXX',
                'razon_social' => 'PRUEBAXXXX',

                'leyenda_ticket' => 'Gracias poor tu preferencia',

                'whatsapp_numero' => '9879879877',
                'whatsapp_mensaje_default' => null,

                'activo' => true,

                // LICENCIA
                'licencia_tipo' => 'mes',
                'licencia_fecha_inicio' => $fechaInicio,
                'licencia_fecha_fin' => $fechaFin,
                'licencia_activa' => true,
                'licencia_ultima_validacion' => $fechaInicio,
            ],

            [
                'id' => 3,
                'nombre' => 'Antojitos el Viejon',
                'logo' => null,

                'colores' => [
                    'primary' => '#1E293B',
                    'secondary' => '#108981',
                    'background' => '#F8FAFC',
                    'text' => '#0F172A',
                    'accent' => '#3B82F6',
                ],

                'configuracion' => [],

                'direccion' => null,
                'telefono' => null,
                'email_contacto' => null,

                'rfc' => 'EL VIEJON',
                'razon_social' => 'XXXXXXXXXXXX',

                'leyenda_ticket' => 'Gracias por su compra',

                'whatsapp_numero' => null,
                'whatsapp_mensaje_default' => null,

                'activo' => true,

                // LICENCIA
                'licencia_tipo' => 'mes',
                'licencia_fecha_inicio' => $fechaInicio,
                'licencia_fecha_fin' => $fechaFin,
                'licencia_activa' => true,
                'licencia_ultima_validacion' => $fechaInicio,
            ],
        ];

        foreach ($empresas as $datos) {
            Empresa::updateOrCreate(
                ['id' => $datos['id']],
                $datos
            );
        }
    }
}