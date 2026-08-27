<!-- resources/js/views/Login.vue -->
<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">POS Admin</h1>
                <p class="text-gray-500 text-sm">Inicia sesión para continuar</p>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm text-center">
                {{ error }}
            </div>

            <form @submit.prevent="handleLogin">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Email o Número de Usuario
                    </label>
                    <input
                        v-model="form.identificador"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="email@ejemplo.com o 1000000001"
                        required
                    />
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Contraseña
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="••••••••"
                        required
                    />
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition disabled:opacity-50"
                >
                    <span v-if="loading" class="inline-block animate-spin mr-2">⟳</span>
                    {{ loading ? 'Iniciando sesión...' : 'Iniciar Sesión' }}
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
            form: {
                identificador: '',
                password: ''
            },
            error: '',
            loading: false
        };
    },
    methods: {
        async handleLogin() {
            this.loading = true;
            this.error = '';

            try {
                const response = await axios.post('/api/v1/login', this.form);
                
                if (response.data.access_token) {
                    localStorage.setItem('token', response.data.access_token);
                    localStorage.setItem('user', JSON.stringify(response.data.user));
                    this.$router.push('/');
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Credenciales incorrectas';
                console.error('Login error:', error);
            } finally {
                this.loading = false;
            }
        }
    }
};
</script>

<style scoped>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>