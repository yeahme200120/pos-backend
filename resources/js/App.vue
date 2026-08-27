<!-- resources/js/App.vue -->
<template>
    <div id="app">
        <!-- Login sin menú -->
        <div v-if="$route.path === '/login'" class="min-h-screen" :style="{ backgroundColor: fondo }">
            <router-view />
        </div>
        
        <!-- Páginas con menú -->
        <div v-else class="flex h-screen" :style="{ backgroundColor: fondo }">
            <!-- Sidebar con color principal -->
            <aside class="w-64 text-white flex-shrink-0" :style="{ backgroundColor: colorPrincipal }">
                <div class="p-4 border-b" :style="{ borderColor: colorSecundario || '#374151' }">
                    <h1 class="text-xl font-bold">POS Admin</h1>
                </div>

                <nav class="mt-4">
                    <router-link 
                        to="/" 
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition"
                        active-class="bg-opacity-30"
                        :style="{ color: colorTexto }"
                    >
                        <span class="ml-2">Dashboard</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-4" :style="{ color: colorTexto || '#9ca3af' }">
                        Gestión
                    </div>

                    <router-link 
                        to="/usuarios" 
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition"
                        active-class="bg-opacity-30"
                        :style="{ color: colorTexto }"
                    >
                        <span class="ml-2">Usuarios</span>
                    </router-link>

                    <router-link 
                        to="/productos" 
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition"
                        active-class="bg-opacity-30"
                        :style="{ color: colorTexto }"
                    >
                        <span class="ml-2">Productos</span>
                    </router-link>

                    <router-link 
                        to="/clientes" 
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition"
                        active-class="bg-opacity-30"
                        :style="{ color: colorTexto }"
                    >
                        <span class="ml-2">Clientes</span>
                    </router-link>

                    <router-link 
                        to="/licencias" 
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition"
                        active-class="bg-opacity-30"
                        :style="{ color: colorTexto }"
                    >
                        <span class="ml-2">Licencias</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-4" :style="{ color: colorTexto || '#9ca3af' }">
                        Reportes
                    </div>

                    <router-link 
                        to="/reportes" 
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition"
                        active-class="bg-opacity-30"
                        :style="{ color: colorTexto }"
                    >
                        <span class="ml-2">Reportes</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-4" :style="{ color: colorTexto || '#9ca3af' }">
                        Configuración
                    </div>

                    <router-link 
                        to="/configuracion" 
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition"
                        active-class="bg-opacity-30"
                        :style="{ color: colorTexto }"
                    >
                        <span class="ml-2">Configuración</span>
                    </router-link>

                    <div class="border-t mt-4 pt-4" :style="{ borderColor: colorSecundario || '#374151' }">
                        <button 
                            @click="logout" 
                            class="flex items-center w-full px-4 py-2 transition text-red-400"
                            :style="{ color: colorTexto }"
                        >
                            <span class="ml-2">Cerrar Sesión</span>
                        </button>
                    </div>
                </nav>
            </aside>

            <!-- Contenido principal -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Header con el MISMO color que el sidebar -->
                <header class="shadow-sm px-6 py-4" :style="{ backgroundColor: colorPrincipal }">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-semibold" :style="{ color: colorTexto }">
                            {{ pageTitle }}
                        </h2>
                        <div class="flex items-center space-x-3">
                            <span class="text-sm" :style="{ color: colorTexto }">
                                {{ userName }}
                            </span>
                            <div class="w-8 h-8 rounded-full bg-opacity-20 flex items-center justify-center" 
                                 :style="{ backgroundColor: colorTexto, color: colorPrincipal }">
                                <span>{{ userInitial }}</span>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Contenido -->
                <main class="flex-1 overflow-y-auto p-6" :style="{ backgroundColor: fondo }">
                    <router-view />
                </main>
            </div>
        </div>
    </div>
</template>

<script>
import { computed, ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';

export default {
    name: 'App',
    setup() {
        const router = useRouter();
        const route = useRoute();

        // Función para cargar colores desde localStorage
        const cargarColores = () => {
            return {
                colorPrincipal: localStorage.getItem('colorPrincipal') || '#1E293B',
                colorSecundario: localStorage.getItem('colorSecundario') || '#374151',
                fondo: localStorage.getItem('fondo') || '#f3f4f6',
                colorTexto: localStorage.getItem('colorTexto') || '#FFFFFF'
            };
        };

        // Estado reactivo con valores iniciales
        const colores = ref(cargarColores());

        // Computed para acceder fácilmente
        const colorPrincipal = computed(() => colores.value.colorPrincipal);
        const colorSecundario = computed(() => colores.value.colorSecundario);
        const fondo = computed(() => colores.value.fondo);
        const colorTexto = computed(() => colores.value.colorTexto);

        // Escuchar cambios en localStorage
        const handleStorageChange = () => {
            colores.value = cargarColores();
        };

        // Escuchar evento personalizado para recargar colores
        const recargarColores = () => {
            colores.value = cargarColores();
        };

        onMounted(() => {
            window.addEventListener('storage', handleStorageChange);
            window.addEventListener('recargar-colores', recargarColores);
        });

        const pageTitle = computed(() => {
            const titles = {
                '/': 'Dashboard',
                '/usuarios': 'Usuarios',
                '/productos': 'Productos',
                '/clientes': 'Clientes',
                '/licencias': 'Licencias',
                '/reportes': 'Reportes',
                '/configuracion': 'Configuración'
            };
            return titles[route.path] || 'POS Admin';
        });

        const userName = computed(() => {
            try {
                const userData = localStorage.getItem('user');
                return userData ? JSON.parse(userData).name : 'Usuario';
            } catch {
                return 'Usuario';
            }
        });

        const userInitial = computed(() => {
            return userName.value ? userName.value.charAt(0).toUpperCase() : 'U';
        });

        const logout = () => {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            router.push('/login');
        };

        return {
            colorPrincipal,
            colorSecundario,
            fondo,
            colorTexto,
            pageTitle,
            userName,
            userInitial,
            logout
        };
    }
};
</script>

<style scoped>
.router-link-active {
    background-color: rgba(255, 255, 255, 0.1);
}

.router-link-exact-active {
    background-color: rgba(255, 255, 255, 0.15);
}

.transition {
    transition: all 0.2s ease;
}

.hover\:bg-opacity-20:hover {
    background-color: rgba(255, 255, 255, 0.2);
}
</style>