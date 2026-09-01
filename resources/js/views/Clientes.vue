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
            <input type="text" v-model="filtros.search" class="form-control"
              placeholder="Buscar por nombre, email, teléfono..." @input="buscar" />
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

    <div class="card shadow-sm">
      <div class="card-body">
        <!-- TABLA -->
        <div class="d-none d-md-block">
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
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
                  <th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="cargando">
                  <td colspan="9" class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando...
                  </td>
                </tr>
                <tr v-else-if="clientes.length === 0">
                  <td colspan="9" class="text-center py-4 text-muted">No hay clientes registrados</td>
                </tr>
                <tr v-for="cliente in clientes" :key="cliente.id">
                  <td class="text-nowrap">{{ cliente.id }}</td>
                  <td><strong>{{ cliente.nombre }}</strong></td>
                  <td class="text-break">{{ cliente.email || '-' }}</td>
                  <td class="text-nowrap">{{ cliente.telefono || '-' }}</td>
                  <td class="text-nowrap">
                    <span class="badge" :class="cliente.tipo === 'empresa' ? 'bg-primary' : 'bg-info'">
                      {{ cliente.tipo === 'empresa' ? 'Empresa' : 'Particular' }}
                    </span>
                  </td>
                  <td class="text-nowrap">{{ cliente.rfc || '-' }}</td>
                  <td class="text-nowrap">${{ Number(cliente.limite_credito || 0).toFixed(2) }}</td>
                  <td class="text-nowrap">
                    <span class="badge" :class="cliente.activo ? 'bg-success' : 'bg-danger'">
                      {{ cliente.activo ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>
                  <td>
                    <div class="d-flex justify-content-center gap-1">
                      <button type="button" class="btn btn-sm btn-info" @click="verHistorial(cliente.id)"
                        title="Historial">📊</button>
                      <button type="button" class="btn btn-sm btn-warning" @click="editarCliente(cliente)"
                        title="Editar">✏️</button>
                      <button type="button" class="btn btn-sm btn-danger" @click="eliminarCliente(cliente.id)"
                        title="Eliminar">🗑️</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- CARDS MÓVIL -->
        <div class="d-md-none">
          <!-- ... (igual que antes) ... -->
        </div>

        <!-- Paginación -->
        <nav v-if="pagination.last_page > 1" class="mt-4">
          <ul class="pagination flex-wrap mb-0">
            <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
              <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page - 1)">Anterior</a>
            </li>
            <li v-for="page in pagination.last_page" :key="page" class="page-item"
              :class="{ active: page === pagination.current_page }">
              <a class="page-link" href="#" @click.prevent="cambiarPagina(page)">{{ page }}</a>
            </li>
            <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
              <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page + 1)">Siguiente</a>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Modal crear/editar -->
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
                <input v-model="form.telefono" class="form-control" maxlength="10" placeholder="10 dígitos" />
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
          <button class="btn" :style="{ backgroundColor: primaryColor, color: '#fff' }" @click="guardarCliente"
            :disabled="guardando">
            <span v-if="guardando" class="spinner-border spinner-border-sm me-1"></span>
            {{ clienteEditando ? 'Actualizar' : 'Crear' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal historial -->
    <div v-if="mostrarHistorial" class="modal-backdrop" @click.self="cerrarHistorial">
      <div class="modal-content-custom modal-lg">
        <div class="modal-header-custom">
          <h5>Historial de Compras - {{ clienteHistorial?.nombre }}</h5>
          <button class="btn-close" @click="cerrarHistorial"></button>
        </div>
        <div class="modal-body-custom">
          <!-- Cargando -->
          <div v-if="historialCargando" class="text-center py-4">
            <span class="spinner-border spinner-border-sm me-2"></span> Cargando historial...
          </div>
          <!-- Sin ventas -->
          <div v-else-if="historialVentas.length === 0" class="text-center py-4">
            <p class="text-muted">Este cliente no tiene compras registradas.</p>
          </div>
          <!-- Tabla de ventas -->
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
            <!-- Paginación del historial -->
            <nav v-if="historialPagination.last_page > 1" class="mt-3">
              <ul class="pagination">
                <li class="page-item" :class="{ disabled: historialPagination.current_page === 1 }">
                  <a class="page-link" href="#"
                    @click.prevent="cambiarPaginaHistorial(historialPagination.current_page - 1)">Anterior</a>
                </li>
                <li class="page-item" v-for="page in historialPagination.last_page" :key="page"
                  :class="{ active: page === historialPagination.current_page }">
                  <a class="page-link" href="#" @click.prevent="cambiarPaginaHistorial(page)">{{ page }}</a>
                </li>
                <li class="page-item"
                  :class="{ disabled: historialPagination.current_page === historialPagination.last_page }">
                  <a class="page-link" href="#"
                    @click.prevent="cambiarPaginaHistorial(historialPagination.current_page + 1)">Siguiente</a>
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
import Swal from 'sweetalert2';

// ✅ Instancia de axios con interceptor
const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Estado
const clientes = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const cargando = ref(false);
const guardando = ref(false);
const historialCargando = ref(false);

const mostrarModal = ref(false);
const mostrarHistorial = ref(false);
const clienteEditando = ref(null);
const clienteHistorial = ref(null);
const historialVentas = ref([]);
const historialPagination = ref({ current_page: 1, last_page: 1 });

const filtros = ref({ search: '', tipo: '', activo: '' });
const clienteOriginal = ref(null);

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
  return localStorage.getItem('colorPrincipal') || '#1E293B';
});

