<!-- resources/js/components/Preload.vue -->
<template>
    <div v-if="loading" class="fixed inset-0 bg-white z-50 flex flex-col items-center justify-center">
        <img :src="logoUrl" alt="Logo" class="h-20 w-auto mb-4" />
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0s"></div>
            <div class="w-3 h-3 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            <div class="w-3 h-3 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
        </div>
        <p class="mt-4 text-gray-500 text-sm">Cargando...</p>
    </div>
</template>

<script>
export default {
    name: 'Preload',
    data() {
        return {
            logoUrl: '/img/logo.png',
            loading: true
        };
    },
    mounted() {
        // Ocultar preload cuando la app esté lista
        this.$nextTick(() => {
            setTimeout(() => {
                this.loading = false;
            }, 800);
        });
    }
};
</script>

<style scoped>
.animate-bounce {
    animation: bounce 1.4s infinite ease-in-out both;
}

@keyframes bounce {
    0%, 80%, 100% { 
        transform: scale(0);
    }
    40% { 
        transform: scale(1);
    }
}
</style>