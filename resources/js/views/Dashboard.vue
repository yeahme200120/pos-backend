<!-- resources/js/views/Dashboard.vue -->
<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">Dashboard</h1>
        
        <!-- Loading -->
        <div v-if="cargando" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando estadísticas...
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded-lg">
            {{ error }}
        </div>

        <!-- Tarjetas -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div v-for="card in cards" :key="card.title" class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-500 text-sm">{{ card.title }}</p>
                        <h3 class="text-2xl font-bold">{{ card.value }}</h3>
                        <p v-if="card.subtitle" class="text-xs text-gray-400 mt-1">{{ card.subtitle }}</p>
                    </div>
                    <div class="p-3 rounded-full" :class="card.color">
                        <i :class="card.icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de ventas por hora -->
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Ventas por Hora (Hoy)</h3>
            <div class="h-64">
                <canvas ref="ventasChart"></canvas>
            </div>
        </div>

        <!-- Ventas recientes -->
        <div v-if="!cargando && !error && ventasRecientes && ventasRecientes.length > 0" class="mt-6 bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Ventas Recientes</h3>
                <router-link to="/ventas" class="text-blue-600 hover:text-blue-800 text-sm">
                    Ver todas →
                </router-link>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Folio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="venta in ventasRecientes" :key="venta.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm">{{ venta.folio }}</td>
                            <td class="px-6 py-4 text-sm">{{ venta.cliente?.nombre || 'Cliente genérico' }}</td>
                            <td class="px-6 py-4 text-sm">{{ formatDate(venta.fecha) }}</td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">${{ formatNumber(venta.total) }}</td>
                            <td class="px-6 py-4 text-sm text-center">
                                <span class="px-2 py-1 text-xs rounded-full" 
                                      :class="venta.estado === 'pagado' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                    {{ venta.estado }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../axios';
// ✅ Importar Chart.js correctamente
import { Chart, registerables } from 'chart.js';
// Registrar todos los componentes de Chart.js
Chart.register(...registerables);

export default {
    name: 'Dashboard',
    data() {
        return {
            cards: [],
            ventasPorHora: [],
            ventasRecientes: [],
            cargando: true,
            error: null,
            chartInstance: null
        };
    },
    mounted() {
        this.cargarDashboard();
    },
    methods: {
        formatNumber(value) {
            if (value === undefined || value === null) return '0.00';
            const num = typeof value === 'string' ? parseFloat(value) : value;
            if (isNaN(num)) return '0.00';
            return num.toFixed(2);
        },
        formatDate(date) {
            if (!date) return '-';
            const d = new Date(date);
            return d.toLocaleDateString('es-MX', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        async cargarDashboard() {
            this.cargando = true;
            this.error = null;

            try {
                const response = await api.get('/dashboard');
                console.log('📊 Datos del dashboard:', response.data);
                
                const data = response.data.data;

                // Actualizar tarjetas
                this.cards = [
                    {
                        title: 'Ventas Hoy',
                        value: `$${this.formatNumber(data.hoy?.total || 0)}`,
                        subtitle: `${data.hoy?.ventas || 0} tickets`,
                        icon: 'fas fa-shopping-bag',
                        color: 'bg-blue-100 text-blue-600'
                    },
                    {
                        title: 'Usuarios',
                        value: data.usuarios?.total || 0,
                        subtitle: `${data.usuarios?.activos || 0} activos`,
                        icon: 'fas fa-users',
                        color: 'bg-green-100 text-green-600'
                    },
                    {
                        title: 'Productos',
                        value: data.inventario?.total_productos || 0,
                        subtitle: `${data.inventario?.stock_bajo || 0} con stock bajo`,
                        icon: 'fas fa-box',
                        color: 'bg-yellow-100 text-yellow-600'
                    },
                    {
                        title: 'Clientes',
                        value: data.clientes?.total || 0,
                        subtitle: `${data.clientes?.activos || 0} activos`,
                        icon: 'fas fa-user-friends',
                        color: 'bg-purple-100 text-purple-600'
                    }
                ];

                // Ventas por hora
                let ventasPorHoraData = data.ventas_por_hora || [];
                
                // Si es un objeto, convertirlo a array
                if (typeof ventasPorHoraData === 'object' && !Array.isArray(ventasPorHoraData)) {
                    this.ventasPorHora = Object.keys(ventasPorHoraData).map(key => ({
                        hora: key,
                        total: ventasPorHoraData[key]?.total || 0,
                        cantidad: ventasPorHoraData[key]?.cantidad || 0
                    }));
                } else {
                    this.ventasPorHora = ventasPorHoraData;
                }

                // Ventas recientes
                this.ventasRecientes = data.ventas_recientes || [];

                // Crear gráfico después de renderizar
                this.$nextTick(() => {
                    this.crearGrafico();
                });

            } catch (error) {
                console.error('❌ Error cargando dashboard:', error);
                this.error = error.response?.data?.message || 'Error al cargar el dashboard';
                
                // Mostrar tarjetas por defecto
                this.cards = [
                    { title: 'Ventas Hoy', value: '$0.00', subtitle: '0 tickets', icon: 'fas fa-shopping-bag', color: 'bg-blue-100 text-blue-600' },
                    { title: 'Usuarios', value: '0', subtitle: '0 activos', icon: 'fas fa-users', color: 'bg-green-100 text-green-600' },
                    { title: 'Productos', value: '0', subtitle: '0 con stock bajo', icon: 'fas fa-box', color: 'bg-yellow-100 text-yellow-600' },
                    { title: 'Clientes', value: '0', subtitle: '0 activos', icon: 'fas fa-user-friends', color: 'bg-purple-100 text-purple-600' }
                ];
            } finally {
                this.cargando = false;
            }
        },
        crearGrafico() {
            try {
                // Destruir gráfico anterior si existe
                if (this.chartInstance) {
                    this.chartInstance.destroy();
                    this.chartInstance = null;
                }

                const canvas = this.$refs.ventasChart;
                if (!canvas) {
                    console.warn('Canvas no encontrado');
                    return;
                }

                const ctx = canvas.getContext('2d');

                // Preparar datos para el gráfico
                let labels = [];
                let dataValues = [];

                if (this.ventasPorHora && this.ventasPorHora.length > 0) {
                    labels = this.ventasPorHora.map(item => item.hora || '');
                    dataValues = this.ventasPorHora.map(item => item.total || 0);
                }

                // Si no hay datos, mostrar 24 horas con ceros
                if (dataValues.length === 0) {
                    const horas = [];
                    for (let i = 0; i < 24; i++) {
                        horas.push(`${String(i).padStart(2, '0')}:00`);
                    }
                    labels = horas;
                    dataValues = Array(24).fill(0);
                }

                console.log('📊 Datos del gráfico:', { labels, dataValues });

                // ✅ Crear gráfico con Chart.js
                this.chartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Ventas ($)',
                            data: dataValues,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: '#3B82F6'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    color: '#6B7280'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value;
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxTicksLimit: 12
                                }
                            }
                        }
                    }
                });

                console.log('✅ Gráfico creado correctamente');
            } catch (error) {
                console.error('❌ Error al crear gráfico:', error);
            }
        }
    },
    beforeUnmount() {
        if (this.chartInstance) {
            this.chartInstance.destroy();
            this.chartInstance = null;
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