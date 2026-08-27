<template>
  <div>
    <h2 class="text-2xl font-bold mb-6">Gestión de Licencias</h2>
    
    <div class="card">
      <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-3">
          <div class="col-md-4">
            <input type="text" v-model="search" class="form-control" placeholder="Buscar usuario..." @input="cargarUsuarios" />
          </div>
          <div class="col-md-3">
            <select v-model="filtroTipo" class="form-control" @change="cargarUsuarios">
              <option value="">Todos los tipos</option>
              <option value="dia">1 día</option>
              <option value="semana">7 días</option>
              <option value="quincena">15 días</option>
              <option value="mes">30 días</option>
              <option value="bimestre">2 meses</option>
              <option value="trimestre">3 meses</option>
              <option value="semestre">6 meses</option>
              <option value="anual">1 año</option>
              <option value="permanente">Permanente</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="filtroEstado" class="form-control" @change="cargarUsuarios">
              <option value="">Todos</option>
              <option value="activa">Activas</option>
              <option value="vencida">Vencidas</option>
            </select>
          </div>
        </div>

        <!-- =========================================
             TABLA DESKTOP / TABLET
        ========================================== -->
        <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200">
          <table class="min-w-[900px] w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                  Usuario
                </th>
                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                  Email
                </th>
                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                  Tipo
                </th>
                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                  Fecha Inicio
                </th>
                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                  Fecha Fin
                </th>
                <th class="px-4 lg:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                  Estado
                </th>
                <th class="px-4 lg:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="filteredUsers.length === 0">
                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                  No hay usuarios registrados
                </td>
              </tr>
              <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 lg:px-6 py-4 text-sm font-medium whitespace-nowrap">
                  {{ user.name }}
                </td>
                <td class="px-4 lg:px-6 py-4 text-sm whitespace-nowrap">
                  {{ user.email }}
                </td>
                <td class="px-4 lg:px-6 py-4 text-sm whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs rounded-full bg-primary text-white">
                    {{ user.licencia_tipo || 'Sin licencia' }}
                  </span>
                </td>
                <td class="px-4 lg:px-6 py-4 text-sm whitespace-nowrap">
                  {{ user.licencia_fecha_inicio ? formatDate(user.licencia_fecha_inicio) : '-' }}
                </td>
                <td class="px-4 lg:px-6 py-4 text-sm whitespace-nowrap">
                  {{ user.licencia_fecha_fin ? formatDate(user.licencia_fecha_fin) : '-' }}
                </td>
                <td class="px-4 lg:px-6 py-4 text-sm text-center whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs rounded-full" 
                        :class="licenciaActiva(user) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                    {{ licenciaActiva(user) ? 'Activa' : 'Vencida' }}
                  </span>
                </td>
                <td class="px-4 lg:px-6 py-4 text-sm text-center whitespace-nowrap">
                  <button class="btn btn-sm btn-primary" @click="editarLicencia(user)">
                    ✏️ Editar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- =========================================
             VISTA MÓVIL (CARDS)
        ========================================== -->
        <div class="md:hidden space-y-3">
          <div v-if="filteredUsers.length === 0" class="bg-white border border-gray-200 rounded-lg p-6 text-center text-sm text-gray-500">
            No hay usuarios registrados
          </div>

          <div v-for="user in filteredUsers" :key="user.id" class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <!-- Encabezado -->
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ user.name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
              </div>
              <span class="inline-flex px-2 py-1 text-xs rounded-full" 
                    :class="licenciaActiva(user) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                {{ licenciaActiva(user) ? 'Activa' : 'Vencida' }}
              </span>
            </div>

            <!-- Información -->
            <div class="px-4 py-3 space-y-3">
              <div>
                <p class="text-xs font-medium text-gray-500">Tipo de Licencia</p>
                <p class="text-sm text-gray-900">
                  <span class="inline-flex px-2 py-1 text-xs rounded-full bg-primary text-white">
                    {{ user.licencia_tipo || 'Sin licencia' }}
                  </span>
                </p>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <p class="text-xs font-medium text-gray-500">Fecha Inicio</p>
                  <p class="text-sm text-gray-900">{{ user.licencia_fecha_inicio ? formatDate(user.licencia_fecha_inicio) : '-' }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Fecha Fin</p>
                  <p class="text-sm text-gray-900">{{ user.licencia_fecha_fin ? formatDate(user.licencia_fecha_fin) : '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Acciones -->
            <div class="flex border-t border-gray-200">
              <button @click="editarLicencia(user)" class="flex-1 py-3 text-sm font-medium text-primary hover:bg-blue-50 transition-colors">
                ✏️ Editar Licencia
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal editar licencia -->
    <div v-if="mostrarModal" class="modal-backdrop" @click.self="cerrarModal">
      <div class="modal-content-custom">
        <div class="modal-header-custom">
          <h5>Editar Licencia - {{ usuarioEditando?.name }}</h5>
          <button class="btn-close" @click="cerrarModal"></button>
        </div>
        <div class="modal-body-custom">
          <div class="mb-3">
            <label class="form-label">Tipo de Licencia</label>
            <select v-model="form.licencia_tipo" class="form-control">
              <option value="dia">1 día</option>
              <option value="semana">7 días</option>
              <option value="quincena">15 días</option>
              <option value="mes">30 días</option>
              <option value="bimestre">2 meses</option>
              <option value="trimestre">3 meses</option>
              <option value="semestre">6 meses</option>
              <option value="anual">1 año</option>
              <option value="permanente">Permanente</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Fecha Inicio</label>
            <input type="date" v-model="form.licencia_fecha_inicio" class="form-control" />
          </div>
          <div class="mb-3">
            <label class="form-label">Fecha Fin</label>
            <input type="date" v-model="form.licencia_fecha_fin" class="form-control" />
          </div>
        </div>
        <div class="modal-footer-custom">
          <button class="btn btn-secondary" @click="cerrarModal">Cancelar</button>
          <button class="btn btn-primary" @click="guardarLicencia">Guardar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const users = ref([]);
const mostrarModal = ref(false);
const usuarioEditando = ref(null);
const search = ref('');
const filtroTipo = ref('');
const filtroEstado = ref('');

const form = ref({
  licencia_tipo: 'mes',
  licencia_fecha_inicio: null,
  licencia_fecha_fin: null
});

function licenciaActiva(user) {
  if (user.licencia_tipo === 'permanente') return true;
  if (!user.licencia_fecha_fin) return false;
  return new Date(user.licencia_fecha_fin) > new Date();
}

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('es-MX', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  });
}

const filteredUsers = computed(() => {
  let result = users.value;
  
  if (search.value) {
    const s = search.value.toLowerCase();
    result = result.filter(u => u.name.toLowerCase().includes(s) || u.email.toLowerCase().includes(s));
  }
  
  if (filtroTipo.value) {
    result = result.filter(u => u.licencia_tipo === filtroTipo.value);
  }
  
  if (filtroEstado.value === 'activa') {
    result = result.filter(u => licenciaActiva(u));
  } else if (filtroEstado.value === 'vencida') {
    result = result.filter(u => !licenciaActiva(u));
  }
  
  return result;
});

async function cargarUsuarios() {
  try {
    const res = await axios.get('/api/v1/admin/usuarios', {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });
    users.value = res.data.data || [];
  } catch (error) {
    console.error('Error al cargar usuarios:', error);
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Error al cargar usuarios'
    });
  }
}

