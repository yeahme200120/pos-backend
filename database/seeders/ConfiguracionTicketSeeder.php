<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionTicket;
use App\Models\Empresa;

class ConfiguracionTicketSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ No hay empresa. Ejecuta EmpresaSeeder primero.');
            return;
        }

        ConfiguracionTicket::firstOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'papel' => '58mm',
                'fuente' => 'Arial',
                'tamano_fuente' => 12,
                'alineacion' => 'izquierda',
                'mostrar_logo' => true,
                'mostrar_qr' => true,
                'qr_contenido' => 'https://miempresa.com',
                'campos' => json_encode([
                    ['nombre' => 'nombre_negocio', 'visible' => true, 'orden' => 1],
                    ['nombre' => 'direccion', 'visible' => true, 'orden' => 2],
                    ['nombre' => 'telefono', 'visible' => true, 'orden' => 3],
                    ['nombre' => 'fecha', 'visible' => true, 'orden' => 4],
                    ['nombre' => 'productos', 'visible' => true, 'orden' => 5],
                    ['nombre' => 'total', 'visible' => true, 'orden' => 6],
                ]),
                'cabecera' => '¡Gracias por su compra!',
                'pie_pagina' => 'Visítenos en www.miempresa.com',
            ]
        );

        $this->command->info('✅ Configuración de ticket creada.');
    }
}