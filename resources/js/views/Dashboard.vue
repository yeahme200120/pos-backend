<!-- resources/js/views/Dashboard.vue -->
<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">Dashboard</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div v-for="card in cards" :key="card.title" class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-500 text-sm">{{ card.title }}</p>
                        <h3 class="text-2xl font-bold">{{ card.value }}</h3>
                    </div>
                    <div class="p-3 rounded-full" :class="card.color">
                        <i :class="card.icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Bienvenido al sistema POS</h3>
            <p class="text-gray-600">Selecciona una opción del menú lateral para comenzar.</p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Dashboard',
    data() {
        return {
            cards: [
                {
                    title: 'Ventas Hoy',
                    value: '$0.00',
                    icon: 'fas fa-shopping-bag',
                    color: 'bg-blue-100 text-blue-600'
                },
                {
                    title: 'Usuarios',
                    value: '0',
                    icon: 'fas fa-users',
                    color: 'bg-green-100 text-green-600'
                },
                {
                    title: 'Productos',
                    value: '0',
                    icon: 'fas fa-box',
                    color: 'bg-yellow-100 text-yellow-600'
                },
                {
                    title: 'Clientes',
                    value: '0',
                    icon: 'fas fa-user-friends',
                    color: 'bg-purple-100 text-purple-600'
                }
            ]
        };
    }
};
</script>