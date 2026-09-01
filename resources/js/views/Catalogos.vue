<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h1 class="text-2xl font-bold">Catálogos</h1>
            <div class="flex flex-wrap gap-2">
                <button 
                    @click="abrirModal('producto')"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"
                >
                    + Nuevo Producto
                </button>
                <button 
                    @click="abrirModal('categoria')"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm"
                >
                    + Nueva Categoría
                </button>
                <button 
                    @click="abrirModal('unidad')"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm"
                >
                    + Nueva Unidad
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex flex-wrap gap-2">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    @click="tabActivo = tab.id"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition"
                    :class="tabActivo === tab.id 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                >
                    <i :class="tab.icon"></i>
                    {{ tab.nombre }}
                    <span class="ml-1 text-xs" :class="tabActivo === tab.id ? 'text-blue-200' : 'text-gray-400'">
                        ({{ tab.total }})
                    </span>
                </button>
            </nav>
        </div>

        <!-- Contenido Productos -->
        <div v-if="tabActivo === 'productos'">
            <div class="bg-white rounded-lg shadow">
                <!-- Filtros -->
                <div class="p-4 border-b border-gray-200 flex flex-wrap gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <input 
                            v-model="filtrosProducto.search"
                            type="text"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Buscar producto..."
                        />
                    </div>
                    <div class="w-48">
                        <select 
                            v-model="filtrosProducto.categoria"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Todas las categorías</option>
                            <option v-for="cat in categorias" :key="cat.id" :value="cat.id">
                                {{ cat.nombre }}
                            </option>
                        </select>
                    </div>
                    <button 
                        @click="limpiarFiltrosProducto"
                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm"
                    >
                        Limpiar
                    </button>
                </div>

                <!-- Tabla Productos -->
                <div class="overflow-x-auto">
                    <div v-if="cargando" class="text-center py-8">
                        <span class="inline-block animate-spin mr-2">⟳</span>
                        Cargando...
                    </div>
                    <div v-else-if="productosFiltrados.length === 0" class="text-center py-8 text-gray-500">
                        No hay productos registrados
                    </div>
                    <table v-else class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Precio</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stock</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="producto in productosFiltrados" :key="producto.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm">{{ producto.codigo }}</td>
                                <td class="px-4 py-3 text-sm font-medium">{{ producto.nombre }}</td>
                                <td class="px-4 py-3 text-sm text-right">${{ formatearNumero(producto.precio) }}</td>
                                <td class="px-4 py-3 text-sm text-right">
                                    <span :class="producto.stock <= producto.stock_minimo ? 'text-red-600 font-bold' : ''">
                                        {{ producto.stock }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ producto.categoria?.nombre || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="px-2 py-1 text-xs rounded-full" 
                                          :class="producto.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        {{ producto.activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <button @click="editarProducto(producto)" class="text-blue-600 hover:text-blue-800 mr-2">
                                        ✏️
                                    </button>
                                    <button @click="eliminarProducto(producto.id)" class="text-red-600 hover:text-red-800">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Contenido Categorías -->
        <div v-if="tabActivo === 'categorias'">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <div v-if="cargando" class="text-center py-8">
                        <span class="inline-block animate-spin mr-2">⟳</span>
                        Cargando...
                    </div>
                    <div v-else-if="categorias.length === 0" class="text-center py-8 text-gray-500">
                        No hay categorías registradas
                    </div>
                    <table v-else class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Color</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Productos</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="categoria in categorias" :key="categoria.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium">{{ categoria.nombre }}</td>
                                <td class="px-4 py-3 text-sm">{{ categoria.descripcion || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="inline-block w-6 h-6 rounded-full border" :style="{ backgroundColor: categoria.color || '#gray-300' }"></span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="px-2 py-1 text-xs rounded-full" 
                                          :class="categoria.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        {{ categoria.activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">{{ categoria.productos_count || 0 }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <button @click="editarCategoria(categoria)" class="text-blue-600 hover:text-blue-800 mr-2">
                                        ✏️
                                    </button>
                                    <button @click="eliminarCategoria(categoria.id)" class="text-red-600 hover:text-red-800">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Contenido Unidades de Medida -->
        <div v-if="tabActivo === 'unidades'">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <div v-if="cargando" class="text-center py-8">
                        <span class="inline-block animate-spin mr-2">⟳</span>
                        Cargando...
                    </div>
                    <div v-else-if="unidades.length === 0" class="text-center py-8 text-gray-500">
                        No hay unidades de medida registradas
                    </div>
                    <table v-else class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Abreviatura</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Fraccionable</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="unidad in unidades" :key="unidad.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium">{{ unidad.nombre }}</td>
                                <td class="px-4 py-3 text-sm">{{ unidad.abreviatura || '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full" 
                                          :class="unidad.tipo === 'unidad' ? 'bg-blue-100 text-blue-800' : 
                                                 unidad.tipo === 'peso' ? 'bg-yellow-100 text-yellow-800' : 
                                                 unidad.tipo === 'volumen' ? 'bg-green-100 text-green-800' : 
                                                 'bg-gray-100 text-gray-800'">
                                        {{ unidad.tipo }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    {{ unidad.fraccionable ? '✅' : '❌' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="px-2 py-1 text-xs rounded-full" 
                                          :class="unidad.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        {{ unidad.activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <button @click="editarUnidad(unidad)" class="text-blue-600 hover:text-blue-800 mr-2">
                                        ✏️
                                    </button>
                                    <button @click="eliminarUnidad(unidad.id)" class="text-red-600 hover:text-red-800">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODALES -->
        <!-- ========================================== -->

        <!-- Modal Producto -->
        <div v-if="modalProducto" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-bold mb-4">{{ editando.producto ? 'Editar' : 'Nuevo' }} Producto</h2>
                <form @submit.prevent="guardarProducto">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Código *</label>
                            <input v-model="formProducto.codigo" type="text" class="w-full px-3 py-2 border rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre *</label>
                            <input v-model="formProducto.nombre" type="text" class="w-full px-3 py-2 border rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descripción</label>
                            <textarea v-model="formProducto.descripcion" class="w-full px-3 py-2 border rounded-lg" rows="2"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Categoría</label>
                            <select v-model="formProducto.categoria_id" class="w-full px-3 py-2 border rounded-lg">
                                <option :value="null">Sin categoría</option>
                                <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Unidad de Medida</label>
                            <select v-model="formProducto.unidad_medida_id" class="w-full px-3 py-2 border rounded-lg">
                                <option :value="null">Sin unidad</option>
                                <option v-for="uni in unidades" :key="uni.id" :value="uni.id">{{ uni.nombre }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Precio *</label>
                                <input v-model="formProducto.precio" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Costo</label>
                                <input v-model="formProducto.costo" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stock</label>
                                <input v-model="formProducto.stock" type="number" class="w-full px-3 py-2 border rounded-lg" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stock Mínimo</label>
                                <input v-model="formProducto.stock_minimo" type="number" class="w-full px-3 py-2 border rounded-lg" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Impuesto (%)</label>
                            <input v-model="formProducto.impuesto" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div class="flex items-center">
                            <input v-model="formProducto.activo" type="checkbox" class="mr-2" />
                            <label class="text-sm font-medium text-gray-700">Activo</label>
                        </div>
                    </div>
                    <div v-if="errorProducto" class="mt-3 p-2 bg-red-100 text-red-700 rounded text-sm">{{ errorProducto }}</div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" @click="cerrarModalProducto" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancelar</button>
                        <button type="submit" :disabled="guardando" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            {{ guardando ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Categoría -->
        <div v-if="modalCategoria" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-bold mb-4">{{ editando.categoria ? 'Editar' : 'Nueva' }} Categoría</h2>
                <form @submit.prevent="guardarCategoria">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre *</label>
                            <input v-model="formCategoria.nombre" type="text" class="w-full px-3 py-2 border rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descripción</label>
                            <textarea v-model="formCategoria.descripcion" class="w-full px-3 py-2 border rounded-lg" rows="2"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Color</label>
                            <div class="flex items-center gap-2">
                                <input v-model="formCategoria.color" type="color" class="w-12 h-12 rounded border cursor-pointer" />
                                <input v-model="formCategoria.color" type="text" class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm" placeholder="#000000" />
                            </div>
                        </div>
                        <div class="flex items-center">
                            <input v-model="formCategoria.activo" type="checkbox" class="mr-2" />
                            <label class="text-sm font-medium text-gray-700">Activo</label>
                        </div>
                    </div>
                    <div v-if="errorCategoria" class="mt-3 p-2 bg-red-100 text-red-700 rounded text-sm">{{ errorCategoria }}</div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" @click="cerrarModalCategoria" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancelar</button>
                        <button type="submit" :disabled="guardando" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                            {{ guardando ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Unidad de Medida -->
        <div v-if="modalUnidad" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-bold mb-4">{{ editando.unidad ? 'Editar' : 'Nueva' }} Unidad de Medida</h2>
                <form @submit.prevent="guardarUnidad">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre *</label>
                            <input v-model="formUnidad.nombre" type="text" class="w-full px-3 py-2 border rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Abreviatura</label>
                            <input v-model="formUnidad.abreviatura" type="text" class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo</label>
                            <select v-model="formUnidad.tipo" class="w-full px-3 py-2 border rounded-lg">
                                <option value="unidad">Unidad</option>
                                <option value="peso">Peso</option>
                                <option value="volumen">Volumen</option>
                                <option value="longitud">Longitud</option>
                                <option value="servicio">Servicio</option>
                            </select>
                        </div>
                        <div class="flex items-center">
                            <input v-model="formUnidad.fraccionable" type="checkbox" class="mr-2" />
                            <label class="text-sm font-medium text-gray-700">Fraccionable</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Factor de Conversión</label>
                            <input v-model="formUnidad.factor_conversion" type="number" step="0.0001" class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div class="flex items-center">
                            <input v-model="formUnidad.activo" type="checkbox" class="mr-2" />
                            <label class="text-sm font-medium text-gray-700">Activo</label>
                        </div>
                    </div>
                    <div v-if="errorUnidad" class="mt-3 p-2 bg-red-100 text-red-700 rounded text-sm">{{ errorUnidad }}</div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" @click="cerrarModalUnidad" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancelar</button>
                        <button type="submit" :disabled="guardando" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50">
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
    name: 'Catalogos',
    data() {
        return {
            tabActivo: 'productos',
            tabs: [
                { id: 'productos', nombre: 'Productos', icon: 'fas fa-box', total: 0 },
                { id: 'categorias', nombre: 'Categorías', icon: 'fas fa-tags', total: 0 },
                { id: 'unidades', nombre: 'Unidades', icon: 'fas fa-ruler', total: 0 }
            ],
            productos: [],
            categorias: [],
            unidades: [],
            cargando: false,
            guardando: false,
            filtrosProducto: { search: '', categoria: '' },
            modalProducto: false,
            modalCategoria: false,
            modalUnidad: false,
            editando: { producto: false, categoria: false, unidad: false },
            formProducto: {
                id: null,
                codigo: '',
                nombre: '',
                descripcion: '',
                categoria_id: null,
                unidad_medida_id: null,
                precio: 0,
                costo: 0,
                stock: 0,
                stock_minimo: 0,
                impuesto: 0,
                activo: true
            },
            formCategoria: {
                id: null,
                nombre: '',
                descripcion: '',
                color: '#3B82F6',
                activo: true
            },
            formUnidad: {
                id: null,
                nombre: '',
                abreviatura: '',
                tipo: 'unidad',
                fraccionable: false,
                factor_conversion: 1,
                activo: true
            },
            errorProducto: null,
            errorCategoria: null,
            errorUnidad: null
        };
    },
    computed: {
        // Filtra productos en memoria según búsqueda y categoría
        productosFiltrados() {
            let list = this.productos;
            if (this.filtrosProducto.search) {
                const search = this.filtrosProducto.search.toLowerCase();
                list = list.filter(p =>
                    p.nombre.toLowerCase().includes(search) ||
                    (p.codigo && p.codigo.toLowerCase().includes(search))
                );
            }
            if (this.filtrosProducto.categoria) {
                list = list.filter(p => p.categoria_id === this.filtrosProducto.categoria);
            }
            return list;
        }
    },
    mounted() {
        this.cargarCatalogos();
    },
    methods: {
        formatearNumero(valor) {
            if (valor === undefined || valor === null) return '0.00';
            const num = typeof valor === 'string' ? parseFloat(valor) : valor;
            if (isNaN(num)) return '0.00';
            return num.toFixed(2);
        },

        // =====================================================
        // CARGA DE CATÁLOGOS (única llamada)
        // =====================================================
        async cargarCatalogos() {
            this.cargando = true;
            try {
                const res = await api.get('/catalogos');
                this.productos = res.data.productos || [];
                this.categorias = res.data.categorias || [];
                this.unidades = res.data.unidades_medida || [];

                this.tabs.find(t => t.id === 'productos').total = this.productos.length;
                this.tabs.find(t => t.id === 'categorias').total = this.categorias.length;
                this.tabs.find(t => t.id === 'unidades').total = this.unidades.length;
            } catch (error) {
                console.error('❌ Error cargando catálogos:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron cargar los catálogos'
                });
            } finally {
                this.cargando = false;
            }
        },

        // =====================================================
        // FILTROS
        // =====================================================
        limpiarFiltrosProducto() {
            this.filtrosProducto = { search: '', categoria: '' };
        },

        // =====================================================
        // ABRIR / CERRAR MODALES
        // =====================================================
        abrirModal(tipo, data = null) {
            if (tipo === 'producto') {
                this.editando.producto = !!data;
                this.formProducto = data
                    ? { ...data }
                    : { id: null, codigo: '', nombre: '', descripcion: '', categoria_id: null, unidad_medida_id: null, precio: 0, costo: 0, stock: 0, stock_minimo: 0, impuesto: 0, activo: true };
                this.errorProducto = null;
                this.modalProducto = true;
            } else if (tipo === 'categoria') {
                this.editando.categoria = !!data;
                this.formCategoria = data
                    ? { ...data }
                    : { id: null, nombre: '', descripcion: '', color: '#3B82F6', activo: true };
                this.errorCategoria = null;
                this.modalCategoria = true;
            } else if (tipo === 'unidad') {
                this.editando.unidad = !!data;
                this.formUnidad = data
                    ? { ...data }
                    : { id: null, nombre: '', abreviatura: '', tipo: 'unidad', fraccionable: false, factor_conversion: 1, activo: true };
                this.errorUnidad = null;
                this.modalUnidad = true;
            }
        },

        cerrarModalProducto() {
            this.modalProducto = false;
            this.editando.producto = false;
        },
        cerrarModalCategoria() {
            this.modalCategoria = false;
            this.editando.categoria = false;
        },
        cerrarModalUnidad() {
            this.modalUnidad = false;
            this.editando.unidad = false;
        },

        editarProducto(p) { this.abrirModal('producto', p); },
        editarCategoria(c) { this.abrirModal('categoria', c); },
        editarUnidad(u) { this.abrirModal('unidad', u); },

        // =====================================================
        // GUARDAR
        // =====================================================
        async guardarProducto() {
            this.guardando = true;
            this.errorProducto = null;
            try {
                const data = { ...this.formProducto };
                data.precio = parseFloat(data.precio) || 0;
                data.costo = parseFloat(data.costo) || 0;
                data.stock = parseInt(data.stock) || 0;
                data.stock_minimo = parseInt(data.stock_minimo) || 0;
                data.impuesto = parseFloat(data.impuesto) || 0;

                if (this.editando.producto) {
                    await api.put(`/productos/${this.formProducto.id}`, data);
                } else {
                    await api.post('/productos', data);
                }
                this.cerrarModalProducto();
                await this.cargarCatalogos();
                Swal.fire({
                    icon: 'success',
                    title: this.editando.producto ? 'Producto actualizado' : 'Producto creado',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (error) {
                this.errorProducto = error.response?.data?.message || 'Error al guardar';
            } finally {
                this.guardando = false;
            }
        },

        async guardarCategoria() {
            this.guardando = true;
            this.errorCategoria = null;
            try {
                if (this.editando.categoria) {
                    await api.put(`/categorias/${this.formCategoria.id}`, this.formCategoria);
                } else {
                    await api.post('/categorias', this.formCategoria);
                }
                this.cerrarModalCategoria();
                await this.cargarCatalogos();
                Swal.fire({
                    icon: 'success',
                    title: this.editando.categoria ? 'Categoría actualizada' : 'Categoría creada',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (error) {
                this.errorCategoria = error.response?.data?.message || 'Error al guardar';
            } finally {
                this.guardando = false;
            }
        },

        async guardarUnidad() {
            this.guardando = true;
            this.errorUnidad = null;
            try {
                if (this.editando.unidad) {
                    await api.put(`/unidades/${this.formUnidad.id}`, this.formUnidad);
                } else {
                    await api.post('/unidades', this.formUnidad);
                }
                this.cerrarModalUnidad();
                await this.cargarCatalogos();
                Swal.fire({
                    icon: 'success',
                    title: this.editando.unidad ? 'Unidad actualizada' : 'Unidad creada',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (error) {
                this.errorUnidad = error.response?.data?.message || 'Error al guardar';
            } finally {
                this.guardando = false;
            }
        },

        // =====================================================
        // ELIMINAR
        // =====================================================
        async eliminarProducto(id) {
            const res = await Swal.fire({
                title: '¿Eliminar producto?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            });
            if (res.isConfirmed) {
                try {
                    await api.delete(`/productos/${id}`);
                    await this.cargarCatalogos();
                    Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false });
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al eliminar' });
                }
            }
        },

        async eliminarCategoria(id) {
            const res = await Swal.fire({
                title: '¿Eliminar categoría?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            });
            if (res.isConfirmed) {
                try {
                    await api.delete(`/categorias/${id}`);
                    await this.cargarCatalogos();
                    Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1500, showConfirmButton: false });
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al eliminar' });
                }
            }
        },

        async eliminarUnidad(id) {
            const res = await Swal.fire({
                title: '¿Eliminar unidad?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            });
            if (res.isConfirmed) {
                try {
                    await api.delete(`/unidades/${id}`);
                    await this.cargarCatalogos();
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