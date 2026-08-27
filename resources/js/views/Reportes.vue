<!-- resources/js/views/Reportes.vue -->
<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">Reportes</h1>
        
        <!-- Filtros -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
                    <input 
                        type="date" 
                        v-model="filtros.fecha_desde"
                        class="w-full px-3 py-2 border rounded-lg"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
                    <input 
                        type="date" 
                        v-model="filtros.fecha_hasta"
                        class="w-full px-3 py-2 border rounded-lg"
                    />
                </div>
                <div class="flex items-end">
                    <button 
                        @click="cargarReportes"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        Buscar
                    </button>
                    <button 
                        @click="exportarReportes"
                        class="ml-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                    >
                        Exportar
                    </button>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div v-if="cargando" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando reportes...
        </div>

        <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded-lg">
            {{ error }}
        </div>

        <div v-else-if="reportes.length === 0" class="bg-yellow-100 text-yellow-700 p-4 rounded-lg">
            No hay datos para mostrar
        </div>

        <div v-else>
            <!-- Tarjetas de resumen -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <p class="text-sm text-gray-500">Total Ventas</p>
                    <h3 class="text-2xl font-bold">{{ formatearNumero(resumen.total_ventas || 0) }}</h3>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <p class="text-sm text-gray-500">Número de Tickets</p>
                    <h3 class="text-2xl font-bold">{{ resumen.numero_tickets || 0 }}</h3>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <p class="text-sm text-gray-500">Ticket Promedio</p>
                    <h3 class="text-2xl font-bold">${{ formatearNumero(resumen.ticket_promedio || 0) }}</h3>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <p class="text-sm text-gray-500">Total Productos</p>
                    <h3 class="text-2xl font-bold">{{ resumen.total_productos || 0 }}</h3>
                </div>
            </div>

            <!-- Tabla de reportes -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Folio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="venta in reportes" :key="venta.id">
                            <td class="px-6 py-4">{{ venta.folio }}</td>
                            <td class="px-6 py-4">{{ formatearFecha(venta.fecha) }}</td>
                            <td class="px-6 py-4">{{ venta.cliente?.nombre || 'Cliente genérico' }}</td>
                            <td class="px-6 py-4">{{ venta.usuario?.name || '-' }}</td>
                            <td class="px-6 py-4 text-right font-semibold">
                                ${{ formatearNumero(venta.total) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-1 text-xs rounded-full" 
                                      :class="venta.estado === 'pagado' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                    {{ venta.estado }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="paginacion" class="flex justify-between items-center mt-4">
                <span class="text-sm text-gray-600">
                    Mostrando {{ reportes.length }} de {{ paginacion.total || reportes.length }}
                </span>
                <div class="flex space-x-2">
                    <button 
                        v-if="paginacion.current_page > 1"
                        @click="cargarPagina(paginacion.current_page - 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100"
                    >
                        Anterior
                    </button>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded">
                        {{ paginacion.current_page || 1 }}
                    </span>
                    <button 
                        v-if="paginacion.current_page < paginacion.last_page"
                        @click="cargarPagina(paginacion.current_page + 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100"
                    >
                        Siguiente
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../axios';

export default {
    name: 'Reportes',
    data() {
        return {
            reportes: [],
            resumen: {
                total_ventas: 0,
                numero_tickets: 0,
                ticket_promedio: 0,
                total_productos: 0
            },
            paginacion: null,
            cargando: false,
            error: null,
            filtros: {
                fecha_desde: '',
                fecha_hasta: ''
            }
        };
    },
    mounted() {
        // Establecer fechas por defecto (últimos 30 días)
        const hoy = new Date();
        const hace30Dias = new Date();
        hace30Dias.setDate(hoy.getDate() - 30);
        
        this.filtros.fecha_hasta = hoy.toISOString().split('T')[0];
        this.filtros.fecha_desde = hace30Dias.toISOString().split('T')[0];
        
        this.cargarReportes();
    },
    methods: {
        formatearNumero(valor) {
            if (valor === undefined || valor === null) return '0.00';
            const num = typeof valor === 'string' ? parseFloat(valor) : valor;
            if (isNaN(num)) return '0.00';
            return num.toFixed(2);
        },
        formatearFecha(fecha) {
            if (!fecha) return '-';
            try {
                const d = new Date(fecha);
                return d.toLocaleDateString('es-MX', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch {
                return fecha;
            }
        },
        async cargarReportes() {
            this.cargando = true;
            this.error = null;

            try {
                const params = {};
                if (this.filtros.fecha_desde) {
                    params.fecha_desde = this.filtros.fecha_desde;
                }
                if (this.filtros.fecha_hasta) {
                    params.fecha_hasta = this.filtros.fecha_hasta;
                }

                const response = await api.get('/admin/reportes', { params });
                
                // Manejar respuesta con paginación
                if (response.data.data) {
                    this.reportes = response.data.data || [];
                    this.paginacion = {
                        current_page: response.data.current_page || 1,
                        last_page: response.data.last_page || 1,
                        total: response.data.total || 0
                    };
                    this.resumen = {
                        total_ventas: response.data.total_ventas || 0,
                        numero_tickets: response.data.numero_tickets || 0,
                        ticket_promedio: response.data.ticket_promedio || 0,
                        total_productos: this.reportes.reduce((sum, v) => sum + (v.detalles?.length || 0), 0)
                    };
                } else {
                    // Si la respuesta es directa
                    this.reportes = Array.isArray(response.data) ? response.data : [];
                    this.paginacion = null;
                    this.resumen = {
                        total_ventas: this.reportes.length,
                        numero_tickets: this.reportes.length,
                        ticket_promedio: this.reportes.reduce((sum, v) => sum + (parseFloat(v.total) || 0), 0) / (this.reportes.length || 1),
                        total_productos: this.reportes.reduce((sum, v) => sum + (v.detalles?.length || 0), 0)
                    };
                }
            } catch (error) {
                console.error('Error cargando reportes:', error);
                this.error = error.response?.data?.message || 'Error al cargar los reportes';
            } finally {
                this.cargando = false;
            }
        },
        async cargarPagina(page) {
            this.cargando = true;
            try {
                const params = { page, ...this.filtros };
                const response = await api.get('/admin/reportes', { params });
                this.reportes = response.data.data || [];
                this.paginacion = {
                    current_page: response.data.current_page || 1,
                    last_page: response.data.last_page || 1,
                    total: response.data.total || 0
                };
            } catch (error) {
                console.error('Error cargando página:', error);
            } finally {
                this.cargando = false;
            }
        },
        async exportarReportes() {
            try {
                const params = {};
                if (this.filtros.fecha_desde) {
                    params.fecha_desde = this.filtros.fecha_desde;
                }
                if (this.filtros.fecha_hasta) {
                    params.fecha_hasta = this.filtros.fecha_hasta;
                }
                
                const response = await api.get('/admin/reportes/exportar', { 
                    params,
                    responseType: 'blob'
                });
                
                // Descargar archivo
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', `reportes_${new Date().toISOString().split('T')[0]}.xlsx`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('Error exportando:', error);
                alert('Error al exportar reportes');
            }
        }
    }
};
</script>

<style scoped>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

</style>