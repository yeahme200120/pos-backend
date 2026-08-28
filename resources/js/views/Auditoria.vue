<!-- resources/js/views/Auditoria.vue -->
<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h1 class="text-2xl font-bold">Auditoría</h1>
            <button 
                @click="exportarAuditoria"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm"
            >
                📊 Exportar
            </button>
        </div>

        <!-- Resumen -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <p class="text-sm text-gray-500">Total Registros</p>
                <p class="text-2xl font-bold text-blue-600">{{ resumen.total || 0 }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <p class="text-sm text-gray-500">Hoy</p>
                <p class="text-2xl font-bold text-green-600">{{ resumen.hoy || 0 }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <p class="text-sm text-gray-500">Acciones Distintas</p>
                <p class="text-2xl font-bold text-purple-600">{{ resumen.acciones?.length || 0 }}</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                    <select v-model="filtros.usuario_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">Todos</option>
                        <option v-for="user in usuarios" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Acción</label>
                    <input v-model="filtros.accion" type="text" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Buscar acción..." />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
                    <input v-model="filtros.fecha_desde" type="date" class="w-full px-3 py-2 border rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
                    <input v-model="filtros.fecha_hasta" type="date" class="w-full px-3 py-2 border rounded-lg text-sm" />
                </div>
            </div>
            <div class="mt-3 flex gap-2">
                <button @click="cargarAuditoria" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                    Buscar
                </button>
                <button @click="limpiarFiltros" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                    Limpiar
                </button>
            </div>
        </div>

        <!-- Tabla -->
        <div v-if="cargando" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando...
        </div>

        <div v-else-if="logs.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No hay registros de auditoría
        </div>

        <div v-else class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tabla</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Registro</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">IP</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">{{ formatearFecha(log.created_at) }}</td>
                            <td class="px-4 py-3 text-sm">{{ log.usuario?.name || 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 text-xs rounded-full" 
                                      :class="log.accion === 'crear' ? 'bg-green-100 text-green-800' :
                                             log.accion === 'actualizar' ? 'bg-yellow-100 text-yellow-800' :
                                             log.accion === 'eliminar' ? 'bg-red-100 text-red-800' :
                                             'bg-blue-100 text-blue-800'">
                                    {{ log.accion }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ log.tabla }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ log.registro_id || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ log.ip || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-center">
                                <button @click="verDetalle(log)" class="text-blue-600 hover:text-blue-800">
                                    👁️
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Detalle -->
        <div v-if="mostrarDetalle" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Detalle de Auditoría</h2>
                    <button @click="mostrarDetalle = false" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="font-semibold">ID:</span> {{ detalle.id }}</div>
                        <div><span class="font-semibold">Usuario:</span> {{ detalle.usuario?.name || 'N/A' }}</div>
                        <div><span class="font-semibold">Acción:</span> {{ detalle.accion }}</div>
                        <div><span class="font-semibold">Tabla:</span> {{ detalle.tabla }}</div>
                        <div><span class="font-semibold">Registro ID:</span> {{ detalle.registro_id || '-' }}</div>
                        <div><span class="font-semibold">IP:</span> {{ detalle.ip || '-' }}</div>
                        <div class="col-span-2"><span class="font-semibold">Fecha:</span> {{ formatearFecha(detalle.created_at) }}</div>
                    </div>
                    <div>
                        <span class="font-semibold">Datos Antes:</span>
                        <pre class="bg-gray-100 p-3 rounded text-sm overflow-auto max-h-40">{{ JSON.stringify(detalle.datos_antes, null, 2) }}</pre>
                    </div>
                    <div>
                        <span class="font-semibold">Datos Después:</span>
                        <pre class="bg-gray-100 p-3 rounded text-sm overflow-auto max-h-40">{{ JSON.stringify(detalle.datos_despues, null, 2) }}</pre>
                    </div>
                </div>
                <button @click="mostrarDetalle = false" class="mt-4 px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cerrar</button>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../axios';
import Swal from 'sweetalert2';

export default {
    name: 'Auditoria',
    data() {
        return {
            logs: [],
            usuarios: [],
            resumen: {},
            cargando: false,
            filtros: { usuario_id: '', accion: '', fecha_desde: '', fecha_hasta: '' },
            mostrarDetalle: false,
            detalle: null,
            paginacion: null
        };
    },
    mounted() {
        this.cargarUsuarios();
        this.cargarAuditoria();
    },
    methods: {
        formatearFecha(fecha) {
            if (!fecha) return '-';
            return new Date(fecha).toLocaleString('es-MX', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        async cargarUsuarios() {
            try {
                const res = await api.get('/admin/usuarios?per_page=100');
                this.usuarios = res.data.data || [];
            } catch (error) {
                console.error('Error cargando usuarios:', error);
            }
        },
        async cargarAuditoria() {
            this.cargando = true;
            try {
                const params = {};
                if (this.filtros.usuario_id) params.usuario_id = this.filtros.usuario_id;
                if (this.filtros.accion) params.accion = this.filtros.accion;
                if (this.filtros.fecha_desde) params.fecha_desde = this.filtros.fecha_desde;
                if (this.filtros.fecha_hasta) params.fecha_hasta = this.filtros.fecha_hasta;

                const res = await api.get('/auditoria', { params });
                this.logs = res.data.data?.data || [];
                this.paginacion = res.data.data;
                this.resumen = res.data.resumen || {};
            } catch (error) {
                console.error('Error cargando auditoría:', error);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al cargar auditoría' });
            } finally {
                this.cargando = false;
            }
        },
        limpiarFiltros() {
            this.filtros = { usuario_id: '', accion: '', fecha_desde: '', fecha_hasta: '' };
            this.cargarAuditoria();
        },
        verDetalle(log) {
            this.detalle = log;
            this.mostrarDetalle = true;
        },
        async exportarAuditoria() {
            try {
                const params = {};
                if (this.filtros.fecha_desde) params.fecha_desde = this.filtros.fecha_desde;
                if (this.filtros.fecha_hasta) params.fecha_hasta = this.filtros.fecha_hasta;

                const res = await api.get('/auditoria/exportar', { params });
                window.open(res.data.url, '_blank');
                Swal.fire({ icon: 'success', title: 'Exportación iniciada', timer: 2000, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al exportar auditoría' });
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