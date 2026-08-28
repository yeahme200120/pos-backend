<!-- resources/js/components/LogoImage.vue -->
<template>
    <img 
        :src="currentSrc" 
        :alt="alt"
        class="w-10 h-10 rounded-full object-cover border"
        @error="useFallback"
        :key="src"
    />
</template>

<script>
export default {
    name: 'LogoImage',
    props: {
        src: {
            type: String,
            default: null
        },
        alt: {
            type: String,
            default: 'Logo'
        }
    },
    data() {
        return {
            currentSrc: this.src || '/img/logo.png',
            errorCount: 0
        };
    },
    watch: {
        src(newSrc) {
            this.currentSrc = newSrc || '/img/logo.png';
            this.errorCount = 0;
        }
    },
    methods: {
        useFallback() {
            this.errorCount++;
            console.log('Error cargando imagen, intento:', this.errorCount, 'URL:', this.currentSrc);
            
            if (this.errorCount === 1 && this.src) {
                // Intentar con URL alternativa
                const baseUrl = window.location.origin;
                if (this.src.startsWith('/storage/')) {
                    this.currentSrc = baseUrl + this.src;
                } else if (this.src.includes('empresas/')) {
                    this.currentSrc = `${baseUrl}/storage/${this.src}`;
                } else {
                    this.currentSrc = '/img/logo.png';
                }
            } else {
                this.currentSrc = '/img/logo.png';
            }
        }
    }
};
</script>