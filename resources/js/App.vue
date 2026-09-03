<template>
    <div id="app">

        <!-- ===================================================== -->
        <!-- LOGIN - SIN SIDEBAR NI HEADER -->
        <!-- ===================================================== -->

        <div v-if="$route.path === '/login'" class="min-h-screen" :style="{ backgroundColor: fondo }">
            <router-view />
        </div>

        <!-- ===================================================== -->
        <!-- PANEL ADMINISTRATIVO -->
        <!-- ===================================================== -->

        <div v-else class="flex h-screen" :style="{ backgroundColor: fondo }">

            <!-- ================================================= -->
            <!-- SIDEBAR -->
            <!-- ================================================= -->

            <aside class="w-64 text-white flex-shrink-0 flex flex-col h-screen overflow-hidden"
                :style="{ backgroundColor: colorPrincipal }">

                <!-- LOGO -->

                <div class="p-4 border-b flex-shrink-0 flex items-center justify-center" :style="{
                    borderColor: colorSecundario || '#374151'
                }">
                    <img :src="logoUrl" alt="Logo" class="h-12 w-auto object-contain" @error="handleLogoError" />
                </div>

                <!-- ================================================= -->
                <!-- MENÚ -->
                <!-- ================================================= -->

                <nav class="flex-1 overflow-y-auto p-2">

                    <!-- ================================================= -->
                    <!-- DASHBOARD -->
                    <!-- ================================================= -->

                    <router-link to="/" class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Dashboard
                        </span>
                    </router-link>

                    <!-- ================================================= -->
                    <!-- GESTIÓN -->
                    <!-- ================================================= -->

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{
                        color: colorTexto || '#9ca3af'
                    }">
                        Gestión
                    </div>

                    <!-- SOLO SUPERADMIN -->

                    <router-link v-if="esSuperAdmin" to="/usuarios"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Usuarios
                        </span>
                    </router-link>

                    <!-- SOLO SUPERADMIN -->

                    <router-link v-if="esSuperAdmin" to="/empresas"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Empresas
                        </span>
                    </router-link>

                    <!-- CATÁLOGOS -->

                    <router-link to="/catalogos"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Catálogos
                        </span>
                    </router-link>

                    <!-- CLIENTES -->

                    <router-link to="/clientes"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Clientes
                        </span>
                    </router-link>

                    <!-- ================================================= -->
                    <!-- VENTAS -->
                    <!-- ================================================= -->

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{
                        color: colorTexto || '#9ca3af'
                    }">
                        Ventas
                    </div>

                    <router-link to="/ventas/nueva"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Nueva Venta
                        </span>
                    </router-link>

                    <router-link to="/ventas"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Historial Ventas
                        </span>
                    </router-link>

                    <!-- ================================================= -->
                    <!-- PROMOCIONES -->
                    <!-- ================================================= -->

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{
                        color: colorTexto || '#9ca3af'
                    }">
                        Promociones
                    </div>

                    <router-link to="/promociones"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Promociones
                        </span>
                    </router-link>

                    <router-link to="/cupones"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Cupones
                        </span>
                    </router-link>

                    <!-- ================================================= -->
                    <!-- REPORTES -->
                    <!-- ================================================= -->

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{
                        color: colorTexto || '#9ca3af'
                    }">
                        Reportes
                    </div>

                    <router-link to="/reportes"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Reportes
                        </span>
                    </router-link>

                    <!-- ================================================= -->
                    <!-- CONFIGURACIÓN -->
                    <!-- ================================================= -->

                    <div class="px-4 py-2 text-xs uppercase mt-2" :style="{
                        color: colorTexto || '#9ca3af'
                    }">
                        Configuración
                    </div>

                    <router-link to="/configuracion"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Configuración
                        </span>
                    </router-link>

                    <!-- ================================================= -->
                    <!-- SISTEMA -->
                    <!-- ================================================= -->

                    <div v-if="esSuperAdmin" class="px-4 py-2 text-xs uppercase mt-2" :style="{
                        color: colorTexto || '#9ca3af'
                    }">
                        Sistema
                    </div>

                    <!-- SOLO SUPERADMIN -->

                    <router-link v-if="esSuperAdmin" to="/auditoria"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Auditoría
                        </span>
                    </router-link>

                    <!-- SOLO SUPERADMIN -->

                    <router-link v-if="esSuperAdmin" to="/licencias"
                        class="flex items-center px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        active-class="bg-opacity-30" :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Licencias
                        </span>
                    </router-link>

                    <div class="h-4"></div>

                </nav>

                <!-- ================================================= -->
                <!-- CERRAR SESIÓN -->
                <!-- ================================================= -->

                <div class="p-4 border-t flex-shrink-0" :style="{
                    borderColor: colorSecundario || '#374151'
                }">

                    <button @click="logout"
                        class="flex items-center w-full px-4 py-2 hover:bg-opacity-20 transition rounded-lg"
                        :style="{ color: colorTexto }">
                        <span class="ml-2">
                            Cerrar Sesión
                        </span>
                    </button>

                </div>

            </aside>

            <!-- ================================================= -->
            <!-- CONTENIDO PRINCIPAL -->
            <!-- ================================================= -->

            <div class="flex-1 flex flex-col overflow-hidden">

                <!-- HEADER -->

                <header class="shadow-sm px-6 py-4 flex-shrink-0" :style="{ backgroundColor: colorPrincipal }">

                    <div class="flex justify-between items-center">

                        <h2 class="text-lg font-semibold" :style="{ color: colorTexto }">
                            {{ pageTitle }}
                        </h2>

                        <div class="flex items-center space-x-3">

                            <span class="text-sm" :style="{ color: colorTexto }">
                                {{ userName }}
                            </span>

                            <div class="w-8 h-8 rounded-full bg-opacity-20 flex items-center justify-center" :style="{
                                backgroundColor: colorTexto,
                                color: colorPrincipal
                            }">
                                <span>
                                    {{ userInitial }}
                                </span>
                            </div>

                        </div>

                    </div>

                </header>

                <!-- CONTENIDO -->

                <main class="flex-1 overflow-y-auto p-6" :style="{ backgroundColor: fondo }">
                    <router-view />
                </main>

            </div>

        </div>
    </div>
