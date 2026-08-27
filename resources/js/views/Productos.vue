<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>📦 Gestión de Productos</h2>
      <button class="btn" :style="{ backgroundColor: primaryColor, color: '#fff' }" @click="abrirModalCrear">
        <i class="bi bi-plus-circle"></i> Nuevo Producto
      </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <input type="text" v-model="filtros.search" class="form-control" placeholder="Buscar por código o nombre..." @input="buscar" />
          </div>
          <div class="col-md-3">
            <select v-model="filtros.categoria_id" class="form-control" @change="buscar">
              <option value="">Todas las categorías</option>
              <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="filtros.activo" class="form-control" @change="buscar">
              <option value="">Todos</option>
              <option value="1">Activos</option>
              <option value="0">Inactivos</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-danger w-100" @click="filtros.stock_minimo = !filtros.stock_minimo; buscar()">
              ⚠️ Stock Bajo
            </button>
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
                <th>Código</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Unidad</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Mínimo</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="producto in productos" :key="producto.id">
                <td><strong>{{ producto.codigo }}</strong></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <img v-if="producto.imagen_url" :src="producto.imagen_url" alt="Producto" width="40" height="40" class="rounded" style="object-fit: cover;" />
                    <span>{{ producto.nombre }}</span>
                  </div>
                </td>
                <td><span class="badge" :style="{ backgroundColor: producto.categoria?.color || '#6c757d', color: '#fff' }">{{ producto.categoria?.nombre || 'Sin categoría' }}</span></td>
                <td>{{ producto.unidad_medida?.nombre || 'N/A' }}</td>
                <td>${{ Number(producto.precio).toFixed(2) }}</td>
                <td>
                  <span class="badge" :class="getStockClass(producto.stock, producto.stock_minimo)">
                    {{ producto.stock }}
                  </span>
                </td>
                <td>{{ producto.stock_minimo }}</td>
                <td>
                  <span class="badge" :class="producto.activo ? 'bg-success' : 'bg-danger'">
                    {{ producto.activo ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td>
                  <button class="btn btn-sm btn-warning me-1" @click="editarProducto(producto)">✏️</button>
                  <button class="btn btn-sm btn-danger" @click="eliminarProducto(producto.id)">🗑️</button>
                  <button class="btn btn-sm btn-info" @click="ajustarStock(producto)">📦</button>
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

    <!-- Modal para crear/editar producto -->
    <div v-if="mostrarModal" class="modal-backdrop" @click.self="cerrarModal">
      <div class="modal-content-custom modal-lg">
        <div class="modal-header-custom">
          <h5>{{ productoEditando ? 'Editar Producto' : 'Nuevo Producto' }}</h5>
          <button class="btn-close" @click="cerrarModal"></button>
        </div>
        <div class="modal-body-custom">
          <form @submit.prevent="guardarProducto">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Código *</label>
                <input v-model="form.codigo" class="form-control" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Nombre *</label>
                <input v-model="form.nombre" class="form-control" required />
              </div>
              <div class="col-md-12">
                <label class="form-label">Descripción</label>
                <textarea v-model="form.descripcion" class="form-control" rows="2"></textarea>
              </div>
              <div class="col-md-4">
                <label class="form-label">Categoría</label>
                <select v-model="form.categoria_id" class="form-control">
                  <option value="">Sin categoría</option>
                  <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Unidad de Medida</label>
                <select v-model="form.unidad_medida_id" class="form-control">
                  <option value="">Sin unidad</option>
                  <option v-for="unidad in unidades" :key="unidad.id" :value="unidad.id">{{ unidad.nombre }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Impuesto (%)</label>
                <input type="number" v-model="form.impuesto" class="form-control" min="0" max="100" step="0.01" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Precio *</label>
                <input type="number" v-model="form.precio" class="form-control" min="0" step="0.01" required />
              </div>
              <div class="col-md-4">
                <label class="form-label">Costo</label>
                <input type="number" v-model="form.costo" class="form-control" min="0" step="0.01" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Stock Inicial</label>
                <input type="number" v-model="form.stock" class="form-control" min="0" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Stock Mínimo</label>
                <input type="number" v-model="form.stock_minimo" class="form-control" min="0" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Imagen</label>
                <input type="file" @change="handleImageUpload" class="form-control" accept="image/*" />
                <small class="text-muted">Formatos: JPG, PNG, GIF, SVG (máx 2MB)</small>
                <div v-if="form.imagen_preview" class="mt-2">
                  <img :src="form.imagen_preview" alt="Vista previa" width="100" class="rounded border" />
                </div>
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
          <button class="btn" :style="{ backgroundColor: primaryColor, color: '#fff' }" @click="guardarProducto">
            {{ productoEditando ? 'Actualizar' : 'Crear' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal para ajustar stock -->
    <div v-if="mostrarModalStock" class="modal-backdrop" @click.self="cerrarModalStock">
      <div class="modal-content-custom">
        <div class="modal-header-custom">
          <h5>Ajustar Stock - {{ productoStock?.nombre }}</h5>
          <button class="btn-close" @click="cerrarModalStock"></button>
        </div>
        <div class="modal-body-custom">
          <div class="mb-3">
            <label class="form-label">Cantidad (positivo para agregar, negativo para restar)</label>
            <input type="number" v-model="ajusteStock.cantidad" class="form-control" required />
            <small class="text-muted">Stock actual: {{ productoStock?.stock }}</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Motivo</label>
            <input type="text" v-model="ajusteStock.motivo" class="form-control" placeholder="Ej: Ajuste por inventario" />
          </div>
        </div>
        <div class="modal-footer-custom">
          <button class="btn btn-secondary" @click="cerrarModalStock">Cancelar</button>
          <button class="btn btn-warning" @click="guardarAjusteStock">Ajustar Stock</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const productos = ref([]);
const categorias = ref([]);
const unidades = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const mostrarModal = ref(false);
const mostrarModalStock = ref(false);
const productoEditando = ref(null);
const productoStock = ref(null);

const filtros = ref({
  search: '',
  categoria_id: '',
  activo: '',
  stock_minimo: false,
});

const form = ref({
  codigo: '',
  nombre: '',
  descripcion: '',
  categoria_id: '',
  unidad_medida_id: '',
  precio: 0,
  costo: 0,
  impuesto: 0,
  stock: 0,
  stock_minimo: 0,
  activo: true,
  imagen: null,
  imagen_preview: null,
});

const ajusteStock = ref({
  cantidad: 0,
  motivo: '',
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

function getStockClass(stock, minimo) {
  if (stock === 0) return 'bg-danger';
  if (stock <= minimo) return 'bg-warning text-dark';
  return 'bg-success';
}

async function cargarProductos(page = 1) {
  try {
    const params = { page, ...filtros.value };
    if (params.stock_minimo) {
      params.stock_minimo = true;
    }
    delete params.search;
    if (filtros.value.search) params.search = filtros.value.search;

    const res = await axios.get('/api/v1/productos', {
      params,
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });
    productos.value = res.data.data || [];
    categorias.value = res.data.categorias || [];
    unidades.value = res.data.unidades || [];
    pagination.value = {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
      total: res.data.total || 0
    };
  } catch (error) {
    console.error('Error al cargar productos:', error);
  }
}

function buscar() {
  cargarProductos();
}

function cambiarPagina(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    cargarProductos(page);
  }
}

function abrirModalCrear() {
  productoEditando.value = null;
  form.value = {
    codigo: '',
    nombre: '',
    descripcion: '',
    categoria_id: '',
    unidad_medida_id: '',
    precio: 0,
    costo: 0,
    impuesto: 0,
    stock: 0,
    stock_minimo: 0,
    activo: true,
    imagen: null,
    imagen_preview: null,
  };
  mostrarModal.value = true;
}

function editarProducto(producto) {
  productoEditando.value = producto;
  form.value = {
    ...producto,
    categoria_id: producto.categoria_id || '',
    unidad_medida_id: producto.unidad_medida_id || '',
    imagen: null,
    imagen_preview: producto.imagen_url || null,
  };
  mostrarModal.value = true;
}

function cerrarModal() {
  mostrarModal.value = false;
  productoEditando.value = null;
}

function handleImageUpload(event) {
  const file = event.target.files[0];
  if (file) {
    form.value.imagen = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      form.value.imagen_preview = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

async function guardarProducto() {
  try {
    const formData = new FormData();
    Object.keys(form.value).forEach(key => {
      if (key === 'imagen' && form.value[key] instanceof File) {
        formData.append(key, form.value[key]);
      } else if (key !== 'imagen_preview' && key !== 'imagen') {
        formData.append(key, form.value[key]);
      }
    });

    const url = productoEditando.value
      ? `/api/v1/productos/${productoEditando.value.id}?_method=PUT`
      : '/api/v1/productos';

    const method = productoEditando.value ? 'post' : 'post';

    const res = await axios({
      method: method,
      url: url,
      data: formData,
      headers: {
        'Content-Type': 'multipart/form-data',
        Authorization: `Bearer ${localStorage.getItem('token')}`
      }
    });

    cerrarModal();
    cargarProductos();
    alert(res.data.message || 'Producto guardado correctamente');
  } catch (error) {
    console.error(error);
    alert(error.response?.data?.message || 'Error al guardar producto');
  }
}

async function eliminarProducto(id) {
  if (confirm('¿Eliminar este producto?')) {
    try {
      await axios.delete(`/api/v1/productos/${id}`, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      cargarProductos();
    } catch (error) {
      alert('Error al eliminar producto');
    }
  }
}

function ajustarStock(producto) {
  productoStock.value = producto;
  ajusteStock.value = { cantidad: 0, motivo: '' };
  mostrarModalStock.value = true;
}

function cerrarModalStock() {
  mostrarModalStock.value = false;
  productoStock.value = null;
}

async function guardarAjusteStock() {
  try {
    await axios.post(
      `/api/v1/productos/${productoStock.value.id}/stock`,
      ajusteStock.value,
      { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } }
    );
    cerrarModalStock();
    cargarProductos();
    alert('Stock ajustado correctamente');
  } catch (error) {
    alert('Error al ajustar stock');
  }
}

// Generar código automático (ejemplo)
function generarCodigo() {
  const prefix = 'PROD';
  const number = String(Date.now()).slice(-6);
  form.value.codigo = `${prefix}${number}`;
}

onMounted(() => {
  cargarProductos();
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
  max-width: 700px;
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