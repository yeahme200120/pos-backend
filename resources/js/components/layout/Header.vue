<!-- resources/js/components/layout/Header.vue -->
<template>
    <header class="bg-white shadow-sm">
        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ pageTitle }}</h2>
            </div>

            <div class="flex items-center space-x-4">
                <button class="text-gray-500 hover:text-gray-700 relative">
                    <i class="fas fa-bell text-xl"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                        0
                    </span>
                </button>

                <div class="flex items-center space-x-3">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-700">{{ user?.name || 'Usuario' }}</p>
                        <p class="text-xs text-gray-500">{{ user?.rol || '' }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center">
                        <i class="fas fa-user text-gray-600"></i>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue'
import { useStore } from 'vuex'
import { useRoute } from 'vue-router'

const store = useStore()
const route = useRoute()

const user = computed(() => store.state.auth.user)
const pageTitle = computed(() => route.meta?.title || 'Panel de Administración')
</script>