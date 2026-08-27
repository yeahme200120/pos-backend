// resources/js/app.js
import './bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import routes from './routes'; // ✅ routes.js exporta default

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
// SweetAlert2 (global)
import Swal from 'sweetalert2';
window.Swal = Swal;

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Guardia de rutas
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    if (to.meta.requiresAuth && !token) {
        next('/login');
    } else if (to.meta.guest && token) {
        next('/');
    } else {
        next();
    }
});

const app = createApp(App);
app.use(router);
app.mount('#app');