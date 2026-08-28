<!-- resources/js/views/Promociones.vue -->
<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h1 class="text-2xl font-bold">Promociones</h1>
            <button 
                @click="abrirModal()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"
            >
                + Nueva Promoción
            </button>
        </div>

        <!-- Filtros -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex flex-wrap gap-3">
                <div class="flex-1 min-w-[200px]">
                    <input 
                        v-model="filtros.search"
                        type="text"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Buscar promoción..."
                        @input="cargarPromociones"
                    />
                </div>
                <div class="flex gap-2">
                    <button 
                        @click="cargarPromociones"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
                    >
                        Buscar
                    </button>
                    <button 
                        @click="limpiarFiltros"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm"
                    >
                        Limpiar
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="cargando" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando promociones...
        </div>

        <!-- Tabla -->
        <div v-else-if="promociones.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No hay promociones registradas
        </div>

        <div v-else class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Vigencia</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Usos</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="promocion in promociones" :key="promocion.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium">{{ promocion.nombre }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 text-xs rounded-full" 
                                      :class="promocion.tipo === 'porcentaje' ? 'bg-blue-100 text-blue-800' :
                                             promocion.tipo === 'monto_fijo' ? 'bg-green-100 text-green-800' :
                                             promocion.tipo === '2x1' ? 'bg-yellow-100 text-yellow-800' :
                                             'bg-purple-100 text-purple-800'">
                                    {{ tipos[promocion.tipo] || promocion.tipo }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                {{ promocion.tipo === 'porcentaje' ? promocion.valor + '%' : '$' + formatearNumero(promocion.valor) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span class="text-xs">
                                    {{ formatearFecha(promocion.fecha_inicio) }}
                                    <br>
                                    <span class="text-gray-400">→</span>
                                    {{ formatearFecha(promocion.fecha_fin) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                {{ promocion.usos_actuales || 0 }}
                                <span v-if="promocion.uso_maximo" class="text-gray-400 text-xs">
                                    / {{ promocion.uso_maximo }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span class="px-2 py-1 text-xs rounded-full" 
                                      :class="promocion.activo && promocion.esta_activa ? 'bg-green-100 text-green-800' :
                                             promocion.activo && !promocion.esta_activa ? 'bg-yellow-100 text-yellow-800' :
                                             'bg-red-100 text-red-800'">
                                    {{ promocion.activo && promocion.esta_activa ? 'Activa' :
                                       promocion.activo && !promocion.esta_activa ? 'Programada' :
                                       'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <button @click="abrirModal(promocion)" class="text-blue-600 hover:text-blue-800 mr-2">
                                    ✏️
                                </button>
                                <button @click="eliminarPromocion(promocion.id)" class="text-red-600 hover:text-red-800">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="paginacion && paginacion.last_page > 1" class="px-4 py-3 border-t flex flex-col sm:flex-row justify-between items-center gap-2">
                <span class="text-sm text-gray-600">
                    Mostrando {{ promociones.length }} de {{ paginacion.total || promociones.length }}
                </span>
                <div class="flex gap-1">
                    <button 
                        v-if="paginacion.current_page > 1"
                        @click="cambiarPagina(paginacion.current_page - 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 text-sm"
                    >
                        Anterior
                    </button>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                        {{ paginacion.current_page || 1 }}
                    </span>
                    <button 
                        v-if="paginacion.current_page < paginacion.last_page"
                        @click="cambiarPagina(paginacion.current_page + 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 text-sm"
                    >
                        Siguiente
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-bold mb-4">{{ editando ? 'Editar' : 'Nueva' }} Promoción</h2>
                
                <form @submit.prevent="guardarPromocion">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Nombre *</label>
                            <input v-model="form.nombre" type="text" class="w-full px-3 py-2 border rounded-lg" required />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Descripción</label>
                            <textarea v-model="form.descripcion" class="w-full px-3 py-2 border rounded-lg" rows="2"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo *</label>
                            <select v-model="form.tipo" class="w-full px-3 py-2 border rounded-lg">
                                <option value="porcentaje">Porcentaje</option>
                                <option value="monto_fijo">Monto Fijo</option>
                                <option value="2x1">2x1</option>
                                <option value="producto_gratis">Producto Gratis</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Valor *</label>
                            <input v-model="form.valor" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha Inicio *</label>
                            <input v-model="form.fecha_inicio" type="date" class="w-full px-3 py-2 border rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha Fin *</label>
                            <input v-model="form.fecha_fin" type="date" class="w-full px-3 py-2 border rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Monto Mínimo</label>
                            <input v-model="form.monto_minimo" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Usos Máximos</label>
                            <input v-model="form.uso_maximo" type="number" class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Aplica a</label>
                            <select v-model="form.aplica_a" class="w-full px-3 py-2 border rounded-lg">
                                <option value="todos">Todos los productos</option>
                                <option value="producto">Productos específicos</option>
                            </select>
                        </div>
                        <div v-if="form.aplica_a === 'producto'" class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Productos</label>
                            <select v-model="form.productos" multiple class="w-full px-3 py-2 border rounded-lg h-32">
                                <option v-for="producto in productos" :key="producto.id" :value="producto.id">
                                    {{ producto.nombre }} ({{ producto.codigo }})
                                </option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Ctrl+Click para seleccionar múltiples</p>
                        </div>
                        <div class="flex items-center md:col-span-2">
                            <input v-model="form.activo" type="checkbox" class="mr-2" />
                            <label class="text-sm font-medium text-gray-700">Activo</label>
                        </div>
                    </div>

                    <div v-if="error" class="mt-3 p-2 bg-red-100 text-red-700 rounded text-sm">{{ error }}</div>

                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" @click="cerrarModal" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="guardando" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            {{ guardando ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../axios';
import Swal from 'sweetalert2';

export default {
    name: 'Promociones',
    data() {
        return {
            promociones: [],
            productos: [],
            paginacion: null,
            cargando: false,
            guardando: false,
            error: null,
            modalVisible: false,
            editando: false,
            filtros: { search: '' },
            tipos: {
                porcentaje: '% Descuento',
                monto_fijo: 'Monto Fijo',
                '2x1': '2x1',
                producto_gratis: 'Producto Gratis'
            },
            form: {
                id: null,
                nombre: '',
                descripcion: '',
                tipo: 'porcentaje',
                valor: 0,
                fecha_inicio: '',
                fecha_fin: '',
                monto_minimo: 0,
                uso_maximo: null,
                aplica_a: 'todos',
                productos: [],
                activo: true
            }
        };
    },
    mounted() {
        this.cargarPromociones();
        this.cargarProductos();
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
                return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
            } catch {
                return fecha;
            }
        },
        async cargarPromociones() {
            this.cargando = true;
            try {
                const params = {};
                if (this.filtros.search) params.search = this.filtros.search;
                
                const res = await api.get('/promociones', { params });
                this.promociones = res.data.data || [];
                
                // Calcular estado de vigencia
                this.promociones.forEach(p => {
                    p.esta_activa = new Date(p.fecha_inicio) <= new Date() && new Date(p.fecha_fin) >= new Date();
                });
                
                this.paginacion = {
                    current_page: res.data.current_page || 1,
                    last_page: res.data.last_page || 1,
                    total: res.data.total || this.promociones.length
                };
            } catch (error) {
                console.error('Error cargando promociones:', error);
            } finally {
                this.cargando = false;
            }
        },
        async cargarProductos() {
            try {
                const res = await api.get('/productos?per_page=100&activo=1');
                this.productos = res.data.data || [];
            } catch (error) {
                console.error('Error cargando productos:', error);
            }
        },
        async cambiarPagina(page) {
            this.cargando = true;
            try {
                const res = await api.get('/promociones', { params: { page, ...this.filtros } });
                this.promociones = res.data.data || [];
                this.paginacion.current_page = page;
            } catch (error) {
                console.error('Error cargando página:', error);
            } finally {
                this.cargando = false;
            }
        },
        limpiarFiltros() {
            this.filtros.search = '';
            this.cargarPromociones();
        },
        abrirModal(promocion = null) {
            if (promocion) {
                this.editando = true;
                this.form = { 
                    ...promocion,
                    productos: promocion.productos?.map(p => p.id) || []
                };
            } else {
                this.editando = false;
                this.form = {
                    id: null,
                    nombre: '',
                    descripcion: '',
                    tipo: 'porcentaje',
                    valor: 0,
                    fecha_inicio: '',
                    fecha_fin: '',
                    monto_minimo: 0,
                    uso_maximo: null,
                    aplica_a: 'todos',
                    productos: [],
                    activo: true
                };
            }
            this.error = null;
            this.modalVisible = true;
        },
        cerrarModal() {
            this.modalVisible = false;
            this.error = null;
        },
        async guardarPromocion() {
            this.guardando = true;
            this.error = null;

            try {
                const data = { ...this.form };
                data.valor = parseFloat(data.valor) || 0;
                data.monto_minimo = parseFloat(data.monto_minimo) || 0;
                data.uso_maximo = data.uso_maximo ? parseInt(data.uso_maximo) : null;

                let response;
                if (this.editando) {
                    response = await api.put(`/promociones/${this.form.id}`, data);
                } else {
                    response = await api.post('/promociones', data);
                }

                this.cerrarModal();
                await this.cargarPromociones();

                Swal.fire({
                    icon: 'success',
                    title: this.editando ? 'Promoción actualizada' : 'Promoción creada',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (error) {
                this.error = error.response?.data?.message || 'Error al guardar';
            } finally {
                this.guardando = false;
            }
        },
        async eliminarPromocion(id) {
            const result = await Swal.fire({
                title: '¿Eliminar promoción?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    await api.delete(`/promociones/${id}`);
                    await this.cargarPromociones();
                    Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1500, showConfirmButton: false });
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al eliminar' });
                }
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