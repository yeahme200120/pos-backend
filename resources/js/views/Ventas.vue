<template>
    <div class="h-full flex flex-col p-2 sm:p-4">
        <h1 class="text-xl sm:text-2xl font-bold mb-3 sm:mb-4">Nueva Venta</h1>

        <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4 min-h-0">
            <!-- Panel izquierdo: Productos -->
            <div class="lg:col-span-2 flex flex-col bg-white rounded-lg shadow overflow-hidden">
                <!-- Filtros -->
                <div class="p-2 sm:p-3 border-b border-gray-200 flex flex-wrap gap-2">
                    <div class="flex-1 min-w-[120px]">
                        <input v-model="filtroProducto" type="text"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Buscar producto..." @input="filtrarProductos" />
                    </div>
                    <div class="w-36 sm:w-48">
                        <select v-model="filtroCategoria"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="filtrarProductos">
                            <option value="">Todas</option>
                            <option v-for="cat in (categorias || [])" :key="cat.id" :value="cat.id">
                                {{ cat.nombre }}
                            </option>
                        </select>
                    </div>
                    <button @click="limpiarFiltros"
                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                        ✕
                    </button>
                </div>

                <!-- Lista de productos - Tarjetas 3D -->
                <div class="flex-1 overflow-y-auto p-2 sm:p-3">
                    <div v-if="cargandoProductos" class="text-center py-8">
                        <span class="inline-block animate-spin mr-2">⟳</span>
                        Cargando productos...
                    </div>
                    <div v-else-if="!productos || productos.length === 0" class="text-center py-8 text-gray-500">
                        No hay productos disponibles
                    </div>
                    <div v-else class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2 sm:gap-3">
                        <div v-for="producto in productosFiltrados" :key="producto.id"
                            class="product-card group cursor-pointer" @click="agregarProductoCarrito(producto)">
                            <div class="card-3d">
                                <div class="card-icon">
                                    <svg class="w-8 h-8 sm:w-10 sm:h-10 text-blue-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <div class="card-info">
                                    <p class="card-title">{{ producto.nombre }}</p>
                                    <p class="card-code">{{ producto.codigo }}</p>
                                    <div class="flex justify-between items-center mt-1">
                                        <span class="card-price">${{ formatearNumero(producto.precio) }}</span>
                                        <span class="card-stock"
                                            :class="(producto.stock || 0) <= 5 ? 'text-red-500' : 'text-gray-400'">
                                            {{ producto.stock || 0 }}
                                        </span>
                                    </div>
                                </div>
                                <div class="card-overlay">
                                    <span class="text-white text-xs sm:text-sm font-medium">Agregar</span>
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel derecho: Carrito -->
            <div class="lg:col-span-1 flex flex-col bg-white rounded-lg shadow overflow-hidden">
                <!-- Cliente -->
                <div class="p-2 sm:p-3 border-b border-gray-200">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select v-model="venta.cliente_id"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option :value="null">Cliente genérico</option>
                        <option v-for="cliente in (clientes || [])" :key="cliente.id" :value="cliente.id">
                            {{ cliente.nombre }}
                        </option>
                    </select>
                </div>

                <!-- Carrito -->
                <div class="flex-1 overflow-y-auto p-2 sm:p-3">
                    <h3 class="font-semibold text-sm mb-2">Carrito <span class="text-gray-400 text-xs">({{ carrito ?
                        carrito.length : 0 }})</span></h3>

                    <div v-if="!carrito || carrito.length === 0" class="text-center py-8 text-gray-400 text-sm">
                        <p class="text-3xl mb-2">🛒</p>
                        <p>Agrega productos</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div v-for="(item, index) in carrito" :key="index"
                            class="border rounded-lg p-2 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm truncate">{{ item.nombre }}</p>
                                    <p class="text-xs text-gray-500">${{ formatearNumero(item.precio) }} c/u</p>
                                </div>
                                <div class="flex items-center gap-1 ml-2">
                                    <button @click="cambiarCantidad(index, -1)"
                                        class="w-6 h-6 bg-gray-200 rounded hover:bg-gray-300 text-sm flex items-center justify-center">
                                        -
                                    </button>
                                    <span class="w-6 text-center text-sm font-medium">{{ item.cantidad }}</span>
                                    <button @click="cambiarCantidad(index, 1)"
                                        class="w-6 h-6 bg-gray-200 rounded hover:bg-gray-300 text-sm flex items-center justify-center">
                                        +
                                    </button>
                                    <button @click="eliminarDelCarrito(index)"
                                        class="ml-1 text-red-500 hover:text-red-700 text-sm">
                                        ✕
                                    </button>
                                </div>
                            </div>
                            <div class="text-right text-sm font-semibold text-blue-600">
                                ${{ formatearNumero(item.precio * item.cantidad) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen y Pagos -->
                <div class="p-2 sm:p-3 border-t border-gray-200 bg-gray-50">
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span>${{ formatearNumero(subtotal || 0) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Descuento:</span>
                            <span>-${{ formatearNumero(descuento || 0) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Impuesto ({{ impuesto || 0 }}%):</span>
                            <span>${{ formatearNumero(impuestoCalculado || 0) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-base sm:text-lg border-t pt-1">
                            <span>Total:</span>
                            <span class="text-blue-600">${{ formatearNumero(total || 0) }}</span>
                        </div>
                    </div>

                    <!-- Mostrar cambio si hay excedente y existe pago en efectivo -->
                    <div v-if="cambio > 0" class="mt-2 p-2 bg-green-100 rounded-lg border border-green-300">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-semibold text-green-800">🔄 Cambio a devolver:</span>
                            <span class="text-lg font-bold text-green-700">${{ formatearNumero(cambio) }}</span>
                        </div>
                        <div v-if="!todosEfectivo" class="text-xs text-green-700 mt-1">
                            * El excedente se devolverá en efectivo
                        </div>
                    </div>

                    <!-- Advertencia de excedente sin efectivo (no bloqueante en vivo) -->
                    <div v-if="excedenteSinDevolver > 0" class="mt-2 p-2 bg-orange-100 rounded-lg border border-orange-300">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-semibold text-orange-800">⚠️ Excedente no devuelto:</span>
                            <span class="text-lg font-bold text-orange-700">${{ formatearNumero(excedenteSinDevolver) }}</span>
                        </div>
                        <div class="text-xs text-orange-700 mt-1">
                            * No hay pago en efectivo, el excedente no se devolverá.
                        </div>
                    </div>

                    <!-- Pagos (todos editables) -->
                    <div class="mt-3 space-y-2">
                        <div v-for="(pago, index) in (venta.pagos || [])" :key="index" class="flex gap-2 items-center">
                            <select v-model="pago.forma_pago" class="flex-1 px-2 py-1 border rounded text-sm"
                                @change="validarPagos">
                                <option value="Efectivo">Efectivo</option>
                                <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                                <option value="Tarjeta Débito">Tarjeta Débito</option>
                                <option value="Transferencia">Transferencia</option>
                            </select>
                            <input type="number" v-model="pago.monto"
                                class="w-20 sm:w-24 px-2 py-1 border rounded text-sm" placeholder="Monto" min="0"
                                step="0.01" @input="validarPagos" />
                            <button v-if="(venta.pagos || []).length > 1 && pago.forma_pago !== 'Efectivo'"
                                @click="eliminarPago(index)"
                                class="text-red-500 hover:text-red-700 text-sm px-2">
                                ✕
                            </button>
                            <span v-else-if="(venta.pagos || []).length > 1 && pago.forma_pago === 'Efectivo'"
                                class="text-gray-400 text-sm px-2"
                                title="El pago en efectivo no se puede eliminar">
                                🔒
                            </span>
                        </div>
                        <button @click="agregarPago" class="text-blue-600 hover:text-blue-800 text-sm">
                            + Agregar pago
                        </button>
                    </div>

                    <!-- Mensaje de error de pagos -->
                    <div v-if="errorPago" class="mt-2 p-2 bg-red-100 text-red-700 rounded text-sm">
                        {{ errorPago }}
                    </div>

                    <!-- ============================================================ -->
                    <!-- DESGLOSE DE PAGOS                                            -->
                    <!-- ============================================================ -->
                    <div v-if="carrito && carrito.length > 0 && Object.keys(desglosePagos).length > 0"
                        class="mt-3 p-2 bg-white rounded-lg border border-gray-200">
                        <p class="text-xs font-bold text-gray-700 mb-2 uppercase">Desglose de pago</p>

                        <div v-for="(monto, tipo) in desglosePagos" :key="tipo"
                            class="flex justify-between text-sm py-0.5">
                            <span class="text-gray-600">{{ tipo }}:</span>
                            <span class="font-mono">${{ formatearNumero(monto) }}</span>
                        </div>

                        <div class="border-t border-gray-300 mt-2 pt-2 flex justify-between text-sm font-semibold">
                            <span>Suma:</span>
                            <span class="font-mono">${{ formatearNumero(totalPagado) }}</span>
                        </div>

                        <div v-if="cambio > 0" class="flex justify-between text-sm font-bold text-green-700 mt-1">
                            <span>Cambio a devolver:</span>
                            <span class="font-mono">${{ formatearNumero(cambio) }}</span>
                        </div>

                        <div v-if="excedenteSinDevolver > 0"
                            class="flex justify-between text-sm font-bold text-orange-700 mt-1">
                            <span>Excedente no devuelto:</span>
                            <span class="font-mono">${{ formatearNumero(excedenteSinDevolver) }}</span>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex gap-2 mt-3">
                        <button @click="guardarPendiente" :disabled="!carrito || carrito.length === 0 || guardando"
                            class="flex-1 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 disabled:opacity-50 text-sm">
                            💾 Guardar Pendiente
                        </button>
                        <button @click="confirmarVenta" :disabled="!carrito || carrito.length === 0 || guardando"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm">
                            {{ guardando ? 'Procesando...' : 'Confirmar' }}
                        </button>
                        <button @click="limpiarCarrito"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                            Limpiar
                        </button>
                    </div>

                    <div v-if="error" class="mt-2 p-2 bg-red-100 text-red-700 rounded text-sm">
                        {{ error }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../axios';
import Swal from 'sweetalert2';

export default {
    name: 'Ventas',
    data() {
        return {
            productos: [],
            categorias: [],
            clientes: [],
            carrito: [],
            filtroProducto: '',
            filtroCategoria: '',
            cargandoProductos: false,
            guardando: false,
            error: null,
            errorPago: null,
            venta: {
                cliente_id: null,
                pagos: [
                    { forma_pago: 'Efectivo', monto: 0 }
                ],
                notas: ''
            },
            impuesto: 16,
            descuento: 0,
            ventaPendienteId: null,
            esVentaPendiente: false
        };
    },
    computed: {
        productosFiltrados() {
            let list = this.productos || [];
            if (this.filtroProducto) {
                const search = this.filtroProducto.toLowerCase();
                list = list.filter(p =>
                    (p.nombre && p.nombre.toLowerCase().includes(search)) ||
                    (p.codigo && p.codigo.toLowerCase().includes(search)) ||
                    (p.descripcion && p.descripcion.toLowerCase().includes(search))
                );
            }
            if (this.filtroCategoria) {
                list = list.filter(p => p.categoria_id === this.filtroCategoria);
            }
            return list;
        },
        subtotal() {
            if (!this.carrito || this.carrito.length === 0) return 0;
            return this.carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        },
        impuestoCalculado() {
            return (this.subtotal - this.descuento) * (this.impuesto / 100);
        },
        total() {
            return this.subtotal - this.descuento + this.impuestoCalculado;
        },
        // Suma de montos con monto > 0 (todos los tipos)
        totalPagado() {
            if (!this.venta || !this.venta.pagos) return 0;
            return this.venta.pagos
                .filter(p => parseFloat(p.monto) > 0)
                .reduce((sum, p) => sum + (parseFloat(p.monto) || 0), 0);
        },
        // Suma solo del efectivo
        totalEfectivo() {
            if (!this.venta || !this.venta.pagos) return 0;
            return this.venta.pagos
                .filter(p => p.forma_pago === 'Efectivo' && parseFloat(p.monto) > 0)
                .reduce((sum, p) => sum + (parseFloat(p.monto) || 0), 0);
        },
        // ¿Hay al menos un pago en efectivo con monto > 0?
        hayEfectivo() {
            return this.totalEfectivo > 0;
        },
        // ¿Todos los pagos con monto > 0 son en efectivo?
        todosEfectivo() {
            if (!this.venta || !this.venta.pagos) return false;
            const pagosConMonto = this.venta.pagos.filter(p => parseFloat(p.monto) > 0);
            if (pagosConMonto.length === 0) return false;
            return pagosConMonto.every(p => p.forma_pago === 'Efectivo');
        },
        // Cambio a devolver: solo si hay excedente Y hay efectivo
        // Nunca puede ser mayor al efectivo disponible
        cambio() {
            const totalPagado = this.totalPagado;
            const total = this.total;
            if (totalPagado > total && this.hayEfectivo) {
                return Math.min(totalPagado - total, this.totalEfectivo);
            }
            return 0;
        },
        // Excedente que NO se puede devolver (no hay efectivo para cubrirlo)
        excedenteSinDevolver() {
            const totalPagado = this.totalPagado;
            const total = this.total;
            if (totalPagado > total && !this.hayEfectivo) {
                return totalPagado - total;
            }
            return 0;
        },
        // Desglose por tipo de pago (solo montos > 0)
        desglosePagos() {
            const grupos = {};
            if (!this.venta || !this.venta.pagos) return grupos;
            this.venta.pagos
                .filter(p => parseFloat(p.monto) > 0)
                .forEach(p => {
                    const tipo = p.forma_pago || 'Otro';
                    grupos[tipo] = (grupos[tipo] || 0) + parseFloat(p.monto || 0);
                });
            return grupos;
        }
    },
    watch: {
        total() {
            this.validarPagos();
        }
    },
    mounted() {
        this.cargarProductos();
        this.cargarClientes();
        this.cargarCategorias();
        this.verificarVentaPendiente();
    },
    methods: {
        formatearNumero(valor) {
            if (valor === undefined || valor === null) return '0.00';
            const num = typeof valor === 'string' ? parseFloat(valor) : valor;
            if (isNaN(num)) return '0.00';
            return num.toFixed(2);
        },
        async cargarProductos() {
            this.cargandoProductos = true;
            try {
                const res = await api.get('/productos?per_page=100&activo=1');
                this.productos = res.data.data?.productos || [];
                console.log('📦 Productos cargados:', this.productos.length);
            } catch (error) {
                console.error('Error cargando productos:', error);
                this.productos = [];
            } finally {
                this.cargandoProductos = false;
            }
        },
        async cargarClientes() {
            try {
                const res = await api.get('/clientes?per_page=100');
                this.clientes = res.data.data || [];
            } catch (error) {
                console.error('Error cargando clientes:', error);
            }
        },
        async cargarCategorias() {
            try {
                const res = await api.get('/catalogos');
                this.categorias = res.data.categorias || [];
            } catch (error) {
                console.error('Error cargando categorías:', error);
            }
        },
        filtrarProductos() { /* computed */ },
        limpiarFiltros() {
            this.filtroProducto = '';
            this.filtroCategoria = '';
        },
        agregarProductoCarrito(producto) {
            if (!this.carrito) this.carrito = [];
            const existente = this.carrito.find(item => item.id === producto.id);
            if (existente) {
                if (existente.cantidad < (producto.stock || 0)) {
                    existente.cantidad++;
                    this.error = null;
                } else {
                    this.error = `Stock insuficiente. Disponible: ${producto.stock}`;
                }
            } else {
                if ((producto.stock || 0) > 0) {
                    this.carrito.push({
                        id: producto.id,
                        nombre: producto.nombre,
                        codigo: producto.codigo,
                        precio: parseFloat(producto.precio) || 0,
                        cantidad: 1,
                        stock: producto.stock || 0
                    });
                    this.error = null;
                } else {
                    this.error = 'Producto sin stock';
                }
            }
            this.$nextTick(() => this.validarPagos());
        },
        cambiarCantidad(index, delta) {
            if (!this.carrito || !this.carrito[index]) return;
            const item = this.carrito[index];
            const nuevaCantidad = item.cantidad + delta;
            if (nuevaCantidad < 1) return;
            if (nuevaCantidad > item.stock) {
                this.error = `Stock insuficiente. Disponible: ${item.stock}`;
                return;
            }
            item.cantidad = nuevaCantidad;
            this.error = null;
            this.$nextTick(() => this.validarPagos());
        },
        eliminarDelCarrito(index) {
            if (this.carrito) this.carrito.splice(index, 1);
            this.$nextTick(() => this.validarPagos());
        },
        limpiarCarrito() {
            if (!this.carrito || this.carrito.length === 0) return;
            Swal.fire({
                title: '¿Limpiar carrito?',
                text: 'Se eliminarán todos los productos',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, limpiar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (result.isConfirmed) {
                    this.carrito = [];
                    this.error = null;
                    this.errorPago = null;
                    this.venta.pagos = [{ forma_pago: 'Efectivo', monto: 0 }];
                    this.validarPagos();
                }
            });
        },
        agregarPago() {
            if (!this.venta) this.venta = { pagos: [] };
            this.venta.pagos.push({ forma_pago: 'Efectivo', monto: 0 });
            this.validarPagos();
        },
        eliminarPago(index) {
            if (!this.venta || !this.venta.pagos) return;

            const pago = this.venta.pagos[index];

            // No permitir eliminar el pago en efectivo.
            if (pago && pago.forma_pago === 'Efectivo') {
                Swal.fire({
                    icon: 'warning',
                    title: 'No se puede eliminar',
                    text: 'El pago en efectivo es obligatorio. Puedes cambiar el monto o el tipo de pago.',
                    timer: 2500,
                    showConfirmButton: false
                });
                return;
            }

            if (this.venta.pagos.length > 1) {
                this.venta.pagos.splice(index, 1);
                this.validarPagos();
            }
        },
        // ✅ Validación de pagos: se relaja la regla de no-efectivo > total
        validarPagos() {
            this.errorPago = null;

            const pagos = this.venta.pagos.filter(p => parseFloat(p.monto) > 0);

            if (pagos.length === 0) {
                this.errorPago = 'Agrega al menos un pago con monto > 0';
                return;
            }

            const total = this.total;
            const totalPagado = pagos.reduce((sum, p) => sum + parseFloat(p.monto), 0);

            // Verificar que el total pagado sea >= total
            if (totalPagado < total) {
                this.errorPago = `El monto pagado ($${this.formatearNumero(totalPagado)}) es menor al total ($${this.formatearNumero(total)})`;
                return;
            }

            // NOTA: se permite que el excedente exista.
            // El excedente se maneja al confirmar la venta:
            //   - Si hay efectivo: se devuelve como cambio.
            //   - Si NO hay efectivo: se pide confirmación con Swal.
        },

        // =============================================
        // VENTA PENDIENTE (mejorada)
        // =============================================
        async verificarVentaPendiente() {
            try {
                const res = await api.get('/ventas/pendiente/actual');
                if (res.status === 200 && res.data) {
                    // Extraer la venta pendiente: puede venir en res.data o en res.data.data
                    let pendiente = res.data.data || res.data;
                    // Si es un array o está vacío, ignorar
                    if (pendiente && typeof pendiente === 'object' && Object.keys(pendiente).length > 0 && pendiente.id) {
                        this.mostrarDialogoVentaPendiente(pendiente);
                    } else {
                        console.log('No hay venta pendiente válida');
                    }
                }
            } catch (error) {
                if (error.response && error.response.status === 404) {
                    console.log('No hay venta pendiente (404)');
                } else {
                    console.error('Error al verificar venta pendiente:', error);
                }
            }
        },

        mostrarDialogoVentaPendiente(pendiente) {
            Swal.fire({
                title: 'Venta pendiente encontrada',
                html: `
            <div style="text-align: left;">
                <p><strong>Folio:</strong> ${pendiente.folio || 'N/A'}</p>
                <p><strong>Productos:</strong> ${pendiente.detalles?.length || 0}</p>
                <p><strong>Total:</strong> $${this.formatearNumero(pendiente.total || 0)}</p>
                <p><strong>Cliente:</strong> ${pendiente.cliente?.nombre || 'Cliente genérico'}</p>
            </div>
        `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '✅ Cargar venta',
                cancelButtonText: '🗑️ Eliminar pendiente'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    this.cargarVentaPendiente(pendiente);
                } else {
                    try {
                        await api.delete('/ventas/pendiente/eliminar');
                        Swal.fire({ icon: 'info', title: 'Venta pendiente eliminada', timer: 2000, showConfirmButton: false });
                    } catch (error) {
                        console.error('Error eliminando pendiente:', error);
                        Swal.fire({ icon: 'error', title: 'Error al eliminar', text: error.response?.data?.message || '' });
                    }
                }
            });
        },

        cargarVentaPendiente(pendiente) {
            // Asegurar que pendiente tenga los campos necesarios
            if (!pendiente || !pendiente.id) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'La venta pendiente no es válida' });
                return;
            }
            this.ventaPendienteId = pendiente.id;
            this.esVentaPendiente = true;
            this.venta.cliente_id = pendiente.cliente_id || null;
            this.venta.notas = pendiente.notas || '';
            this.carrito = (pendiente.detalles || []).map(d => ({
                id: d.producto_id,
                nombre: d.producto?.nombre || d.nombre_producto || 'Producto',
                codigo: d.producto?.codigo || d.codigo_producto || '',
                precio: parseFloat(d.precio_unitario) || 0,
                cantidad: parseFloat(d.cantidad) || 0,
                stock: d.producto?.stock || 0
            }));
            if (pendiente.pagos && pendiente.pagos.length > 0) {
                this.venta.pagos = pendiente.pagos.map(p => ({
                    forma_pago: p.forma_pago || 'Efectivo',
                    monto: parseFloat(p.monto) || 0
                }));
            } else {
                this.venta.pagos = [{ forma_pago: 'Efectivo', monto: 0 }];
            }
            this.descuento = parseFloat(pendiente.descuento || 0);
            this.impuesto = parseFloat(pendiente.impuesto || 16);
            this.$nextTick(() => this.validarPagos());
            Swal.fire({ icon: 'success', title: 'Venta pendiente cargada', timer: 2000, showConfirmButton: false });
        },

        // =============================================
        // GUARDAR Y CONFIRMAR VENTA
        // =============================================
        async guardarPendiente() {
            if (!this.carrito || this.carrito.length === 0) {
                this.error = 'Agrega al menos un producto';
                return;
            }
            try {
                const data = {
                    cliente_id: this.venta.cliente_id,
                    productos: this.carrito.map(item => ({
                        producto_id: item.id,
                        cantidad: item.cantidad,
                        precio: item.precio,
                        descuento: 0
                    })),
                    pagos: this.venta.pagos
                        .filter(p => p.monto > 0)
                        .map(p => ({
                            forma_pago: p.forma_pago,
                            monto: parseFloat(p.monto)
                        })),
                    descuento_global: this.descuento,
                    impuesto_global: this.impuesto,
                    notas: this.venta.notas || ''
                };
                await api.post('/ventas/pendiente/guardar', data);
                Swal.fire({ icon: 'info', title: 'Venta guardada como pendiente', timer: 2000, showConfirmButton: false });
                this.carrito = [];
                this.venta.pagos = [{ forma_pago: 'Efectivo', monto: 0 }];
                this.venta.cliente_id = null;
                this.venta.notas = '';
                this.descuento = 0;
                this.error = null;
                this.errorPago = null;
            } catch (error) {
                console.error('Error guardando pendiente:', error);
                this.error = error.response?.data?.message || 'Error al guardar pendiente';
            }
        },

        async confirmarVenta() {
            if (!this.carrito || this.carrito.length === 0) {
                this.error = 'Agrega al menos un producto';
                return;
            }

            this.validarPagos();
            if (this.errorPago) {
                this.error = this.errorPago;
                return;
            }

            const pagosValidos = this.venta.pagos.filter(p => p.monto > 0);
            if (pagosValidos.length === 0) {
                this.error = 'Agrega al menos un pago con monto > 0';
                return;
            }

            const totalPagado = this.totalPagado;
            if (totalPagado < this.total) {
                this.error = `El monto pagado ($${this.formatearNumero(totalPagado)}) es menor al total ($${this.formatearNumero(this.total)})`;
                return;
            }

            // ============================================================
            // SWAL PREVIO: EXCEDENTE SIN EFECTIVO
            // ============================================================
            if (this.excedenteSinDevolver > 0) {
                const confirmExcedente = await Swal.fire({
                    icon: 'warning',
                    title: 'Cobro mayor al total',
                    html: `
                        <div style="text-align: left;">
                            <p>Estás cobrando <strong>$${this.formatearNumero(this.excedenteSinDevolver)}</strong> de más y no hay pago en efectivo.</p>
                            <p>El excedente <strong>no podrá ser devuelto</strong>.</p>
                            <p>¿Confirmas cobrar con excedente?</p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar cobro',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#6b7280'
                });

                if (!confirmExcedente.isConfirmed) {
                    return;
                }
            }

            // ============================================================
            // CONSTRUCCIÓN DEL DIÁLOGO DE CONFIRMACIÓN
            // ============================================================
            let htmlContent = `
                <div style="text-align: left;">
                    <p><strong>Total de la venta:</strong> $${this.formatearNumero(this.total)}</p>
                    <p><strong>Productos:</strong> ${this.carrito.length}</p>
                    <p><strong>Cliente:</strong> ${this.venta.cliente_id ? this.clientes.find(c => c.id === this.venta.cliente_id)?.nombre : 'Cliente genérico'}</p>
                    <hr style="margin: 8px 0;">
                    <p><strong>Desglose de pagos:</strong></p>
            `;

            // Desglose por tipo de pago
            for (const [tipo, monto] of Object.entries(this.desglosePagos)) {
                htmlContent += `<p style="margin: 2px 0;">• ${tipo}: $${this.formatearNumero(monto)}</p>`;
            }

            htmlContent += `<p style="margin-top: 6px;"><strong>Suma:</strong> $${this.formatearNumero(totalPagado)}</p>`;

            if (this.cambio > 0) {
                htmlContent += `
                    <div style="background: #d1fae5; padding: 10px; border-radius: 8px; margin-top: 8px;">
                        <p style="color: #065f46; font-weight: bold; margin: 0;">🔄 Cambio a devolver (en efectivo):</p>
                        <p style="color: #065f46; font-size: 1.2rem; font-weight: bold; margin: 4px 0 0 0;">$${this.formatearNumero(this.cambio)}</p>
                    </div>
                `;
            }

            if (this.excedenteSinDevolver > 0) {
                htmlContent += `
                    <div style="background: #fef3c7; padding: 10px; border-radius: 8px; margin-top: 8px;">
                        <p style="color: #92400e; font-weight: bold; margin: 0;">⚠️ Excedente no devuelto</p>
                        <p style="color: #92400e; font-size: 1.2rem; font-weight: bold; margin: 4px 0 0 0;">$${this.formatearNumero(this.excedenteSinDevolver)}</p>
                        <p style="color: #92400e; font-size: 0.8rem; margin-top: 4px;">No hay efectivo, el excedente no se devuelve.</p>
                    </div>
                `;
            }

            htmlContent += `</div>`;

            const result = await Swal.fire({
                title: 'Confirmar Venta',
                html: htmlContent,
                icon: (this.cambio > 0 || this.excedenteSinDevolver > 0) ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '✅ Confirmar Venta',
                cancelButtonText: '❌ Guardar como pendiente'
            });

            if (!result.isConfirmed) {
                await this.guardarPendiente();
                return;
            }

            this.guardando = true;
            this.error = null;

            try {
                // ============================================================
                // AJUSTE DE PAGOS: restar el cambio SOLO del efectivo
                // El monto enviado es el NETO (sin cambio)
                // El cambio se envía como campo informativo
                // ============================================================
                let pagosAjustados = this.venta.pagos
                    .filter(p => p.monto > 0)
                    .map(p => ({
                        forma_pago: p.forma_pago,
                        monto: parseFloat(p.monto),
                        cambio: 0
                    }));

                const totalPagadoOriginal = pagosAjustados.reduce((sum, p) => sum + p.monto, 0);
                const diferencia = totalPagadoOriginal - this.total;

                if (diferencia > 0) {
                    // Calcular cuánto del excedente puede cubrirse con efectivo
                    const totalEfectivo = pagosAjustados
                        .filter(p => p.forma_pago === 'Efectivo')
                        .reduce((sum, p) => sum + p.monto, 0);

                    const cambioReal = Math.min(diferencia, totalEfectivo);
                    const excedenteNoDevuelto = diferencia - cambioReal;

                    // Restar el cambio del efectivo (dejar el efectivo en neto)
                    let restante = cambioReal;
                    let cambioAsignado = false;

                    for (let pago of pagosAjustados) {
                        if (pago.forma_pago === 'Efectivo' && restante > 0) {
                            if (pago.monto >= restante) {
                                pago.monto = pago.monto - restante;
                                if (!cambioAsignado) {
                                    pago.cambio = cambioReal;
                                    cambioAsignado = true;
                                }
                                restante = 0;
                            } else {
                                restante -= pago.monto;
                                pago.monto = 0;
                            }
                        }
                        if (restante <= 0) break;
                    }

                    // Si sobra excedente (no había efectivo suficiente),
                    // NO se descuenta de otros métodos; simplemente se pierde.
                    // El monto enviado del resto queda tal cual.
                    if (excedenteNoDevuelto > 0) {
                        console.warn(
                            `Excedente no devuelto de $${excedenteNoDevuelto.toFixed(2)} ` +
                            `porque no había suficiente efectivo.`
                        );
                    }
                }

                // Construir payload con pagos ajustados
                const data = {
                    cliente_id: this.venta.cliente_id,
                    productos: this.carrito.map(item => ({
                        producto_id: item.id,
                        cantidad: item.cantidad,
                        precio: item.precio,
                        descuento: 0
                    })),
                    pagos: pagosAjustados
                        .filter(p => p.monto > 0 || p.cambio > 0)
                        .map(p => ({
                            forma_pago: p.forma_pago,
                            monto: parseFloat(p.monto.toFixed(2)),
                            cambio: parseFloat((p.cambio || 0).toFixed(2))
                        })),
                    descuento_global: this.descuento,
                    impuesto_global: this.impuesto,
                    notas: this.venta.notas || ''
                };

                await api.post('/ventas', data);

                if (this.ventaPendienteId) {
                    try { await api.delete('/ventas/pendiente/eliminar'); } catch (e) { }
                }

                Swal.fire({ icon: 'success', title: '¡Venta registrada!', timer: 2000, showConfirmButton: false });

                // Limpiar carrito
                this.carrito = [];
                this.venta.pagos = [{ forma_pago: 'Efectivo', monto: 0 }];
                this.descuento = 0;
                this.venta.cliente_id = null;
                this.venta.notas = '';
                this.ventaPendienteId = null;
                this.esVentaPendiente = false;
                this.error = null;
                this.errorPago = null;

            } catch (error) {
                this.error = error.response?.data?.message || 'Error al registrar la venta';
                Swal.fire({ icon: 'error', title: 'Error', text: this.error });
            } finally {
                this.guardando = false;
            }
        }
    }
};
</script>

<style scoped>
/* ============================================
   TARJETAS 3D
============================================ */
.product-card {
    perspective: 800px;
    height: 100%;
}

.card-3d {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 100px;
    background: white;
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform-style: preserve-3d;
    display: flex;
    flex-direction: column;
    border: 1px solid #f0f0f0;
    cursor: pointer;
}

.product-card:hover .card-3d {
    transform: translateY(-4px) rotateX(2deg) rotateY(2deg);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    border-color: #3b82f6;
}

.product-card:active .card-3d {
    transform: scale(0.96);
    transition-duration: 0.1s;
}

.card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #eff6ff;
    border-radius: 10px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.product-card:hover .card-icon {
    background: #dbeafe;
    transform: scale(1.05);
}

.card-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.card-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1f2937;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-code {
    font-size: 0.6rem;
    color: #9ca3af;
    margin-top: 2px;
}

.card-price {
    font-size: 0.85rem;
    font-weight: 700;
    color: #2563eb;
}

.card-stock {
    font-size: 0.6rem;
    font-weight: 500;
    background: #f3f4f6;
    padding: 1px 8px;
    border-radius: 10px;
}

.card-overlay {
    position: absolute;
    inset: 0;
    background: rgba(37, 99, 235, 0.9);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    opacity: 0;
    transition: all 0.3s ease;
    backdrop-filter: blur(2px);
}

.product-card:hover .card-overlay {
    opacity: 1;
}

/* ============================================
   SCROLL PERSONALIZADO
============================================ */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* ============================================
   ANIMACIONES
============================================ */
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

/* ============================================
   RESPONSIVE - MÓVIL
============================================ */
@media (max-width: 640px) {
    .card-3d {
        min-height: 80px;
        padding: 10px;
        border-radius: 10px;
    }

    .card-icon {
        width: 32px;
        height: 32px;
    }

    .card-icon svg {
        width: 18px;
        height: 18px;
    }

    .card-title {
        font-size: 0.7rem;
    }

    .card-price {
        font-size: 0.75rem;
    }

    .card-stock {
        font-size: 0.55rem;
    }

    .card-overlay span {
        font-size: 0.7rem;
    }
}
</style>