function editarLicencia(user) {
  usuarioEditando.value = user;
  form.value = {
    licencia_tipo: user.licencia_tipo || 'mes',
    licencia_fecha_inicio: user.licencia_fecha_inicio || null,
    licencia_fecha_fin: user.licencia_fecha_fin || null
  };
  mostrarModal.value = true;
}

function cerrarModal() {
  mostrarModal.value = false;
  usuarioEditando.value = null;
}

async function guardarLicencia() {
  try {
    const response = await axios.put(`/api/v1/admin/usuarios/${usuarioEditando.value.id}`, {
      licencia_tipo: form.value.licencia_tipo,
      licencia_fecha_inicio: form.value.licencia_fecha_inicio,
      licencia_fecha_fin: form.value.licencia_fecha_fin,
    }, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });

    cerrarModal();
    await cargarUsuarios();

    await Swal.fire({
      icon: 'success',
      title: 'Licencia actualizada',
      text: `Licencia de ${usuarioEditando.value.name} actualizada correctamente`,
      timer: 2000,
      showConfirmButton: false
    });
  } catch (error) {
    console.error('Error al guardar licencia:', error);
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Error al guardar licencia'
    });
  }
}

onMounted(cargarUsuarios);
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}
.modal-content-custom {
  background: white;
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}
.modal-header-custom {
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-body-custom {
  padding: 20px;
}
.modal-footer-custom {
  padding: 12px 20px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>