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

        
        <!-- TABLA: desde md (768px) -->
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

                <!-- Cargando -->
                <tr v-if="cargando">
                  <td colspan="9" class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Cargando...
                  </td>
                </tr>

                <!-- Sin clientes -->
                <tr v-else-if="clientes.length === 0">
                  <td colspan="9" class="text-center py-4 text-muted">
                    No hay clientes registrados
                  </td>
                </tr>

                <!-- Clientes -->
                <tr v-for="cliente in clientes" :key="cliente.id">

                  <!-- ID -->
                  <td class="text-nowrap">
                    {{ cliente.id }}
                  </td>

                  <!-- Nombre -->
                  <td>
                    <strong>
                      {{ cliente.nombre }}
                    </strong>
                  </td>

                  <!-- Email -->
                  <td class="text-break">
                    {{ cliente.email || '-' }}
                  </td>

                  <!-- Teléfono -->
                  <td class="text-nowrap">
                    {{ cliente.telefono || '-' }}
                  </td>

                  <!-- Tipo -->
                  <td class="text-nowrap">
                    <span class="badge" :class="cliente.tipo === 'empresa'
                        ? 'bg-primary'
                        : 'bg-info'
                      ">
                      {{
                        cliente.tipo === 'empresa'
                          ? 'Empresa'
                          : 'Particular'
                      }}
                    </span>
                  </td>

                  <!-- RFC -->
                  <td class="text-nowrap">
                    {{ cliente.rfc || '-' }}
                  </td>

                  <!-- Crédito -->
                  <td class="text-nowrap">
                    ${{ Number(cliente.limite_credito || 0).toFixed(2) }}
                  </td>

                  <!-- Estado -->
                  <td class="text-nowrap">
                    <span class="badge" :class="cliente.activo
                        ? 'bg-success'
                        : 'bg-danger'
                      ">
                      {{ cliente.activo ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>

                  <!-- Acciones -->
                  <td>
                    <div class="d-flex justify-content-center gap-1">

                      <button type="button" class="btn btn-sm btn-info" @click="verHistorial(cliente.id)"
                        title="Historial de compras">
                        📊
                      </button>

                      <button type="button" class="btn btn-sm btn-warning" @click="editarCliente(cliente)"
                        title="Editar cliente">
                        ✏️
                      </button>

                      <button type="button" class="btn btn-sm btn-danger" @click="eliminarCliente(cliente.id)"
                        title="Eliminar cliente">
                        🗑️
                      </button>

                    </div>
                  </td>

                </tr>

              </tbody>

            </table>
          </div>

        </div>


        <!-- ==========================================
     CLIENTES - CARDS MÓVIL
=========================================== -->
        <div class="d-md-none">

          <!-- Cargando -->
          <div v-if="cargando" class="text-center py-5">
            <span class="spinner-border spinner-border-sm me-2"></span>
            Cargando...
          </div>


          <!-- Sin clientes -->
          <div v-else-if="clientes.length === 0" class="text-center py-5 text-muted">
            No hay clientes registrados
          </div>


          <!-- Cards -->
          <div v-for="cliente in clientes" :key="cliente.id" class="card border shadow-sm mb-3">

            <!-- HEADER -->
            <div class="card-header bg-light">

              <div class="d-flex justify-content-between align-items-start">

                <div class="me-2" style="min-width: 0;">

                  <div class="fw-bold text-dark text-break">
                    {{ cliente.nombre }}
                  </div>

                  <small class="text-muted">
                    ID: {{ cliente.id }}
                  </small>

                </div>


                <!-- Estado -->
                <span class="badge flex-shrink-0" :class="cliente.activo
                    ? 'bg-success'
                    : 'bg-danger'
                  ">
                  {{ cliente.activo ? 'Activo' : 'Inactivo' }}
                </span>

              </div>

            </div>


            <!-- INFORMACIÓN -->
            <div class="card-body">

              <!-- Email -->
              <div class="mb-3">

                <div class="small text-muted fw-semibold mb-1">
                  Email
                </div>

                <div class="text-break">
                  {{ cliente.email || '-' }}
                </div>

              </div>


              <!-- Teléfono -->
              <div class="mb-3">

                <div class="small text-muted fw-semibold mb-1">
                  Teléfono
                </div>

                <div>
                  {{ cliente.telefono || '-' }}
                </div>

              </div>


              <!-- Tipo -->
              <div class="mb-3">

                <div class="small text-muted fw-semibold mb-1">
                  Tipo
                </div>

                <span class="badge" :class="cliente.tipo === 'empresa'
                    ? 'bg-primary'
                    : 'bg-info'
                  ">
                  {{
                    cliente.tipo === 'empresa'
                      ? 'Empresa'
                      : 'Particular'
                  }}
                </span>

              </div>


              <!-- RFC -->
              <div class="mb-3">

                <div class="small text-muted fw-semibold mb-1">
                  RFC
                </div>

                <div class="text-break">
                  {{ cliente.rfc || '-' }}
                </div>

              </div>


              <!-- Límite de crédito -->
              <div>

                <div class="small text-muted fw-semibold mb-1">
                  Límite de Crédito
                </div>

                <div class="fw-bold">
                  ${{ Number(cliente.limite_credito || 0).toFixed(2) }}
                </div>

              </div>

            </div>


            <!-- ACCIONES -->
            <div class="card-footer bg-white">

              <div class="row g-2">

                <div class="col-4">
                  <button type="button" class="btn btn-info btn-sm w-100" @click="verHistorial(cliente.id)"
                    title="Historial de compras">
                    📊
                    <span class="d-none d-sm-inline">
                      Historial
                    </span>
                  </button>
                </div>


                <div class="col-4">
                  <button type="button" class="btn btn-warning btn-sm w-100" @click="editarCliente(cliente)"
                    title="Editar cliente">
                    ✏️
                    <span class="d-none d-sm-inline">
                      Editar
                    </span>
                  </button>
                </div>


                <div class="col-4">
                  <button type="button" class="btn btn-danger btn-sm w-100" @click="eliminarCliente(cliente.id)"
                    title="Eliminar cliente">
                    🗑️
                    <span class="d-none d-sm-inline">
                      Eliminar
                    </span>
                  </button>
                </div>

              </div>

            </div>

          </div>

        </div>


        <nav v-if="pagination.last_page > 1" class="mt-4" aria-label="Paginación de clientes">

          <ul class="pagination flex-wrap mb-0">

            <!-- Anterior -->
            <li class="page-item" :class="{
              disabled: pagination.current_page === 1
            }">

              <a class="page-link" href="#" @click.prevent="
                pagination.current_page > 1 &&
                cambiarPagina(
                  pagination.current_page - 1
                )
                ">
                Anterior
              </a>

            </li>


            <!-- Páginas -->
            <li v-for="page in pagination.last_page" :key="page" class="page-item" :class="{
              active:
                page === pagination.current_page
            }">

              <a class="page-link" href="#" @click.prevent="cambiarPagina(page)">
                {{ page }}
              </a>

            </li>


            <!-- Siguiente -->
            <li class="page-item" :class="{
              disabled:
                pagination.current_page ===
                pagination.last_page
            }">

              <a class="page-link" href="#" @click.prevent="
                pagination.current_page <
                pagination.last_page &&
                cambiarPagina(
                  pagination.current_page + 1
                )
                ">
                Siguiente
              </a>

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

    <!-- Modal de historial de compras -->
    <div v-if="mostrarHistorial" class="modal-backdrop" @click.self="cerrarHistorial">
      <div class="modal-content-custom modal-lg">
        <div class="modal-header-custom">
          <h5>Historial de Compras - {{ clienteHistorial?.nombre }}</h5>
          <button class="btn-close" @click="cerrarHistorial"></button>
        </div>
        <div class="modal-body-custom">
          <div v-if="historialCargando" class="text-center py-4">
            <span class="spinner-border spinner-border-sm me-2"></span> Cargando historial...
          </div>
          <div v-else-if="historialVentas.length === 0" class="text-center py-4">
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

