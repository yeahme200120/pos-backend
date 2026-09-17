<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h1 class="text-2xl font-bold">Registros de Prueba</h1>

            <button
                v-if="esSuperadmin && seleccionados.length > 0"
                @click="abrirModalLote"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm"
            >
                🚀 Convertir {{ seleccionados.length }} a Real
            </button>
        </div>

        <!-- Filtros -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex flex-wrap gap-3">
                <div class="flex-1 min-w-[200px]">
                    <input
                        v-model="filtros.search"
                        type="text"
                        class="w-full px-3 py-2 border rounded-lg text-sm"
                        placeholder="Buscar empresa, email o MAC..."
                        @input="cargarPruebas"
                    />
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="cargando" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando registros...
        </div>

        <!-- Sin datos -->
        <div v-else-if="pruebas.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No hay registros de prueba
        </div>

        <!-- Tabla -->
        <div v-else class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3">
                                <input type="checkbox" @change="toggleTodos" :checked="todosSeleccionados" />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">RFC</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teléfono</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">MAC</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Licencia</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="r in pruebas" :key="r.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-center">
                                <input
                                    type="checkbox"
                                    :value="r.id"
                                    v-model="seleccionados"
                                    :disabled="!esPrueba(r)"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <div class="w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center">
                                    <img :src="r.empresa?.logo_url || '/img/logo.png'" class="w-full h-full object-cover" />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium">{{ r.empresa?.nombre || r.empresa_nombre }}</td>
                            <td class="px-4 py-3 text-sm">{{ r.empresa?.rfc || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ r.empresa?.telefono || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ r.empresa?.email_contacto || r.email }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-xs">{{ r.mac_address || '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="r.empresa?.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                >
                                    {{ r.empresa?.activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <span
                                        class="px-2 py-1 text-xs rounded-full"
                                        :class="claseEstadoLicencia(r)"
                                    >
                                        {{ textoEstadoLicencia(r) }}
                                    </span>
                                    <span v-if="r.empresa?.licencia_tipo" class="text-xs text-gray-500">
                                        {{ nombreTipoLicencia(r.empresa.licencia_tipo) }}
                                    </span>
                                    <span
                                        v-if="r.empresa?.licencia_fecha_fin && r.empresa.licencia_tipo !== 'permanente'"
                                        class="text-xs text-gray-400"
                                    >
                                        {{ formatearFecha(r.empresa.licencia_fecha_fin) }}
                                    </span>
                                    <span
                                        v-if="esPrueba(r)"
                                        class="px-2 py-0.5 text-[10px] uppercase font-bold rounded bg-yellow-100 text-yellow-800"
                                    >
                                        Prueba
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 text-[10px] uppercase font-bold rounded bg-blue-100 text-blue-800"
                                    >
                                        Real
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <button
                                    v-if="esPrueba(r) && esSuperadmin"
                                    @click="abrirModalIndividual(r)"
                                    class="text-green-600 hover:text-green-800 mr-2"
                                    title="Convertir a Real"
                                >
                                    🚀
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Conversión -->
        <div
            v-if="modalVisible"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        >
            <div class="bg-white rounded-lg p-6 w-full max-w-lg">
                <h2 class="text-xl font-bold mb-4">
                    🚀 Convertir a cuenta real
                </h2>

                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                    <p class="font-semibold">{{ tituloModal }}</p>
                    <p class="text-xs text-gray-600 mt-1">
                        La cuenta, usuarios, ventas y catálogos NO se tocan.
                        Solo se actualiza la licencia.
                    </p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de licencia</label>
                    <select v-model="form.licencia_tipo" @change="recalcularFin" class="w-full px-3 py-2 border rounded-lg">
                        <option value="dia">1 día</option>
                        <option value="semana">7 días</option>
                        <option value="quincena">15 días</option>
                        <option value="mes">1 mes</option>
                        <option value="bimestre">2 meses</option>
                        <option value="trimestre">3 meses</option>
                        <option value="semestre">6 meses</option>
                        <option value="anual">1 año</option>
                        <option value="permanente">Permanente</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de inicio</label>
                    <input
                        v-model="form.licencia_fecha_inicio"
                        @change="recalcularFin"
                        type="datetime-local"
                        class="w-full px-3 py-2 border rounded-lg"
                        :disabled="form.licencia_tipo === 'permanente'"
                    />
                </div>

                <div v-if="form.licencia_tipo !== 'permanente'" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de vencimiento</label>
                    <input
                        v-model="form.licencia_fecha_fin"
                        type="datetime-local"
                        class="w-full px-3 py-2 border rounded-lg"
                    />
                </div>

                <div v-if="error" class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
                    {{ error }}
                </div>

                <div class="flex justify-end gap-2">
                    <button @click="cerrarModal" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                        Cancelar
                    </button>
                    <button
                        @click="convertir"
                        :disabled="guardando"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ guardando ? 'Convirtiendo...' : 'Convertir a Real' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../axios';
import Swal from 'sweetalert2';

export default {
    name: 'Pruebas',
    data() {
        return {
            pruebas: [],
            cargando: false,
            esSuperadmin: false,
            filtros: { search: '' },
            seleccionados: [],
            modalVisible: false,
            modalLote: false,
            registroActual: null,
            guardando: false,
            error: null,
            form: {
                licencia_tipo: 'mes',
                licencia_fecha_inicio: null,
                licencia_fecha_fin: null,
            },
        };
    },
    computed: {
        todosSeleccionados() {
            return this.pruebas.filter(this.esPrueba).length > 0 &&
                this.seleccionados.length === this.pruebas.filter(this.esPrueba).length;
        },
        tituloModal() {
            if (this.modalLote) {
                return `Convertir ${this.seleccionados.length} cuenta(s) a real`;
            }
            return this.registroActual?.empresa?.nombre || '';
        },
    },
    mounted() {
        this.verificarRol();
        this.cargarPruebas();
    },
    methods: {
        verificarRol() {
            const u = localStorage.getItem('user');
            if (u) {
                try { this.esSuperadmin = JSON.parse(u).rol === 'superadmin'; } catch (_) {}
            }
        },
        async cargarPruebas() {
            this.cargando = true;
            try {
                const res = await api.get('/admin/registros-prueba', {
                    params: { search: this.filtros.search },
                });
                this.pruebas = res.data.data || [];
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los registros' });
            } finally {
                this.cargando = false;
            }
        },
        esPrueba(r) {
            const t = r.empresa?.licencia_tipo;
            return ['dia', 'semana', 'quincena'].includes(t);
        },
        toggleTodos(e) {
            if (e.target.checked) {
                this.seleccionados = this.pruebas.filter(this.esPrueba).map(r => r.id);
            } else {
                this.seleccionados = [];
            }
        },
        abrirModalIndividual(r) {
            this.registroActual = r;
            this.modalLote = false;
            this.inicializarForm();
            this.modalVisible = true;
        },
        abrirModalLote() {
            this.registroActual = null;
            this.modalLote = true;
            this.inicializarForm();
            this.modalVisible = true;
        },
        inicializarForm() {
            const ahora = new Date();
            const pad = n => String(n).padStart(2, '0');
            const inicio = `${ahora.getFullYear()}-${pad(ahora.getMonth()+1)}-${pad(ahora.getDate())}T${pad(ahora.getHours())}:${pad(ahora.getMinutes())}`;
            this.form = {
                licencia_tipo: 'mes',
                licencia_fecha_inicio: inicio,
                licencia_fecha_fin: this.calcularFin(inicio, 'mes'),
            };
            this.error = null;
        },
        recalcularFin() {
            if (this.form.licencia_tipo === 'permanente') {
                this.form.licencia_fecha_fin = null;
                return;
            }
            this.form.licencia_fecha_fin = this.calcularFin(this.form.licencia_fecha_inicio, this.form.licencia_tipo);
        },
        calcularFin(inicio, tipo) {
            if (!inicio || tipo === 'permanente') return null;
            const [fecha, hora] = inicio.split('T');
            const [y, m, d] = fecha.split('-').map(Number);
            const [hh, mm] = (hora || '00:00').split(':').map(Number);
            const date = new Date(y, m - 1, d, hh, mm);
            const map = {
                dia: () => date.setDate(date.getDate() + 1),
                semana: () => date.setDate(date.getDate() + 7),
                quincena: () => date.setDate(date.getDate() + 15),
                mes: () => date.setMonth(date.getMonth() + 1),
                bimestre: () => date.setMonth(date.getMonth() + 2),
                trimestre: () => date.setMonth(date.getMonth() + 3),
                semestre: () => date.setMonth(date.getMonth() + 6),
                anual: () => date.setFullYear(date.getFullYear() + 1),
            };
            if (map[tipo]) map[tipo]();
            const pad = n => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },
        cerrarModal() {
            this.modalVisible = false;
            this.registroActual = null;
            this.error = null;
        },
        async convertir() {
            this.guardando = true;
            this.error = null;
            try {
                if (this.modalLote) {
                    await api.post('/admin/registros-prueba/convertir-real-lote', {
                        ids: this.seleccionados,
                        licencia_tipo: this.form.licencia_tipo,
                        licencia_fecha_inicio: this.form.licencia_fecha_inicio,
                        licencia_fecha_fin: this.form.licencia_fecha_fin,
                    });
                } else {
                    await api.post(`/admin/registros-prueba/${this.registroActual.id}/convertir-real`, {
                        licencia_tipo: this.form.licencia_tipo,
                        licencia_fecha_inicio: this.form.licencia_fecha_inicio,
                        licencia_fecha_fin: this.form.licencia_fecha_fin,
                    });
                }

                this.cerrarModal();
                this.seleccionados = [];
                await this.cargarPruebas();

                Swal.fire({ icon: 'success', title: 'Convertido', timer: 1500, showConfirmButton: false });
            } catch (e) {
                this.error = e.response?.data?.message || 'Error al convertir.';
            } finally {
                this.guardando = false;
            }
        },
        claseEstadoLicencia(r) {
            const e = r.empresa;
            if (!e) return 'bg-red-100 text-red-800';
            if (e.licencia_tipo === 'permanente' && e.licencia_activa) return 'bg-green-100 text-green-800';
            if (e.licencia_fecha_fin && e.licencia_activa) {
                const fin = new Date(e.licencia_fecha_fin);
                const gracia = new Date(fin); gracia.setDate(gracia.getDate() + 3);
                if (new Date() <= fin) return 'bg-green-100 text-green-800';
                if (new Date() <= gracia) return 'bg-yellow-100 text-yellow-800';
            }
            return 'bg-red-100 text-red-800';
        },
        textoEstadoLicencia(r) {
            const e = r.empresa;
            if (!e || !e.licencia_activa) return 'Desactivada';
            if (e.licencia_tipo === 'permanente') return 'Permanente';
            if (!e.licencia_fecha_fin) return 'Sin configurar';
            const fin = new Date(e.licencia_fecha_fin);
            const gracia = new Date(fin); gracia.setDate(gracia.getDate() + 3);
            if (new Date() <= fin) return 'Activa';
            if (new Date() <= gracia) return 'En gracia';
            return 'Vencida';
        },
        nombreTipoLicencia(tipo) {
            const map = {
                dia: '1 día', semana: '7 días', quincena: '15 días',
                mes: '1 mes', bimestre: '2 meses', trimestre: '3 meses',
                semestre: '6 meses', anual: '1 año', permanente: 'Permanente',
            };
            return map[tipo] || tipo;
        },
        formatearFecha(f) {
            if (!f) return '-';
            const d = new Date(f);
            return isNaN(d.getTime()) ? '-' : d.toLocaleDateString('es-MX');
        },
    },
};
</script>