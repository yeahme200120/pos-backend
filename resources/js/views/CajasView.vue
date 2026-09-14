<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

// ======================================================
// CONFIGURACIÓN VISUAL
// ======================================================

const primaryColor = computed(() => {
  return localStorage.getItem('colorPrincipal') || '#4678EC';
});

// ======================================================
// ESTADO
// ======================================================

const cajaActual = ref(null);
const operaciones = ref([]);

const cargando = ref(false);
const cargandoOperaciones = ref(false);

const mostrarAbrir = ref(false);
const mostrarCerrar = ref(false);
const mostrarMovimiento = ref(false);

const filtroFecha = ref('');
const filtroTipo = ref('');

const pagina = ref(1);
const porPagina = 15;

// ======================================================
// FORMULARIOS
// ======================================================

const formAbrir = reactive({
  monto_apertura: '',
  notas_apertura: '',
});

const formCerrar = reactive({
  monto_cierre_declarado: '',
  notas_cierre: '',
});

const formMovimiento = reactive({
  tipo: 'ingreso',
  concepto: '',
  monto: '',
  referencia: '',
  notas: '',
});

// ======================================================
// COMPUTED
// ======================================================

const cajaAbierta = computed(() => {
  return cajaActual.value?.estado === 'abierta';
});

const operacionesFiltradas = computed(() => {
  let resultado = [...operaciones.value];

  if (filtroTipo.value) {
    resultado = resultado.filter(
      (item) => String(item.tipo || '').toLowerCase() === filtroTipo.value
    );
  }

  if (filtroFecha.value) {
    resultado = resultado.filter((item) => {
      const fecha = obtenerFecha(item.fecha_movimiento || item.created_at);

      return fecha === filtroFecha.value;
    });
  }

  return resultado;
});

const totalPaginas = computed(() => {
  return Math.max(
    1,
    Math.ceil(operacionesFiltradas.value.length / porPagina)
  );
});

const operacionesPaginadas = computed(() => {
  const inicio = (pagina.value - 1) * porPagina;

  return operacionesFiltradas.value.slice(inicio, inicio + porPagina);
});

const totalIngresos = computed(() => {
  return operaciones.value
    .filter((item) => String(item.tipo).toLowerCase() === 'ingreso')
    .reduce((total, item) => total + numero(item.monto), 0);
});

const totalRetiros = computed(() => {
  return operaciones.value
    .filter((item) => {
      const tipo = String(item.tipo).toLowerCase();

      return ['retiro', 'gasto'].includes(tipo);
    })
    .reduce((total, item) => total + numero(item.monto), 0);
});

// ======================================================
// UTILIDADES
// ======================================================

function numero(valor) {
  const numeroConvertido = Number(valor);

  return Number.isFinite(numeroConvertido) ? numeroConvertido : 0;
}

function moneda(valor) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
  }).format(numero(valor));
}

function fechaHora(valor) {
  if (!valor) {
    return '—';
  }

  const fecha = new Date(valor);

  if (Number.isNaN(fecha.getTime())) {
    return '—';
  }

  return fecha.toLocaleString('es-MX', {
    dateStyle: 'short',
    timeStyle: 'short',
  });
}

