<template>
  <div>
    <h2>Gestión de Licencias</h2>
    
    <div class="card">
      <div class="card-body">
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

        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th>Usuario</th>
                <th>Email</th>
                <th>Tipo</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in filteredUsers" :key="user.id">
                <td>{{ user.name }}</td>
                <td>{{ user.email }}</td>
                <td><span class="badge bg-primary">{{ user.licencia_tipo || 'Sin licencia' }}</span></td>
                <td>{{ user.licencia_fecha_inicio || '-' }}</td>
                <td>{{ user.licencia_fecha_fin || '-' }}</td>
                <td>
                  <span class="badge" :class="licenciaActiva(user) ? 'bg-success' : 'bg-danger'">
                    {{ licenciaActiva(user) ? 'Activa' : 'Vencida' }}
                  </span>
                </td>
                <td>
                  <button class="btn btn-sm btn-primary" @click="editarLicencia(user)">✏️ Editar</button>
                </td>
              </tr>
            </tbody>
          </table>
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

const users = ref([]);
const mostrarModal = ref(false);
const usuarioEditando = ref(null);
const search = ref('');
const filtroTipo = ref('');
const filtroEstado = ref('');

const form = ref({ licencia_tipo: 'mes', licencia_fecha_inicio: null, licencia_fecha_fin: null });

function licenciaActiva(user) {
  if (user.licencia_tipo === 'permanente') return true;
  if (!user.licencia_fecha_fin) return false;
  return new Date(user.licencia_fecha_fin) > new Date();
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
    users.value = res.data.data;
  } catch (error) {
    console.error('Error al cargar usuarios:', error);
  }
}

function editarLicencia(user) {
  usuarioEditando.value = user;
  form.value = { ...user };
  mostrarModal.value = true;
}

function cerrarModal() {
  mostrarModal.value = false;
  usuarioEditando.value = null;
}

async function guardarLicencia() {
  try {
    await axios.put(`/api/v1/admin/usuarios/${usuarioEditando.value.id}`, {
      licencia_tipo: form.value.licencia_tipo,
      licencia_fecha_inicio: form.value.licencia_fecha_inicio,
      licencia_fecha_fin: form.value.licencia_fecha_fin,
    }, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });
    cerrarModal();
    cargarUsuarios();
  } catch (error) {
    alert('Error al guardar licencia');
    console.error(error);
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