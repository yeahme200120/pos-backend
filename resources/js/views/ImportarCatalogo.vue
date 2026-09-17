<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">Importar catálogo por Excel</h1>

        <div class="bg-white p-6 rounded-lg shadow max-w-3xl">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario destino *</label>
                <select v-model="form.usuario_id" class="w-full px-3 py-2 border rounded-lg">
                    <option :value="null">-- Selecciona --</option>
                    <option v-for="u in usuarios" :key="u.id" :value="u.id">
                        {{ u.name }} — {{ u.email }} ({{ u.numero_usuario }})
                    </option>
                </select>
                <p class="text-xs text-gray-500 mt-1">La empresa destino se toma del usuario.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de catálogo *</label>
                <select v-model="form.tipo_catalogo" class="w-full px-3 py-2 border rounded-lg">
                    <option value="productos">Productos</option>
                    <option value="clientes">Clientes</option>
                    <option value="categorias">Categorías</option>
                    <option value="impuestos">Impuestos</option>
                    <option value="formas_pago">Formas de pago</option>
                    <option value="unidades_medida">Unidades de medida</option>
                    <option value="promociones">Promociones</option>
                    <option value="cupones">Cupones</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Archivo Excel *</label>
                <input type="file" accept=".xlsx,.xls" @change="onFile" class="w-full text-sm" />
            </div>

            <div v-if="error" class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
                {{ error }}
            </div>

            <div class="flex justify-end gap-2">
                <button @click="importar" :disabled="cargando"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    {{ cargando ? 'Importando...' : 'Importar' }}
                </button>
            </div>
        </div>

        <!-- Resultado -->
        <div v-if="resultado" class="mt-6 bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-bold mb-4">Resultado de importación</h2>

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="p-3 bg-green-50 border border-green-200 rounded">
                    <p class="text-xs text-green-700 font-semibold uppercase">Insertados</p>
                    <p class="text-2xl font-bold text-green-800">{{ resultado.insertados }}</p>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-xs text-blue-700 font-semibold uppercase">Actualizados</p>
                    <p class="text-2xl font-bold text-blue-800">{{ resultado.actualizados }}</p>
                </div>
                <div class="p-3 bg-red-50 border border-red-200 rounded">
                    <p class="text-xs text-red-700 font-semibold uppercase">Rechazados</p>
                    <p class="text-2xl font-bold text-red-800">{{ resultado.rechazados }}</p>
                </div>
            </div>

            <div v-if="resultado.detalle?.rechazados?.length" class="overflow-x-auto">
                <h3 class="font-semibold mb-2">Filas rechazadas</h3>
                <table class="w-full text-sm divide-y divide-gray-200 border rounded">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Fila</th>
                            <th class="px-3 py-2 text-left">Campo con error</th>
                            <th class="px-3 py-2 text-left">Mensaje</th>
                            <th class="px-3 py-2 text-left">Datos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in resultado.detalle.rechazados" :key="i">
                            <td class="px-3 py-2">{{ r.fila }}</td>
                            <td class="px-3 py-2 bg-yellow-100 font-bold text-yellow-900">{{ r.campo_error }}</td>
                            <td class="px-3 py-2 text-red-700">{{ r.mensaje }}</td>
                            <td class="px-3 py-2 text-xs font-mono">{{ JSON.stringify(r.datos_fila) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../axios';
import Swal from 'sweetalert2';

export default {
    name: 'ImportarCatalogo',
    data() {
        return {
            usuarios: [],
            archivo: null,
            cargando: false,
            error: null,
            resultado: null,
            form: {
                usuario_id: null,
                tipo_catalogo: 'productos',
            },
        };
    },
    mounted() {
        this.cargarUsuarios();
    },
    methods: {
        async cargarUsuarios() {
            try {
                const res = await api.get('/admin/usuarios');
                this.usuarios = res.data.data || [];
            } catch (_) {}
        },
        onFile(e) {
            this.archivo = e.target.files[0] || null;
        },
        async importar() {
            this.error = null;
            this.resultado = null;

            if (!this.form.usuario_id) return this.error = 'Selecciona un usuario.';
            if (!this.archivo) return this.error = 'Selecciona un archivo.';

            this.cargando = true;

            try {
                const base64 = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(this.archivo);
                });

                const res = await api.post('/admin/catalogos/importar-excel', {
                    usuario_id: this.form.usuario_id,
                    tipo_catalogo: this.form.tipo_catalogo,
                    archivo_base64: base64,
                    nombre_archivo: this.archivo.name,
                });

                this.resultado = res.data.data;
                Swal.fire({ icon: 'success', title: 'Importación completa', timer: 1500, showConfirmButton: false });
            } catch (e) {
                this.error = e.response?.data?.message || 'Error al importar.';
            } finally {
                this.cargando = false;
            }
        },
    },
};
</script>