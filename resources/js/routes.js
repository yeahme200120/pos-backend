// resources/js/routes.js
import Login from './views/Login.vue';
import Dashboard from './views/Dashboard.vue';
import Usuarios from './views/Usuarios.vue';
import Licencias from './views/Licencias.vue';
import Reportes from './views/Reportes.vue';
import Configuracion from './views/Configuracion.vue';
import Productos from './views/Productos.vue';
import Clientes from './views/Clientes.vue';

// ✅ USAR export default CORRECTAMENTE
const routes = [
    { 
        path: '/login', 
        component: Login, 
        meta: { guest: true } 
    },
    { 
        path: '/', 
        component: Dashboard, 
        meta: { requiresAuth: true } 
    },
    { 
        path: '/usuarios', 
        component: Usuarios, 
        meta: { requiresAuth: true } 
    },
    { 
        path: '/licencias', 
        component: Licencias, 
        meta: { requiresAuth: true } 
    },
    { 
        path: '/reportes', 
        component: Reportes, 
        meta: { requiresAuth: true } 
    },
    { 
        path: '/configuracion', 
        component: Configuracion, 
        meta: { requiresAuth: true } 
    },
    { 
        path: '/productos', 
        component: Productos, 
        meta: { requiresAuth: true } 
    },
    { 
        path: '/clientes', 
        component: Clientes, 
        meta: { requiresAuth: true } 
    },
];

export default routes;