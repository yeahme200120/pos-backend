import axios from 'axios';

window.axios = axios;

// ============================================
// CONFIGURACIÓN AXIOS
// ============================================

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.headers.common['Content-Type'] = 'application/json';

// ============================================
// REQUEST INTERCEPTOR
// ============================================

window.axios.interceptors.request.use(
    (config) => {

        const token = localStorage.getItem('token');

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        return config;
    },

    (error) => {
        return Promise.reject(error);
    }
);

// ============================================
// RESPONSE INTERCEPTOR
// ============================================

window.axios.interceptors.response.use(

    (response) => {
        return response;
    },

    (error) => {

        const status = error.response?.status;

        // ========================================
        // TOKEN INVÁLIDO / SESIÓN EXPIRADA
        // ========================================

        if (status === 401) {

            console.warn('⚠️ Sesión no válida');

            localStorage.removeItem('token');
            localStorage.removeItem('user');

            // IMPORTANTE:
            // No redireccionar si YA estamos en login.
            if (window.location.pathname !== '/login') {

                window.location.href = '/login';
            }
        }

        return Promise.reject(error);
    }
);