function obtenerFecha(valor) {
  if (!valor) {
    return '';
  }

  const fecha = new Date(valor);

  if (Number.isNaN(fecha.getTime())) {
    return '';
  }

  const year = fecha.getFullYear();
  const month = String(fecha.getMonth() + 1).padStart(2, '0');
  const day = String(fecha.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function formatearTipo(tipo) {
  const tipos = {
    ingreso: 'Ingreso',
    retiro: 'Retiro',
    gasto: 'Gasto',
    ajuste: 'Ajuste',
  };

  return tipos[String(tipo || '').toLowerCase()] || tipo || '—';
}

function claseTipo(tipo) {
  switch (String(tipo || '').toLowerCase()) {
    case 'ingreso':
      return 'badge-success';

    case 'retiro':
    case 'gasto':
      return 'badge-danger';

    case 'ajuste':
      return 'badge-warning';

    default:
      return 'badge-secondary';
  }
}

function mensajeError(error, mensajeDefault = 'Ocurrió un error.') {
  const response = error?.response;

  if (!response) {
    return error?.message || mensajeDefault;
  }

  if (response.status === 422) {
    const errores = response.data?.errors;

    if (errores && typeof errores === 'object') {
      const mensajes = Object.values(errores)
        .flat()
        .filter(Boolean);

      if (mensajes.length) {
        return mensajes.join('<br>');
      }
    }
  }

  return (
    response.data?.message ||
    response.data?.error ||
    mensajeDefault
  );
}

function normalizarLista(response) {
  const data = response?.data;

  if (Array.isArray(data)) {
    return data;
  }

  if (Array.isArray(data?.data)) {
    return data.data;
  }

  if (Array.isArray(data?.operaciones)) {
    return data.operaciones;
  }

  if (Array.isArray(data?.movimientos)) {
    return data.movimientos;
  }

  return [];
}

function normalizarCaja(response) {
  const data = response?.data;

  return data?.caja ||
    data?.data ||
    data ||
    null;
}

function resetPagina() {
  pagina.value = 1;
}

// ======================================================
// CARGA DE DATOS
// ======================================================

async function cargarCajaActual() {
  cargando.value = true;

  try {
    const response = await api.get('/cajas/actual');

    cajaActual.value = normalizarCaja(response);
  } catch (error) {
    cajaActual.value = null;

    if (error?.response?.status !== 404) {
      await Swal.fire({
        icon: 'error',
        title: 'No se pudo consultar la caja',
        html: mensajeError(
          error,
          'No fue posible obtener la caja actual.'
        ),
      });
    }
  } finally {
    cargando.value = false;
  }
}

async function cargarOperaciones() {
  cargandoOperaciones.value = true;

  try {
    const params = {};

    if (filtroFecha.value) {
      params.fecha = filtroFecha.value;
    }

    if (filtroTipo.value) {
      params.tipo = filtroTipo.value;
    }

    const response = await api.get('/cajas/operaciones', {
      params,
    });

    operaciones.value = normalizarLista(response);

    resetPagina();
  } catch (error) {
    operaciones.value = [];

    await Swal.fire({
      icon: 'error',
      title: 'No se pudieron cargar las operaciones',
      html: mensajeError(
        error,
        'No fue posible obtener los movimientos de caja.'
      ),
    });
  } finally {
    cargandoOperaciones.value = false;
  }
}

async function recargarTodo() {
  await Promise.all([
    cargarCajaActual(),
    cargarOperaciones(),
  ]);
}

// ======================================================
// MODALES
// ======================================================

function abrirModalAbrirCaja() {
  formAbrir.monto_apertura = '';
  formAbrir.notas_apertura = '';

  mostrarAbrir.value = true;
}

function cerrarModalAbrirCaja() {
  mostrarAbrir.value = false;
}

function abrirModalCerrarCaja() {
  formCerrar.monto_cierre_declarado = '';
  formCerrar.notas_cierre = '';

  mostrarCerrar.value = true;
}

function cerrarModalCerrarCaja() {
  mostrarCerrar.value = false;
}

function abrirModalMovimiento() {
  formMovimiento.tipo = 'ingreso';
  formMovimiento.concepto = '';
  formMovimiento.monto = '';
  formMovimiento.referencia = '';
  formMovimiento.notas = '';

  mostrarMovimiento.value = true;
}

function cerrarModalMovimiento() {
  mostrarMovimiento.value = false;
}

// ======================================================
// ABRIR CAJA
// ======================================================

async function abrirCaja() {
  if (formAbrir.monto_apertura === '') {
    await Swal.fire({
      icon: 'warning',
      title: 'Monto requerido',
      text: 'Indica el monto de apertura de la caja.',
    });

    return;
  }

  const monto = numero(formAbrir.monto_apertura);

  if (monto < 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Monto inválido',
      text: 'El monto de apertura no puede ser negativo.',
    });

    return;
  }

  try {
    cargando.value = true;

    await api.post('/cajas/abrir', {
      monto_apertura: monto,
      notas_apertura: formAbrir.notas_apertura?.trim() || null,
    });

    mostrarAbrir.value = false;

    await Swal.fire({
      icon: 'success',
      title: 'Caja abierta',
      text: 'La caja fue abierta correctamente.',
      timer: 1800,
      showConfirmButton: false,
    });

    await recargarTodo();
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'No se pudo abrir la caja',
      html: mensajeError(
        error,
        'No fue posible abrir la caja.'
      ),
    });
  } finally {
    cargando.value = false;
  }
}

