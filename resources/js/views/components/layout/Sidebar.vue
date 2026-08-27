<!-- resources/js/components/layout/Sidebar.vue -->
<template>
    <aside class="w-64 bg-gray-800 text-white">
        <div class="p-4 border-b border-gray-700">
            <h1 class="text-xl font-bold">POS Admin</h1>
        </div>

        <nav class="mt-4">
            <template v-for="menu in menus" :key="menu.path">
                <!-- Menu con subitems -->
                <div v-if="menu.children" class="mb-2">
                    <div class="px-4 py-2 text-xs text-gray-400 uppercase">
                        {{ menu.title }}
                    </div>
                    <router-link
                        v-for="child in menu.children"
                        :key="child.path"
                        :to="child.path"
                        class="flex items-center px-4 py-2 hover:bg-gray-700"
                        active-class="bg-gray-700"
                    >
                        <i :class="child.icon" class="w-5"></i>
                        <span>{{ child.name }}</span>
                        <span v-if="child.badge" class="ml-auto bg-blue-500 text-xs px-2 py-1 rounded-full">
                            {{ child.badge }}
                        </span>
                    </router-link>
                </div>

                <!-- Item simple -->
                <router-link
                    v-else
                    :to="menu.path"
                    class="flex items-center px-4 py-2 hover:bg-gray-700"
                    active-class="bg-gray-700"
                >
                    <i :class="menu.icon" class="w-5"></i>
                    <span>{{ menu.name }}</span>
                    <span v-if="menu.badge" class="ml-auto bg-blue-500 text-xs px-2 py-1 rounded-full">
                        {{ menu.badge }}
                    </span>
                </router-link>
            </template>
        </nav>
    </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useStore } from 'vuex'

const store = useStore()

const menus = computed(() => [
    {
        path: '/dashboard',
        name: 'Dashboard',
        icon: 'fas fa-chart-pie'
    },
    {
        title: 'Gestión',
        children: [
            {
                path: '/productos',
                name: 'Productos',
                icon: 'fas fa-box',
                badge: store.state.productos.total
            },
            {
                path: '/clientes',
                name: 'Clientes',
                icon: 'fas fa-users',
                badge: store.state.clientes.total
            },
            {
                path: '/ventas',
                name: 'Ventas',
                icon: 'fas fa-shopping-cart',
                badge: store.state.ventas.totalHoy
            },
            {
                path: '/usuarios',
                name: 'Usuarios',
                icon: 'fas fa-user-cog'
            }
        ]
    },
    {
        title: 'Reportes',
        children: [
            {
                path: '/reportes',
                name: 'Reportes',
                icon: 'fas fa-file-alt'
            },
            {
                path: '/estadisticas',
                name: 'Estadísticas',
                icon: 'fas fa-chart-bar'
            }
        ]
    },
    {
        title: 'Configuración',
        children: [
            {
                path: '/configuracion',
                name: 'Configuración',
                icon: 'fas fa-cog'
            },
            {
                path: '/ticket-config',
                name: 'Ticket',
                icon: 'fas fa-receipt'
            },
            {
                path: '/auditoria',
                name: 'Auditoría',
                icon: 'fas fa-history'
            }
        ]
    }
])
</script>