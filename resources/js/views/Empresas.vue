<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h1 class="text-2xl font-bold">Empresas</h1>

            <button
                @click="abrirModal()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"
                v-if="esSuperadmin"
            >
                + Nueva Empresa
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
                        placeholder="Buscar empresa..."
                        @input="cargarEmpresas"
                    />
                </div>

                <div class="flex gap-2">
                    <button
                        @click="cargarEmpresas"
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
            Cargando empresas...
        </div>

        <!-- Sin empresas -->
        <div
            v-else-if="empresas.length === 0"
            class="bg-white rounded-lg shadow p-8 text-center text-gray-500"
        >
            No hay empresas registradas
        </div>

        <!-- Tabla -->
        <div v-else class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Logo
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Nombre
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                RFC
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Teléfono
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Email
                            </th>

                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                Estado
                            </th>

                            <th
                                v-if="esSuperadmin"
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"
                            >
                                Licencia
                            </th>

                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr
                            v-for="empresa in empresas"
                            :key="empresa.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-4 py-3">
                                <div
                                    class="w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center"
                                >
                                    <img
                                        :src="empresa.logo_url || '/img/logo.png'"
                                        :alt="empresa.nombre"
                                        class="w-full h-full object-cover"
                                        @error="handleImageError(empresa)"
                                        loading="lazy"
                                    />
                                </div>
                            </td>

                            <td class="px-4 py-3 text-sm font-medium">
                                {{ empresa.nombre }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ empresa.rfc || '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ empresa.telefono || '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ empresa.email_contacto || '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm text-center">
                                <span
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="
                                        empresa.activo
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800'
                                    "
                                >
                                    {{ empresa.activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>

                            <td
                                v-if="esSuperadmin"
                                class="px-4 py-3 text-sm text-center"
                            >
                                <div class="flex flex-col items-center gap-1">

                                    <span
                                        class="px-2 py-1 text-xs rounded-full"
                                        :class="claseEstadoLicencia(empresa)"
                                    >
                                        {{ textoEstadoLicencia(empresa) }}
                                    </span>

                                    <span
                                        v-if="empresa.licencia_tipo"
                                        class="text-xs text-gray-500"
                                    >
                                        {{ nombreTipoLicencia(empresa.licencia_tipo) }}
                                    </span>

                                    <span
                                        v-if="
                                            empresa.licencia_fecha_fin &&
                                            empresa.licencia_tipo !== 'permanente'
                                        "
                                        class="text-xs text-gray-400"
                                    >
                                        {{ formatearFecha(empresa.licencia_fecha_fin) }}
                                    </span>

                                </div>
                            </td>

                            <td class="px-4 py-3 text-sm text-center">

                                <button
                                    @click="abrirModal(empresa)"
                                    class="text-blue-600 hover:text-blue-800 mr-2"
                                    title="Editar empresa"
                                >
                                    ✏️
                                </button>

                                <button
                                    @click="abrirModalLicencia(empresa)"
                                    class="text-purple-600 hover:text-purple-800 mr-2"
                                    title="Administrar licencia"
                                >
                                    🔑
                                </button>

                                <button
                                    v-if="esSuperadmin"
                                    @click="eliminarEmpresa(empresa.id)"
                                    class="text-red-600 hover:text-red-800"
                                    title="Eliminar empresa"
                                >
                                    🗑️
                                </button>

                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="paginacion && paginacion.last_page > 1"
                class="px-4 py-3 border-t flex flex-col sm:flex-row justify-between items-center gap-2"
            >
                <span class="text-sm text-gray-600">
                    Mostrando {{ empresas.length }}
                    de {{ paginacion.total || empresas.length }}
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

        <!-- ============================================ -->
        <!-- MODAL EMPRESA -->
        <!-- ============================================ -->

        <div
            v-if="modalVisible"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        >
            <div
                class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto"
            >
                <h2 class="text-xl font-bold mb-4">
                    {{ editando ? 'Editar' : 'Nueva' }} Empresa
                </h2>

                <form @submit.prevent="guardarEmpresa">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <!-- Datos básicos -->

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Nombre *
                            </label>

                            <input
                                v-model="form.nombre"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                RFC
                            </label>

                            <input
                                v-model="form.rfc"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Razón Social
                            </label>

                            <input
                                v-model="form.razon_social"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Teléfono
                            </label>

                            <input
                                v-model="form.telefono"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Email Contacto
                            </label>

                            <input
                                v-model="form.email_contacto"
                                type="email"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Dirección
                            </label>

                            <textarea
                                v-model="form.direccion"
                                class="w-full px-3 py-2 border rounded-lg"
                                rows="2"
                            ></textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Leyenda del Ticket
                            </label>

                            <input
                                v-model="form.leyenda_ticket"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg"
                                placeholder="¡Gracias por su compra!"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                WhatsApp
                            </label>

                            <input
                                v-model="form.whatsapp_numero"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg"
                            />
                        </div>

                        <!-- COLORES -->

                        <div class="md:col-span-2 border-t pt-4 mt-2">

                            <h3 class="text-md font-semibold mb-3">
                                Colores de la Empresa
                            </h3>

                            <p class="text-xs text-gray-500 mb-3">
                                El color principal se aplica al sidebar, menú y header
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Color Principal *
                                    </label>

                                    <div class="flex items-center gap-2">

                                        <input
                                            type="color"
                                            v-model="form.colores.primary"
                                            class="w-12 h-12 rounded cursor-pointer border"
                                        />

                                        <input
                                            type="text"
                                            v-model="form.colores.primary"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm"
                                        />

                                    </div>

                                    <p class="text-xs text-gray-400 mt-1">
                                        Sidebar · Menú · Header
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Color Secundario
                                    </label>

                                    <div class="flex items-center gap-2">

                                        <input
                                            type="color"
                                            v-model="form.colores.secondary"
                                            class="w-12 h-12 rounded cursor-pointer border"
                                        />

                                        <input
                                            type="text"
                                            v-model="form.colores.secondary"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm"
                                        />

                                    </div>

                                    <p class="text-xs text-gray-400 mt-1">
                                        Bordes y detalles
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Color de Fondo
                                    </label>

                                    <div class="flex items-center gap-2">

                                        <input
                                            type="color"
                                            v-model="form.colores.background"
                                            class="w-12 h-12 rounded cursor-pointer border"
                                        />

                                        <input
                                            type="text"
                                            v-model="form.colores.background"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm"
                                        />

                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Color de Texto
                                    </label>

                                    <div class="flex items-center gap-2">

                                        <input
                                            type="color"
                                            v-model="form.colores.text"
                                            class="w-12 h-12 rounded cursor-pointer border"
                                        />

                                        <input
                                            type="text"
                                            v-model="form.colores.text"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm"
                                        />

                                    </div>

                                    <p class="text-xs text-gray-400 mt-1">
                                        Texto en sidebar y menú
                                    </p>
                                </div>

                            </div>
                        </div>

                        <!-- MÓDULOS -->

                        <div class="md:col-span-2 border-t pt-4 mt-2">

                            <h3 class="text-md font-semibold mb-3">
                                Módulos del sistema
                            </h3>

                            <div class="flex flex-col gap-2">

                                <div class="flex items-center gap-3">

                                    <input
                                        type="checkbox"
                                        id="edit_usa_mesas"
                                        v-model="form.configuracion.usa_mesas"
                                        class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />

                                    <label
                                        for="edit_usa_mesas"
                                        class="text-sm font-medium text-gray-700"
                                    >
                                        Activar módulo de mesas (restaurante / comandas)
                                    </label>

                                </div>

                                <div class="flex items-center gap-3">

                                    <input
                                        type="checkbox"
                                        id="edit_usa_cajas"
                                        v-model="form.configuracion.usa_cajas"
                                        class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />

                                    <label
                                        for="edit_usa_cajas"
                                        class="text-sm font-medium text-gray-700"
                                    >
                                        Activar módulo de cajas (múltiples puntos de venta)
                                    </label>

                                </div>

                            </div>
                        </div>

                        <!-- LOGO -->

                        <div class="md:col-span-2 border-t pt-4 mt-2">

                            <label class="block text-sm font-medium text-gray-700">
                                Logo
                            </label>

                            <div class="flex flex-col items-center gap-4">

                                <div class="relative">

                                    <img
                                        :src="logoPreview || '/img/logo.png'"
                                        alt="Logo"
                                        class="w-32 h-32 rounded-full object-cover border-4 border-gray-200"
                                    />

                                    <div
                                        v-if="logoFile"
                                        class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-full"
                                    >
                                        <span class="text-white text-xs">
                                            ✏️ Editando
                                        </span>
                                    </div>

                                </div>

                                <div class="w-full">

                                    <div class="flex flex-wrap gap-2">

                                        <input
                                            type="file"
                                            @change="onFileChange"
                                            accept="image/*"
                                            class="flex-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                        />

                                        <button
                                            v-if="logoFile"
                                            @click="resetLogo"
                                            type="button"
                                            class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm"
                                        >
                                            ✕
                                        </button>

                                    </div>

                                    <div
                                        v-if="logoFile"
                                        class="mt-3 bg-gray-50 p-3 rounded-lg"
                                    >

                                        <p class="text-xs font-medium text-gray-700 mb-2">
                                            Ajustar imagen
                                        </p>

                                        <div
                                            class="relative bg-gray-200 rounded-lg overflow-hidden"
                                            style="height: 200px;"
                                        >

                                            <canvas
                                                ref="canvasEditor"
                                                class="w-full h-full object-contain cursor-move"
                                                @mousedown="iniciarArrastre"
                                                @mousemove="moverArrastre"
                                                @mouseup="terminarArrastre"
                                                @mouseleave="terminarArrastre"
                                                @wheel.prevent="zoomConRueda"
                                            ></canvas>

                                        </div>

                                        <div class="mt-3 space-y-2">

                                            <div>

                                                <label class="text-xs text-gray-600">
                                                    Zoom
                                                </label>

                                                <div class="flex items-center gap-2">

                                                    <button
                                                        @click="zoomImagen(-0.1)"
                                                        type="button"
                                                        class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-xs"
                                                    >
                                                        ➖
                                                    </button>

                                                    <span class="text-xs font-medium w-12 text-center">
                                                        {{ Math.round(zoom * 100) }}%
                                                    </span>

                                                    <button
                                                        @click="zoomImagen(0.1)"
                                                        type="button"
                                                        class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-xs"
                                                    >
                                                        ➕
                                                    </button>

                                                    <button
                                                        @click="rotarImagen(90)"
                                                        type="button"
                                                        class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs"
                                                    >
                                                        🔄 Rotar 90°
                                                    </button>

                                                    <button
                                                        @click="restaurarImagen"
                                                        type="button"
                                                        class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 text-xs"
                                                    >
                                                        ↩️ Restaurar
                                                    </button>

                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between text-xs text-gray-400">

                                                <span>
                                                    🖱️ Arrastra para mover
                                                </span>

                                                <span>
                                                    🔍 Rueda para zoom
                                                </span>

                                                <span>
                                                    📐 {{ Math.round(imagenInfo.ancho || 0) }}x{{
                                                        Math.round(imagenInfo.alto || 0)
                                                    }}
                                                </span>

                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activo -->

                        <div class="flex items-center md:col-span-2">

                            <input
                                v-model="form.activo"
                                type="checkbox"
                                class="mr-2"
                            />

                            <label class="text-sm font-medium text-gray-700">
                                Activo
                            </label>

                        </div>

                    </div>

                    <div
                        v-if="error"
                        class="mt-3 p-2 bg-red-100 text-red-700 rounded text-sm"
                    >
                        {{ error }}
                    </div>

                    <div class="flex justify-end gap-2 mt-4">

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

        <!-- ============================================ -->
        <!-- MODAL ADMINISTRACIÓN DE LICENCIA -->
        <!-- ============================================ -->

        <div
            v-if="modalLicenciaVisible && esSuperadmin"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] p-4"
        >
            <div class="bg-white rounded-lg p-6 w-full max-w-lg">

                <div class="flex items-center justify-between mb-5">

                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            Administrar licencia
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            {{ empresaLicencia?.nombre || '' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="cerrarModalLicencia"
                        class="text-gray-400 hover:text-gray-700 text-xl"
                    >
                        ✕
                    </button>

                </div>

                <form @submit.prevent="guardarLicencia">

                    <!-- Tipo -->

                    <div class="mb-4">

                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de licencia
                        </label>

                        <select
                            v-model="formLicencia.licencia_tipo"
                            @change="actualizarFechaVencimiento"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            required
                        >

                            <option value="dia">
                                Día
                            </option>

                            <option value="semana">
                                Semana
                            </option>

                            <option value="quincena">
                                Quincena
                            </option>

                            <option value="mes">
                                Mes
                            </option>

                            <option value="bimestre">
                                Bimestre
                            </option>

                            <option value="trimestre">
                                Trimestre
                            </option>

                            <option value="semestre">
                                Semestre
                            </option>

                            <option value="anual">
                                Anual
                            </option>

                            <option value="permanente">
                                Permanente
                            </option>

                        </select>

                    </div>

                    <!-- Fecha inicio -->

                    <div class="mb-4">

                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Fecha de inicio
                        </label>

                        <input
                            v-model="formLicencia.licencia_fecha_inicio"
                            @change="actualizarFechaVencimiento"
                            type="datetime-local"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            :disabled="formLicencia.licencia_tipo === 'permanente'"
                        />

                        <p class="text-xs text-gray-500 mt-1">
                            Si no existe, se asignará automáticamente la fecha actual.
                        </p>

                    </div>

                    <!-- Fecha fin -->

                    <div
                        v-if="formLicencia.licencia_tipo !== 'permanente'"
                        class="mb-4"
                    >

                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Fecha de vencimiento
                        </label>

                        <input
                            v-model="formLicencia.licencia_fecha_fin"
                            type="datetime-local"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            required
                        />

                        <p class="text-xs text-gray-500 mt-1">
                            Se calcula automáticamente al cambiar el tipo o la fecha de inicio.
                        </p>

                    </div>

                    <!-- Permanente -->

                    <div
                        v-if="formLicencia.licencia_tipo === 'permanente'"
                        class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg"
                    >

                        <div class="flex items-center gap-2">

                            <span class="text-green-600 text-lg">
                                ✓
                            </span>

                            <div>

                                <p class="text-sm font-semibold text-green-800">
                                    Licencia permanente
                                </p>

                                <p class="text-xs text-green-700">
                                    No tendrá fecha de vencimiento.
                                </p>

                            </div>

                        </div>

                    </div>

                    <!-- Activa -->

                    <div class="mb-5">

                        <label class="flex items-center gap-2 cursor-pointer">

                            <input
                                v-model="formLicencia.licencia_activa"
                                type="checkbox"
                                class="w-4 h-4 text-purple-600 rounded"
                            />

                            <span class="text-sm font-medium text-gray-700">
                                Licencia activa
                            </span>

                        </label>

                        <p class="text-xs text-gray-500 mt-1 ml-6">
                            Desactivar la licencia impedirá que la empresa utilice el POS.
                        </p>

                    </div>

                    <!-- Resumen -->

                    <div
                        v-if="empresaLicencia"
                        class="mb-5 p-4 bg-gray-50 rounded-lg border"
                    >

                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">
                            Estado actual
                        </p>

                        <div class="grid grid-cols-2 gap-3 text-sm">

                            <div>

                                <span class="text-gray-500">
                                    Empresa:
                                </span>

                                <div class="font-medium">
                                    {{ empresaLicencia.nombre }}
                                </div>

                            </div>

                            <div>

                                <span class="text-gray-500">
                                    Estado:
                                </span>

                                <div
                                    class="font-medium"
                                    :class="
                                        formLicencia.licencia_activa
                                            ? 'text-green-600'
                                            : 'text-red-600'
                                    "
                                >
                                    {{
                                        formLicencia.licencia_activa
                                            ? 'Activa'
                                            : 'Desactivada'
                                    }}
                                </div>

                            </div>

                            <div>

                                <span class="text-gray-500">
                                    Tipo:
                                </span>

                                <div class="font-medium">
                                    {{ nombreTipoLicencia(formLicencia.licencia_tipo) }}
                                </div>

                            </div>

                            <div
                                v-if="formLicencia.licencia_tipo !== 'permanente'"
                            >

                                <span class="text-gray-500">
                                    Vencimiento:
                                </span>

                                <div class="font-medium">
                                    {{
                                        formLicencia.licencia_fecha_fin
                                            ? formatearFecha(formLicencia.licencia_fecha_fin)
                                            : '-'
                                    }}
                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- Error -->

                    <div
                        v-if="errorLicencia"
                        class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm"
                    >
                        {{ errorLicencia }}
                    </div>

                    <!-- Botones -->

                    <div class="flex justify-end gap-2">

                        <button
                            type="button"
                            @click="cerrarModalLicencia"
                            class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            :disabled="guardandoLicencia"
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50"
                        >
                            {{
                                guardandoLicencia
                                    ? 'Guardando...'
                                    : 'Guardar licencia'
                            }}
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
    name: 'Empresas',

    data() {
        return {
            empresas: [],
            paginacion: null,
            cargando: false,
            guardando: false,
            error: null,

            modalVisible: false,
            editando: false,

            logoPreview: null,
            logoFile: null,

            esSuperadmin: false,

            filtros: {
                search: ''
            },

            form: {
                id: null,
                nombre: '',
                rfc: '',
                razon_social: '',
                telefono: '',
                email_contacto: '',
                direccion: '',
                leyenda_ticket: '',
                whatsapp_numero: '',

                colores: {
                    primary: '#1E293B',
                    secondary: '#108981',
                    background: '#f3f4f6',
                    text: '#FFFFFF',
                    text_navbar: '#FFFFFF',
                    menu_hover: '#2d3748'
                },

                configuracion: {
                    usa_mesas: false,
                    usa_cajas: false
                },

                activo: true
            },

            // Editor de imagen
            imagenOriginal: null,
            imagenEditada: null,
            zoom: 1,
            rotacion: 0,
            offsetX: 0,
            offsetY: 0,
            isDragging: false,
            startX: 0,
            startY: 0,

            imagenInfo: {
                ancho: 0,
                alto: 0
            },

            // Licencia
            modalLicenciaVisible: false,
            empresaLicencia: null,

            formLicencia: {
                licencia_tipo: 'mes',
                licencia_fecha_inicio: null,
                licencia_fecha_fin: null,
                licencia_activa: true
            },

            guardandoLicencia: false,
            errorLicencia: null
        };
    },

    mounted() {
        this.verificarRol();
        this.cargarEmpresas();
    },

    methods: {

        verificarRol() {
            const userData = localStorage.getItem('user');

            if (userData) {
                try {
                    const user = JSON.parse(userData);

                    this.esSuperadmin = user.rol === 'superadmin';

                } catch (e) {
                    console.error('Error parsing user:', e);
                }
            }
        },

        async cargarEmpresas() {

            this.cargando = true;

            try {

                const params = {};

                if (this.filtros.search) {
                    params.search = this.filtros.search;
                }

                const res = await api.get('/empresas', {
                    params
                });

                this.empresas = res.data.data || [];

                this.paginacion = {
                    current_page: res.data.current_page || 1,
                    last_page: res.data.last_page || 1,
                    total: res.data.total || this.empresas.length
                };

                // Corregir URLs de logos
                this.empresas = this.empresas.map(emp => {

                    if (emp.logo_url) {

                        if (
                            emp.logo_url.includes('localhost') &&
                            !emp.logo_url.includes('localhost:')
                        ) {
                            emp.logo_url = emp.logo_url.replace(
                                'localhost',
                                'localhost:8000'
                            );
                        }

                        if (emp.logo_url.startsWith('/')) {
                            emp.logo_url =
                                `${window.location.origin}${emp.logo_url}`;
                        }

                        const timestamp = Date.now();

                        emp.logo_url =
                            `${emp.logo_url}${emp.logo_url.includes('?') ? '&' : '?'}t=${timestamp}`;

                    } else if (emp.logo) {

                        const baseUrl = window.location.origin;
                        const timestamp = Date.now();

                        emp.logo_url =
                            `${baseUrl}/storage/${emp.logo}?t=${timestamp}`;
                    }

                    return emp;
                });

            } catch (error) {

                console.error('Error cargando empresas:', error);

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron cargar las empresas'
                });

            } finally {

                this.cargando = false;
            }
        },

        async cambiarPagina(page) {

            this.cargando = true;

            try {

                const res = await api.get('/empresas', {
                    params: {
                        page,
                        ...this.filtros
                    }
                });

                this.empresas = res.data.data || [];

                this.paginacion.current_page = page;

            } catch (error) {

                console.error('Error cargando página:', error);

            } finally {

                this.cargando = false;
            }
        },

        limpiarFiltros() {

            this.filtros.search = '';

            this.cargarEmpresas();
        },

        // =========================================================
        // EDITOR DE IMAGEN
        // =========================================================

        onFileChange(event) {

            const file = event.target.files[0];

            if (!file) {
                return;
            }

            this.logoFile = file;

            const reader = new FileReader();

            reader.onload = (e) => {

                this.logoPreview = e.target.result;
                this.imagenOriginal = e.target.result;

                this.zoom = 1;
                this.rotacion = 0;
                this.offsetX = 0;
                this.offsetY = 0;

                this.imagenInfo = {
                    ancho: 0,
                    alto: 0
                };

                this.$nextTick(() => {
                    this.dibujarImagen();
                });
            };

            reader.readAsDataURL(file);
        },

        resetLogo() {

            this.logoFile = null;
            this.logoPreview = null;
            this.imagenOriginal = null;
            this.imagenEditada = null;

            this.zoom = 1;
            this.rotacion = 0;
            this.offsetX = 0;
            this.offsetY = 0;
        },

        dibujarImagen() {

            const canvas = this.$refs.canvasEditor;

            if (!canvas || !this.imagenOriginal) {
                return;
            }

            const ctx = canvas.getContext('2d');

            const img = new Image();

            img.onload = () => {

                const containerWidth =
                    canvas.parentElement.clientWidth || 400;

                const containerHeight =
                    canvas.parentElement.clientHeight || 200;

                canvas.width = containerWidth;
                canvas.height = containerHeight;

                const ancho = img.width * this.zoom;
                const alto = img.height * this.zoom;

                const offsetX = this.offsetX || 0;
                const offsetY = this.offsetY || 0;

                this.imagenInfo = {
                    ancho,
                    alto
                };

                ctx.clearRect(
                    0,
                    0,
                    canvas.width,
                    canvas.height
                );

                ctx.fillStyle = '#e5e7eb';

                ctx.fillRect(
                    0,
                    0,
                    canvas.width,
                    canvas.height
                );

                ctx.strokeStyle = 'rgba(0,0,0,0.1)';
                ctx.lineWidth = 1;

                for (
                    let i = 0;
                    i < canvas.width;
                    i += 30
                ) {

                    ctx.beginPath();

                    ctx.moveTo(i, 0);
                    ctx.lineTo(i, canvas.height);

                    ctx.stroke();
                }

                for (
                    let i = 0;
                    i < canvas.height;
                    i += 30
                ) {

                    ctx.beginPath();

                    ctx.moveTo(0, i);
                    ctx.lineTo(canvas.width, i);

                    ctx.stroke();
                }

                ctx.save();

                ctx.translate(
                    canvas.width / 2 + offsetX,
                    canvas.height / 2 + offsetY
                );

                ctx.rotate(
                    (this.rotacion || 0) * Math.PI / 180
                );

                ctx.drawImage(
                    img,
                    -ancho / 2,
                    -alto / 2,
                    ancho,
                    alto
                );

                ctx.restore();

                ctx.strokeStyle = 'rgba(59, 130, 246, 0.8)';
                ctx.lineWidth = 2;
                ctx.setLineDash([5, 5]);

                const radio =
                    Math.min(canvas.width, canvas.height) * 0.4;

                ctx.beginPath();

                ctx.arc(
                    canvas.width / 2,
                    canvas.height / 2,
                    radio,
                    0,
                    2 * Math.PI
                );

                ctx.stroke();

                ctx.setLineDash([]);

                ctx.fillStyle = 'rgba(59, 130, 246, 0.6)';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';

                ctx.fillText(
                    'Área de recorte',
                    canvas.width / 2,
                    20
                );
            };

            img.src = this.imagenOriginal;
        },

        zoomImagen(valor) {

            this.zoom = Math.max(
                0.2,
                Math.min(3, this.zoom + valor)
            );

            this.dibujarImagen();
        },

        zoomConRueda(event) {

            const delta =
                event.deltaY > 0
                    ? -0.05
                    : 0.05;

            this.zoomImagen(delta);
        },

        rotarImagen(angulo) {

            this.rotacion =
                (this.rotacion + angulo) % 360;

            this.dibujarImagen();
        },

        restaurarImagen() {

            this.zoom = 1;
            this.rotacion = 0;
            this.offsetX = 0;
            this.offsetY = 0;

            this.dibujarImagen();
        },

        iniciarArrastre(event) {

            this.isDragging = true;

            this.startX = event.clientX;
            this.startY = event.clientY;
        },

        moverArrastre(event) {

            if (!this.isDragging) {
                return;
            }

            const dx =
                event.clientX - this.startX;

            const dy =
                event.clientY - this.startY;

            this.offsetX += dx;
            this.offsetY += dy;

            this.startX = event.clientX;
            this.startY = event.clientY;

            this.dibujarImagen();
        },

        terminarArrastre() {

            this.isDragging = false;
        },

        obtenerImagenRecortada() {

            const canvas =
                this.$refs.canvasEditor;

            if (!canvas) {
                return null;
            }

            const tempCanvas =
                document.createElement('canvas');

            const ctx =
                tempCanvas.getContext('2d');

            const size =
                Math.min(canvas.width, canvas.height) * 0.8;

            const x =
                (canvas.width - size) / 2;

            const y =
                (canvas.height - size) / 2;

            tempCanvas.width = size;
            tempCanvas.height = size;

            ctx.beginPath();

            ctx.arc(
                size / 2,
                size / 2,
                size / 2,
                0,
                2 * Math.PI
            );

            ctx.clip();

            ctx.drawImage(
                canvas,
                -x,
                -y
            );

            return tempCanvas.toDataURL('image/png');
        },

        // =========================================================
        // EMPRESA
        // =========================================================

        abrirModal(empresa = null) {

            if (empresa) {

                this.editando = true;

                let coloresEmpresa =
                    empresa.colores;

                if (typeof coloresEmpresa === 'string') {

                    try {

                        coloresEmpresa =
                            JSON.parse(coloresEmpresa);

                    } catch (e) {

                        console.error(
                            'Error parseando colores:',
                            e
                        );

                        coloresEmpresa = {};
                    }
                }

                const coloresForm = {

                    primary:
                        coloresEmpresa?.primary ||
                        '#1E293B',

                    secondary:
                        coloresEmpresa?.secondary ||
                        '#108981',

                    background:
                        coloresEmpresa?.background ||
                        '#f3f4f6',

                    text:
                        coloresEmpresa?.text ||
                        '#FFFFFF',

                    text_navbar:
                        coloresEmpresa?.text_navbar ||
                        '#FFFFFF',

                    menu_hover:
                        coloresEmpresa?.menu_hover ||
                        '#2d3748'
                };

                let config =
                    empresa.configuracion || {};

                if (typeof config === 'string') {

                    try {

                        config =
                            JSON.parse(config);

                    } catch (e) {

                        config = {};
                    }
                }

                const configForm = {

                    usa_mesas:
                        config.usa_mesas ?? false,

                    usa_cajas:
                        config.usa_cajas ?? false
                };

                this.form = {

                    ...empresa,

                    colores: coloresForm,

                    configuracion: configForm
                };

                this.logoPreview =
                    empresa.logo_url || null;

                this.logoFile = null;

            } else {

                this.editando = false;

                this.form = {

                    id: null,

                    nombre: '',

                    rfc: '',

                    razon_social: '',

                    telefono: '',

                    email_contacto: '',

                    direccion: '',

                    leyenda_ticket: '',

                    whatsapp_numero: '',

                    colores: {

                        primary: '#1E293B',
                        secondary: '#108981',
                        background: '#f3f4f6',
                        text: '#FFFFFF',
                        text_navbar: '#FFFFFF',
                        menu_hover: '#2d3748'
                    },

                    configuracion: {

                        usa_mesas: false,
                        usa_cajas: false
                    },

                    activo: true
                };

                this.logoPreview = null;
                this.logoFile = null;
            }

            this.error = null;

            this.modalVisible = true;
        },

        cerrarModal() {

            this.modalVisible = false;

            this.logoFile = null;

            this.error = null;
        },

        async guardarEmpresa() {

            this.guardando = true;

            this.error = null;

            try {

                const formData =
                    new FormData();

                const datos = {

                    nombre:
                        this.form.nombre || '',

                    rfc:
                        this.form.rfc || '',

                    razon_social:
                        this.form.razon_social || '',

                    telefono:
                        this.form.telefono || '',

                    email_contacto:
                        this.form.email_contacto || '',

                    direccion:
                        this.form.direccion || '',

                    leyenda_ticket:
                        this.form.leyenda_ticket || '',

                    whatsapp_numero:
                        this.form.whatsapp_numero || '',

                    activo:
                        this.form.activo === true ||
                        this.form.activo === '1' ||
                        this.form.activo === 1,

                    configuracion:
                        JSON.stringify(
                            this.form.configuracion
                        )
                };

                const coloresMapeados = {

                    primary:
                        this.form.colores.primary ||
                        '#1E293B',

                    secondary:
                        this.form.colores.secondary ||
                        '#108981',

                    background:
                        this.form.colores.background ||
                        '#f3f4f6',

                    text:
                        this.form.colores.text ||
                        '#FFFFFF',

                    text_navbar:
                        this.form.colores.text_navbar ||
                        '#FFFFFF',

                    menu_hover:
                        this.form.colores.menu_hover ||
                        '#2d3748'
                };

                formData.append(
                    'colores',
                    JSON.stringify(coloresMapeados)
                );

                Object.keys(datos).forEach(key => {

                    if (
                        datos[key] !== null &&
                        datos[key] !== undefined
                    ) {

                        if (key === 'activo') {

                            formData.append(
                                key,
                                datos[key] ? '1' : '0'
                            );

                        } else {

                            formData.append(
                                key,
                                datos[key]
                            );
                        }
                    }
                });

                // Logo únicamente si se seleccionó uno nuevo
                if (
                    this.logoFile &&
                    this.$refs.canvasEditor
                ) {

                    const croppedImage =
                        this.obtenerImagenRecortada();

                    if (croppedImage) {

                        const blob =
                            await fetch(croppedImage)
                                .then(res => res.blob());

                        formData.append(
                            'logo',
                            blob,
                            'logo.webp'
                        );
                    }
                }

                let response;

                if (this.editando) {

                    formData.append(
                        '_method',
                        'PUT'
                    );

                    response =
                        await api.post(
                            `/empresas/${this.form.id}`,
                            formData,
                            {
                                headers: {
                                    'Content-Type':
                                        'multipart/form-data'
                                }
                            }
                        );

                } else {

                    response =
                        await api.post(
                            '/empresas',
                            formData,
                            {
                                headers: {
                                    'Content-Type':
                                        'multipart/form-data'
                                }
                            }
                        );
                }

                console.log(
                    'Respuesta del servidor:',
                    response.data
                );

                this.cerrarModal();

                await new Promise(
                    resolve => setTimeout(resolve, 500)
                );

                await this.cargarEmpresas();

                if (
                    response.data &&
                    response.data.data &&
                    response.data.data.colores
                ) {

                    let coloresGuardados =
                        response.data.data.colores;

                    if (
                        typeof coloresGuardados === 'string'
                    ) {

                        try {

                            coloresGuardados =
                                JSON.parse(
                                    coloresGuardados
                                );

                        } catch (e) {

                            console.error(
                                'Error parseando colores:',
                                e
                            );
                        }
                    }

                    this.aplicarColores(
                        coloresGuardados
                    );
                }

                window.dispatchEvent(
                    new CustomEvent(
                        'recargar-colores'
                    )
                );

                window.dispatchEvent(
                    new CustomEvent(
                        'recargar-logo'
                    )
                );

                Swal.fire({
                    icon: 'success',
                    title: this.editando
                        ? 'Empresa actualizada'
                        : 'Empresa creada',
                    timer: 1500,
                    showConfirmButton: false
                });

            } catch (error) {

                console.error(
                    'Error completo:',
                    error
                );

                console.error(
                    'Error response:',
                    error.response
                );

                this.error =
                    error.response?.data?.message ||
                    'Error al guardar';

            } finally {

                this.guardando = false;
            }
        },

        async eliminarEmpresa(id) {

            const result =
                await Swal.fire({

                    title: '¿Eliminar empresa?',

                    text: 'Esta acción no se puede deshacer',

                    icon: 'warning',

                    showCancelButton: true,

                    confirmButtonColor: '#d33',

                    confirmButtonText:
                        'Sí, eliminar',

                    cancelButtonText:
                        'Cancelar'
                });

            if (result.isConfirmed) {

                try {

                    await api.delete(
                        `/empresas/${id}`
                    );

                    await this.cargarEmpresas();

                    Swal.fire({

                        icon: 'success',

                        title: 'Eliminada',

                        timer: 1500,

                        showConfirmButton: false
                    });

                } catch (error) {

                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text:
                            error.response?.data?.message ||
                            'Error al eliminar'
                    });
                }
            }
        },

        handleImageError(empresa) {

            console.log(
                'Error cargando imagen para:',
                empresa.nombre,
                'URL:',
                empresa.logo_url
            );

            empresa.logo_url = null;

            this.$forceUpdate();
        },

        getLogoUrl(empresa) {

            return empresa.logo_url ||
                '/img/logo.png';
        },

        aplicarColores(colores) {

            if (!colores) {
                return;
            }

            const mapeo = {

                primary:
                    'colorPrincipal',

                secondary:
                    'colorSecundario',

                background:
                    'fondo',

                text:
                    'colorTexto',

                text_navbar:
                    'colorTextoNavbar',

                menu_hover:
                    'colorMenu'
            };

            Object.keys(mapeo).forEach(
                keyBackend => {

                    const keyLocal =
                        mapeo[keyBackend];

                    if (colores[keyBackend]) {

                        localStorage.setItem(
                            keyLocal,
                            colores[keyBackend]
                        );
                    }
                }
            );

            localStorage.setItem(
                'colores_empresa',
                JSON.stringify(colores)
            );

            window.dispatchEvent(
                new CustomEvent(
                    'recargar-colores'
                )
            );
        },

        // =========================================================
        // LICENCIAS
        // =========================================================

        abrirModalLicencia(empresa) {

            this.empresaLicencia = empresa;

            /*
             * IMPORTANTE:
             *
             * No reemplazamos las fechas existentes por null.
             * Si la empresa ya tiene licencia, se conserva.
             */

            let fechaInicio =
                this.normalizarFechaParaInput(
                    empresa.licencia_fecha_inicio
                );

            let fechaFin =
                this.normalizarFechaParaInput(
                    empresa.licencia_fecha_fin
                );

            const tipo =
                empresa.licencia_tipo ||
                'mes';

            /*
             * Si no existe fecha de inicio,
             * usamos HOY.
             *
             * No calculamos aquí una nueva fecha fin
             * si ya existe una fecha fin.
             */
            if (!fechaInicio) {

                fechaInicio =
                    this.obtenerFechaActualLocal();
            }

            /*
             * Si es permanente, no debe existir
             * fecha de vencimiento.
             */
            if (tipo === 'permanente') {

                fechaFin = null;

            } else if (!fechaFin) {

                /*
                 * Si no existe fecha fin,
                 * la calculamos automáticamente
                 * usando la fecha de inicio.
                 */
                fechaFin =
                    this.calcularFechaVencimiento(
                        fechaInicio,
                        tipo
                    );
            }

            this.formLicencia = {

                licencia_tipo: tipo,

                licencia_fecha_inicio:
                    fechaInicio,

                licencia_fecha_fin:
                    fechaFin,

                licencia_activa:
                    empresa.licencia_activa !== undefined
                        ? Boolean(empresa.licencia_activa)
                        : true
            };

            this.modalLicenciaVisible = true;

            this.errorLicencia = null;
        },

        cerrarModalLicencia() {

            this.modalLicenciaVisible = false;

            this.empresaLicencia = null;

            this.errorLicencia = null;
        },

        /*
         * Obtener fecha/hora local actual
         * en formato compatible con datetime-local.
         *
         * Ejemplo:
         * 2026-09-14T15:30
         */
        obtenerFechaActualLocal() {

            const ahora = new Date();

            const year =
                ahora.getFullYear();

            const month =
                String(
                    ahora.getMonth() + 1
                ).padStart(2, '0');

            const day =
                String(
                    ahora.getDate()
                ).padStart(2, '0');

            const hours =
                String(
                    ahora.getHours()
                ).padStart(2, '0');

            const minutes =
                String(
                    ahora.getMinutes()
                ).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        },

        /*
         * Convierte fechas provenientes del backend
         * a formato YYYY-MM-DDTHH:mm.
         */
        normalizarFechaParaInput(fecha) {

            if (!fecha) {
                return null;
            }

            /*
             * Si ya viene como datetime-local:
             * YYYY-MM-DDTHH:mm
             */
            if (
                typeof fecha === 'string' &&
                /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(fecha)
            ) {
                return fecha;
            }

            const date =
                new Date(fecha);

            if (isNaN(date.getTime())) {
                return null;
            }

            /*
             * Usamos la hora local del navegador
             * para que datetime-local no haga
             * conversiones UTC inesperadas.
             */
            const year =
                date.getFullYear();

            const month =
                String(
                    date.getMonth() + 1
                ).padStart(2, '0');

            const day =
                String(
                    date.getDate()
                ).padStart(2, '0');

            const hours =
                String(
                    date.getHours()
                ).padStart(2, '0');

            const minutes =
                String(
                    date.getMinutes()
                ).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        },

        /*
         * Calcula la fecha de vencimiento
         * dependiendo del tipo de licencia.
         */
        calcularFechaVencimiento(
            fechaInicio,
            tipo
        ) {

            if (
                !fechaInicio ||
                !tipo ||
                tipo === 'permanente'
            ) {
                return null;
            }

            /*
             * Parseamos manualmente para evitar
             * problemas de UTC del navegador.
             */
            const partes =
                fechaInicio.split('T');

            const fecha =
                partes[0];

            const hora =
                partes[1] || '00:00';

            const [year, month, day] =
                fecha.split('-').map(Number);

            const [hours, minutes] =
                hora.split(':').map(Number);

            const resultado =
                new Date(
                    year,
                    month - 1,
                    day,
                    hours,
                    minutes,
                    0,
                    0
                );

            switch (tipo) {

                case 'dia':

                    resultado.setDate(
                        resultado.getDate() + 1
                    );

                    break;

                case 'semana':

                    resultado.setDate(
                        resultado.getDate() + 7
                    );

                    break;

                case 'quincena':

                    resultado.setDate(
                        resultado.getDate() + 15
                    );

                    break;

                case 'mes':

                    resultado.setMonth(
                        resultado.getMonth() + 1
                    );

                    break;

                case 'bimestre':

                    resultado.setMonth(
                        resultado.getMonth() + 2
                    );

                    break;

                case 'trimestre':

                    resultado.setMonth(
                        resultado.getMonth() + 3
                    );

                    break;

                case 'semestre':

                    resultado.setMonth(
                        resultado.getMonth() + 6
                    );

                    break;

                case 'anual':

                    resultado.setFullYear(
                        resultado.getFullYear() + 1
                    );

                    break;

                default:

                    return null;
            }

            return this.formatearParaDateTimeLocal(
                resultado
            );
        },

        /*
         * Convierte Date a YYYY-MM-DDTHH:mm
         */
        formatearParaDateTimeLocal(date) {

            const year =
                date.getFullYear();

            const month =
                String(
                    date.getMonth() + 1
                ).padStart(2, '0');

            const day =
                String(
                    date.getDate()
                ).padStart(2, '0');

            const hours =
                String(
                    date.getHours()
                ).padStart(2, '0');

            const minutes =
                String(
                    date.getMinutes()
                ).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        },

        /*
         * Se ejecuta cuando cambia:
         *
         * - tipo de licencia
         * - fecha de inicio
         *
         * El vencimiento se recalcula.
         */
        actualizarFechaVencimiento() {

            /*
             * Permanente:
             * siempre elimina la fecha fin.
             */
            if (
                this.formLicencia.licencia_tipo ===
                'permanente'
            ) {

                this.formLicencia.licencia_fecha_fin =
                    null;

                return;
            }

            /*
             * Si no existe inicio,
             * ponemos hoy.
             */
            if (
                !this.formLicencia.licencia_fecha_inicio
            ) {

                this.formLicencia.licencia_fecha_inicio =
                    this.obtenerFechaActualLocal();
            }

            /*
             * Siempre recalculamos la fecha fin
             * al cambiar tipo o inicio.
             */
            this.formLicencia.licencia_fecha_fin =
                this.calcularFechaVencimiento(
                    this.formLicencia.licencia_fecha_inicio,
                    this.formLicencia.licencia_tipo
                );
        },

        /*
         * Guarda la licencia.
         *
         * Mantiene exactamente los nombres
         * de campos utilizados actualmente.
         */
        async guardarLicencia() {

            this.guardandoLicencia = true;

            this.errorLicencia = null;

            try {

                /*
                 * Si no hay fecha de inicio,
                 * asignar hoy.
                 */
                if (
                    !this.formLicencia.licencia_fecha_inicio
                ) {

                    this.formLicencia.licencia_fecha_inicio =
                        this.obtenerFechaActualLocal();
                }

                /*
                 * Permanente:
                 * fecha fin siempre NULL.
                 */
                if (
                    this.formLicencia.licencia_tipo ===
                    'permanente'
                ) {

                    this.formLicencia.licencia_fecha_fin =
                        null;

                } else {

                    /*
                     * Si falta fecha de vencimiento,
                     * calcularla.
                     */
                    if (
                        !this.formLicencia.licencia_fecha_fin
                    ) {

                        this.formLicencia.licencia_fecha_fin =
                            this.calcularFechaVencimiento(
                                this.formLicencia.licencia_fecha_inicio,
                                this.formLicencia.licencia_tipo
                            );
                    }
                }

                /*
                 * Construimos explícitamente el objeto.
                 *
                 * NO enviamos campos adicionales.
                 * Esto evita afectar otras partes
                 * de la aplicación.
                 */
                const datosLicencia = {

                    licencia_tipo:
                        this.formLicencia.licencia_tipo,

                    licencia_fecha_inicio:
                        this.formLicencia.licencia_fecha_inicio,

                    licencia_fecha_fin:
                        this.formLicencia.licencia_fecha_fin,

                    licencia_activa:
                        this.formLicencia.licencia_activa
                            ? true
                            : false
                };

                console.log(
                    'Guardando licencia:',
                    datosLicencia
                );

                await api.put(
                    `/admin/empresas/${this.empresaLicencia.id}/licencia`,
                    datosLicencia
                );

                this.cerrarModalLicencia();

                await this.cargarEmpresas();

                Swal.fire({

                    icon: 'success',

                    title: 'Licencia actualizada',

                    timer: 1500,

                    showConfirmButton: false
                });

            } catch (error) {

                console.error(
                    'Error guardando licencia:',
                    error
                );

                console.error(
                    'Respuesta:',
                    error.response
                );

                this.errorLicencia =
                    error.response?.data?.message ||
                    'Error al guardar licencia';

            } finally {

                this.guardandoLicencia = false;
            }
        },

        /*
         * Estado de licencia para la tabla.
         *
         * Respeta la fecha de inicio.
         */
        licenciaActiva(empresa) {

            if (!empresa.licencia_activa) {
                return false;
            }

            if (!empresa.licencia_tipo) {
                return false;
            }

            if (
                empresa.licencia_tipo ===
                'permanente'
            ) {
                return true;
            }

            if (
                !empresa.licencia_fecha_inicio ||
                !empresa.licencia_fecha_fin
            ) {
                return false;
            }

            const ahora =
                new Date();

            const inicio =
                new Date(
                    empresa.licencia_fecha_inicio
                );

            const fin =
                new Date(
                    empresa.licencia_fecha_fin
                );

            return (
                ahora >= inicio &&
                ahora <= fin
            );
        },

        claseEstadoLicencia(empresa) {

            if (
                empresa.licencia_tipo ===
                'permanente' &&
                empresa.licencia_activa
            ) {

                return 'bg-green-100 text-green-800';
            }

            if (
                empresa.licencia_fecha_fin &&
                empresa.licencia_activa
            ) {

                const ahora =
                    new Date();

                const fin =
                    new Date(
                        empresa.licencia_fecha_fin
                    );

                /*
                 * Periodo de gracia de 3 días.
                 */
                const limiteGracia =
                    new Date(fin);

                limiteGracia.setDate(
                    limiteGracia.getDate() + 3
                );

                if (ahora <= fin) {

                    return 'bg-green-100 text-green-800';

                } else if (ahora <= limiteGracia) {

                    return 'bg-yellow-100 text-yellow-800';
                }
            }

            return 'bg-red-100 text-red-800';
        },

        textoEstadoLicencia(empresa) {

            if (!empresa.licencia_activa) {
                return 'Desactivada';
            }

            if (
                empresa.licencia_tipo ===
                'permanente'
            ) {
                return 'Permanente';
            }

            if (
                !empresa.licencia_fecha_inicio ||
                !empresa.licencia_fecha_fin
            ) {
                return 'Sin configurar';
            }

            const ahora =
                new Date();

            const inicio =
                new Date(
                    empresa.licencia_fecha_inicio
                );

            const fin =
                new Date(
                    empresa.licencia_fecha_fin
                );

            if (ahora < inicio) {
                return 'No iniciada';
            }

            if (ahora <= fin) {
                return 'Activa';
            }

            const limiteGracia =
                new Date(fin);

            limiteGracia.setDate(
                limiteGracia.getDate() + 3
            );

            if (ahora <= limiteGracia) {
                return 'En gracia';
            }

            return 'Vencida';
        },

        nombreTipoLicencia(tipo) {

            const map = {

                dia: '1 día',

                semana: '7 días',

                quincena: '15 días',

                mes: '1 mes',

                bimestre: '2 meses',

                trimestre: '3 meses',

                semestre: '6 meses',

                anual: '1 año',

                permanente: 'Permanente'
            };

            return map[tipo] || tipo;
        },

        formatearFecha(fecha) {

            if (!fecha) {
                return '-';
            }

            const date =
                new Date(fecha);

            if (isNaN(date.getTime())) {
                return '-';
            }

            return date.toLocaleDateString(
                'es-MX'
            );
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
