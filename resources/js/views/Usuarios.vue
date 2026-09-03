<template>
    <div>
        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h1 class="text-2xl font-bold">
                Usuarios
            </h1>

            <button
                @click="abrirModal()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                + Nuevo Usuario
            </button>
        </div>

        <!-- BUSCADOR -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    v-model="filtros.search"
                    @keyup.enter="cargarUsuarios"
                    type="text"
                    class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Buscar por nombre, email o número..."
                />

                <button
                    @click="cargarUsuarios"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                >
                    Buscar
                </button>
            </div>
        </div>

        <!-- LOADING -->
        <div
            v-if="loading"
            class="text-center py-8"
        >
            <span class="inline-block animate-spin mr-2">
                ⟳
            </span>

            Cargando...
        </div>

        <!-- ERROR -->
        <div
            v-else-if="error"
            class="bg-red-100 text-red-700 p-4 rounded-lg"
        >
            {{ error }}
        </div>

        <!-- CONTENIDO -->
        <div
            v-else
            class="bg-white rounded-lg shadow overflow-hidden"
        >
            <!-- DESKTOP -->
            <div class="hidden md:block overflow-x-auto">

                <table class="min-w-[1150px] w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">
                        <tr>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Número
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Nombre
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Empresa
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Email
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Rol
                            </th>

                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                Licencia
                            </th>

                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                Estado
                            </th>

                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                Acciones
                            </th>

                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">

                        <tr v-if="usuarios.length === 0">
                            <td
                                colspan="8"
                                class="px-6 py-8 text-center text-sm text-gray-500"
                            >
                                No hay usuarios registrados
                            </td>
                        </tr>

                        <tr
                            v-for="user in usuarios"
                            :key="user.id"
                            class="hover:bg-gray-50 transition-colors"
                        >

                            <!-- NUMERO -->
                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                {{ user.numero_usuario || '-' }}
                            </td>

                            <!-- NOMBRE -->
                            <td class="px-4 py-4 text-sm font-medium whitespace-nowrap">
                                {{ user.name }}
                            </td>

                            <!-- EMPRESA -->
                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                {{ user.empresa?.nombre || '-' }}
                            </td>

                            <!-- EMAIL -->
                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                {{ user.email }}
                            </td>

                            <!-- ROL -->
                            <td class="px-4 py-4 text-sm whitespace-nowrap">

                                <span
                                    class="inline-flex px-2 py-1 text-xs rounded-full"
                                    :class="claseRol(user.rol)"
                                >
                                    {{ nombreRol(user.rol) }}
                                </span>

                            </td>

                            <!-- LICENCIA -->
                            <td class="px-4 py-4 text-sm text-center">

                                <div
                                    v-if="user.empresa"
                                    class="flex flex-col items-center gap-1"
                                >

                                    <span
                                        class="inline-flex px-2 py-1 text-xs rounded-full"
                                        :class="claseLicencia(user.empresa)"
                                    >
                                        {{ textoLicencia(user.empresa) }}
                                    </span>

                                    <span
                                        v-if="user.empresa.dias_restantes !== null &&
                                              user.empresa.dias_restantes !== undefined &&
                                              user.empresa.licencia_vigente"
                                        class="text-xs text-gray-500"
                                    >
                                        {{ user.empresa.dias_restantes }} días
                                    </span>

                                </div>

                                <span
                                    v-else
                                    class="text-gray-400 text-xs"
                                >
                                    Sin empresa
                                </span>

                            </td>

                            <!-- ESTADO -->
                            <td class="px-4 py-4 text-center">

                                <span
                                    class="inline-flex px-2 py-1 text-xs rounded-full"
                                    :class="
                                        user.activo
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800'
                                    "
                                >
                                    {{ user.activo ? 'Activo' : 'Inactivo' }}
                                </span>

                            </td>

                            <!-- ACCIONES -->
                            <td class="px-4 py-4 text-sm text-center">

                                <div class="flex justify-center items-center gap-3">

                                    <button
                                        @click="abrirModal(user)"
                                        class="text-blue-600 hover:text-blue-800 font-medium"
                                    >
                                        Editar
                                    </button>

                                    <button
                                        v-if="user.empresa_id"
                                        @click="abrirLicencia(user)"
                                        class="text-purple-600 hover:text-purple-800 font-medium"
                                    >
                                        Licencia
                                    </button>

                                    <button
                                        @click="eliminarUsuario(user.id)"
                                        class="text-red-600 hover:text-red-800 font-medium"
                                    >
                                        Eliminar
                                    </button>

                                </div>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <!-- MOBILE -->
            <div class="md:hidden space-y-3 p-3">

                <div
                    v-if="usuarios.length === 0"
                    class="border border-gray-200 rounded-lg p-6 text-center text-sm text-gray-500"
                >
                    No hay usuarios registrados
                </div>

                <div
                    v-for="user in usuarios"
                    :key="user.id"
                    class="border border-gray-200 rounded-lg shadow-sm overflow-hidden"
                >

                    <!-- CABECERA -->
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b">

                        <div class="min-w-0">

                            <p class="text-sm font-semibold text-gray-900 truncate">
                                {{ user.name }}
                            </p>

                            <p class="text-xs text-gray-500 mt-0.5">
                                Nº {{ user.numero_usuario || '-' }}
                            </p>

                        </div>

                        <span
                            class="flex-shrink-0 inline-flex px-2 py-1 text-xs rounded-full"
                            :class="
                                user.activo
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-red-100 text-red-800'
                            "
                        >
                            {{ user.activo ? 'Activo' : 'Inactivo' }}
                        </span>

                    </div>

                    <!-- INFORMACIÓN -->
                    <div class="px-4 py-3 space-y-3">

                        <div>
                            <p class="text-xs font-medium text-gray-500">
                                Empresa
                            </p>

                            <p class="text-sm text-gray-900">
                                {{ user.empresa?.nombre || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-500">
                                Email
                            </p>

                            <p class="text-sm text-gray-900 break-all">
                                {{ user.email }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-500">
                                Teléfono
                            </p>

                            <p class="text-sm text-gray-900">
                                {{ user.telefono || '-' }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between">

                            <p class="text-xs font-medium text-gray-500">
                                Rol
                            </p>

                            <span
                                class="inline-flex px-2 py-1 text-xs rounded-full"
                                :class="claseRol(user.rol)"
                            >
                                {{ nombreRol(user.rol) }}
                            </span>

                        </div>

                        <div class="flex items-center justify-between">

                            <p class="text-xs font-medium text-gray-500">
                                Licencia
                            </p>

                            <div class="text-right">

                                <span
                                    v-if="user.empresa"
                                    class="inline-flex px-2 py-1 text-xs rounded-full"
                                    :class="claseLicencia(user.empresa)"
                                >
                                    {{ textoLicencia(user.empresa) }}
                                </span>

                                <p
                                    v-if="
                                        user.empresa &&
                                        user.empresa.dias_restantes !== null &&
                                        user.empresa.dias_restantes !== undefined &&
                                        user.empresa.licencia_vigente
                                    "
                                    class="text-xs text-gray-500 mt-1"
                                >
                                    {{ user.empresa.dias_restantes }} días restantes
                                </p>

                            </div>

                        </div>

                    </div>

                    <!-- ACCIONES -->
                    <div class="flex border-t">

                        <button
                            @click="abrirModal(user)"
                            class="flex-1 py-3 text-sm font-medium text-blue-600 hover:bg-blue-50"
                        >
                            Editar
                        </button>

                        <div class="w-px bg-gray-200"></div>

                        <button
                            v-if="user.empresa_id"
                            @click="abrirLicencia(user)"
                            class="flex-1 py-3 text-sm font-medium text-purple-600 hover:bg-purple-50"
                        >
                            Licencia
                        </button>

                        <div
                            v-if="user.empresa_id"
                            class="w-px bg-gray-200"
                        ></div>

                        <button
                            @click="eliminarUsuario(user.id)"
                            class="flex-1 py-3 text-sm font-medium text-red-600 hover:bg-red-50"
                        >
                            Eliminar
                        </button>

                    </div>

                </div>

            </div>

            <!-- PAGINACIÓN -->
            <div
                v-if="paginacion"
                class="px-6 py-4 border-t flex flex-col sm:flex-row justify-between items-center gap-3"
            >

                <span class="text-sm text-gray-600">
                    Mostrando
                    {{ usuarios.length }}
                    de
                    {{ paginacion.total || usuarios.length }}
                </span>

                <div class="flex space-x-2">

                    <button
                        v-if="paginacion.current_page > 1"
                        @click="cargarPagina(paginacion.current_page - 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 text-sm"
                    >
                        Anterior
                    </button>

                    <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                        {{ paginacion.current_page || 1 }}
                    </span>

                    <button
                        v-if="paginacion.current_page < paginacion.last_page"
                        @click="cargarPagina(paginacion.current_page + 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 text-sm"
                    >
                        Siguiente
                    </button>

                </div>

            </div>

        </div>

        <!-- =====================================================
             MODAL USUARIO
        ====================================================== -->

        <div
            v-if="modalVisible"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        >

            <div
                class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto"
            >

                <h2 class="text-xl font-bold mb-4">
                    {{ editando ? 'Editar' : 'Nuevo' }} Usuario
                </h2>

                <form @submit.prevent="guardarUsuario">

                    <div class="space-y-4">

                        <!-- NOMBRE -->
                        <div>

                            <label class="block text-sm font-medium text-gray-700">
                                Nombre *
                            </label>

                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            />

                        </div>

                        <!-- EMAIL -->
                        <div>

                            <label class="block text-sm font-medium text-gray-700">
                                Email *
                            </label>

                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            />

                        </div>

                        <!-- TELEFONO -->
                        <div>

                            <label class="block text-sm font-medium text-gray-700">
                                Teléfono
                            </label>

                            <input
                                v-model="form.telefono"
                                type="text"
                                maxlength="10"
                                inputmode="numeric"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="5512345678"
                            />

                            <p class="text-xs text-gray-400 mt-1">
                                Solo números, 10 dígitos
                            </p>

                        </div>

                        <!-- ROL -->
                        <div>

                            <label class="block text-sm font-medium text-gray-700">
                                Rol *
                            </label>

                            <select
                                v-model="form.rol"
                                class="w-full px-3 py-2 border rounded-lg"
                            >

                                <option value="vendedor">
                                    Vendedor
                                </option>

                                <option value="cajero">
                                    Cajero
                                </option>

                                <option value="admin">
                                    Administrador
                                </option>

                                <option value="superadmin">
                                    Super Admin
                                </option>

                            </select>

                        </div>

                        <!-- EMPRESA -->
                        <div>

                            <label class="block text-sm font-medium text-gray-700">
                                Empresa *
                            </label>

                            <select
                                v-model="form.empresa_id"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            >

                                <option
                                    v-for="empresa in empresas"
                                    :key="empresa.id"
                                    :value="empresa.id"
                                >
                                    {{ empresa.nombre }}
                                </option>

                            </select>

                        </div>

                        <!-- PASSWORD CREAR -->
                        <div v-if="!editando">

                            <label class="block text-sm font-medium text-gray-700">
                                Contraseña *
                            </label>

                            <input
                                v-model="form.password"
                                type="password"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            />

                            <p class="text-xs text-gray-400 mt-1">
                                Mínimo 6 caracteres
                            </p>

                        </div>

                        <!-- PASSWORD EDITAR -->
                        <div v-else>

                            <label class="block text-sm font-medium text-gray-700">
                                Contraseña
                            </label>

                            <input
                                v-model="form.password"
                                type="password"
                                class="w-full px-3 py-2 border rounded-lg"
                                placeholder="Dejar vacío para no cambiar"
                            />

                        </div>

                        <!-- ACTIVO -->
                        <div class="flex items-center">

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

                    <!-- ERROR -->
                    <div
                        v-if="errorModal"
                        class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm"
                    >
                        {{ errorModal }}
                    </div>

                    <!-- BOTONES -->
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

        <!-- =====================================================
             MODAL LICENCIA
        ====================================================== -->

        <div
            v-if="licenciaModalVisible"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] p-4"
        >

            <div
                class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto"
            >

                <div class="mb-6">

                    <h2 class="text-xl font-bold">
                        Administrar Licencia
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        {{ licenciaForm.empresa_nombre }}
                    </p>

                </div>

                <form @submit.prevent="guardarLicencia">

                    <div class="space-y-4">

                        <!-- TIPO -->
                        <div>

                            <label class="block text-sm font-medium text-gray-700">
                                Tipo de licencia *
                            </label>

                            <select
                                v-model="licenciaForm.licencia_tipo"
                                @change="calcularFechaFin"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            >

                                <option value="dia">
                                    1 día
                                </option>

                                <option value="semana">
                                    1 semana
                                </option>

                                <option value="quincena">
                                    15 días
                                </option>

                                <option value="mes">
                                    1 mes
                                </option>

                                <option value="bimestre">
                                    2 meses
                                </option>

                                <option value="trimestre">
                                    3 meses
                                </option>

                                <option value="semestre">
                                    6 meses
                                </option>

                                <option value="anual">
                                    1 año
                                </option>

                                <option value="permanente">
                                    Permanente
                                </option>

                            </select>

                        </div>

                        <!-- FECHA INICIO -->
                        <div>

                            <label class="block text-sm font-medium text-gray-700">
                                Fecha de inicio *
                            </label>

                            <input
                                v-model="licenciaForm.licencia_fecha_inicio"
                                @change="calcularFechaFin"
                                type="date"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            />

                        </div>

                        <!-- FECHA FIN -->
                        <div
                            v-if="
                                licenciaForm.licencia_tipo !==
                                'permanente'
                            "
                        >

                            <label class="block text-sm font-medium text-gray-700">
                                Fecha de vencimiento *
                            </label>

                            <input
                                v-model="licenciaForm.licencia_fecha_fin"
                                type="date"
                                class="w-full px-3 py-2 border rounded-lg"
                                required
                            />

                        </div>

                        <!-- ACTIVA -->
                        <div class="flex items-center">

                            <input
                                v-model="licenciaForm.licencia_activa"
                                type="checkbox"
                                class="mr-2"
                            />

                            <label class="text-sm font-medium text-gray-700">
                                Licencia activa
                            </label>

                        </div>

                        <!-- ESTADO -->
                        <div
                            v-if="licenciaInfo"
                            class="p-4 rounded-lg border bg-gray-50"
                        >

                            <p class="text-sm font-semibold text-gray-700">
                                Estado actual
                            </p>

                            <div class="mt-2">

                                <span
                                    class="inline-flex px-2 py-1 text-xs rounded-full"
                                    :class="
                                        licenciaInfo.licencia_vigente
                                            ? 'bg-green-100 text-green-800'
                                            : licenciaInfo.licencia_pendiente
                                                ? 'bg-yellow-100 text-yellow-800'
                                                : 'bg-red-100 text-red-800'
                                    "
                                >
                                    {{ textoEstadoLicencia(licenciaInfo) }}
                                </span>

                            </div>

                            <p
                                v-if="
                                    licenciaInfo.dias_restantes !== null &&
                                    licenciaInfo.dias_restantes !== undefined
                                "
                                class="text-xs text-gray-500 mt-2"
                            >
                                Días restantes:
                                <strong>
                                    {{ licenciaInfo.dias_restantes }}
                                </strong>
                            </p>

                        </div>

                    </div>

                    <!-- ERROR -->
                    <div
                        v-if="errorLicencia"
                        class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm"
                    >
                        {{ errorLicencia }}
                    </div>

                    <!-- BOTONES -->
                    <div class="flex justify-end gap-2 mt-6">

                        <button
                            type="button"
                            @click="cerrarLicencia"
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
    name: 'Usuarios',

    data() {
        return {
            usuarios: [],
            empresas: [],

            paginacion: null,

            loading: true,
            error: null,

            modalVisible: false,
            editando: false,
            guardando: false,
            errorModal: null,

            licenciaModalVisible: false,
            guardandoLicencia: false,
            errorLicencia: null,
            licenciaInfo: null,

            form: {
                id: null,
                name: '',
                email: '',
                telefono: '',
                password: '',
                rol: 'vendedor',
                empresa_id: null,
                activo: true
            },

            licenciaForm: {
                empresa_id: null,
                empresa_nombre: '',
                licencia_tipo: 'mes',
                licencia_fecha_inicio: '',
                licencia_fecha_fin: '',
                licencia_activa: true
            },

            filtros: {
                search: ''
            }
        };
    },

    mounted() {
        this.cargarUsuarios();
        this.cargarEmpresas();
    },

    methods: {

        /*
        |--------------------------------------------------------------------------
        | USUARIOS
        |--------------------------------------------------------------------------
        */

        async cargarUsuarios(page = 1) {

            this.loading = true;
            this.error = null;

            try {

                const params = {
                    page
                };

                if (this.filtros.search) {
                    params.search =
                        this.filtros.search;
                }

                const response =
                    await api.get(
                        '/admin/usuarios',
                        { params }
                    );

                if (
                    response.data &&
                    Array.isArray(response.data.data)
                ) {

                    this.usuarios =
                        response.data.data;

                    this.paginacion = {
                        current_page:
                            response.data.current_page || 1,

                        last_page:
                            response.data.last_page || 1,

                        total:
                            response.data.total || 0
                    };

                } else {

                    this.usuarios =
                        Array.isArray(
                            response.data
                        )
                            ? response.data
                            : [];

                    this.paginacion = null;
                }

            } catch (error) {

                console.error(
                    'Error cargando usuarios:',
                    error
                );

                this.error =
                    error.response?.data?.message ||
                    'Error al cargar usuarios';

            } finally {

                this.loading = false;
            }
        },

        async cargarPagina(page) {
            await this.cargarUsuarios(page);
        },

        async cargarEmpresas() {

            try {

                const response =
                    await api.get(
                        '/admin/empresas'
                    );

                this.empresas =
                    Array.isArray(response.data)
                        ? response.data
                        : [];

                if (
                    this.empresas.length > 0 &&
                    !this.form.empresa_id
                ) {

                    this.form.empresa_id =
                        this.empresas[0].id;
                }

            } catch (error) {

                console.error(
                    'Error cargando empresas:',
                    error
                );
            }
        },

        abrirModal(user = null) {

            if (user) {

                this.editando = true;

                this.form = {
                    id: user.id,
                    name: user.name || '',
                    email: user.email || '',
                    telefono: user.telefono || '',
                    password: '',
                    rol: user.rol || 'vendedor',
                    empresa_id:
                        user.empresa_id ||
                        (
                            this.empresas.length > 0
                                ? this.empresas[0].id
                                : null
                        ),
                    activo:
                        user.activo !== false
                };

            } else {

                this.editando = false;

                this.form = {
                    id: null,
                    name: '',
                    email: '',
                    telefono: '',
                    password: '',
                    rol: 'vendedor',
                    empresa_id:
                        this.empresas.length > 0
                            ? this.empresas[0].id
                            : null,
                    activo: true
                };
            }

            this.errorModal = null;
            this.modalVisible = true;
        },

        cerrarModal() {

            this.modalVisible = false;
            this.errorModal = null;
        },

        async guardarUsuario() {

            this.guardando = true;
            this.errorModal = null;

            if (
                !this.editando &&
                !this.form.password
            ) {

                this.errorModal =
                    'La contraseña es obligatoria para nuevos usuarios.';

                this.guardando = false;
                return;
            }

            if (
                this.form.password &&
                this.form.password.length < 6
            ) {

                this.errorModal =
                    'La contraseña debe tener al menos 6 caracteres.';

                this.guardando = false;
                return;
            }

            if (
                this.form.telefono &&
                !/^\d{10}$/.test(
                    this.form.telefono
                )
            ) {

                this.errorModal =
                    'El teléfono debe tener exactamente 10 dígitos numéricos.';

                this.guardando = false;
                return;
            }

            if (!this.form.empresa_id) {

                this.errorModal =
                    'Debe seleccionar una empresa.';

                this.guardando = false;
                return;
            }

            try {

                const datos = {
                    name:
                        this.form.name,

                    email:
                        this.form.email,

                    telefono:
                        this.form.telefono || null,

                    rol:
                        this.form.rol,

                    empresa_id:
                        this.form.empresa_id,

                    activo:
                        this.form.activo !== false
                };

                if (this.form.password) {
                    datos.password =
                        this.form.password;
                }

                let response;

                if (this.editando) {

                    response =
                        await api.put(
                            `/admin/usuarios/${this.form.id}`,
                            datos
                        );

                } else {

                    response =
                        await api.post(
                            '/admin/usuarios',
                            datos
                        );
                }

                if (response.data) {

                    await this.cargarUsuarios();

                    this.cerrarModal();

                    Swal.fire({
                        icon: 'success',

                        title:
                            this.editando
                                ? 'Usuario actualizado'
                                : 'Usuario creado',

                        text:
                            response.data.message,

                        timer: 2000,

                        showConfirmButton: false
                    });
                }

            } catch (error) {

                console.error(
                    '❌ Error:',
                    error
                );

                let errorMsg =
                    'Error al guardar usuario';

                if (
                    error.response?.data?.errors
                ) {

                    errorMsg =
                        Object.values(
                            error.response.data.errors
                        )
                        .flat()
                        .join(', ');

                } else if (
                    error.response?.data?.message
                ) {

                    errorMsg =
                        error.response.data.message;
                }

                this.errorModal =
                    errorMsg;

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg
                });

            } finally {

                this.guardando = false;
            }
        },

        async eliminarUsuario(id) {

            const result =
                await Swal.fire({

                    title:
                        '¿Estás seguro?',

                    text:
                        'Esta acción no se puede deshacer',

                    icon:
                        'warning',

                    showCancelButton:
                        true,

                    confirmButtonColor:
                        '#d33',

                    cancelButtonColor:
                        '#3085d6',

                    confirmButtonText:
                        'Sí, eliminar',

                    cancelButtonText:
                        'Cancelar'
                });

            if (!result.isConfirmed) {
                return;
            }

            try {

                await api.delete(
                    `/admin/usuarios/${id}`
                );

                await this.cargarUsuarios();

                Swal.fire({
                    icon: 'success',

                    title:
                        'Eliminado',

                    text:
                        'Usuario eliminado correctamente',

                    timer:
                        2000,

                    showConfirmButton:
                        false
                });

            } catch (error) {

                Swal.fire({
                    icon: 'error',

                    title:
                        'Error',

                    text:
                        error.response?.data?.message ||
                        'Error al eliminar usuario'
                });
            }
        },

        /*
        |--------------------------------------------------------------------------
        | LICENCIA
        |--------------------------------------------------------------------------
        */

        async abrirLicencia(user) {

            this.errorLicencia = null;
            this.licenciaInfo = null;

            if (!user.empresa_id) {

                Swal.fire({
                    icon: 'error',
                    title: 'Sin empresa',
                    text: 'Este usuario no tiene una empresa asignada.'
                });

                return;
            }

            try {

                const response =
                    await api.get(
                        `/admin/empresas/${user.empresa_id}/licencia`
                    );

                const licencia =
                    response.data;

                this.licenciaInfo =
                    licencia;

                this.licenciaForm = {
                    empresa_id:
                        user.empresa_id,

                    empresa_nombre:
                        licencia.empresa || 'Empresa',

                    licencia_tipo:
                        licencia.licencia_tipo || 'mes',

                    licencia_fecha_inicio:
                        this.formatearFechaInput(
                            licencia.licencia_fecha_inicio
                        ) || this.fechaHoy(),

                    licencia_fecha_fin:
                        this.formatearFechaInput(
                            licencia.licencia_fecha_fin
                        ) || '',

                    licencia_activa:
                        licencia.licencia_activa !== false
                };

                this.licenciaModalVisible =
                    true;

            } catch (error) {

                console.error(
                    'Error cargando licencia:',
                    error
                );

                Swal.fire({
                    icon: 'error',

                    title:
                        'Error',

                    text:
                        error.response?.data?.message ||
                        'No se pudo cargar la licencia.'
                });
            }
        },

        async guardarLicencia() {

            this.guardandoLicencia = true;
            this.errorLicencia = null;

            if (
                !this.licenciaForm.licencia_fecha_inicio
            ) {

                this.errorLicencia =
                    'La fecha de inicio es obligatoria.';

                this.guardandoLicencia = false;
                return;
            }

            if (
                this.licenciaForm.licencia_tipo !==
                'permanente' &&
                !this.licenciaForm.licencia_fecha_fin
            ) {

                this.errorLicencia =
                    'La fecha de vencimiento es obligatoria.';

                this.guardandoLicencia = false;
                return;
            }

            try {

                const datos = {
                    licencia_tipo:
                        this.licenciaForm.licencia_tipo,

                    licencia_fecha_inicio:
                        this.licenciaForm.licencia_fecha_inicio,

                    licencia_fecha_fin:
                        this.licenciaForm.licencia_tipo ===
                        'permanente'
                            ? null
                            : this.licenciaForm.licencia_fecha_fin,

                    licencia_activa:
                        this.licenciaForm.licencia_activa
                };

                const response =
                    await api.put(
                        `/admin/empresas/${this.licenciaForm.empresa_id}/licencia`,
                        datos
                    );

                this.licenciaInfo =
                    response.data.licencia;

                this.cerrarLicencia();

                await this.cargarEmpresas();
                await this.cargarUsuarios();

                Swal.fire({
                    icon: 'success',

                    title:
                        'Licencia actualizada',

                    text:
                        response.data.message,

                    timer:
                        2000,

                    showConfirmButton:
                        false
                });

            } catch (error) {

                console.error(
                    'Error guardando licencia:',
                    error
                );

                if (
                    error.response?.data?.errors
                ) {

                    this.errorLicencia =
                        Object.values(
                            error.response.data.errors
                        )
                        .flat()
                        .join(', ');

                } else {

                    this.errorLicencia =
                        error.response?.data?.message ||
                        'Error al actualizar la licencia.';
                }

            } finally {

                this.guardandoLicencia =
                    false;
            }
        },

        cerrarLicencia() {

            this.licenciaModalVisible =
                false;

            this.errorLicencia =
                null;

            this.licenciaInfo =
                null;
        },

        calcularFechaFin() {

            if (
                this.licenciaForm.licencia_tipo ===
                'permanente'
            ) {

                this.licenciaForm.licencia_fecha_fin =
                    '';

                return;
            }

            if (
                !this.licenciaForm.licencia_fecha_inicio
            ) {
                return;
            }

            const fecha =
                new Date(
                    `${this.licenciaForm.licencia_fecha_inicio}T00:00:00`
                );

            switch (
                this.licenciaForm.licencia_tipo
            ) {

                case 'dia':
                    fecha.setDate(
                        fecha.getDate() + 1
                    );
                    break;

                case 'semana':
                    fecha.setDate(
                        fecha.getDate() + 7
                    );
                    break;

                case 'quincena':
                    fecha.setDate(
                        fecha.getDate() + 15
                    );
                    break;

                case 'mes':
                    fecha.setMonth(
                        fecha.getMonth() + 1
                    );
                    break;

                case 'bimestre':
                    fecha.setMonth(
                        fecha.getMonth() + 2
                    );
                    break;

                case 'trimestre':
                    fecha.setMonth(
                        fecha.getMonth() + 3
                    );
                    break;

                case 'semestre':
                    fecha.setMonth(
                        fecha.getMonth() + 6
                    );
                    break;

                case 'anual':
                    fecha.setFullYear(
                        fecha.getFullYear() + 1
                    );
                    break;
            }

            const year =
                fecha.getFullYear();

            const month =
                String(
                    fecha.getMonth() + 1
                ).padStart(2, '0');

            const day =
                String(
                    fecha.getDate()
                ).padStart(2, '0');

            this.licenciaForm.licencia_fecha_fin =
                `${year}-${month}-${day}`;
        },

        /*
        |--------------------------------------------------------------------------
        | FORMATO
        |--------------------------------------------------------------------------
        */

        fechaHoy() {

            const fecha =
                new Date();

            const year =
                fecha.getFullYear();

            const month =
                String(
                    fecha.getMonth() + 1
                ).padStart(2, '0');

            const day =
                String(
                    fecha.getDate()
                ).padStart(2, '0');

            return `${year}-${month}-${day}`;
        },

        formatearFechaInput(fecha) {

            if (!fecha) {
                return '';
            }

            if (
                typeof fecha === 'string'
            ) {

                return fecha.substring(
                    0,
                    10
                );
            }

            return '';
        },

        nombreRol(rol) {

            const roles = {
                superadmin:
                    'Super Admin',

                admin:
                    'Administrador',

                cajero:
                    'Cajero',

                vendedor:
                    'Vendedor'
            };

            return roles[rol] ||
                rol ||
                '-';
        },

        claseRol(rol) {

            if (
                rol === 'superadmin'
            ) {

                return 'bg-red-100 text-red-800';
            }

            if (
                rol === 'admin'
            ) {

                return 'bg-yellow-100 text-yellow-800';
            }

            if (
                rol === 'cajero'
            ) {

                return 'bg-purple-100 text-purple-800';
            }

            return 'bg-blue-100 text-blue-800';
        },

        textoLicencia(empresa) {

            if (!empresa) {
                return 'Sin empresa';
            }

            if (
                empresa.licencia_tipo ===
                'permanente'
            ) {

                return 'Permanente';
            }

            if (
                empresa.licencia_vigente
            ) {

                return this.nombreTipoLicencia(
                    empresa.licencia_tipo
                );
            }

            if (
                empresa.licencia_pendiente
            ) {

                return 'Pendiente';
            }

            if (
                empresa.licencia_vencida
            ) {

                return 'Vencida';
            }

            if (
                empresa.licencia_activa === false
            ) {

                return 'Inactiva';
            }

            return 'Sin licencia';
        },

        claseLicencia(empresa) {

            if (!empresa) {
                return 'bg-gray-100 text-gray-700';
            }

            if (
                empresa.licencia_tipo ===
                'permanente' &&
                empresa.licencia_vigente
            ) {

                return 'bg-purple-100 text-purple-800';
            }

            if (
                empresa.licencia_vigente
            ) {

                return 'bg-green-100 text-green-800';
            }

            if (
                empresa.licencia_pendiente
            ) {

                return 'bg-yellow-100 text-yellow-800';
            }

            return 'bg-red-100 text-red-800';
        },

        textoEstadoLicencia(licencia) {

            if (
                licencia.licencia_tipo ===
                'permanente'
            ) {

                return licencia.licencia_activa
                    ? 'Licencia permanente'
                    : 'Licencia inactiva';
            }

            if (
                licencia.licencia_pendiente
            ) {

                return 'Licencia pendiente';
            }

            if (
                licencia.licencia_vigente
            ) {

                return 'Licencia vigente';
            }

            if (
                licencia.licencia_vencida
            ) {

                return 'Licencia vencida';
            }

            return 'Licencia inactiva';
        },

        nombreTipoLicencia(tipo) {

            const tipos = {
                dia:
                    'Diaria',

                semana:
                    'Semanal',

                quincena:
                    'Quincenal',

                mes:
                    'Mensual',

                bimestre:
                    'Bimestral',

                trimestre:
                    'Trimestral',

                semestre:
                    'Semestral',

                anual:
                    'Anual',

                permanente:
                    'Permanente'
            };

            return tipos[tipo] ||
                'Sin licencia';
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
