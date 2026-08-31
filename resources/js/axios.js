import axios from 'axios';

// ============================================
// CONTADOR DE PETICIONES ACTIVAS
// ============================================

let requestCount = 0;

function showPreload() {

    requestCount++;

    const preload = document.getElementById('global-preload');

    if (preload) {
        preload.style.display = 'flex';
    }
}

function hidePreload() {

    requestCount--;

    if (requestCount <= 0) {

        requestCount = 0;

        const preload = document.getElementById('global-preload');

        if (preload) {
            preload.style.display = 'none';
        }
    }
}

// ============================================
// INSTANCIA DE API
// ============================================

const api = axios.create({

    baseURL: '/api/v1',

    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },

    // Evita que Axios cancele automáticamente algunas
    // peticiones en navegadores/proxies.
    timeout: 30000,
});

// ============================================
// REQUEST INTERCEPTOR
// ============================================

api.interceptors.request.use(

    (config) => {

        showPreload();

        const token = localStorage.getItem('token');

        if (token) {

            config.headers = config.headers || {};

            config.headers.Authorization =
                `Bearer ${token}`;
        }

        console.log(
            '📤 API:',
            `${config.baseURL}${config.url}`
        );

        return config;
    },

    (error) => {

        hidePreload();

        return Promise.reject(error);
    }
);

// ============================================
// RESPONSE INTERCEPTOR
// ============================================

api.interceptors.response.use(

    (response) => {

        hidePreload();

        return response;
    },

    (error) => {

        hidePreload();

        const status = error.response?.status;

        // ==========================================
        // 401 - NO AUTENTICADO
        // ==========================================

        if (status === 401) {

            console.warn(
                '🔐 API respondió 401'
            );

            // Eliminar sesión
            localStorage.removeItem('token');
            localStorage.removeItem('user');

            // IMPORTANTE:
            // No redireccionar si ya estamos en login.
            if (
                window.location.pathname !== '/login'
            ) {

                console.warn(
                    '↪️ Redirigiendo al login...'
                );

                window.location.replace('/login');
            }
        }

        // ==========================================
        // 403 - LICENCIA / PERMISOS
        // ==========================================

        if (status === 403) {

            console.warn(
                '⛔ API respondió 403:',
                error.response?.data
            );
        }

        // ==========================================
        // REQUEST ABORTED
        // ==========================================

        if (
            error.code === 'ERR_CANCELED' ||
            error.message === 'Request aborted'
        ) {

            console.warn(
                '⚠️ Petición Axios cancelada.'
            );
        }

        return Promise.reject(error);
    }
);

// ============================================
// EXPORTAR
// ============================================

export default api;