const filtros = ref({
  search: '',
  tipo: '',
  activo: '',
});

// ✅ Guardar copia original para comparar cambios
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
  const stored = localStorage.getItem('colorPrincipal') || localStorage.getItem('color_menu');
  if (stored) {
    return stored;
  }
  return '#1E293B';
});

async function cargarClientes(page = 1) {
  cargando.value = true;
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
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Error al cargar clientes'
    });
  } finally {
    cargando.value = false;
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
  clienteOriginal.value = { ...cliente }; // ✅ Guardar copia original
  form.value = { ...cliente };
  mostrarModal.value = true;
}

function cerrarModal() {
  mostrarModal.value = false;
  clienteEditando.value = null;
  clienteOriginal.value = null;
}

// ✅ Función para comparar cambios
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
    const valorOriginal = original[key] ?? '';
    const valorActual = actual[key] ?? '';

    // Para booleanos, comparar como booleanos
    if (typeof valorOriginal === 'boolean' || typeof valorActual === 'boolean') {
      if (Boolean(valorOriginal) !== Boolean(valorActual)) {
        campos.push({
          label: label,
          antes: valorOriginal ? 'Activo' : 'Inactivo',
          despues: valorActual ? 'Activo' : 'Inactivo'
        });
      }
    } else if (String(valorOriginal).trim() !== String(valorActual).trim()) {
      campos.push({
        label: label,
        antes: valorOriginal || '(vacío)',
        despues: valorActual || '(vacío)'
      });
    }
  }

  return campos;
}

