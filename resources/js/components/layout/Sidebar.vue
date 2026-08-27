<!-- resources/js/components/layout/Sidebar.vue -->
<template>
    <aside class="w-64 bg-gray-800 text-white flex-shrink-0">
        <div class="p-4 border-b border-gray-700">
            <h1 class="text-xl font-bold">POS Admin</h1>
        </div>

        <nav class="mt-4">
            <!-- Dashboard -->
            <router-link
                to="/dashboard"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-chart-pie w-5"></i>
                <span class="ml-2">Dashboard</span>
            </router-link>

            <div class="px-4 py-2 text-xs text-gray-400 uppercase mt-4">Gestión</div>

            <router-link
                to="/productos"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-box w-5"></i>
                <span class="ml-2">Productos</span>
                <span class="ml-auto bg-blue-500 text-xs px-2 py-1 rounded-full">{{ totalProductos }}</span>
            </router-link>

            <router-link
                to="/clientes"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-users w-5"></i>
                <span class="ml-2">Clientes</span>
                <span class="ml-auto bg-blue-500 text-xs px-2 py-1 rounded-full">{{ totalClientes }}</span>
            </router-link>

            <router-link
                to="/ventas"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-shopping-cart w-5"></i>
                <span class="ml-2">Ventas</span>
                <span class="ml-auto bg-blue-500 text-xs px-2 py-1 rounded-full">{{ totalVentasHoy }}</span>
            </router-link>

            <router-link
                to="/usuarios"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-user-cog w-5"></i>
                <span class="ml-2">Usuarios</span>
            </router-link>

            <div class="px-4 py-2 text-xs text-gray-400 uppercase mt-4">Reportes</div>

            <router-link
                to="/reportes"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-file-alt w-5"></i>
                <span class="ml-2">Reportes</span>
            </router-link>

            <div class="px-4 py-2 text-xs text-gray-400 uppercase mt-4">Configuración</div>

            <router-link
                to="/configuracion"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-cog w-5"></i>
                <span class="ml-2">Configuración</span>
            </router-link>

            <router-link
                to="/ticket-config"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-receipt w-5"></i>
                <span class="ml-2">Ticket</span>
            </router-link>

            <router-link
                to="/auditoria"
                class="flex items-center px-4 py-2 hover:bg-gray-700 transition"
                active-class="bg-gray-700"
            >
                <i class="fas fa-history w-5"></i>
                <span class="ml-2">Auditoría</span>
            </router-link>

            <div class="border-t border-gray-700 mt-4 pt-4">
                <button
                    @click="logout"
                    class="flex items-center w-full px-4 py-2 hover:bg-gray-700 text-red-400 transition"
                >
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span class="ml-2">Cerrar Sesión</span>
                </button>
            </div>
        </nav>
    </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'

const store = useStore()
const router = useRouter()

const totalProductos = computed(() => store.state.productos?.total || 0)
const totalClientes = computed(() => store.state.clientes?.total || 0)
const totalVentasHoy = computed(() => store.state.ventas?.totalHoy || 0)

const logout = async () => {
    await store.dispatch('auth/logout')
    router.push('/login')
}
</script>