// =============================================
// CARGAR CLIENTES
// =============================================
async function cargarClientes(page = 1) {
  cargando.value = true;
  try {
    const params = { page, ...filtros.value };
    if (filtros.value.search) params.search = filtros.value.search;

    const res = await api.get('/clientes', { params });

    console.log('📥 Clientes recibidos:', res.data);

    // Asignar datos
    clientes.value = res.data.data || [];
    pagination.value = {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
      total: res.data.total || 0,
    };
  } catch (error) {
    console.error('❌ Error al cargar clientes:', error);
    let mensaje = 'Error al cargar clientes';
    if (error.response?.data?.message) {
      mensaje = error.response.data.message;
    }
    await Swal.fire({ icon: 'error', title: 'Error', text: mensaje });
  } finally {
    cargando.value = false;
  }
}

function buscar() { cargarClientes(); }
function cambiarPagina(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    cargarClientes(page);
  }
}

// =============================================
// CRUD CLIENTES
// =============================================
function abrirModalCrear() {
  clienteEditando.value = null;
  clienteOriginal.value = null;
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
  clienteOriginal.value = { ...cliente };
  form.value = { ...cliente };
  mostrarModal.value = true;
}

function cerrarModal() {
  mostrarModal.value = false;
  clienteEditando.value = null;
  clienteOriginal.value = null;
}

function getCamposActualizados() {
  if (!clienteOriginal.value) return null;
  const campos = [];
  const original = clienteOriginal.value;
  const actual = form.value;
  const camposComparar = {
    nombre: 'Nombre',
    email: 'Email',
    telefono: 'Teléfono',
    direccion: 'Dirección',
    rfc: 'RFC',
    tipo: 'Tipo',
    limite_credito: 'Límite de Crédito',
    notas: 'Notas',
    activo: 'Estado'
  };
  for (const [key, label] of Object.entries(camposComparar)) {
    const vOriginal = original[key] ?? '';
    const vActual = actual[key] ?? '';
    if (typeof vOriginal === 'boolean' || typeof vActual === 'boolean') {
      if (Boolean(vOriginal) !== Boolean(vActual)) {
        campos.push({ label, antes: vOriginal ? 'Activo' : 'Inactivo', despues: vActual ? 'Activo' : 'Inactivo' });
      }
    } else if (String(vOriginal).trim() !== String(vActual).trim()) {
      campos.push({ label, antes: vOriginal || '(vacío)', despues: vActual || '(vacío)' });
    }
  }
  return campos;
}

function mostrarMensajeActualizacion(campos) {
  if (!campos || campos.length === 0) {
    return Swal.fire({ icon: 'info', title: 'Sin cambios', text: 'No se detectaron cambios.', timer: 2000, showConfirmButton: false });
  }
  let html = '<div style="text-align: left;"><p><strong>Campos actualizados:</strong></p><ul style="list-style: none; padding: 0;">';
  for (const campo of campos) {
    html += `<li style="padding: 4px 0; border-bottom: 1px solid #eee;">
      <strong>${campo.label}:</strong><br>
      <span style="color: #dc3545;">⬅ ${campo.antes}</span>
      <span style="color: #28a745;"> ➡ ${campo.despues}</span>
    </li>`;
  }
  html += '</ul></div>';
  return Swal.fire({ icon: 'success', title: 'Cliente actualizado', html, timer: 4000, showConfirmButton: true, confirmButtonText: 'Aceptar' });
}

async function guardarCliente() {
  guardando.value = true;
  try {
    const url = clienteEditando.value ? `/clientes/${clienteEditando.value.id}` : '/clientes';
    const method = clienteEditando.value ? 'put' : 'post';

    // ✅ Enviar solo los campos del formulario (sin empresa_id)
    const payload = { ...form.value };
    // El backend asignará empresa_id automáticamente (superadmin usará la suya)

    const response = await api[method](url, payload);

    const esEdicion = !!clienteEditando.value;
    const campos = esEdicion ? getCamposActualizados() : null;

    cerrarModal();
    await cargarClientes();

    if (esEdicion) {
      await mostrarMensajeActualizacion(campos);
    } else {
      await Swal.fire({ icon: 'success', title: 'Cliente creado', text: 'Cliente creado correctamente', timer: 2000, showConfirmButton: false });
    }
  } catch (error) {
    console.error(error);
    await Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al guardar cliente' });
  } finally {
    guardando.value = false;
  }
}

async function eliminarCliente(id) {
  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: 'Esta acción no se puede deshacer',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  });
  if (result.isConfirmed) {
    try {
      await api.delete(`/clientes/${id}`);
      await cargarClientes();
      await Swal.fire({ icon: 'success', title: 'Eliminado', text: 'Cliente eliminado correctamente', timer: 2000, showConfirmButton: false });
    } catch (error) {
      await Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al eliminar cliente' });
    }
  }
}

// =============================================
// HISTORIAL
// =============================================
async function verHistorial(id, page = 1) {
  historialCargando.value = true;
  try {
    const res = await api.get(`/clientes/${id}/historial`, { params: { page } });
    clienteHistorial.value = clientes.value.find(c => c.id === id);
    historialVentas.value = res.data.data || [];
    historialPagination.value = {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
    };
    mostrarHistorial.value = true;
  } catch (error) {
    console.error('Error cargando historial:', error);
    await Swal.fire({ icon: 'error', title: 'Error', text: 'Error al cargar historial de compras' });
  } finally {
    historialCargando.value = false;
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

// =============================================
// MOUNT
// =============================================
onMounted(() => {
  cargarClientes();
});
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
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