// ✅ Función para mostrar mensaje con campos actualizados
function mostrarMensajeActualizacion(campos) {
  if (!campos || campos.length === 0) {
    return Swal.fire({
      icon: 'info',
      title: 'Sin cambios',
      text: 'No se detectaron cambios en el cliente.',
      timer: 2000,
      showConfirmButton: false
    });
  }

  let html = '<div style="text-align: left;">';
  html += '<p><strong>Campos actualizados:</strong></p>';
  html += '<ul style="list-style: none; padding: 0;">';
  for (const campo of campos) {
    html += `<li style="padding: 4px 0; border-bottom: 1px solid #eee;">
      <strong>${campo.label}:</strong><br>
      <span style="color: #dc3545;">⬅ ${campo.antes}</span>
      <span style="color: #28a745;"> ➡ ${campo.despues}</span>
    </li>`;
  }
  html += '</ul></div>';

  return Swal.fire({
    icon: 'success',
    title: 'Cliente actualizado',
    html: html,
    timer: 4000,
    showConfirmButton: true,
    confirmButtonText: 'Aceptar'
  });
}

async function guardarCliente() {
  guardando.value = true;
  try {
    const url = clienteEditando.value
      ? `/api/v1/clientes/${clienteEditando.value.id}`
      : '/api/v1/clientes';
    const method = clienteEditando.value ? 'put' : 'post';

    const response = await axios[method](url, form.value, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });

    const esEdicion = !!clienteEditando.value;
    const campos = esEdicion ? getCamposActualizados() : null;

    cerrarModal();
    await cargarClientes();

    if (esEdicion) {
      // ✅ Mostrar mensaje con campos actualizados
      await mostrarMensajeActualizacion(campos);
    } else {
      await Swal.fire({
        icon: 'success',
        title: 'Cliente creado',
        text: 'Cliente creado correctamente',
        timer: 2000,
        showConfirmButton: false
      });
    }
  } catch (error) {
    console.error(error);
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Error al guardar cliente'
    });
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
      await axios.delete(`/api/v1/clientes/${id}`, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      await cargarClientes();

      await Swal.fire({
        icon: 'success',
        title: 'Eliminado',
        text: 'Cliente eliminado correctamente',
        timer: 2000,
        showConfirmButton: false
      });
    } catch (error) {
      await Swal.fire({
        icon: 'error',
        title: 'Error',
        text: error.response?.data?.message || 'Error al eliminar cliente'
      });
    }
  }
}

async function verHistorial(id, page = 1) {
  historialCargando.value = true;
  try {
    const res = await axios.get(`/api/v1/clientes/${id}/historial`, {
      params: { page },
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
    console.error('Error cargando historial:', error);
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Error al cargar historial de compras'
    });
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