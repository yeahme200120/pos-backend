// resources/js/axios.js
import axios from 'axios';

// Contador de peticiones activas
let requestCount = 0;

// Función para mostrar/ocultar preload
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

// Configuración base de Axios
const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Interceptor para agregar el token y mostrar preload
api.interceptors.request.use(
    (config) => {
        // Mostrar preload
        showPreload();
        
        const token = localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        console.log('📤 URL:', config.baseURL + config.url);
        return config;
    },
    (error) => {
        hidePreload();
        return Promise.reject(error);
    }
);

// Interceptor para manejar respuestas y ocultar preload
api.interceptors.response.use(
    (response) => {
        hidePreload();
        return response;
    },
    (error) => {
        hidePreload();
        
        // Si el token expiró (401), redirigir al login
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('token');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default api;