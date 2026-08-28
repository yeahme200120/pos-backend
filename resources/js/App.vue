<!-- resources/js/App.vue -->
<template>
    <div id="app">
        <!-- Login sin menú -->
        <div v-if="$route.path === '/login'" class="min-h-screen" :style="{ backgroundColor: fondo }">
            <router-view />
        </div>

        <!-- Páginas con menú -->
        <div v-else class="flex h-screen" :style="{ backgroundColor: fondo }">
            <!-- Sidebar con colores dinámicos -->
            <aside class="w-64 text-white flex-shrink-0 flex flex-col h-screen overflow-hidden"
                :style="{ backgroundColor: colorPrincipal }">

                <!-- ✅ Logo - Dinámico -->
                <div class="p-4 border-b flex-shrink-0 flex items-center justify-center"
                    :style="{ borderColor: colorSecundario || '#374151' }">
                    <img :src="logoUrl" alt="Logo" class="h-12 w-auto object-contain" @error="handleLogoError" />
                </div>

                <!-- Menú - Scrollable -->
                <nav class="flex-1 overflow-y-auto p-2">
                    <router-link to="/" class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Dashboard</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{ color: colorTexto || '#9ca3af' }">
                        Gestión
                    </div>

                    <router-link to="/usuarios"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Usuarios</span>
                    </router-link>

                    <router-link to="/empresas"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Empresas</span>
                    </router-link>

                    <router-link to="/catalogos"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Catálogos</span>
                    </router-link>

                    <router-link to="/clientes"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Clientes</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{ color: colorTexto || '#9ca3af' }">
                        Ventas
                    </div>

                    <router-link to="/ventas/nueva"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Nueva Venta</span>
                    </router-link>

                    <router-link to="/ventas"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Historial Ventas</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{ color: colorTexto || '#9ca3af' }">
                        Promociones
                    </div>

                    <router-link to="/promociones"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Promociones</span>
                    </router-link>

                    <router-link to="/cupones"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Cupones</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{ color: colorTexto || '#9ca3af' }">
                        Reportes
                    </div>

                    <router-link to="/reportes"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Reportes</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{ color: colorTexto || '#9ca3af' }">
                        Configuración
                    </div>

                    <router-link to="/configuracion"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Configuración</span>
                    </router-link>

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{ color: colorTexto || '#9ca3af' }">
                        Sistema
                    </div>

                    <router-link to="/auditoria"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Auditoría</span>
                    </router-link>

                    <router-link to="/licencias"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">Licencias</span>
                    </router-link>

                    <!-- Espacio -->
                    <div class="h-4"></div>
                </nav>

                <!-- Logout - Fijo abajo -->
                <div class="p-4 border-t flex-shrink-0" :style="{ borderColor: colorSecundario || '#374151' }">
                    <button @click="logout"
                        class="flex items-center w-full px-4 py-2 hover:bg-opacity-20 transition rounded-lg text-red-400"
                        :style="{ color: colorTexto }">
                        <span class="ml-2">Cerrar Sesión</span>
                    </button>
                </div>
            </aside>

            <!-- Contenido principal -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Header -->
                <header class="shadow-sm px-6 py-4 flex-shrink-0" :style="{ backgroundColor: colorPrincipal }">
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
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from './axios';

export default {
    name: 'App',
    setup() {
        const logoUrl = ref('/img/logo.png');
        const router = useRouter();
        const route = useRoute();

        const colorPrincipal = ref(localStorage.getItem('colorPrincipal') || '#1E293B');
        const colorSecundario = ref(localStorage.getItem('colorSecundario') || '#374151');
        const fondo = ref(localStorage.getItem('fondo') || '#f3f4f6');
        const colorTexto = ref(localStorage.getItem('colorTexto') || '#FFFFFF');

        const cargarColores = () => {
            colorPrincipal.value = localStorage.getItem('colorPrincipal') || '#1E293B';
            colorSecundario.value = localStorage.getItem('colorSecundario') || '#374151';
            fondo.value = localStorage.getItem('fondo') || '#f3f4f6';
            colorTexto.value = localStorage.getItem('colorTexto') || '#FFFFFF';
        };

        // resources/js/App.vue
        const cargarLogo = async () => {
            try {
                const timestamp = Date.now();
                const response = await api.get(`/empresa/logo?t=${timestamp}`);

                if (response.data && response.data.logo_url) {
                    let logoUrlStr = response.data.logo_url;

                    // Corregir URL - asegurar que incluya el puerto correcto
                    if (logoUrlStr.includes('localhost') && !logoUrlStr.includes('localhost:')) {
                        logoUrlStr = logoUrlStr.replace('localhost', 'localhost:8000');
                    }

                    // Si es una URL relativa, hacerla absoluta
                    if (logoUrlStr.startsWith('/')) {
                        logoUrlStr = `${window.location.origin}${logoUrlStr}`;
                    }

                    // Forzar recarga con timestamp único
                    const uniqueTimestamp = Date.now() + Math.random();
                    logoUrl.value = `${logoUrlStr}?t=${uniqueTimestamp}`;

                    console.log('✅ Logo actualizado:', logoUrl.value);
                } else {
                    logoUrl.value = '/img/logo.png';
                }
            } catch (error) {
                console.error('Error cargando logo:', error);
                logoUrl.value = '/img/logo.png';
            }
        };

        const handleLogoError = () => {
            logoUrl.value = '/img/logo.png';
        };

        const recargarColores = () => {
            cargarColores();
        };

        // ✅ Escuchar cambios en localStorage (otras pestañas)
        const handleStorageChange = (e) => {
            if (['colorPrincipal', 'colorSecundario', 'fondo', 'colorTexto'].includes(e.key)) {
                cargarColores();
            }
            if (e.key === 'logo_actualizado') {
                cargarLogo();
            }
        };

        onMounted(() => {
            // ✅ Registrar eventos
            window.addEventListener('recargar-colores', recargarColores);
            window.addEventListener('recargar-logo', cargarLogo);
            window.addEventListener('storage', handleStorageChange);

            // ✅ Cargar logo al iniciar
            cargarLogo();

            console.log('📌 App montada - escuchando eventos');
        });

        onUnmounted(() => {
            window.removeEventListener('recargar-colores', recargarColores);
            window.removeEventListener('recargar-logo', cargarLogo);
            window.removeEventListener('storage', handleStorageChange);
        });

        const pageTitle = computed(() => {
            const titles = {
                '/': 'Dashboard',
                '/usuarios': 'Usuarios',
                '/empresas': 'Empresas',
                '/catalogos': 'Catálogos',
                '/clientes': 'Clientes',
                '/licencias': 'Licencias',
                '/reportes': 'Reportes',
                '/configuracion': 'Configuración',
                '/ventas': 'Historial Ventas',
                '/ventas/nueva': 'Nueva Venta',
                '/promociones': 'Promociones',
                '/cupones': 'Cupones',
                '/auditoria': 'Auditoría'
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
            logout,
            logoUrl,
            handleLogoError
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

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>