</template>

<script>
import {
    computed,
    ref,
    onMounted,
    onUnmounted
} from 'vue';

import {
    useRouter,
    useRoute
} from 'vue-router';

import api from './axios';

export default {

    name: 'App',

    setup() {

        const router = useRouter();
        const route = useRoute();

        // =====================================================
        // USUARIO ACTUAL
        // =====================================================

        const usuarioActual = ref(null);

        /**
         * Lee exclusivamente el usuario almacenado
         * en la sesión actual.
         */
        const cargarUsuario = () => {

            try {

                const userData =
                    localStorage.getItem('user');

                if (!userData) {

                    usuarioActual.value = null;

                    return;
                }

                const usuario =
                    JSON.parse(userData);

                if (
                    !usuario ||
                    typeof usuario !== 'object'
                ) {

                    usuarioActual.value = null;

                    return;
                }

                usuarioActual.value = {
                    ...usuario
                };
                

            } catch (error) {

                console.error(
                    '❌ Error leyendo usuario:',
                    error
                );

                usuarioActual.value = null;
            }
        };

        /**
         * Determina si el usuario actual es superadmin.
         *
         * Se normalizan las posibles variantes para evitar
         * problemas si alguna respuesta utiliza:
         *
         * superadmin
         * super_admin
         * super-admin
         */
        const esSuperAdmin = computed(() => {

            const rol =
                usuarioActual.value?.rol;

            if (!rol) {
                return false;
            }

            const rolNormalizado =
                String(rol)
                    .trim()
                    .toLowerCase()
                    .replace(/[\s_-]/g, '');

            return rolNormalizado === 'superadmin';
        });

        // =====================================================
        // NOMBRE DEL USUARIO
        // =====================================================

        const userName = computed(() => {

            return (
                usuarioActual.value?.name ||
                'Usuario'
            );
        });

        // =====================================================
        // INICIAL
        // =====================================================

        const userInitial = computed(() => {

            const nombre =
                usuarioActual.value?.name;

            if (!nombre) {
                return 'U';
            }

            return nombre
                .trim()
                .charAt(0)
                .toUpperCase();
        });

        // =====================================================
        // ACTUALIZAR USUARIO
        // =====================================================

        const recargarUsuario = (event) => {

            try {

                // =============================================
                // SI EL LOGIN ENVÍA EL USUARIO
                // =============================================

                if (
                    event?.detail &&
                    typeof event.detail === 'object'
                ) {

                    usuarioActual.value = {
                        ...event.detail
                    };

                    return;
                }

                // =============================================
                // SI EL EVENTO NO TRAE USUARIO
                // =============================================

                cargarUsuario();

            } catch (error) {

                console.error(
                    '❌ Error actualizando usuario:',
                    error
                );

                cargarUsuario();
            }
        };

        // =====================================================
        // LOGO
        // =====================================================

        const logoUrl =
            ref('/img/logo.png');

        // =====================================================
        // COLORES
        // =====================================================

        const colorPrincipal = ref(
            localStorage.getItem(
                'colorPrincipal'
            ) || '#1E293B'
        );

        const colorSecundario = ref(
            localStorage.getItem(
                'colorSecundario'
            ) || '#374151'
        );

        const fondo = ref(
            localStorage.getItem(
                'fondo'
            ) || '#f3f4f6'
        );

        const colorTexto = ref(
            localStorage.getItem(
                'colorTexto'
            ) || '#FFFFFF'
        );

        const cargarColores = () => {

            colorPrincipal.value =
                localStorage.getItem(
                    'colorPrincipal'
                ) || '#1E293B';

            colorSecundario.value =
                localStorage.getItem(
                    'colorSecundario'
                ) || '#374151';

            fondo.value =
                localStorage.getItem(
                    'fondo'
                ) || '#f3f4f6';

            colorTexto.value =
                localStorage.getItem(
                    'colorTexto'
                ) || '#FFFFFF';
        };

        // =====================================================
        // CARGAR LOGO
        // =====================================================

        const cargarLogo = async () => {

            // ==========================================
            // NO CARGAR LOGO EN LOGIN
            // ==========================================

            if (route.path === '/login') {

                console.log(
                    '🔐 Login activo: no se carga logo de empresa'
                );

                logoUrl.value =
                    '/img/logo.png';

                return;
            }

            // ==========================================
            // VERIFICAR TOKEN
            // ==========================================

            const token =
                localStorage.getItem('token');

            if (!token) {

                console.log(
                    '🔐 No existe token: no se carga logo'
                );

                logoUrl.value =
                    '/img/logo.png';

                return;
            }

            // ==========================================
            // CONSULTAR LOGO
            // ==========================================

            try {

                console.log(
                    '📤 Consultando logo de empresa...'
                );

                const response =
                    await api.get(
                        '/empresa/logo',
                        {
                            params: {
                                t: Date.now()
                            }
                        }
                    );

                console.log(
                    '📥 Respuesta logo:',
                    response.data
                );

                if (
                    response.data &&
                    response.data.logo_url
                ) {

                    let url =
                        response.data.logo_url;

                    // ======================================
                    // URL RELATIVA
                    // ======================================

                    if (
                        url.startsWith('/')
                    ) {

                        url =
                            `${window.location.origin}${url}`;
                    }

                    // ======================================
                    // CACHE BUSTER
                    // ======================================

                    const separator =
                        url.includes('?')
                            ? '&'
                            : '?';

                    logoUrl.value =
                        `${url}${separator}t=${Date.now()}`;

                    console.log(
                        '✅ Logo actualizado:',
                        logoUrl.value
                    );

                } else {

                    console.log(
                        'ℹ️ La empresa no tiene logo.'
                    );

                    logoUrl.value =
                        '/img/logo.png';
                }

            } catch (error) {

                // ==========================================
                // PETICIÓN CANCELADA
                // ==========================================

                if (
                    error.code ===
                    'ERR_CANCELED' ||
                    error.message ===
                    'Request aborted'
                ) {

                    console.warn(
                        '⚠️ Petición del logo cancelada.'
                    );

                    return;
                }

                // ==========================================
                // 401
                // ==========================================

                if (
                    error.response?.status === 401
                ) {

                    console.warn(
                        '🔐 Sesión inválida al cargar logo.'
                    );

                    return;
                }

                // ==========================================
                // OTRO ERROR
                // ==========================================

                console.error(
                    '❌ Error cargando logo:',
                    error
                );

                logoUrl.value =
                    '/img/logo.png';
            }
        };

        // =====================================================
        // ERROR DEL IMG
        // =====================================================

        const handleLogoError = () => {

            console.warn(
                '⚠️ No se pudo mostrar el logo'
            );

            logoUrl.value =
                '/img/logo.png';
        };

        // =====================================================
        // EVENTOS
        // =====================================================

        const recargarColores = () => {

            cargarColores();
        };

        const recargarLogo = () => {

            if (
                route.path !== '/login'
            ) {

                cargarLogo();
            }
        };

        // =====================================================
        // STORAGE
        // =====================================================

        const handleStorageChange = (e) => {

            // ================================================
            // USUARIO
            // ================================================

            if (e.key === 'user') {

                cargarUsuario();
            }

            // ================================================
            // TOKEN
            // ================================================

            if (
                e.key === 'token' &&
                !e.newValue
            ) {

                usuarioActual.value =
                    null;
            }

            // ================================================
            // COLORES
            // ================================================

            if (
                [
                    'colorPrincipal',
                    'colorSecundario',
                    'fondo',
                    'colorTexto'
                ].includes(e.key)
            ) {

                cargarColores();
            }

            // ================================================
            // LOGO
            // ================================================

            if (
                e.key === 'logo_actualizado'
            ) {

                recargarLogo();
            }
        };

        // =====================================================
        // MONTAR APP
        // =====================================================

        onMounted(() => {

            window.addEventListener(
                'recargar-colores',
                recargarColores
            );

            window.addEventListener(
                'recargar-logo',
                recargarLogo
            );

            window.addEventListener(
                'storage',
                handleStorageChange
            );

            window.addEventListener(
                'usuario-actualizado',
                recargarUsuario
            );

            // ================================================
            // CARGAR USUARIO ACTUAL
            // ================================================

            cargarUsuario();

            console.log(
                '📌 App montada - usuario actual:',
                usuarioActual.value
            );

            // ================================================
            // CARGAR LOGO SOLO CON SESIÓN
            // ================================================

            if (
                route.path !== '/login' &&
                localStorage.getItem('token')
            ) {

                cargarLogo();

            } else {

                console.log(
                    '🔐 Login o sin token: no se consulta logo'
                );
            }
        });

        // =====================================================
        // DESMONTAR
        // =====================================================

        onUnmounted(() => {

            window.removeEventListener(
                'recargar-colores',
                recargarColores
            );

            window.removeEventListener(
                'recargar-logo',
                recargarLogo
            );

            window.removeEventListener(
                'storage',
                handleStorageChange
            );

            window.removeEventListener(
                'usuario-actualizado',
                recargarUsuario
            );
        });

        // =====================================================
        // TÍTULO
        // =====================================================

        const pageTitle = computed(() => {

            const titles = {

                '/':
                    'Dashboard',

                '/usuarios':
                    'Usuarios',

                '/empresas':
                    'Empresas',

                '/catalogos':
                    'Catálogos',

                '/clientes':
                    'Clientes',

                '/licencias':
                    'Licencias',

                '/reportes':
                    'Reportes',

                '/configuracion':
                    'Configuración',

                '/ventas':
                    'Historial Ventas',

                '/ventas/nueva':
                    'Nueva Venta',

                '/promociones':
                    'Promociones',

                '/cupones':
                    'Cupones',

                '/auditoria':
                    'Auditoría'
            };

            return (
                titles[route.path] ||
                'POS Admin'
            );
        });

        // =====================================================
        // LOGOUT
        // =====================================================

        const logout = async () => {

            try {

                console.log(
                    '🚪 Cerrando sesión...'
                );

                const token =
                    localStorage.getItem(
                        'token'
                    );

                if (token) {

                    await api.post(
                        '/logout'
                    );

                    console.log(
                        '✅ Sesión cerrada en Laravel'
                    );
                }

            } catch (error) {

                console.warn(
                    '⚠️ No se pudo cerrar sesión en servidor:',
                    error
                );

            } finally {

                // ==========================================
                // ELIMINAR SESIÓN
                // ==========================================

                localStorage.removeItem(
                    'token'
                );

                localStorage.removeItem(
                    'user'
                );

                usuarioActual.value =
                    null;

                console.log(
                    '🧹 Sesión local eliminada'
                );

                // ==========================================
                // LOGIN
                // ==========================================

                if (
                    route.path !== '/login'
                ) {

                    router.replace(
                        '/login'
                    );
                }
            }
        };

        // =====================================================
        // RETURN
        // =====================================================

        return {

            // Colores
            colorPrincipal,
            colorSecundario,
            fondo,
            colorTexto,

            // Usuario
            userName,
            userInitial,
            esSuperAdmin,

            // Navegación
            pageTitle,

            // Sesión
            logout,

            // Logo
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
