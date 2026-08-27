<!-- resources/js/views/Usuarios.vue -->
<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Usuarios</h1>
            <button @click="abrirModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                + Nuevo Usuario
            </button>
        </div>

        <!-- Barra de búsqueda -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex gap-4">
                <input v-model="filtros.search" @keyup.enter="cargarUsuarios" type="text"
                    class="flex-1 px-3 py-2 border rounded-lg" placeholder="Buscar por nombre, email o número..." />
                <button @click="cargarUsuarios" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    Buscar
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando...
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded-lg">
            {{ error }}
        </div>

        <!-- Tabla -->
        <div v-else class="bg-white rounded-lg shadow overflow-hidden">
            <!-- CONTENEDOR RESPONSIVO -->
            <div class="w-full overflow-hidden">

                <!-- =========================================
         TABLA DESKTOP / TABLET
    ========================================== -->
                <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200">

                    <table class="min-w-[900px] w-full divide-y divide-gray-200">

                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                                    Número
                                </th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                                    Nombre
                                </th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                                    Email
                                </th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                                    Teléfono
                                </th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                                    Rol
                                </th>
                                <th class="px-4 lg:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                                    Estado
                                </th>
                                <th class="px-4 lg:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody class="bg-white divide-y divide-gray-200">

                            <!-- Sin usuarios -->
                            <tr v-if="usuarios.length === 0">
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No hay usuarios registrados
                                </td>
                            </tr>

                            <!-- Usuarios -->
                            <tr v-for="user in usuarios" :key="user.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 lg:px-6 py-4 text-sm whitespace-nowrap">
                                    {{ user.numero_usuario || '-' }}
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-sm font-medium whitespace-nowrap">
                                    {{ user.name }}
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-sm whitespace-nowrap">
                                    {{ user.email }}
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-sm whitespace-nowrap">
                                    {{ user.telefono || '-' }}
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-sm whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full" 
                                          :class="user.rol === 'superadmin' ? 'bg-red-100 text-red-800' : 
                                                 user.rol === 'admin' ? 'bg-yellow-100 text-yellow-800' : 
                                                 'bg-blue-100 text-blue-800'">
                                        {{ user.rol }}
                                    </span>
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-sm text-center whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full" 
                                          :class="user.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        {{ user.activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-sm text-center whitespace-nowrap">
                                    <div class="flex justify-center items-center gap-3">
                                        <button @click="abrirModal(user)" class="text-blue-600 hover:text-blue-800 font-medium transition-colors">
                                            Editar
                                        </button>
                                        <button @click="eliminarUsuario(user.id)" class="text-red-600 hover:text-red-800 font-medium transition-colors">
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- =========================================
         VISTA MÓVIL
    ========================================== -->
                <div class="md:hidden space-y-3">
                    <div v-if="usuarios.length === 0" class="bg-white border border-gray-200 rounded-lg p-6 text-center text-sm text-gray-500">
                        No hay usuarios registrados
                    </div>

                    <div v-for="user in usuarios" :key="user.id" class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ user.name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">Nº {{ user.numero_usuario || '-' }}</p>
                            </div>
                            <span class="flex-shrink-0 inline-flex px-2 py-1 text-xs rounded-full" 
                                  :class="user.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                {{ user.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                        <div class="px-4 py-3 space-y-3">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Email</p>
                                <p class="text-sm text-gray-900 break-all">{{ user.email }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500">Teléfono</p>
                                <p class="text-sm text-gray-900">{{ user.telefono || '-' }}</p>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-medium text-gray-500">Rol</p>
                                <span class="inline-flex px-2 py-1 text-xs rounded-full" 
                                      :class="user.rol === 'superadmin' ? 'bg-red-100 text-red-800' : 
                                             user.rol === 'admin' ? 'bg-yellow-100 text-yellow-800' : 
                                             'bg-blue-100 text-blue-800'">
                                    {{ user.rol }}
                                </span>
                            </div>
                        </div>
                        <div class="flex border-t border-gray-200">
                            <button @click="abrirModal(user)" class="flex-1 py-3 text-sm font-medium text-blue-600 hover:bg-blue-50 transition-colors">
                                Editar
                            </button>
                            <div class="w-px bg-gray-200"></div>
                            <button @click="eliminarUsuario(user.id)" class="flex-1 py-3 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="paginacion" class="px-6 py-4 border-t flex justify-between items-center">
                <span class="text-sm text-gray-600">
                    Mostrando {{ usuarios.length }} de {{ paginacion.total || usuarios.length }}
                </span>
                <div class="flex space-x-2">
                    <button v-if="paginacion.current_page > 1" @click="cargarPagina(paginacion.current_page - 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 text-sm">
                        Anterior
                    </button>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                        {{ paginacion.current_page || 1 }}
                    </span>
                    <button v-if="paginacion.current_page < paginacion.last_page"
                        @click="cargarPagina(paginacion.current_page + 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 text-sm">
                        Siguiente
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-bold mb-4">{{ editando ? 'Editar' : 'Nuevo' }} Usuario</h2>

                <form @submit.prevent="guardarUsuario">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre *</label>
                            <input v-model="form.name" type="text"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email *</label>
                            <input v-model="form.email" type="email"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                            <input v-model="form.telefono" type="text" maxlength="10"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="10 dígitos (ej: 5512345678)" />
                            <p class="text-xs text-gray-400 mt-1">Solo números, 10 dígitos</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Rol *</label>
                            <select v-model="form.rol"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="vendedor">Vendedor</option>
                                <option value="admin">Administrador</option>
                                <option value="superadmin">Super Admin</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Empresa *</label>
                            <select v-model="form.empresa_id"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option v-for="empresa in empresas" :key="empresa.id" :value="empresa.id">
                                    {{ empresa.nombre }}
                                </option>
                            </select>
                        </div>
                        <!-- ✅ Contraseña: OBLIGATORIA solo en creación -->
                        <div v-if="!editando">
                            <label class="block text-sm font-medium text-gray-700">Contraseña *</label>
                            <input v-model="form.password" type="password"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required />
                            <p class="text-xs text-gray-400 mt-1">Mínimo 6 caracteres</p>
                        </div>
                        <!-- ✅ Contraseña: OPCIONAL en edición -->
                        <div v-if="editando">
                            <label class="block text-sm font-medium text-gray-700">Contraseña</label>
                            <input v-model="form.password" type="password"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Dejar vacío para no cambiar" />
                            <p class="text-xs text-gray-400 mt-1">Dejar vacío para mantener la actual</p>
                        </div>
                        <div class="flex items-center">
                            <input v-model="form.activo" type="checkbox" class="mr-2" />
                            <label class="text-sm font-medium text-gray-700">Activo</label>
                        </div>
                    </div>

                    <div v-if="errorModal" class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                        {{ errorModal }}
                    </div>

                    <div class="flex justify-end space-x-2 mt-6">
                        <button type="button" @click="cerrarModal"
                            class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="guardando"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
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
        async cargarUsuarios(page = 1) {
            this.loading = true;
            this.error = null;

            try {
                const params = { page };
                if (this.filtros.search) {
                    params.search = this.filtros.search;
                }

                const response = await api.get('/admin/usuarios', { params });

                if (response.data.data) {
                    this.usuarios = response.data.data;
                    this.paginacion = {
                        current_page: response.data.current_page || 1,
                        last_page: response.data.last_page || 1,
                        total: response.data.total || 0
                    };
                } else {
                    this.usuarios = Array.isArray(response.data) ? response.data : [];
                }
            } catch (error) {
                console.error('Error cargando usuarios:', error);
                this.error = error.response?.data?.message || 'Error al cargar usuarios';
            } finally {
                this.loading = false;
            }
        },
        async cargarPagina(page) {
            await this.cargarUsuarios(page);
        },
        async cargarEmpresas() {
            try {
                const response = await api.get('/admin/empresas');
                this.empresas = response.data || [];
                if (this.empresas.length > 0 && !this.form.empresa_id) {
                    this.form.empresa_id = this.empresas[0].id;
                }
            } catch (error) {
                console.error('Error cargando empresas:', error);
            }
        },
        abrirModal(user = null) {
            if (user) {
                this.editando = true;
                this.form = {
                    ...user,
                    password: '', // ✅ Limpiar contraseña en edición
                    empresa_id: user.empresa_id || (this.empresas.length > 0 ? this.empresas[0].id : null)
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
                    empresa_id: this.empresas.length > 0 ? this.empresas[0].id : null,
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

            // ✅ Validación: contraseña obligatoria en creación
            if (!this.editando && !this.form.password) {
                this.errorModal = 'La contraseña es obligatoria para nuevos usuarios.';
                this.guardando = false;
                return;
            }

            // ✅ Validación: contraseña mínima 6 caracteres
            if (!this.editando && this.form.password && this.form.password.length < 6) {
                this.errorModal = 'La contraseña debe tener al menos 6 caracteres.';
                this.guardando = false;
                return;
            }

            // ✅ Validación: teléfono 10 dígitos
            if (this.form.telefono && !/^\d{10}$/.test(this.form.telefono)) {
                this.errorModal = 'El teléfono debe tener exactamente 10 dígitos numéricos.';
                this.guardando = false;
                return;
            }

            try {
                const datos = {
                    name: this.form.name,
                    email: this.form.email,
                    telefono: this.form.telefono,
                    rol: this.form.rol,
                    empresa_id: this.form.empresa_id,
                    activo: this.form.activo !== undefined ? this.form.activo : true
                };

                // ✅ Enviar contraseña SOLO si se proporcionó
                if (this.form.password) {
                    datos.password = this.form.password;
                }

                console.log('📤 Datos a enviar:', datos);

                let response;
                if (this.editando) {
                    response = await api.put(`/admin/usuarios/${this.form.id}`, datos);
                } else {
                    response = await api.post('/admin/usuarios', datos);
                }

                if (response.data) {
                    await this.cargarUsuarios();
                    this.cerrarModal();

                    Swal.fire({
                        icon: 'success',
                        title: this.editando ? 'Usuario actualizado' : 'Usuario creado',
                        text: response.data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            } catch (error) {
                console.error('❌ Error:', error);
                
                // ✅ Mostrar errores de validación del backend
                let errorMsg = 'Error al guardar usuario';
                if (error.response?.data?.errors) {
                    const errors = error.response.data.errors;
                    errorMsg = Object.values(errors).flat().join(', ');
                } else if (error.response?.data?.message) {
                    errorMsg = error.response.data.message;
                }
                
                this.errorModal = errorMsg;

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg,
                });
            } finally {
                this.guardando = false;
            }
        },
        async eliminarUsuario(id) {
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
                    await api.delete(`/admin/usuarios/${id}`);
                    await this.cargarUsuarios();

                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: 'Usuario eliminado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.response?.data?.message || 'Error al eliminar usuario',
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
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>