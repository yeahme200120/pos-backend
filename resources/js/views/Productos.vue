<!-- resources/js/views/Productos.vue -->
<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Productos</h1>
            <button @click="abrirModal()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                + Nuevo Producto
            </button>
        </div>

        <!-- Barra de búsqueda -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex gap-4">
                <input v-model="filtros.search" @keyup.enter="cargarProductos" type="text"
                    class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Buscar por nombre o código..." />
                <button @click="cargarProductos"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Buscar
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="cargando" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando...
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded-lg">
            {{ error }}
        </div>

        <!-- =============================== -->

        <!-- TABLA + CARDS RESPONSIVAS -->

        <!-- =============================== -->

        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

            <!-- ================================= -->
            <!-- TABLA: ESCRITORIO / TABLET -->
            <!-- ================================= -->
            <div class="hidden md:block overflow-x-auto">

                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">
                        <tr>

                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Código
                            </th>

                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Nombre
                            </th>

                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Precio
                            </th>

                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Costo
                            </th>

                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Stock
                            </th>

                            <th
                                class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Estado
                            </th>

                            <th
                                class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Acciones
                            </th>

                        </tr>
                    </thead>


                    <tbody class="bg-white divide-y divide-gray-200">

                        <!-- Sin productos -->
                        <tr v-if="productos.length === 0">

                            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                <div class="text-3xl mb-2">📦</div>
                                No hay productos registrados
                            </td>

                        </tr>


                        <!-- Productos -->
                        <tr v-for="producto in productos" :key="producto.id" class="hover:bg-gray-50 transition-colors">

                            <!-- Código -->
                            <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">
                                {{ producto.codigo }}
                            </td>


                            <!-- Nombre -->
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900 min-w-[200px]">
                                {{ producto.nombre }}
                            </td>


                            <!-- Precio -->
                            <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900 whitespace-nowrap">
                                ${{ formatearNumero(producto.precio) }}
                            </td>


                            <!-- Costo -->
                            <td class="px-6 py-4 text-sm text-right text-gray-600 whitespace-nowrap">
                                ${{ formatearNumero(producto.costo) }}
                            </td>


                            <!-- Stock -->
                            <td class="px-6 py-4 text-sm text-right whitespace-nowrap">

                                <span :class="producto.stock <= producto.stock_minimo
                                    ? 'text-red-600 font-bold'
                                    : 'text-gray-700 font-medium'">
                                    {{ producto.stock }}
                                </span>

                                <span v-if="producto.stock <= producto.stock_minimo && producto.stock > 0"
                                    class="ml-1 text-xs text-amber-600 font-medium">
                                    ⚠️ Bajo
                                </span>

                                <span v-if="producto.stock === 0" class="ml-1 text-xs text-red-600 font-bold">
                                    Agotado
                                </span>

                            </td>


                            <!-- Estado -->
                            <td class="px-6 py-4 text-sm text-center whitespace-nowrap">

                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full"
                                    :class="producto.activo
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-red-100 text-red-800'">

                                    <span class="w-1.5 h-1.5 rounded-full" :class="producto.activo
                                        ? 'bg-green-500'
                                        : 'bg-red-500'"></span>

                                    {{ producto.activo ? 'Activo' : 'Inactivo' }}

                                </span>

                            </td>


                            <!-- Acciones -->
                            <td class="px-6 py-4 text-sm text-center whitespace-nowrap">

                                <div class="flex justify-center items-center gap-2">

                                    <button @click="abrirModal(producto)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-blue-600 hover:bg-blue-50 hover:text-blue-800 font-medium transition-colors">
                                        ✏️
                                        Editar
                                    </button>

                                    <button @click="eliminarProducto(producto.id)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-red-600 hover:bg-red-50 hover:text-red-800 font-medium transition-colors">
                                        🗑️
                                        Eliminar
                                    </button>

                                </div>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


            <!-- ================================= -->
            <!-- CARDS: SOLO MÓVIL -->
            <!-- ================================= -->
            <div class="md:hidden bg-gray-50 p-3">

                <!-- Sin productos -->
                <div v-if="productos.length === 0"
                    class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center">

                    <div class="text-4xl mb-3">
                        📦
                    </div>

                    <p class="text-sm font-semibold text-gray-700">
                        No hay productos registrados
                    </p>

                    <p class="text-xs text-gray-400 mt-1">
                        Los productos aparecerán aquí
                    </p>

                </div>


                <!-- Lista de cards -->
                <div class="space-y-3">

                    <div v-for="producto in productos" :key="producto.id"
                        class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

                        <!-- ================= -->
                        <!-- HEADER -->
                        <!-- ================= -->
                        <div class="p-4">

                            <div class="flex items-start justify-between gap-3">

                                <div class="flex items-start gap-3 min-w-0">

                                    <!-- Icono -->
                                    <div
                                        class="w-11 h-11 flex-shrink-0 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                                        📦
                                    </div>


                                    <div class="min-w-0">

                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                            {{ producto.codigo || 'Sin código' }}
                                        </p>

                                        <h3 class="mt-1 text-base font-bold text-gray-900 leading-tight break-words">
                                            {{ producto.nombre }}
                                        </h3>

                                    </div>

                                </div>


                                <!-- Estado -->
                                <span
                                    class="flex-shrink-0 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-bold"
                                    :class="producto.activo
                                        ? 'bg-green-50 text-green-700 ring-1 ring-green-200'
                                        : 'bg-red-50 text-red-700 ring-1 ring-red-200'">

                                    <span class="w-1.5 h-1.5 rounded-full" :class="producto.activo
                                        ? 'bg-green-500'
                                        : 'bg-red-500'"></span>

                                    {{ producto.activo ? 'Activo' : 'Inactivo' }}

                                </span>

                            </div>

                        </div>


                        <!-- ================= -->
                        <!-- PRECIOS -->
                        <!-- ================= -->
                        <div class="px-4 pb-4">

                            <div class="grid grid-cols-2 gap-2">

                                <!-- Precio -->
                                <div class="bg-blue-50 border border-blue-100 rounded-xl p-3">

                                    <p class="text-[10px] font-semibold text-blue-500 uppercase">
                                        Precio
                                    </p>

                                    <p class="text-lg font-bold text-blue-700 mt-1">
                                        ${{ formatearNumero(producto.precio) }}
                                    </p>

                                </div>


                                <!-- Costo -->
                                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3">

                                    <p class="text-[10px] font-semibold text-gray-400 uppercase">
                                        Costo
                                    </p>

                                    <p class="text-lg font-bold text-gray-700 mt-1">
                                        ${{ formatearNumero(producto.costo) }}
                                    </p>

                                </div>

                            </div>

                        </div>


                        <!-- ================= -->
                        <!-- STOCK -->
                        <!-- ================= -->
                        <div class="px-4 pb-4">

                            <div class="rounded-xl border p-3" :class="producto.stock === 0
                                    ? 'bg-red-50 border-red-200'
                                    : producto.stock <= producto.stock_minimo
                                        ? 'bg-amber-50 border-amber-200'
                                        : 'bg-green-50 border-green-200'
                                ">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-3">

                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center" :class="producto.stock === 0
                                                ? 'bg-red-100 text-red-600'
                                                : producto.stock <= producto.stock_minimo
                                                    ? 'bg-amber-100 text-amber-600'
                                                    : 'bg-green-100 text-green-600'
                                            ">
                                            📊
                                        </div>


                                        <div>

                                            <p class="text-[10px] font-semibold text-gray-500 uppercase">
                                                Stock
                                            </p>

                                            <p class="text-sm font-bold mt-0.5" :class="producto.stock === 0
                                                    ? 'text-red-700'
                                                    : producto.stock <= producto.stock_minimo
                                                        ? 'text-amber-700'
                                                        : 'text-green-700'
                                                ">
                                                {{ producto.stock }} unidades
                                            </p>

                                        </div>

                                    </div>


                                    <!-- Indicador -->
                                    <span v-if="producto.stock === 0"
                                        class="px-2 py-1 rounded-full bg-red-100 text-red-700 text-[9px] font-bold">
                                        AGOTADO
                                    </span>

                                    <span v-else-if="producto.stock <= producto.stock_minimo"
                                        class="px-2 py-1 rounded-full bg-amber-100 text-amber-700 text-[9px] font-bold">
                                        STOCK BAJO
                                    </span>

                                    <span v-else
                                        class="px-2 py-1 rounded-full bg-green-100 text-green-700 text-[9px] font-bold">
                                        DISPONIBLE
                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- ================= -->
                        <!-- ACCIONES -->
                        <!-- ================= -->
                        <div class="border-t border-gray-100 bg-gray-50 px-4 py-3">

                            <div class="grid grid-cols-2 gap-2">

                                <button @click="abrirModal(producto)"
                                    class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-white border border-blue-200 text-blue-600 hover:bg-blue-50 active:scale-[0.98] font-semibold text-sm transition-all">
                                    ✏️
                                    Editar
                                </button>


                                <button @click="eliminarProducto(producto.id)"
                                    class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-white border border-red-200 text-red-600 hover:bg-red-50 active:scale-[0.98] font-semibold text-sm transition-all">
                                    🗑️
                                    Eliminar
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Modal Crear/Editar -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-bold mb-4">{{ editando ? 'Editar' : 'Nuevo' }} Producto</h2>

                <form @submit.prevent="guardarProducto">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Código</label>
                            <input v-model="form.codigo" type="text"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input v-model="form.nombre" type="text"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Precio</label>
                            <input v-model="form.precio" type="number" step="0.01" min="0"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Costo</label>
                            <input v-model="form.costo" type="number" step="0.01" min="0"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock</label>
                            <input v-model="form.stock" type="number" min="0"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock Mínimo</label>
                            <input v-model="form.stock_minimo" type="number" min="0"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Impuesto (%)</label>
                            <input v-model="form.impuesto" type="number" step="0.01" min="0" max="100"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div class="flex items-center">
                            <input v-model="form.activo" type="checkbox"
                                class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" />
                            <label class="text-sm font-medium text-gray-700">Activo</label>
                        </div>
                    </div>

                    <div v-if="error" class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                        {{ error }}
                    </div>

                    <div class="flex justify-end space-x-2 mt-6">
                        <button type="button" @click="cerrarModal"
                            class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="guardando"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
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

                    Swal.fire({
                        icon: 'success',
                        title: this.editando ? 'Producto actualizado' : 'Producto creado',
                        text: response.data.message || 'Operación exitosa',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            } catch (error) {
                console.error('Error guardando producto:', error);
                this.error = error.response?.data?.message || 'Error al guardar producto';

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: this.error,
                });
            } finally {
                this.guardando = false;
            }
        },
        async eliminarProducto(id) {
            const result = await Swal.fire({
                title: '¿Estás seguro?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    await api.delete(`/productos/${id}`);
                    await this.cargarProductos();

                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: 'Producto eliminado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } catch (error) {
                    console.error('Error eliminando producto:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.response?.data?.message || 'Error al eliminar producto',
                    });
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
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.transition {
    transition: all 0.2s ease;
}
</style>