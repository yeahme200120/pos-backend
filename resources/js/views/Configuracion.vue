<!-- resources/js/views/Configuracion.vue -->
<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">Configuración</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Configuración de Colores -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-semibold mb-4">Personalizar Colores</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Color Principal (Sidebar y Navbar)
                        </label>
                        <div class="flex items-center space-x-2">
                            <input 
                                type="color" 
                                v-model="config.colorPrincipal"
                                class="w-12 h-12 rounded cursor-pointer border"
                            />
                            <input 
                                type="text" 
                                v-model="config.colorPrincipal"
                                class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm"
                            />
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Este color se aplicará al sidebar y al navbar</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Color Secundario (Bordes)
                        </label>
                        <div class="flex items-center space-x-2">
                            <input 
                                type="color" 
                                v-model="config.colorSecundario"
                                class="w-12 h-12 rounded cursor-pointer border"
                            />
                            <input 
                                type="text" 
                                v-model="config.colorSecundario"
                                class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Color de Fondo
                        </label>
                        <div class="flex items-center space-x-2">
                            <input 
                                type="color" 
                                v-model="config.fondo"
                                class="w-12 h-12 rounded cursor-pointer border"
                            />
                            <input 
                                type="text" 
                                v-model="config.fondo"
                                class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Color de Texto
                        </label>
                        <div class="flex items-center space-x-2">
                            <input 
                                type="color" 
                                v-model="config.colorTexto"
                                class="w-12 h-12 rounded cursor-pointer border"
                            />
                            <input 
                                type="text" 
                                v-model="config.colorTexto"
                                class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm"
                            />
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Color del texto en sidebar y navbar</p>
                    </div>

                    <div class="flex space-x-2 pt-4">
                        <button 
                            @click="guardarConfiguracion" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                        >
                            Guardar Cambios
                        </button>
                        <button 
                            @click="restablecerConfiguracion" 
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                        >
                            Restablecer
                        </button>
                    </div>

                    <div v-if="mensaje" class="p-3 rounded-lg" :class="mensajeClase">
                        {{ mensaje }}
                    </div>
                </div>
            </div>

            <!-- Configuración del Ticket -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-semibold mb-4">Configuración del Ticket</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tamaño de Papel
                        </label>
                        <select 
                            v-model="ticketConfig.papel"
                            class="w-full px-3 py-2 border rounded-lg"
                        >
                            <option value="58mm">58mm</option>
                            <option value="80mm">80mm</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Fuente
                        </label>
                        <select 
                            v-model="ticketConfig.fuente"
                            class="w-full px-3 py-2 border rounded-lg"
                        >
                            <option value="Arial">Arial</option>
                            <option value="Courier">Courier</option>
                            <option value="Times New Roman">Times New Roman</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tamaño de Fuente
                        </label>
                        <input 
                            type="number" 
                            v-model="ticketConfig.tamano_fuente"
                            class="w-full px-3 py-2 border rounded-lg"
                            min="8"
                            max="30"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Cabecera del Ticket
                        </label>
                        <input 
                            type="text" 
                            v-model="ticketConfig.cabecera"
                            class="w-full px-3 py-2 border rounded-lg"
                            placeholder="¡Gracias por su compra!"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Pie de Página
                        </label>
                        <input 
                            type="text" 
                            v-model="ticketConfig.pie_pagina"
                            class="w-full px-3 py-2 border rounded-lg"
                            placeholder="Visítenos en www.miempresa.com"
                        />
                    </div>

                    <button 
                        @click="guardarTicketConfig" 
                        class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                    >
                        Guardar Configuración del Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Swal from 'sweetalert2';

export default {
    name: 'Configuracion',
    data() {
        return {
            config: {
                colorPrincipal: '#1E293B',
                colorSecundario: '#374151',
                fondo: '#f3f4f6',
                colorTexto: '#FFFFFF'
            },
            ticketConfig: {
                papel: '58mm',
                fuente: 'Arial',
                tamano_fuente: 12,
                cabecera: '¡Gracias por su compra!',
                pie_pagina: 'Visítenos en www.miempresa.com'
            },
            mensaje: '',
            mensajeClase: ''
        };
    },
    mounted() {
        this.cargarConfiguracion();
        this.cargarTicketConfig();
    },
    methods: {
        cargarConfiguracion() {
            const keys = ['colorPrincipal', 'colorSecundario', 'fondo', 'colorTexto'];
            keys.forEach(key => {
                const value = localStorage.getItem(key);
                if (value) {
                    this.config[key] = value;
                }
            });
        },
        cargarTicketConfig() {
            try {
                const saved = localStorage.getItem('ticket_config');
                if (saved) {
                    this.ticketConfig = JSON.parse(saved);
                }
            } catch (e) {
                console.error('Error cargando ticket config:', e);
            }
        },
        guardarConfiguracion() {
            try {
                // Guardar en localStorage
                Object.keys(this.config).forEach(key => {
                    localStorage.setItem(key, this.config[key]);
                });

                // Disparar evento para actualizar App.vue
                window.dispatchEvent(new CustomEvent('recargar-colores', {
                    detail: this.config
                }));

                // También disparar evento storage para otras pestañas
                window.dispatchEvent(new Event('storage'));

                this.mensaje = '✅ Configuración guardada correctamente';
                this.mensajeClase = 'bg-green-100 text-green-700';
                
                // Recargar la página para aplicar cambios
                setTimeout(() => {
                    window.location.reload();
                }, 1000);

            } catch (error) {
                this.mensaje = '❌ Error al guardar configuración';
                this.mensajeClase = 'bg-red-100 text-red-700';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al guardar configuración'
                });
            }
        },
        restablecerConfiguracion() {
            const defaults = {
                colorPrincipal: '#1E293B',
                colorSecundario: '#374151',
                fondo: '#f3f4f6',
                colorTexto: '#FFFFFF'
            };
            
            Object.keys(defaults).forEach(key => {
                this.config[key] = defaults[key];
                localStorage.setItem(key, defaults[key]);
            });

            window.dispatchEvent(new CustomEvent('recargar-colores', {
                detail: defaults
            }));
            window.dispatchEvent(new Event('storage'));

            this.mensaje = '🔄 Configuración restablecida';
            this.mensajeClase = 'bg-yellow-100 text-yellow-700';
            
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        },
        guardarTicketConfig() {
            try {
                localStorage.setItem('ticket_config', JSON.stringify(this.ticketConfig));
                
                this.mensaje = '✅ Configuración del ticket guardada';
                this.mensajeClase = 'bg-green-100 text-green-700';
                
                setTimeout(() => {
                    this.mensaje = '';
                }, 3000);

                Swal.fire({
                    icon: 'success',
                    title: 'Configuración guardada',
                    text: 'Configuración del ticket guardada correctamente',
                    timer: 2000,
                    showConfirmButton: false
                });
            } catch (error) {
                this.mensaje = '❌ Error al guardar configuración del ticket';
                this.mensajeClase = 'bg-red-100 text-red-700';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al guardar configuración del ticket'
                });
            }
        }
    }
};
</script>