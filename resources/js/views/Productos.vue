<!-- resources/js/views/Productos.vue -->
<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Productos</h1>
            <button 
                @click="abrirModal()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
                + Nuevo Producto
            </button>
        </div>

        <!-- Barra de búsqueda -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex gap-4">
                <input 
                    v-model="filtros.search"
                    @keyup.enter="cargarProductos"
                    type="text"
                    class="flex-1 px-3 py-2 border rounded-lg"
                    placeholder="Buscar por nombre o código..."
                />
                <button 
                    @click="cargarProductos"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                >
                    Buscar
                </button>
            </div>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Precio</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stock</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="cargando">
                        <td colspan="6" class="px-6 py-4 text-center">Cargando...</td>
                    </tr>
                    <tr v-else-if="productos.length === 0">
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No hay productos</td>
                    </tr>
                    <tr v-for="producto in productos" :key="producto.id">
                        <td class="px-6 py-4">{{ producto.codigo }}</td>
                        <td class="px-6 py-4">{{ producto.nombre }}</td>
                        <td class="px-6 py-4 text-right">${{ formatearNumero(producto.precio) }}</td>
                        <td class="px-6 py-4 text-right">
                            <span :class="producto.stock <= producto.stock_minimo ? 'text-red-600 font-bold' : ''">
                                {{ producto.stock }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 text-xs rounded-full" 
                                  :class="producto.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                {{ producto.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button @click="abrirModal(producto)" class="text-blue-600 hover:text-blue-800 mr-2">
                                Editar
                            </button>
                            <button @click="eliminarProducto(producto.id)" class="text-red-600 hover:text-red-800">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-bold mb-4">{{ editando ? 'Editar' : 'Nuevo' }} Producto</h2>
                
                <form @submit.prevent="guardarProducto">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Código</label>
                            <input 
                                v-model="form.codigo"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input 
                                v-model="form.nombre"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Precio</label>
                            <input 
                                v-model="form.precio"
                                type="number"
                                step="0.01"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Costo</label>
                            <input 
                                v-model="form.costo"
                                type="number"
                                step="0.01"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock</label>
                            <input 
                                v-model="form.stock"
                                type="number"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock Mínimo</label>
                            <input 
                                v-model="form.stock_minimo"
                                type="number"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Impuesto (%)</label>
                            <input 
                                v-model="form.impuesto"
                                type="number"
                                step="0.01"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>
                        <div class="flex items-center">
                            <input 
                                v-model="form.activo"
                                type="checkbox"
                                class="mr-2"
                            />
                            <label class="text-sm font-medium text-gray-700">Activo</label>
                        </div>
                    </div>

                    <div v-if="error" class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                        {{ error }}
                    </div>

                    <div class="flex justify-end space-x-2 mt-6">
                        <button 
                            type="button"
                            @click="cerrarModal"
                            class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            :disabled="guardando"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
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

export default {
    name: 'Productos',
    data() {
        return {
            productos: [],
            cargando: false,
            guardando: false,
            error: null,
            modalVisible: false,
            editando: false,
            form: {
                id: null,
                codigo: '',
                nombre: '',
                precio: 0,
                costo: 0,
                stock: 0,
                stock_minimo: 0,
                impuesto: 0,
                activo: true
            },
            filtros: {
                search: ''
            }
        };
    },
    mounted() {
        this.cargarProductos();
    },
    methods: {
        formatearNumero(valor) {
            if (valor === undefined || valor === null) return '0.00';
            const num = typeof valor === 'string' ? parseFloat(valor) : valor;
            if (isNaN(num)) return '0.00';
            return num.toFixed(2);
        },
        async cargarProductos() {
            this.cargando = true;
            this.error = null;
            try {
                const params = {};
                if (this.filtros.search) {
                    params.search = this.filtros.search;
                }
                const response = await api.get('/productos', { params });
                this.productos = response.data.data || [];
            } catch (error) {
                console.error('Error cargando productos:', error);
                this.error = error.response?.data?.message || 'Error al cargar productos';
            } finally {
                this.cargando = false;
            }
        },
        abrirModal(producto = null) {
            if (producto) {
                this.editando = true;
                this.form = { ...producto };
            } else {
                this.editando = false;
                this.form = {
                    id: null,
                    codigo: '',
                    nombre: '',
                    precio: 0,
                    costo: 0,
                    stock: 0,
                    stock_minimo: 0,
                    impuesto: 0,
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
        async guardarProducto() {
            this.guardando = true;
            this.error = null;

            try {
                // Preparar datos
                const datos = {
                    codigo: this.form.codigo,
                    nombre: this.form.nombre,
                    precio: parseFloat(this.form.precio) || 0,
                    costo: parseFloat(this.form.costo) || 0,
                    stock: parseInt(this.form.stock) || 0,
                    stock_minimo: parseInt(this.form.stock_minimo) || 0,
                    impuesto: parseFloat(this.form.impuesto) || 0,
                    activo: this.form.activo !== undefined ? this.form.activo : true
                };

                let response;
                if (this.editando) {
                    response = await api.put(`/productos/${this.form.id}`, datos);
                } else {
                    response = await api.post('/productos', datos);
                }

                if (response.data) {
                    await this.cargarProductos();
                    this.cerrarModal();
                }
            } catch (error) {
                console.error('Error guardando producto:', error);
                this.error = error.response?.data?.message || 'Error al guardar producto';
            } finally {
                this.guardando = false;
            }
        },
        async eliminarProducto(id) {
            if (!confirm('¿Estás seguro de eliminar este producto?')) return;

            try {
                await api.delete(`/productos/${id}`);
                await this.cargarProductos();
            } catch (error) {
                console.error('Error eliminando producto:', error);
                alert(error.response?.data?.message || 'Error al eliminar producto');
            }
        }
    }
};
</script>