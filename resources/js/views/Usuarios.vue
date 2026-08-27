<!-- resources/js/views/Usuarios.vue -->
<template>
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Usuarios</h1>
        
        <div v-if="loading" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando...
        </div>

        <div v-else-if="error" class="text-red-500 text-center py-8">
            {{ error }}
        </div>

        <div v-else>
            <table class="min-w-full bg-white rounded-lg shadow">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-4 py-2 text-left">ID</th>
                        <th class="px-4 py-2 text-left">Nombre</th>
                        <th class="px-4 py-2 text-left">Email</th>
                        <th class="px-4 py-2 text-left">Rol</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in usuarios" :key="user.id" class="border-t">
                        <td class="px-4 py-2">{{ user.id }}</td>
                        <td class="px-4 py-2">{{ user.name }}</td>
                        <td class="px-4 py-2">{{ user.email }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 text-xs rounded-full" 
                                  :class="user.rol === 'superadmin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'">
                                {{ user.rol }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import api from '../axios';

export default {
    data() {
        return {
            usuarios: [],
            loading: true,
            error: null
        }
    },
    mounted() {
        this.cargarUsuarios()
    },
    methods: {
        async cargarUsuarios() {
            try {
                const response = await api.get('/admin/usuarios')
                this.usuarios = response.data.data || []
            } catch (error) {
                this.error = 'Error al cargar usuarios'
                console.error('Error:', error)
            } finally {
                this.loading = false
            }
        }
    }
}
</script>