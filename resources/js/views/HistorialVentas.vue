<!-- resources/js/views/HistorialVentas.vue -->
<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 sm:mb-6 gap-3">
            <h1 class="text-2xl font-bold">Historial de Ventas</h1>
            <div class="flex flex-wrap gap-2">
                <button @click="exportarVentas"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                    📊 Exportar
                </button>
                <router-link to="/ventas/nueva"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                    + Nueva Venta
                </router-link>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white p-3 sm:p-4 rounded-lg shadow mb-4 sm:mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
                    <input type="date" v-model="filtros.fecha_desde"
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
                    <input type="date" v-model="filtros.fecha_hasta"
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select v-model="filtros.estado"
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">Todos</option>
                        <option value="pagado">Pagado</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 flex-wrap">
                    <button @click="cargarVentas"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                        Buscar
                    </button>
                    <button @click="limpiarFiltros"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm">
                        Limpiar
                    </button>
                    <button @click="cargarVentasHoy"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm">
                        Hoy
                    </button>
                    <button @click="cargarTodasLasVentas"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm">
                        Todas
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="cargando" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando ventas...
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded-lg">
            {{ error }}
        </div>

        <!-- Resumen SIEMPRE visible cuando hay ventas cargadas -->
        <div v-if="!cargando && !error && ventasCargadas" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="bg-white p-3 rounded-lg shadow text-center cursor-pointer hover:shadow-lg transition"
                @click="aplicarFiltroEstado('pagado')">
                <p class="text-xs text-gray-500">Total Ventas</p>
                <p class="text-lg font-bold text-blue-600">${{ formatearNumero(resumen.total) }}</p>
                <p class="text-xs text-gray-400">Click para filtrar</p>
            </div>
            <div class="bg-white p-3 rounded-lg shadow text-center cursor-pointer hover:shadow-lg transition"
                @click="aplicarFiltroEstado('pagado')">
                <p class="text-xs text-gray-500">Tickets</p>
                <p class="text-lg font-bold">{{ resumen.cantidad }}</p>
                <p class="text-xs text-gray-400">Click para filtrar</p>
            </div>
            <div class="bg-white p-3 rounded-lg shadow text-center cursor-pointer hover:shadow-lg transition"
                @click="aplicarFiltroEstado('pagado')">
                <p class="text-xs text-gray-500">Promedio</p>
                <p class="text-lg font-bold text-green-600">${{ formatearNumero(resumen.promedio) }}</p>
                <p class="text-xs text-gray-400">Click para filtrar</p>
            </div>
            <div class="bg-white p-3 rounded-lg shadow text-center cursor-pointer hover:shadow-lg transition"
                @click="aplicarFiltroEstado('cancelado')">
                <p class="text-xs text-gray-500">Canceladas</p>
                <p class="text-lg font-bold text-red-600">{{ resumen.canceladas }}</p>
                <p class="text-xs text-gray-400">Click para filtrar</p>
            </div>
        </div>

        <!-- Tabla -->
        <div v-if="!cargando && !error && ventas.length === 0 && ventasCargadas"
            class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            <p class="text-3xl mb-2">📭</p>
            <p>No hay ventas para el filtro seleccionado</p>
            <button @click="cargarTodasLasVentas" class="mt-2 text-blue-600 hover:underline text-sm">
                Ver todas las ventas
            </button>
        </div>

        <!-- Tabla Desktop -->
        <div v-if="!cargando && !error && ventas.length > 0" class="bg-white rounded-lg shadow overflow-hidden">
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Folio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="venta in ventas" :key="venta.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-sm font-medium text-blue-600 cursor-pointer hover:underline"
                                @click="verDetalle(venta.id)">
                                {{ venta.folio || 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-sm">{{ formatearFecha(venta.fecha) }}</td>
                            <td class="px-4 py-3 text-sm">{{ venta.cliente?.nombre || 'Cliente genérico' }}</td>
                            <td class="px-4 py-3 text-sm">{{ venta.usuario?.name || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold">
                                ${{ formatearNumero(venta.total) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span class="px-2 py-1 text-xs rounded-full cursor-pointer" :class="venta.estado === 'pagado' ? 'bg-green-100 text-green-800' :
                                    venta.estado === 'cancelado' ? 'bg-red-100 text-red-800' :
                                        'bg-yellow-100 text-yellow-800'" @click="aplicarFiltroEstado(venta.estado)">
                                    {{ venta.estado || 'N/A' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <div class="flex justify-center items-center gap-1">
                                    <button @click="verDetalle(venta.id)"
                                        class="text-blue-600 hover:text-blue-800 transition p-1" title="Ver detalle">
                                        👁️
                                    </button>
                                    <button v-if="venta.estado === 'pagado'" @click="anularVenta(venta.id)"
                                        class="text-red-600 hover:text-red-800 transition p-1" title="Anular venta">
                                        ❌
                                    </button>
                                    <!-- Ver ticket -->
                                    <button @click="imprimirTicket(venta.id)"
                                        class="text-blue-600 hover:text-blue-800 transition p-1"
                                        title="Ver ticket en navegador">
                                        👁️
                                    </button>

                                    <!-- Descargar ticket -->
                                    <button @click="descargarTicket(venta.id)"
                                        class="text-green-600 hover:text-green-800 transition p-1"
                                        title="Descargar ticket PDF">
                                        📥
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Móvil: Cards -->
            <div class="md:hidden divide-y divide-gray-200">
                <div v-for="venta in ventas" :key="venta.id" class="p-4 space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-semibold text-blue-600 cursor-pointer hover:underline"
                                @click="verDetalle(venta.id)">
                                {{ venta.folio || 'N/A' }}
                            </p>
                            <p class="text-xs text-gray-500">{{ formatearFecha(venta.fecha) }}</p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full cursor-pointer" :class="venta.estado === 'pagado' ? 'bg-green-100 text-green-800' :
                            venta.estado === 'cancelado' ? 'bg-red-100 text-red-800' :
                                'bg-yellow-100 text-yellow-800'" @click="aplicarFiltroEstado(venta.estado)">
                            {{ venta.estado || 'N/A' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-1 text-sm">
                        <span class="text-gray-500">Cliente:</span>
                        <span class="text-right">{{ venta.cliente?.nombre || 'Cliente genérico' }}</span>
                        <span class="text-gray-500">Vendedor:</span>
                        <span class="text-right">{{ venta.usuario?.name || '-' }}</span>
                        <span class="text-gray-500 font-semibold">Total:</span>
                        <span class="text-right font-bold">${{ formatearNumero(venta.total) }}</span>
                    </div>
                    <div class="flex gap-2 pt-2 border-t flex-wrap">
                        <button @click="verDetalle(venta.id)"
                            class="flex-1 py-1 text-sm text-blue-600 hover:bg-blue-50 rounded transition">
                            👁️ Detalle
                        </button>
                        <button v-if="venta.estado === 'pagado'" @click="anularVenta(venta.id)"
                            class="flex-1 py-1 text-sm text-red-600 hover:bg-red-50 rounded transition">
                            ❌ Anular
                        </button>
                        <button @click="imprimirTicket(venta.id)"
                            class="flex-1 py-1 text-sm text-gray-600 hover:bg-gray-50 rounded transition">
                            🖨️ Ticket
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="paginacion && paginacion.last_page > 1"
                class="px-4 py-3 border-t flex flex-col sm:flex-row justify-between items-center gap-2">
                <span class="text-sm text-gray-600">
                    Mostrando {{ ventas.length }} de {{ paginacion.total || ventas.length }}
                </span>
                <div class="flex gap-1">
                    <button v-if="paginacion.current_page > 1" @click="cambiarPagina(paginacion.current_page - 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 transition text-sm">
                        Anterior
                    </button>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                        {{ paginacion.current_page || 1 }}
                    </span>
                    <button v-if="paginacion.current_page < paginacion.last_page"
                        @click="cambiarPagina(paginacion.current_page + 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 transition text-sm">
                        Siguiente
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL DETALLE VENTA                          -->
        <!-- ============================================ -->
        <div v-if="mostrarDetalle"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Detalle de Venta</h2>
                    <button @click="cerrarDetalle" class="text-gray-500 hover:text-gray-700 text-xl">✕</button>
                </div>

                <div v-if="detalleCargando" class="text-center py-8">
                    <span class="inline-block animate-spin mr-2">⟳</span>
                    Cargando...
                </div>

                <div v-else-if="detalleError" class="text-red-500 text-center py-8">
                    {{ detalleError }}
                </div>

                <div v-else-if="ventaDetalle" class="space-y-4">
                    <!-- Info general -->
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <p class="text-xs text-gray-500">Folio</p>
                                <p class="font-bold text-blue-600">{{ ventaDetalle.folio || 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Fecha</p>
                                <p class="font-semibold">{{ formatearFecha(ventaDetalle.fecha) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Estado</p>
                                <span class="px-2 py-1 text-xs rounded-full" :class="ventaDetalle.estado === 'pagado' ? 'bg-green-100 text-green-800' :
                                    ventaDetalle.estado === 'cancelado' ? 'bg-red-100 text-red-800' :
                                        'bg-yellow-100 text-yellow-800'">
                                    {{ ventaDetalle.estado || 'N/A' }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Total</p>
                                <p class="font-bold text-lg text-blue-600">${{ formatearNumero(ventaDetalle.total) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Cliente y Vendedor -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <p class="text-xs text-gray-500 font-semibold">👤 Cliente</p>
                            <p class="font-medium">{{ ventaDetalle.cliente?.nombre || 'Cliente genérico' }}</p>
                            <p class="text-sm text-gray-500">{{ ventaDetalle.cliente?.email || 'Sin email' }}</p>
                            <p class="text-sm text-gray-500">{{ ventaDetalle.cliente?.telefono || 'Sin teléfono' }}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <p class="text-xs text-gray-500 font-semibold">👤 Vendedor</p>
                            <p class="font-medium">{{ ventaDetalle.usuario?.name || '-' }}</p>
                            <p class="text-sm text-gray-500">{{ ventaDetalle.usuario?.email || '-' }}</p>
                        </div>
                    </div>

                    <!-- Productos -->
                    <div>
                        <h3 class="font-semibold text-sm mb-2 flex items-center gap-2">
                            📦 Productos
                            <span class="text-xs text-gray-400">({{ ventaDetalle.detalles?.length || 0 }} items)</span>
                        </h3>
                        <div class="bg-white border rounded-lg overflow-hidden">
                            <table class="w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Producto</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Cant</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Precio</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Desc.</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="detalle in ventaDetalle.detalles" :key="detalle.id"
                                        class="border-t hover:bg-gray-50">
                                        <td class="px-3 py-2 text-sm">
                                            <p class="font-medium">{{ detalle.producto?.nombre || 'Producto' }}</p>
                                            <p class="text-xs text-gray-400">{{ detalle.producto?.codigo || '' }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-center">{{ detalle.cantidad || 0 }}</td>
                                        <td class="px-3 py-2 text-sm text-right">${{
                                            formatearNumero(detalle.precio_unitario) }}</td>
                                        <td class="px-3 py-2 text-sm text-right text-green-600">
                                            ${{ formatearNumero(detalle.descuento || 0) }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-right font-semibold">
                                            ${{ formatearNumero(detalle.subtotal) }}
                                        </td>
                                    </tr>
                                    <tr class="bg-gray-50 font-semibold">
                                        <td colspan="3" class="px-3 py-2 text-right text-sm">Subtotal:</td>
                                        <td colspan="2" class="px-3 py-2 text-right text-sm">${{
                                            formatearNumero(ventaDetalle.subtotal) }}</td>
                                    </tr>
                                    <tr v-if="ventaDetalle.descuento > 0" class="bg-gray-50">
                                        <td colspan="3" class="px-3 py-2 text-right text-sm text-green-600">Descuento:
                                        </td>
                                        <td colspan="2" class="px-3 py-2 text-right text-sm text-green-600">-${{
                                            formatearNumero(ventaDetalle.descuento) }}</td>
                                    </tr>
                                    <tr class="bg-gray-50">
                                        <td colspan="3" class="px-3 py-2 text-right text-sm">Impuesto ({{
                                            ventaDetalle.impuesto || 0 }}%):</td>
                                        <td colspan="2" class="px-3 py-2 text-right text-sm">${{
                                            formatearNumero(ventaDetalle.impuesto || 0) }}</td>
                                    </tr>
                                    <tr class="bg-blue-50 font-bold">
                                        <td colspan="3" class="px-3 py-2 text-right text-sm text-blue-600">TOTAL:</td>
                                        <td colspan="2" class="px-3 py-2 text-right text-lg text-blue-600">${{
                                            formatearNumero(ventaDetalle.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagos -->
                    <div>
                        <h3 class="font-semibold text-sm mb-2 flex items-center gap-2">
                            💳 Pagos
                            <span class="text-xs text-gray-400">({{ ventaDetalle.pagos?.length || 0 }}
                                transacciones)</span>
                        </h3>
                        <div class="bg-white border rounded-lg overflow-hidden">
                            <table class="w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Forma de Pago
                                        </th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Monto</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Cambio</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Referencia
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="pago in ventaDetalle.pagos" :key="pago.id"
                                        class="border-t hover:bg-gray-50">
                                        <td class="px-3 py-2 text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full" :class="pago.forma_pago === 'Efectivo' ? 'bg-green-100 text-green-800' :
                                                pago.forma_pago === 'Tarjeta Crédito' ? 'bg-blue-100 text-blue-800' :
                                                    pago.forma_pago === 'Tarjeta Débito' ? 'bg-indigo-100 text-indigo-800' :
                                                        pago.forma_pago === 'Transferencia' ? 'bg-purple-100 text-purple-800' :
                                                            'bg-gray-100 text-gray-800'">
                                                {{ pago.forma_pago || 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-right font-semibold">
                                            ${{ formatearNumero(pago.monto) }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-right">
                                            {{ pago.cambio > 0 ? '$' + formatearNumero(pago.cambio) : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-500">
                                            {{ pago.referencia || '-' }}
                                        </td>
                                    </tr>
                                    <tr class="bg-gray-50 font-semibold">
                                        <td class="px-3 py-2 text-right text-sm" colspan="1">Total Pagado:</td>
                                        <td class="px-3 py-2 text-right text-sm text-blue-600">
                                            ${{ formatearNumero(totalPagado) }}
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr v-if="cambioTotal > 0" class="bg-green-50">
                                        <td class="px-3 py-2 text-right text-sm text-green-600" colspan="1">Cambio
                                            Total:</td>
                                        <td class="px-3 py-2 text-right text-sm text-green-600">
                                            ${{ formatearNumero(cambioTotal) }}
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Notas -->
                    <div v-if="ventaDetalle.notas" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-xs text-gray-500 font-semibold">📝 Notas</p>
                        <p class="text-sm">{{ ventaDetalle.notas }}</p>
                    </div>

                    <!-- Acciones -->
                    <div class="flex flex-wrap justify-end gap-2 pt-2 border-t">
                        <button v-if="ventaDetalle?.estado === 'pagado'" @click="anularVenta(ventaDetalle.id)"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm">
                            ❌ Anular Venta
                        </button>
                        <button @click="imprimirTicket(ventaDetalle?.id)"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-sm">
                            🖨️ Imprimir Ticket
                        </button>
                        <button @click="cerrarDetalle"
                            class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition text-sm">
                            Cerrar
                        </button>
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
    name: 'HistorialVentas',
    data() {
        return {
            ventas: [],
            paginacion: null,
            resumen: { total: 0, cantidad: 0, promedio: 0, canceladas: 0 },
            cargando: false,
            error: null,
            ventasCargadas: false,
            filtros: {
                fecha_desde: '',
                fecha_hasta: '',
                estado: ''
            },
            mostrarDetalle: false,
            detalleCargando: false,
            detalleError: null,
            ventaDetalle: null
        };
    },
    computed: {
        totalPagado() {
            if (!this.ventaDetalle || !this.ventaDetalle.pagos) return 0;
            return this.ventaDetalle.pagos.reduce((sum, p) => sum + parseFloat(p.monto || 0), 0);
        },
        cambioTotal() {
            if (!this.ventaDetalle || !this.ventaDetalle.pagos) return 0;
            return this.ventaDetalle.pagos.reduce((sum, p) => sum + parseFloat(p.cambio || 0), 0);
        }
    },
    mounted() {
        this.cargarTodasLasVentas();
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
                return new Date(fecha).toLocaleDateString('es-MX', {
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
        aplicarFiltroEstado(estado) {
            if (!estado) return;
            // Si ya está filtrado por ese estado, quitar el filtro
            if (this.filtros.estado === estado) {
                this.filtros.estado = '';
            } else {
                this.filtros.estado = estado;
            }
            this.cargarVentas();
        },
        cargarVentasHoy() {
            const hoy = new Date();
            const hoyStr = hoy.toISOString().split('T')[0];
            this.filtros.fecha_desde = hoyStr;
            this.filtros.fecha_hasta = hoyStr;
            this.filtros.estado = '';
            this.cargarVentas();
        },
        cargarTodasLasVentas() {
            this.filtros.fecha_desde = '';
            this.filtros.fecha_hasta = '';
            this.filtros.estado = '';
            this.cargarVentas();
        },
        limpiarFiltros() {
            this.filtros.fecha_desde = '';
            this.filtros.fecha_hasta = '';
            this.filtros.estado = '';
            this.cargarVentas();
        },
        async cargarVentas() {
            this.cargando = true;
            this.error = null;
            try {
                const params = {};
                if (this.filtros.fecha_desde) params.fecha_desde = this.filtros.fecha_desde;
                if (this.filtros.fecha_hasta) params.fecha_hasta = this.filtros.fecha_hasta;
                if (this.filtros.estado) params.estado = this.filtros.estado;

                const res = await api.get('/ventas', { params });

                const responseData = res.data;
                let ventasData = [];
                let paginationData = null;

                if (responseData && responseData.data) {
                    if (Array.isArray(responseData.data)) {
                        ventasData = responseData.data;
                        paginationData = {
                            current_page: responseData.current_page || 1,
                            last_page: responseData.last_page || 1,
                            total: responseData.total || ventasData.length
                        };
                    } else if (responseData.data.data && Array.isArray(responseData.data.data)) {
                        ventasData = responseData.data.data;
                        paginationData = {
                            current_page: responseData.data.current_page || 1,
                            last_page: responseData.data.last_page || 1,
                            total: responseData.data.total || ventasData.length
                        };
                    } else {
                        for (const key in responseData.data) {
                            if (Array.isArray(responseData.data[key])) {
                                ventasData = responseData.data[key];
                                break;
                            }
                        }
                    }
                } else if (Array.isArray(responseData)) {
                    ventasData = responseData;
                }

                this.ventas = ventasData;
                this.paginacion = paginationData;
                this.ventasCargadas = true;

                if (this.ventas.length > 0) {
                    const pagadas = this.ventas.filter(v => v.estado === 'pagado');
                    const canceladas = this.ventas.filter(v => v.estado === 'cancelado');
                    this.resumen = {
                        total: pagadas.reduce((sum, v) => sum + parseFloat(v.total || 0), 0),
                        cantidad: pagadas.length,
                        promedio: pagadas.length > 0 ? pagadas.reduce((sum, v) => sum + parseFloat(v.total || 0), 0) / pagadas.length : 0,
                        canceladas: canceladas.length
                    };
                } else {
                    this.resumen = { total: 0, cantidad: 0, promedio: 0, canceladas: 0 };
                }

            } catch (error) {
                console.error('❌ Error:', error);
                this.error = error.response?.data?.message || 'Error al cargar ventas';
            } finally {
                this.cargando = false;
            }
        },
        async cambiarPagina(page) {
            if (page < 1 || page > this.paginacion.last_page) return;
            this.cargando = true;
            try {
                const params = { page, ...this.filtros };
                const res = await api.get('/ventas', { params });
                const responseData = res.data;
                if (responseData && responseData.data && Array.isArray(responseData.data)) {
                    this.ventas = responseData.data;
                } else if (responseData && responseData.data && responseData.data.data && Array.isArray(responseData.data.data)) {
                    this.ventas = responseData.data.data;
                } else {
                    this.ventas = [];
                }
                this.paginacion.current_page = page;
            } catch (error) {
                console.error('Error:', error);
            } finally {
                this.cargando = false;
            }
        },
        async verDetalle(id) {
            this.mostrarDetalle = true;
            this.detalleCargando = true;
            this.detalleError = null;
            this.ventaDetalle = null;

            try {
                const res = await api.get(`/ventas/${id}`);
                this.ventaDetalle = res.data.data || res.data;
            } catch (error) {
                this.detalleError = error.response?.data?.message || 'Error al cargar detalle';
            } finally {
                this.detalleCargando = false;
            }
        },
        cerrarDetalle() {
            this.mostrarDetalle = false;
            this.ventaDetalle = null;
        },
        async imprimirTicket(id) {
            try {
                Swal.fire({
                    title: 'Generando ticket...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const res = await api.get(
                    `/ventas/${id}/ticket`,
                    {
                        responseType: 'blob'
                    }
                );

                // Verificar que realmente sea PDF
                if (!res.data || res.data.size === 0) {
                    throw new Error('El servidor no generó el PDF');
                }

                const blob = new Blob(
                    [res.data],
                    { type: 'application/pdf' }
                );

                const blobUrl = window.URL.createObjectURL(blob);

                Swal.close();

                // Abrir PDF en nueva pestaña
                const nuevaVentana = window.open(
                    blobUrl,
                    '_blank'
                );

                if (!nuevaVentana) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'Ventana bloqueada',
                        text: 'El navegador bloqueó la ventana. Permite ventanas emergentes para este sitio.',
                        confirmButtonText: 'Entendido'
                    });

                    // Liberar después
                    setTimeout(() => {
                        window.URL.revokeObjectURL(blobUrl);
                    }, 10000);

                    return;
                }

                // Liberar memoria después de un tiempo
                setTimeout(() => {
                    window.URL.revokeObjectURL(blobUrl);
                }, 60000);

            } catch (error) {

                Swal.close();

                console.error('❌ Error generando ticket:', error);

                // Intentar leer error cuando Laravel devuelve JSON aunque
                // Axios esté configurado como blob
                let mensaje = 'No se pudo generar el ticket';

                if (error.response?.data instanceof Blob) {

                    try {
                        const texto = await error.response.data.text();

                        const json = JSON.parse(texto);

                        mensaje =
                            json.message ||
                            json.error ||
                            mensaje;

                    } catch (e) {
                        console.error('Error leyendo respuesta:', e);
                    }

                } else {

                    mensaje =
                        error.response?.data?.message ||
                        error.message ||
                        mensaje;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error al generar ticket',
                    text: mensaje
                });
            }
        },

        async descargarTicket(id) {
            try {

                Swal.fire({
                    title: 'Generando PDF...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const res = await api.get(
                    `/ventas/${id}/ticket`,
                    {
                        params: {
                            download: 1
                        },
                        responseType: 'blob'
                    }
                );

                if (!res.data || res.data.size === 0) {
                    throw new Error('El servidor no generó el PDF');
                }

                const blob = new Blob(
                    [res.data],
                    { type: 'application/pdf' }
                );

                const blobUrl = window.URL.createObjectURL(blob);

                const link = document.createElement('a');

                link.href = blobUrl;

                link.download = `ticket_${id}.pdf`;

                document.body.appendChild(link);

                link.click();

                document.body.removeChild(link);

                setTimeout(() => {
                    window.URL.revokeObjectURL(blobUrl);
                }, 1000);

                Swal.close();

                Swal.fire({
                    icon: 'success',
                    title: 'Ticket descargado',
                    timer: 1500,
                    showConfirmButton: false
                });

            } catch (error) {

                Swal.close();

                console.error('❌ Error descargando ticket:', error);

                let mensaje = 'No se pudo descargar el ticket';

                if (error.response?.data instanceof Blob) {

                    try {

                        const texto = await error.response.data.text();

                        const json = JSON.parse(texto);

                        mensaje =
                            json.message ||
                            json.error ||
                            mensaje;

                    } catch (e) {
                        console.error(e);
                    }

                } else {

                    mensaje =
                        error.response?.data?.message ||
                        error.message ||
                        mensaje;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: mensaje
                });
            }
        },
        async anularVenta(id) {
            const result = await Swal.fire({
                title: '¿Anular esta venta?',
                text: 'Esta acción no se puede deshacer. El stock se restaurará.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, anular',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    await api.post(`/ventas/${id}/anular`);
                    await this.cargarVentas();
                    if (this.mostrarDetalle) this.cerrarDetalle();
                    Swal.fire({ icon: 'success', title: 'Venta anulada', timer: 2000, showConfirmButton: false });
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al anular' });
                }
            }
        },
        async exportarVentas() {
            try {
                const params = {};
                if (this.filtros.fecha_desde) params.fecha_desde = this.filtros.fecha_desde;
                if (this.filtros.fecha_hasta) params.fecha_hasta = this.filtros.fecha_hasta;
                if (this.filtros.estado) params.estado = this.filtros.estado;

                const res = await api.get('/ventas/exportar', { params, responseType: 'blob' });
                const url = window.URL.createObjectURL(new Blob([res.data]));
                const link = document.createElement('a');
                link.href = url;
                link.download = `ventas_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                Swal.fire({ icon: 'success', title: 'Exportación exitosa', timer: 2000, showConfirmButton: false });
            } catch {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al exportar ventas' });
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
</style>