// ======================================================
// CERRAR CAJA
// ======================================================

async function cerrarCaja() {
  if (!cajaActual.value?.id) {
    return;
  }

  if (formCerrar.monto_cierre_declarado === '') {
    await Swal.fire({
      icon: 'warning',
      title: 'Monto requerido',
      text: 'Indica el monto declarado para el cierre.',
    });

    return;
  }

  const monto = numero(formCerrar.monto_cierre_declarado);

  if (monto < 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Monto inválido',
      text: 'El monto de cierre no puede ser negativo.',
    });

    return;
  }

  const confirmacion = await Swal.fire({
    icon: 'warning',
    title: '¿Cerrar caja?',
    html: `
      <div class="text-start">
        <p class="mb-2">
          Esta acción cerrará la caja actual.
        </p>
        <p class="mb-0">
          <strong>Monto declarado:</strong>
          ${moneda(monto)}
        </p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Sí, cerrar caja',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  });

  if (!confirmacion.isConfirmed) {
    return;
  }

  try {
    cargando.value = true;

    await api.post(`/cajas/${cajaActual.value.id}/cerrar`, {
      monto_cierre_declarado: monto,
      notas_cierre: formCerrar.notas_cierre?.trim() || null,
    });

    mostrarCerrar.value = false;

    await Swal.fire({
      icon: 'success',
      title: 'Caja cerrada',
      text: 'La caja fue cerrada correctamente.',
      timer: 1800,
      showConfirmButton: false,
    });

    await recargarTodo();
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'No se pudo cerrar la caja',
      html: mensajeError(
        error,
        'No fue posible cerrar la caja.'
      ),
    });
  } finally {
    cargando.value = false;
  }
}

// ======================================================
// MOVIMIENTO
// ======================================================

async function registrarMovimiento() {
  if (!cajaActual.value?.id) {
    await Swal.fire({
      icon: 'warning',
      title: 'Caja no disponible',
      text: 'No existe una caja abierta para registrar el movimiento.',
    });

    return;
  }

  if (!formMovimiento.concepto.trim()) {
    await Swal.fire({
      icon: 'warning',
      title: 'Concepto requerido',
      text: 'Indica el concepto del movimiento.',
    });

    return;
  }

  const monto = numero(formMovimiento.monto);

  if (monto <= 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Monto inválido',
      text: 'El monto debe ser mayor que cero.',
    });

    return;
  }

  try {
    cargando.value = true;

    await api.post('/cajas/movimientos', {
      tipo: formMovimiento.tipo,
      concepto: formMovimiento.concepto.trim(),
      monto,
      referencia: formMovimiento.referencia?.trim() || null,
      notas: formMovimiento.notas?.trim() || null,
      caja_id: cajaActual.value.id,
    });

    mostrarMovimiento.value = false;

    await Swal.fire({
      icon: 'success',
      title: 'Movimiento registrado',
      text: 'El movimiento fue registrado correctamente.',
      timer: 1800,
      showConfirmButton: false,
    });

    await recargarTodo();
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'No se pudo registrar el movimiento',
      html: mensajeError(
        error,
        'No fue posible registrar el movimiento de caja.'
      ),
    });
  } finally {
    cargando.value = false;
  }
}

// ======================================================
// FILTROS
// ======================================================

function cambiarFiltro() {
  resetPagina();

  cargarOperaciones();
}

function limpiarFiltros() {
  filtroFecha.value = '';
  filtroTipo.value = '';

  resetPagina();

  cargarOperaciones();
}

// ======================================================
// PAGINACIÓN
// ======================================================

function paginaAnterior() {
  if (pagina.value > 1) {
    pagina.value--;
  }
}

function paginaSiguiente() {
  if (pagina.value < totalPaginas.value) {
    pagina.value++;
  }
}

// ======================================================
// CICLO DE VIDA
// ======================================================

onMounted(() => {
  recargarTodo();
});
</script>

<template>
  <div
    class="cajas-view"
    :style="{ '--primary-color': primaryColor }"
  >
    <!-- =====================================================
         ENCABEZADO
    ====================================================== -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h2 class="mb-1 fw-bold">
          💰 Gestión de Cajas
        </h2>

        <p class="text-secondary mb-0">
          Administra aperturas, cierres y movimientos de caja.
        </p>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button
          v-if="!cajaAbierta"
          type="button"
          class="btn btn-primary"
          @click="abrirModalAbrirCaja"
        >
          <i class="bi bi-unlock me-1"></i>
          Abrir caja
        </button>

        <button
          v-if="cajaAbierta"
          type="button"
          class="btn btn-outline-primary"
          @click="abrirModalMovimiento"
        >
          <i class="bi bi-plus-circle me-1"></i>
          Movimiento
        </button>

        <button
          v-if="cajaAbierta"
          type="button"
          class="btn btn-outline-danger"
          @click="abrirModalCerrarCaja"
        >
          <i class="bi bi-lock me-1"></i>
          Cerrar caja
        </button>

        <button
          type="button"
          class="btn btn-outline-secondary"
          :disabled="cargando || cargandoOperaciones"
          @click="recargarTodo"
        >
          <i
            class="bi bi-arrow-clockwise me-1"
            :class="{ 'spin': cargando || cargandoOperaciones }"
          ></i>

          Actualizar
        </button>
      </div>
    </div>

    <!-- =====================================================
         CAJA ACTUAL
    ====================================================== -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <div class="small text-secondary mb-1">
                  Caja actual
                </div>

                <h4 class="fw-bold mb-2">
                  {{ cajaActual?.id ? `Caja #${cajaActual.id}` : 'Sin caja abierta' }}
                </h4>
              </div>

              <span
                v-if="cajaActual"
                class="status-badge"
                :class="cajaAbierta ? 'status-open' : 'status-closed'"
              >
                {{ cajaAbierta ? 'Abierta' : 'Cerrada' }}
              </span>
            </div>

            <div
              v-if="cajaActual"
              class="row g-3 mt-2"
            >
              <div class="col-6 col-md-3">
                <div class="metric">
                  <span>Monto apertura</span>
                  <strong>
                    {{ moneda(cajaActual.monto_apertura) }}
                  </strong>
                </div>
              </div>

              <div class="col-6 col-md-3">
                <div class="metric">
                  <span>Monto esperado</span>
                  <strong>
                    {{ moneda(cajaActual.monto_esperado) }}
                  </strong>
                </div>
              </div>

              <div class="col-6 col-md-3">
                <div class="metric">
                  <span>Monto declarado</span>
                  <strong>
                    {{ moneda(cajaActual.monto_cierre_declarado) }}
                  </strong>
                </div>
              </div>

              <div class="col-6 col-md-3">
                <div class="metric">
                  <span>Diferencia</span>
                  <strong
                    :class="{
                      'text-danger': numero(cajaActual.diferencia) < 0,
                      'text-success': numero(cajaActual.diferencia) > 0,
                    }"
                  >
                    {{ moneda(cajaActual.diferencia) }}
                  </strong>
                </div>
              </div>
            </div>

            <div
              v-if="cajaActual"
              class="mt-3 pt-3 border-top small text-secondary"
            >
              <div class="d-flex flex-wrap gap-4">
                <span>
                  <i class="bi bi-calendar3 me-1"></i>
                  Fecha comercial:
                  <strong class="text-dark">
                    {{ cajaActual.fecha_comercial || '—' }}
                  </strong>
                </span>

                <span>
                  <i class="bi bi-clock me-1"></i>
                  Apertura:
                  <strong class="text-dark">
                    {{ fechaHora(cajaActual.abierta_en) }}
                  </strong>
                </span>

                <span v-if="cajaActual.cerrada_en">
                  <i class="bi bi-clock-history me-1"></i>
                  Cierre:
                  <strong class="text-dark">
                    {{ fechaHora(cajaActual.cerrada_en) }}
                  </strong>
                </span>
              </div>
            </div>

            <div
              v-if="!cajaActual"
              class="empty-current mt-3"
            >
              <i class="bi bi-cash-stack"></i>

              <div>
                <strong>No hay una caja abierta</strong>

                <p class="mb-0 text-secondary">
                  Abre una caja para comenzar a registrar operaciones.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- RESUMEN MOVIMIENTOS -->
      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="fw-bold mb-3">
              Resumen de movimientos
            </h6>

            <div class="summary-row">
              <span>
                <span class="summary-dot success"></span>
                Ingresos
              </span>

              <strong class="text-success">
                {{ moneda(totalIngresos) }}
              </strong>
            </div>

            <div class="summary-row">
              <span>
                <span class="summary-dot danger"></span>
                Retiros / gastos
              </span>

              <strong class="text-danger">
                {{ moneda(totalRetiros) }}
              </strong>
            </div>

            <div class="summary-row">
              <span>
                <span class="summary-dot primary"></span>
                Movimientos
              </span>

              <strong>
                {{ operaciones.length }}
              </strong>
            </div>

            <div class="summary-total">
              <span>Neto de movimientos</span>

              <strong>
                {{ moneda(totalIngresos - totalRetiros) }}
              </strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- =====================================================
         HISTORIAL
    ====================================================== -->
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h5 class="fw-bold mb-1">
              Historial de operaciones
            </h5>

            <p class="text-secondary small mb-0">
              Consulta los movimientos registrados en las cajas.
            </p>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <input
              v-model="filtroFecha"
              type="date"
              class="form-control"
              @change="cambiarFiltro"
            />

            <select
              v-model="filtroTipo"
              class="form-select"
              @change="cambiarFiltro"
            >
              <option value="">
                Todos los tipos
              </option>

              <option value="ingreso">
                Ingresos
              </option>

              <option value="retiro">
                Retiros
              </option>

              <option value="gasto">
                Gastos
              </option>

              <option value="ajuste">
                Ajustes
              </option>
            </select>

            <button
              v-if="filtroFecha || filtroTipo"
              type="button"
              class="btn btn-outline-secondary"
              @click="limpiarFiltros"
            >
              <i class="bi bi-x-circle me-1"></i>
              Limpiar
            </button>
          </div>
        </div>

        <!-- DESKTOP -->
        <div class="table-responsive d-none d-md-block">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Concepto</th>
                <th>Referencia</th>
                <th class="text-end">Monto</th>
                <th>Notas</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="cargandoOperaciones">
                <td
                  colspan="6"
                  class="text-center py-5"
                >
                  <div class="spinner-border spinner-border-sm me-2"></div>
                  Cargando operaciones...
                </td>
              </tr>

              <tr v-else-if="!operacionesPaginadas.length">
                <td
                  colspan="6"
                  class="text-center py-5 text-secondary"
                >
                  <div class="empty-table">
                    <i class="bi bi-receipt"></i>

                    <div>
                      <strong>No hay operaciones</strong>
                      <div class="small">
                        No existen movimientos para los filtros seleccionados.
                      </div>
                    </div>
                  </div>
                </td>
              </tr>

              <tr
                v-for="operacion in operacionesPaginadas"
                v-else
                :key="operacion.id"
              >
                <td>
                  <div class="fw-semibold">
                    {{ fechaHora(
                      operacion.fecha_movimiento ||
                      operacion.created_at
                    ) }}
                  </div>
                </td>

                <td>
                  <span
                    class="type-badge"
                    :class="claseTipo(operacion.tipo)"
                  >
                    {{ formatearTipo(operacion.tipo) }}
                  </span>
                </td>

                <td>
                  <div class="fw-semibold">
                    {{ operacion.concepto || '—' }}
                  </div>

                  <div
                    v-if="operacion.usuario?.name"
                    class="small text-secondary"
                  >
                    {{ operacion.usuario.name }}
                  </div>
                </td>

                <td>
                  {{ operacion.referencia || '—' }}
                </td>

                <td class="text-end fw-bold">
                  {{ moneda(operacion.monto) }}
                </td>

                <td>
                  <span class="small text-secondary">
                    {{ operacion.notas || '—' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- MOBILE -->
        <div class="d-md-none">
          <div
            v-if="cargandoOperaciones"
            class="text-center py-5"
          >
            <div class="spinner-border spinner-border-sm me-2"></div>
            Cargando operaciones...
          </div>

          <div
            v-else-if="!operacionesPaginadas.length"
            class="text-center py-5 text-secondary"
          >
            <i class="bi bi-receipt empty-icon"></i>

            <div class="mt-2 fw-semibold">
              No hay operaciones
            </div>
          </div>

          <div
            v-for="operacion in operacionesPaginadas"
            v-else
            :key="operacion.id"
            class="operation-card"
          >
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="small text-secondary">
                  {{ fechaHora(
                    operacion.fecha_movimiento ||
                    operacion.created_at
                  ) }}
                </div>

                <div class="fw-bold mt-1">
                  {{ operacion.concepto || 'Sin concepto' }}
                </div>
              </div>

              <span
                class="type-badge"
                :class="claseTipo(operacion.tipo)"
              >
                {{ formatearTipo(operacion.tipo) }}
              </span>
            </div>

            <div class="operation-amount">
              {{ moneda(operacion.monto) }}
            </div>

            <div class="operation-details">
              <div>
                <span>Referencia</span>
                <strong>
                  {{ operacion.referencia || '—' }}
                </strong>
              </div>

              <div>
                <span>Notas</span>
                <strong>
                  {{ operacion.notas || '—' }}
                </strong>
              </div>
            </div>
          </div>
        </div>

        <!-- PAGINACIÓN -->
        <div
          v-if="totalPaginas > 1"
          class="d-flex justify-content-between align-items-center mt-4"
        >
          <small class="text-secondary">
            Página {{ pagina }} de {{ totalPaginas }}
          </small>

          <div class="d-flex gap-2">
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              :disabled="pagina <= 1"
              @click="paginaAnterior"
            >
              <i class="bi bi-chevron-left"></i>
            </button>

            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              :disabled="pagina >= totalPaginas"
              @click="paginaSiguiente"
            >
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- =====================================================
         MODAL ABRIR CAJA
    ====================================================== -->
    <div
      v-if="mostrarAbrir"
      class="modal-backdrop"
      @click.self="cerrarModalAbrirCaja"
    >
      <div class="modal-content-custom">
        <div class="modal-header-custom">
          <div>
            <h5 class="mb-1 fw-bold">
              Abrir caja
            </h5>

            <small class="text-secondary">
              Registra el monto inicial de efectivo.
            </small>
          </div>

          <button
            type="button"
            class="btn-close"
            @click="cerrarModalAbrirCaja"
          ></button>
        </div>

        <form @submit.prevent="abrirCaja">
          <div class="modal-body-custom">
            <div class="mb-3">
              <label class="form-label fw-semibold">
                Monto de apertura
              </label>

              <div class="input-group">
                <span class="input-group-text">
                  $
                </span>

                <input
                  v-model="formAbrir.monto_apertura"
                  type="number"
                  min="0"
                  step="0.01"
                  class="form-control"
                  placeholder="0.00"
                  required
                />
              </div>
            </div>

            <div>
              <label class="form-label fw-semibold">
                Notas de apertura
              </label>

              <textarea
                v-model="formAbrir.notas_apertura"
                class="form-control"
                rows="3"
                maxlength="2000"
                placeholder="Observaciones opcionales..."
              ></textarea>
            </div>
          </div>

          <div class="modal-footer-custom">
            <button
              type="button"
              class="btn btn-outline-secondary"
              :disabled="cargando"
              @click="cerrarModalAbrirCaja"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="btn btn-primary"
              :disabled="cargando"
            >
              <span
                v-if="cargando"
                class="spinner-border spinner-border-sm me-1"
              ></span>

              Abrir caja
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- =====================================================
         MODAL CERRAR CAJA
    ====================================================== -->
    <div
      v-if="mostrarCerrar"
      class="modal-backdrop"
      @click.self="cerrarModalCerrarCaja"
    >
      <div class="modal-content-custom">
        <div class="modal-header-custom">
          <div>
            <h5 class="mb-1 fw-bold">
              Cerrar caja
            </h5>

            <small class="text-secondary">
              Declara el efectivo contado al finalizar la operación.
            </small>
          </div>

          <button
            type="button"
            class="btn-close"
            @click="cerrarModalCerrarCaja"
          ></button>
        </div>

        <form @submit.prevent="cerrarCaja">
          <div class="modal-body-custom">
            <div class="closing-summary mb-3">
              <div>
                <span>Monto esperado</span>

                <strong>
                  {{ moneda(cajaActual?.monto_esperado) }}
                </strong>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">
                Monto de cierre declarado
              </label>

              <div class="input-group">
                <span class="input-group-text">
                  $
                </span>

                <input
                  v-model="formCerrar.monto_cierre_declarado"
                  type="number"
                  min="0"
                  step="0.01"
                  class="form-control"
                  placeholder="0.00"
                  required
                />
              </div>
            </div>

            <div>
              <label class="form-label fw-semibold">
                Notas de cierre
              </label>

              <textarea
                v-model="formCerrar.notas_cierre"
                class="form-control"
                rows="3"
                maxlength="2000"
                placeholder="Observaciones del cierre..."
              ></textarea>
            </div>
          </div>

          <div class="modal-footer-custom">
            <button
              type="button"
              class="btn btn-outline-secondary"
              :disabled="cargando"
              @click="cerrarModalCerrarCaja"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="btn btn-danger"
              :disabled="cargando"
            >
              <span
                v-if="cargando"
                class="spinner-border spinner-border-sm me-1"
              ></span>

              Cerrar caja
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- =====================================================
         MODAL MOVIMIENTO
    ====================================================== -->
    <div
      v-if="mostrarMovimiento"
      class="modal-backdrop"
      @click.self="cerrarModalMovimiento"
    >
      <div class="modal-content-custom modal-lg">
        <div class="modal-header-custom">
          <div>
            <h5 class="mb-1 fw-bold">
              Registrar movimiento
            </h5>

            <small class="text-secondary">
              Registra ingresos, retiros, gastos o ajustes.
            </small>
          </div>

          <button
            type="button"
            class="btn-close"
            @click="cerrarModalMovimiento"
          ></button>
        </div>

        <form @submit.prevent="registrarMovimiento">
          <div class="modal-body-custom">
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">
                  Tipo
                </label>

                <select
                  v-model="formMovimiento.tipo"
                  class="form-select"
                  required
                >
                  <option value="ingreso">
                    Ingreso
                  </option>

                  <option value="retiro">
                    Retiro
                  </option>

                  <option value="gasto">
                    Gasto
                  </option>

                  <option value="ajuste">
                    Ajuste
                  </option>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">
                  Monto
                </label>

                <div class="input-group">
                  <span class="input-group-text">
                    $
                  </span>

                  <input
                    v-model="formMovimiento.monto"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="form-control"
                    placeholder="0.00"
                    required
                  />
                </div>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">
                  Concepto
                </label>

                <input
                  v-model="formMovimiento.concepto"
                  type="text"
                  class="form-control"
                  maxlength="255"
                  placeholder="Ej. Compra de insumos"
                  required
                />
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">
                  Referencia
                </label>

                <input
                  v-model="formMovimiento.referencia"
                  type="text"
                  class="form-control"
                  maxlength="150"
                  placeholder="Folio, comprobante o referencia..."
                />
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">
                  Notas
                </label>

                <textarea
                  v-model="formMovimiento.notas"
                  class="form-control"
                  rows="3"
                  maxlength="2000"
                  placeholder="Observaciones adicionales..."
                ></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer-custom">
            <button
              type="button"
              class="btn btn-outline-secondary"
              :disabled="cargando"
              @click="cerrarModalMovimiento"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="btn btn-primary"
              :disabled="cargando"
            >
              <span
                v-if="cargando"
                class="spinner-border spinner-border-sm me-1"
              ></span>

              Registrar movimiento
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cajas-view {
  --color-primary: var(--primary-color, #4678EC);
  --color-primary-dark: #2858C7;

  --color-background: #F8FAFC;
  --color-surface: #FFFFFF;

  --color-text: #172033;
  --color-text-secondary: #526078;
  --color-muted: #7B879C;

  --color-border: #E2E8F0;

  --color-success: #2E9B6F;
  --color-warning: #D89432;
  --color-danger: #D95C5C;

  color: var(--color-text);
}

/* ======================================================
   BOTONES
====================================================== */

.cajas-view .btn-primary {
  background-color: var(--color-primary);
  border-color: var(--color-primary);
}

.cajas-view .btn-primary:hover {
  background-color: var(--color-primary-dark);
  border-color: var(--color-primary-dark);
}

.cajas-view .btn-outline-primary {
  color: var(--color-primary);
  border-color: var(--color-primary);
}

.cajas-view .btn-outline-primary:hover {
  color: #fff;
  background-color: var(--color-primary);
  border-color: var(--color-primary);
}

/* ======================================================
   MÉTRICAS
====================================================== */

.metric {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.metric span {
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

.metric strong {
  color: var(--color-text);
  font-size: 1rem;
}

/* ======================================================
   ESTADOS
====================================================== */

.status-badge,
.type-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
}

.status-open,
.badge-success {
  color: var(--color-success);
  background: color-mix(
    in srgb,
    var(--color-success) 12%,
    transparent
  );
}

.status-closed,
.badge-secondary {
  color: var(--color-muted);
  background: color-mix(
    in srgb,
    var(--color-muted) 12%,
    transparent
  );
}

.badge-danger {
  color: var(--color-danger);
  background: color-mix(
    in srgb,
    var(--color-danger) 12%,
    transparent
  );
}

.badge-warning {
  color: var(--color-warning);
  background: color-mix(
    in srgb,
    var(--color-warning) 12%,
    transparent
  );
}

/* ======================================================
   RESUMEN
====================================================== */

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border);
}

.summary-row > span {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text-secondary);
}

.summary-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.summary-dot.success {
  background: var(--color-success);
}

.summary-dot.danger {
  background: var(--color-danger);
}

.summary-dot.primary {
  background: var(--color-primary);
}

.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding-top: 14px;
  margin-top: 4px;
}

.summary-total span {
  color: var(--color-text-secondary);
}

.summary-total strong {
  font-size: 1.05rem;
}

/* ======================================================
   ESTADO VACÍO
====================================================== */

.empty-current,
.empty-table {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px;
  border: 1px dashed var(--color-border);
  border-radius: 10px;
  background: var(--color-background);
}

.empty-current > i,
.empty-table i,
.empty-icon {
  font-size: 1.8rem;
  color: var(--color-primary);
}

.empty-table {
  justify-content: center;
  border: 0;
  background: transparent;
}

/* ======================================================
   TABLA
====================================================== */

.table thead th {
  color: var(--color-text-secondary);
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  border-bottom-color: var(--color-border);
  white-space: nowrap;
}

.table tbody td {
  border-bottom-color: var(--color-border);
  color: var(--color-text);
}

.table tbody tr:last-child td {
  border-bottom: 0;
}

/* ======================================================
   TARJETAS MOBILE
====================================================== */

.operation-card {
  padding: 15px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: var(--color-surface);
  margin-bottom: 10px;
}

.operation-card:last-child {
  margin-bottom: 0;
}

.operation-amount {
  font-size: 1.2rem;
  font-weight: 800;
  margin: 14px 0;
  color: var(--color-text);
}

.operation-details {
  display: grid;
  gap: 9px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}

.operation-details > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.operation-details span {
  color: var(--color-muted);
  font-size: 0.75rem;
}

.operation-details strong {
  font-size: 0.85rem;
  font-weight: 600;
  word-break: break-word;
}

/* ======================================================
   MODALES
====================================================== */

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 20px;
}

