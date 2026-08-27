<!-- resources/js/views/Login.vue -->
<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
            <h1 class="text-2xl font-bold text-center mb-6">POS Admin</h1>
            <form @submit.prevent="handleLogin">
                <div class="mb-4">
                    <input
                        v-model="form.identificador"
                        type="text"
                        class="w-full px-3 py-2 border rounded-lg"
                        placeholder="Email o Número de Usuario"
                        required
                    />
                </div>
                <div class="mb-6">
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full px-3 py-2 border rounded-lg"
                        placeholder="Contraseña"
                        required
                    />
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            form: { identificador: '', password: '' }
        };
    },
    methods: {
        async handleLogin() {
            try {
                const response = await axios.post('/api/v1/login', this.form);
                if (response.data.access_token) {
                    localStorage.setItem('token', response.data.access_token);
                    localStorage.setItem('user', JSON.stringify(response.data.user));
                    this.$router.push('/');
                }
            } catch (error) {
                alert('Credenciales incorrectas');
            }
        }
    }
};
</script>