<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>👤 Gestión de Clientes</h2>
      <button class="btn" :style="{ backgroundColor: primaryColor, color: '#fff' }" @click="abrirModalCrear">
        <i class="bi bi-plus-circle"></i> Nuevo Cliente
      </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <input type="text" v-model="filtros.search" class="form-control" placeholder="Buscar por nombre, email, teléfono..." @input="buscar" />
          </div>
          <div class="col-md-3">
            <select v-model="filtros.tipo" class="form-control" @change="buscar">
              <option value="">Todos los tipos</option>
              <option value="particular">Particular</option>
              <option value="empresa">Empresa</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="filtros.activo" class="form-control" @change="buscar">
              <option value="">Todos</option>
              <option value="1">Activos</option>
              <option value="0">Inactivos</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>Tipo</th>
                <th>RFC</th>
                <th>Límite Crédito</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cliente in clientes" :key="cliente.id">
                <td>{{ cliente.id }}</td>
                <td><strong>{{ cliente.nombre }}</strong></td>
                <td>{{ cliente.email || '-' }}</td>
                <td>{{ cliente.telefono || '-' }}</td>
                <td>
                  <span class="badge" :class="cliente.tipo === 'empresa' ? 'bg-primary' : 'bg-info'">
                    {{ cliente.tipo === 'empresa' ? 'Empresa' : 'Particular' }}
                  </span>
                </td>
                <td>{{ cliente.rfc || '-' }}</td>
                <td>${{ Number(cliente.limite_credito).toFixed(2) }}</td>
                <td>
                  <span class="badge" :class="cliente.activo ? 'bg-success' : 'bg-danger'">
                    {{ cliente.activo ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td>
                  <button class="btn btn-sm btn-info me-1" @click="verHistorial(cliente.id)" title="Historial de compras">📊</button>
                  <button class="btn btn-sm btn-warning me-1" @click="editarCliente(cliente)">✏️</button>
                  <button class="btn btn-sm btn-danger" @click="eliminarCliente(cliente.id)">🗑️</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        <nav v-if="pagination.last_page > 1">
          <ul class="pagination">
            <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
              <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page - 1)">Anterior</a>
            </li>
            <li class="page-item" v-for="page in pagination.last_page" :key="page" :class="{ active: page === pagination.current_page }">
              <a class="page-link" href="#" @click.prevent="cambiarPagina(page)">{{ page }}</a>
            </li>
            <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
              <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page + 1)">Siguiente</a>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Modal para crear/editar cliente -->
    <div v-if="mostrarModal" class="modal-backdrop" @click.self="cerrarModal">
      <div class="modal-content-custom">
        <div class="modal-header-custom">
          <h5>{{ clienteEditando ? 'Editar Cliente' : 'Nuevo Cliente' }}</h5>
          <button class="btn-close" @click="cerrarModal"></button>
        </div>
        <div class="modal-body-custom">
          <form @submit.prevent="guardarCliente">
            <div class="row g-3">
              <div class="col-md-12">
                <label class="form-label">Nombre *</label>
                <input v-model="form.nombre" class="form-control" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input v-model="form.telefono" class="form-control" />
              </div>
              <div class="col-md-12">
                <label class="form-label">Dirección</label>
                <textarea v-model="form.direccion" class="form-control" rows="2"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">RFC</label>
                <input v-model="form.rfc" class="form-control" maxlength="13" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Tipo</label>
                <select v-model="form.tipo" class="form-control">
                  <option value="particular">Particular</option>
                  <option value="empresa">Empresa</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Límite de Crédito</label>
                <input type="number" v-model="form.limite_credito" class="form-control" min="0" step="0.01" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Notas</label>
                <input v-model="form.notas" class="form-control" />
              </div>
              <div class="col-md-12">
                <div class="form-check">
                  <input v-model="form.activo" type="checkbox" class="form-check-input" id="activoCheck" />
                  <label class="form-check-label" for="activoCheck">Activo</label>
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer-custom">
          <button class="btn btn-secondary" @click="cerrarModal">Cancelar</button>
          <button class="btn" :style="{ backgroundColor: primaryColor, color: '#fff' }" @click="guardarCliente">
            {{ clienteEditando ? 'Actualizar' : 'Crear' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de historial de compras -->
    <div v-if="mostrarHistorial" class="modal-backdrop" @click.self="cerrarHistorial">
      <div class="modal-content-custom modal-lg">
        <div class="modal-header-custom">
          <h5>Historial de Compras - {{ clienteHistorial?.nombre }}</h5>
          <button class="btn-close" @click="cerrarHistorial"></button>
        </div>
        <div class="modal-body-custom">
          <div v-if="historialVentas.length === 0" class="text-center py-4">
            <p class="text-muted">Este cliente no tiene compras registradas.</p>
          </div>
          <div v-else>
            <div class="table-responsive">
              <table class="table table-bordered table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Vendedor</th>
                    <th>Productos</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="venta in historialVentas" :key="venta.id">
                    <td>{{ venta.id }}</td>
                    <td>{{ new Date(venta.created_at).toLocaleString() }}</td>
                    <td>${{ Number(venta.total).toFixed(2) }}</td>
                    <td>{{ venta.usuario?.name || 'N/A' }}</td>
                    <td>
                      <span v-for="(detalle, idx) in venta.detalles" :key="idx" class="badge bg-info me-1">
                        {{ detalle.producto?.nombre || 'N/A' }} x{{ detalle.cantidad }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <nav v-if="historialPagination.last_page > 1">
              <ul class="pagination">
                <li class="page-item" :class="{ disabled: historialPagination.current_page === 1 }">
                  <a class="page-link" href="#" @click.prevent="cambiarPaginaHistorial(historialPagination.current_page - 1)">Anterior</a>
                </li>
                <li class="page-item" v-for="page in historialPagination.last_page" :key="page" :class="{ active: page === historialPagination.current_page }">
                  <a class="page-link" href="#" @click.prevent="cambiarPaginaHistorial(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: historialPagination.current_page === historialPagination.last_page }">
                  <a class="page-link" href="#" @click.prevent="cambiarPaginaHistorial(historialPagination.current_page + 1)">Siguiente</a>
                </li>
              </ul>
            </nav>
          </div>
        </div>
        <div class="modal-footer-custom">
          <button class="btn btn-secondary" @click="cerrarHistorial">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const clientes = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const mostrarModal = ref(false);
const mostrarHistorial = ref(false);
const clienteEditando = ref(null);
const clienteHistorial = ref(null);
const historialVentas = ref([]);
const historialPagination = ref({ current_page: 1, last_page: 1 });

const filtros = ref({
  search: '',
  tipo: '',
  activo: '',
});

const form = ref({
  nombre: '',
  email: '',
  telefono: '',
  direccion: '',
  rfc: '',
  tipo: 'particular',
  limite_credito: 0,
  notas: '',
  activo: true,
});

const primaryColor = computed(() => {
  const stored = localStorage.getItem('empresa_colores');
  if (stored) {
    try {
      const parsed = JSON.parse(stored);
      return parsed.primary || '#1E293B';
    } catch (e) {}
  }
  return '#1E293B';
});

async function cargarClientes(page = 1) {
  try {
    const params = { page, ...filtros.value };
    if (filtros.value.search) params.search = filtros.value.search;

    const res = await axios.get('/api/v1/clientes', {
      params,
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });
    clientes.value = res.data.data || [];
    pagination.value = {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
      total: res.data.total || 0
    };
  } catch (error) {
    console.error('Error al cargar clientes:', error);
  }
}

function buscar() {
  cargarClientes();
}

function cambiarPagina(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    cargarClientes(page);
  }
}

function abrirModalCrear() {
  clienteEditando.value = null;
  form.value = {
    nombre: '',
    email: '',
    telefono: '',
    direccion: '',
    rfc: '',
    tipo: 'particular',
    limite_credito: 0,
    notas: '',
    activo: true,
  };
  mostrarModal.value = true;
}

function editarCliente(cliente) {
  clienteEditando.value = cliente;
  form.value = { ...cliente };
  mostrarModal.value = true;
}

function cerrarModal() {
  mostrarModal.value = false;
  clienteEditando.value = null;
}

async function guardarCliente() {
  try {
    const url = clienteEditando.value
      ? `/api/v1/clientes/${clienteEditando.value.id}`
      : '/api/v1/clientes';
    const method = clienteEditando.value ? 'put' : 'post';

    await axios[method](url, form.value, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });

    cerrarModal();
    cargarClientes();
    alert(clienteEditando.value ? 'Cliente actualizado correctamente' : 'Cliente creado correctamente');
  } catch (error) {
    console.error(error);
    alert(error.response?.data?.message || 'Error al guardar cliente');
  }
}

async function eliminarCliente(id) {
  if (confirm('¿Eliminar este cliente?')) {
    try {
      await axios.delete(`/api/v1/clientes/${id}`, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      cargarClientes();
    } catch (error) {
      alert('Error al eliminar cliente');
    }
  }
}

async function verHistorial(id) {
  try {
    const res = await axios.get(`/api/v1/clientes/${id}/historial`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });
    clienteHistorial.value = clientes.value.find(c => c.id === id);
    historialVentas.value = res.data.data || [];
    historialPagination.value = {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
    };
    mostrarHistorial.value = true;
  } catch (error) {
    alert('Error al cargar historial');
  }
}

function cerrarHistorial() {
  mostrarHistorial.value = false;
  clienteHistorial.value = null;
  historialVentas.value = [];
}

function cambiarPaginaHistorial(page) {
  if (page >= 1 && page <= historialPagination.value.last_page) {
    verHistorial(clienteHistorial.value.id, page);
  }
}

onMounted(() => {
  cargarClientes();
});
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
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}
.modal-lg {
  max-width: 800px;
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