.modal-content-custom {
  background: var(--color-surface);
  border-radius: 12px;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
}

.modal-lg {
  max-width: 800px;
}

.modal-header-custom {
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
}

.modal-body-custom {
  padding: 20px;
}

.modal-footer-custom {
  padding: 12px 20px;
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

/* ======================================================
   CIERRE
====================================================== */

.closing-summary {
  border-radius: 10px;
  padding: 14px;
  background: var(--color-background);
  border: 1px solid var(--color-border);
}

.closing-summary > div {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
}

.closing-summary span {
  color: var(--color-text-secondary);
}

.closing-summary strong {
  font-size: 1.1rem;
}

/* ======================================================
   FORMULARIOS
====================================================== */

.form-control,
.form-select {
  border-color: var(--color-border);
  color: var(--color-text);
}

.form-control:focus,
.form-select:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 0.2rem color-mix(
    in srgb,
    var(--color-primary) 15%,
    transparent
  );
}

.input-group-text {
  background: var(--color-background);
  border-color: var(--color-border);
  color: var(--color-text-secondary);
}

/* ======================================================
   UTILIDADES
====================================================== */

.text-secondary {
  color: var(--color-text-secondary) !important;
}

.text-success {
  color: var(--color-success) !important;
}

.text-danger {
  color: var(--color-danger) !important;
}

.text-warning {
  color: var(--color-warning) !important;
}

.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

/* ======================================================
   RESPONSIVE
====================================================== */

@media (max-width: 767.98px) {
  .cajas-view {
    padding-bottom: 20px;
  }

  .modal-backdrop {
    padding: 10px;
  }

  .modal-content-custom {
    width: 100%;
    max-height: 95vh;
  }

  .modal-body-custom {
    padding: 16px;
  }

  .modal-header-custom {
    padding: 14px 16px;
  }

  .modal-footer-custom {
    padding: 12px 16px;
  }
}
</style>