<script
    setup>    import { computed, onMounted, reactive, ref } from 'vue'; import axios from 'axios'; import Swal from 'sweetalert2'; const api = axios.create({ baseURL: '/api/v1', headers: { 'Content-Type': 'application/json', Accept: 'application/json', }, }); api.interceptors.request.use((config) => { const token = localStorage.getItem('token'); if (token) { config.headers.Authorization = `Bearer ${token}`; } return config; }); // ====================================================== // CONFIGURACIÓN VISUAL // ====================================================== const primaryColor = computed(() => { return localStorage.getItem('colorPrincipal') || '#4678EC'; }); // ====================================================== // ESTADO // ====================================================== const mesas = ref([]); const cargando = ref(false); const guardando = ref(false); const mostrarModal = ref(false); const editando = ref(false); const mesaSeleccionada = ref(null); const busqueda = ref(''); const filtroEstado = ref(''); const filtroActivo = ref(''); const pagina = ref(1); const porPagina = 12; // ====================================================== // FORMULARIO // ====================================================== const form = reactive({ nombre: '', capacidad: '', estado: 'libre', activo: true, notas: '', }); // ====================================================== // COMPUTED // ====================================================== const mesasFiltradas = computed(() => { const texto = busqueda.value.trim().toLowerCase(); return mesas.value.filter((mesa) => { const coincideBusqueda = !texto || String(mesa.nombre || '') .toLowerCase() .includes(texto) || String(mesa.notas || '') .toLowerCase() .includes(texto); const coincideEstado = !filtroEstado.value || String(mesa.estado || '').toLowerCase() === filtroEstado.value; const coincideActivo = filtroActivo.value === '' || String(Boolean(mesa.activo)) === filtroActivo.value; return ( coincideBusqueda && coincideEstado && coincideActivo ); }); }); const totalPaginas = computed(() => { return Math.max( 1, Math.ceil(mesasFiltradas.value.length / porPagina) ); }); const mesasPaginadas = computed(() => { const inicio = (pagina.value - 1) * porPagina; return mesasFiltradas.value.slice( inicio, inicio + porPagina ); }); const totalMesas = computed(() => mesas.value.length); const mesasLibres = computed(() => { return mesas.value.filter( (mesa) => String(mesa.estado || '').toLowerCase() === 'libre' && Boolean(mesa.activo) ).length; }); const mesasOcupadas = computed(() => { return mesas.value.filter( (mesa) => String(mesa.estado || '').toLowerCase() === 'ocupada' ).length; }); const mesasReservadas = computed(() => { return mesas.value.filter( (mesa) => String(mesa.estado || '').toLowerCase() === 'reservada' ).length; }); const mesasInactivas = computed(() => { return mesas.value.filter( (mesa) => !Boolean(mesa.activo) ).length; }); // ====================================================== // UTILIDADES // ====================================================== function numero(valor) { const convertido = Number(valor); return Number.isFinite(convertido) ? convertido : 0; } function mensajeError( error, mensajeDefault = 'Ocurrió un error.' ) { const response = error?.response; if (!response) { return error?.message || mensajeDefault; } if (response.status === 422) { const errores = response.data?.errors; if (errores && typeof errores === 'object') { const mensajes = Object.values(errores) .flat() .filter(Boolean); if (mensajes.length) { return mensajes.join('<br>'); } } } return ( response.data?.message || response.data?.error || mensajeDefault ); } function normalizarLista(response) { const data = response?.data; if (Array.isArray(data)) { return data; } if (Array.isArray(data?.data)) { return data.data; } if (Array.isArray(data?.mesas)) { return data.mesas; } return []; } function normalizarMesa(response) { const data = response?.data; return ( data?.mesa || data?.data || data || null ); } function claseEstado(estado) { switch ( String(estado || '').toLowerCase() ) { case 'libre': return 'estado-libre'; case 'ocupada': return 'estado-ocupada'; case 'reservada': return 'estado-reservada'; case 'fuera_servicio': return 'estado-fuera'; default: return 'estado-default'; } } function textoEstado(estado) { const estados = { libre: 'Libre', ocupada: 'Ocupada', reservada: 'Reservada', fuera_servicio: 'Fuera de servicio', }; return ( estados[ String(estado || '').toLowerCase() ] || estado || 'Sin estado' ); } function resetPagina() { pagina.value = 1; } function limpiarFiltros() { busqueda.value = ''; filtroEstado.value = ''; filtroActivo.value = ''; resetPagina(); } // ====================================================== // CARGAR MESAS // ====================================================== async function cargarMesas() { cargando.value = true; try { const response = await api.get('/mesas'); mesas.value = normalizarLista(response); if (pagina.value > totalPaginas.value) { pagina.value = totalPaginas.value; } } catch (error) { mesas.value = []; await Swal.fire({ icon: 'error', title: 'No se pudieron cargar las mesas', html: mensajeError( error, 'No fue posible obtener las mesas de la empresa.' ), }); } finally { cargando.value = false; } } // ====================================================== // MODAL // ====================================================== function limpiarFormulario() { form.nombre = ''; form.capacidad = ''; form.estado = 'libre'; form.activo = true; form.notas = ''; } function abrirModalCrear() { editando.value = false; mesaSeleccionada.value = null; limpiarFormulario(); mostrarModal.value = true; } function abrirModalEditar(mesa) { editando.value = true; mesaSeleccionada.value = mesa; form.nombre = mesa.nombre || ''; form.capacidad = mesa.capacidad !== null && mesa.capacidad !== undefined ? mesa.capacidad : ''; form.estado = mesa.estado || 'libre'; form.activo = Boolean(mesa.activo); form.notas = mesa.notas || ''; mostrarModal.value = true; } function cerrarModal() { if (guardando.value) { return; } mostrarModal.value = false; mesaSeleccionada.value = null; limpiarFormulario(); } // ====================================================== // VALIDACIÓN // ====================================================== function validarFormulario() { const nombre = form.nombre.trim(); if (!nombre) { return 'El nombre de la mesa es obligatorio.'; } if (nombre.length > 255) { return 'El nombre de la mesa no puede superar los 255 caracteres.'; } if (form.capacidad !== '') { const capacidad = numero(form.capacidad); if ( !Number.isInteger(capacidad) || capacidad <= 0 ) { return 'La capacidad debe ser un número entero mayor que cero.'; } } if (!form.estado) { return 'Selecciona el estado de la mesa.'; } return null; } // ====================================================== // GUARDAR // ====================================================== async function guardarMesa() { const errorValidacion = validarFormulario(); if (errorValidacion) { await Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: errorValidacion, }); return; } const payload = { nombre: form.nombre.trim(), capacidad:form.capacidad === '' ? null : Number(form.capacidad), estado: form.estado, activo: Boolean(form.activo), notas: form.notas?.trim() || null, }; try { guardando.value = true; if (editando.value && mesaSeleccionada.value?.id) { await api.put( `/mesas/${mesaSeleccionada.value.id}`, payload ); } else { await api.post('/mesas', payload); } mostrarModal.value = false; await Swal.fire({ icon: 'success', title: editando.value ? 'Mesa actualizada' : 'Mesa creada', text: editando.value ? 'La mesa fue actualizada correctamente.' : 'La mesa fue creada correctamente.', timer: 1800, showConfirmButton: false, }); await cargarMesas(); } catch (error) { await Swal.fire({ icon: 'error', title: editando.value ? 'No se pudo actualizar' : 'No se pudo crear', html: mensajeError( error, editando.value ? 'No fue posible actualizar la mesa.' : 'No fue posible crear la mesa.' ), }); } finally { guardando.value = false; } } // ====================================================== // CAMBIAR ESTADO // ====================================================== async function cambiarEstado(mesa, nuevoEstado) { if (!mesa?.id) { return; } if ( String(mesa.estado || '').toLowerCase() === nuevoEstado ) { return; } const nombres = { libre: 'Libre', ocupada: 'Ocupada', reservada: 'Reservada', fuera_servicio: 'Fuera de servicio', }; const confirmacion = await Swal.fire({ icon: 'question', title: 'Cambiar estado', html: ` <p class="mb-2"> ¿Deseas cambiar el estado de <strong>${escapeHtml(mesa.nombre)}</strong>? </p> <p class="mb-0"> Nuevo estado: <strong>${nombres[nuevoEstado] || nuevoEstado}</strong> </p> `, showCancelButton: true, confirmButtonText: 'Sí, cambiar', cancelButtonText: 'Cancelar', reverseButtons: true, }); if (!confirmacion.isConfirmed) { return; } try { cargando.value = true; await api.put(`/mesas/${mesa.id}`, { nombre: mesa.nombre, capacidad: mesa.capacidad ?? null, estado: nuevoEstado, activo: Boolean(mesa.activo), notas: mesa.notas || null, }); await Swal.fire({ icon: 'success', title: 'Estado actualizado', text: `La mesa ahora está ${nombres[nuevoEstado] || nuevoEstado}.`, timer: 1600, showConfirmButton: false, }); await cargarMesas(); } catch (error) { await Swal.fire({ icon: 'error',title: 'No se pudo cambiar el estado', html: mensajeError( error, 'No fue posible actualizar el estado de la mesa.' ), }); } finally { cargando.value = false; } } // ====================================================== // ACTIVAR / DESACTIVAR // ====================================================== async function cambiarActivo(mesa) { if (!mesa?.id) { return; } const nuevoEstado = !Boolean(mesa.activo); const confirmacion = await Swal.fire({ icon: nuevoEstado ? 'question' : 'warning', title: nuevoEstado ? 'Activar mesa' : 'Desactivar mesa', text: nuevoEstado ? `¿Deseas activar la mesa "${mesa.nombre}"?` : `¿Deseas desactivar la mesa "${mesa.nombre}"?`, showCancelButton: true, confirmButtonText: nuevoEstado ? 'Sí, activar' : 'Sí, desactivar', cancelButtonText: 'Cancelar', reverseButtons: true, }); if (!confirmacion.isConfirmed) { return; } try { cargando.value = true; await api.put(`/mesas/${mesa.id}`, { nombre: mesa.nombre, capacidad: mesa.capacidad ?? null, estado: nuevoEstado ? mesa.estado || 'libre' : mesa.estado, activo: nuevoEstado, notas: mesa.notas || null, }); await Swal.fire({ icon: 'success', title: nuevoEstado ? 'Mesa activada' : 'Mesa desactivada', timer: 1500, showConfirmButton: false, }); await cargarMesas(); } catch (error) { await Swal.fire({ icon: 'error', title: 'No se pudo actualizar la mesa', html: mensajeError( error, 'No fue posible cambiar el estado activo de la mesa.' ), }); } finally { cargando.value = false; } } // ====================================================== // SEGURIDAD PARA MENSAJES HTML DE SWEETALERT // ====================================================== function escapeHtml(value) { return String(value ?? '') .replaceAll('&', '&') .replaceAll('<', '<') .replaceAll('>', '>') .replaceAll('"', '"') .replaceAll("'", '''); } // ====================================================== // PAGINACIÓN // ====================================================== function paginaAnterior() { if (pagina.value > 1) { pagina.value--; } } function paginaSiguiente() { if (pagina.value < totalPaginas.value) { pagina.value++; } } // ====================================================== // CICLO DE VIDA // ====================================================== onMounted(() => { cargarMesas(); }); </script>
<template>
    <div class="mesas-view" :style="{ '--primary-color': primaryColor }">
        <!-- ===================================================== ENCABEZADO ====================================================== -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1 fw-bold"> 🪑 Gestión de Mesas </h2>
                <p class="text-secondary mb-0"> Administra las mesas disponibles para la operación del negocio. </p>
            </div>
            <div class="d-flex flex-wrap gap-2"> <button type="button" class="btn btn-primary" @click="abrirModalCrear">
                    <i class="bi bi-plus-circle me-1"></i> Nueva mesa </button> <button type="button"
                    class="btn btn-outline-secondary" :disabled="cargando" @click="cargarMesas"> <i
                        class="bi bi-arrow-clockwise me-1" :class="{ spin: cargando }"></i> Actualizar </button> </div>
        </div>
        <!-- ====================================================== RESUMEN ======================================================= -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="summary-card">
                    <div class="summary-icon primary"> <i class="bi bi-grid-3x3-gap"></i> </div>
                    <div> <span>Total de mesas</span> <strong>{{ totalMesas }}</strong> </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="summary-card">
                    <div class="summary-icon success"> <i class="bi bi-check-circle"></i> </div>
                    <div> <span>Libres</span> <strong>{{ mesasLibres }}</strong> </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="summary-card">
                    <div class="summary-icon warning"> <i class="bi bi-person-workspace"></i> </div>
                    <div> <span>Ocupadas</span> <strong>{{ mesasOcupadas }}</strong> </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="summary-card">
                    <div class="summary-icon danger"> <i class="bi bi-pause-circle"></i> </div>
                    <div> <span>Inactivas</span> <strong>{{ mesasInactivas }}</strong> </div>
                </div>
            </div>
        </div>
        <!-- ====================================================== FILTROS ====================================================== -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5"> <label class="form-label fw-semibold"> Buscar </label>
                        <div class="input-group"> <span class="input-group-text"> <i class="bi bi-search"></i> </span>
                            <input v-model="busqueda" type="text" class="form-control"
                                placeholder="Buscar por nombre o notas..." @input="resetPagina" />
                        </div>
                    </div>
                    <div class="col-12 col-md-3"> <label class="form-label fw-semibold"> Estado </label> <select
                            v-model="filtroEstado" class="form-select" @change="resetPagina">
                            <option value=""> Todos </option>
                            <option value="libre"> Libres </option>
                            <option value="ocupada"> Ocupadas </option>
                            <option value="reservada"> Reservadas </option>
                            <option value="fuera_servicio"> Fuera de servicio </option>
                        </select> </div>
                    <div class="col-12 col-md-3"> <label class="form-label fw-semibold"> Disponibilidad </label> <select
                            v-model="filtroActivo" class="form-select" @change="resetPagina">
                            <option value=""> Todas </option>
                            <option value="true"> Activas </option>
                            <option value="false"> Inactivas </option>
                        </select> </div>
                    <div class="col-12 col-md-1"> <button v-if="busqueda || filtroEstado || filtroActivo" type="button"
                            class="btn btn-outline-secondary w-100" title="Limpiar filtros" @click="limpiarFiltros"> <i
                                class="bi bi-x-lg"></i> </button> </div>
                </div>
            </div>
        </div>
        <!-- ====================================================== MESAS DE ESCRITORIO ====================================================== -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1"> Mesas </h5>
                        <p class="text-secundary small mb-0"> {{ mesasFiltradas.length }} resultado{{
                            mesasFiltradas.length === 1 ? '' : 's' }} </p>
                    </div>
                </div>
                <div v-if="cargando" class="text-center py-5">
                    <div class="spinner-border spinner-border-sm me-2"></div> Cargando mesas...
                </div>
                <div v-else-if="!mesasPaginadas.length" class="empty-state"> <i class="bi bi-grid-3x3-gap"></i>
                    <h5 class="fw-bold mt-3"> No hay mesas </h5>
                    <p class="text-secondary mb-3"> No existen mesas que coincidan con los filtros actuales. </p>
                    <button type="button" class="btn btn-primary" @click="abrirModalCrear"> <i
                            class="bi bi-plus-circle me-1"></i> Crear primera mesa </button>
                </div> <!-- ESCRITORIO DE CUADRÍCULA -->
                <div v-else class="row g-3">
                    <div v-for="mesa in mesasPaginadas" :key="mesa.id" class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="mesa-card" :class="{ 'mesa-inactiva': !mesa.activo }">
                            <div class="mesa-card-header" :class="claseEstado(mesa.estado)">
                                <div class="mesa-number"> <i class="bi bi-grid-3x3-gap-fill"></i> </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1 fw-bold"> {{ mesa.nombre }} </h5> <span class="estado-badge"
                                        :class="claseEstado(mesa.estado)"> {{ textoEstado(mesa.estado) }} </span>
                                </div> <span class="active-indicator" :class="mesa.activo ? 'active' : 'inactive'"
                                    :title="mesa.activo ? 'Activa' : 'Inactiva'">>span>
                            </div>
                            <div class="mesa-card-body">
                                <div v-if="mesa.capacidad" class="mesa-detail"> <i class="bi bi-people"></i> <span>
                                        Capacidad: <strong> {{ mesa.capacidad }} </strong> </span> </div>
                                <div v-if="mesa.notas" class="mesa-detail"> <i class="bi bi-sticky"></i> <span
                                        class="text-truncate"> {{ mesa.notas }} </span> </div>
                                <div v-if="!mesa.capacidad && !mesa.notas" class="text-secondary small"> Sin información
                                    adicional. </div>
                            </div>
                            <div class="mesa-card-footer"> <button type="button" class="btn btn-sm btn-outline-primary"
                                    @click="abrirModalEditar(mesa)"> <i class="bi bi-pencil me-1"></i> Editar </button>
                                <div class="dropdown"> <button type="button"
                                        class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                        data-bs-toggle="dropdown" aria-expanded="false"> Estado </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li> <button type="button" class="dropdown-item"
                                                @click="cambiarEstado(mesa, 'libre')"> <i
                                                    class="bi bi-check-circle me-2 text-success"></i> Libre </button>
                                        </li>
                                        <li> <button type="button" class="dropdown-item"
                                                @click="cambiarEstado(mesa, 'ocupada')"> <i
                                                    class="bi bi-person-workspace me-2 text-warning"></i> Ocupada
                                            </button> </li>
                                        <li> <button type="button" class="dropdown-item"
                                                @click="cambiarEstado(mesa, 'reservada')"> <i
                                                    class="bi bi-calendar-check me-2"></i> Reservada </button> </li>
                                        <li> <button type="button" class="dropdown-item"
                                                @click="cambiarEstado(mesa, 'fuera_servicio')"> <i
                                                    class="bi bi-slash-circle me-2 text-danger"></i> Fuera de servicio
                                            </button> </li>
                                    </ul>
                                </div> <button type="button" class="btn btn-sm"
                                    :class="mesa.activo ? 'btn-outline-danger' : 'btn-outline-success'"
                                    @click="cambiarActivo(mesa)"> <i class="bi"
                                        :class="mesa.activo ? 'bi-pause-circle' : 'bi-play-circle'"></i> </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ================================================== PAGINACIÓN =================================================== -->
                <div v-if="totalPaginas > 1" class="d-flex justify-content-between align-items-center mt-4"> <small
                        class="text-secondary"> Página {{ pagina }} de {{ totalPaginas }} </small>
                    <div class="d-flex gap-2"> <button type="button" class="btn btn-sm btn-outline-secondary"
                            :disable="pagina <= 1" @click="paginaAnterior"> <i class="bi bi-chevron-left"></i> </button>
                        <button type="button" class="btn btn-sm btn-outline-secundario"
                            :disabled="pagina >= totalPaginas" @click="paginaSiguiente"> <i
                                class="bi bi-chevron-right"></i> </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="mostrarModal" class="modal-backdrop" @click.self="cerrarModal">
            <div class="modal-content-custom">
                <div class="modal-header-custom">
                    <div>
                        <h5 class="mb-1 fw-bold"> {{ editando ? 'Editar mesa' : 'Nueva mesa' }} </h5> <small
                            class="text-secundary"> {{ editando ? 'Actualiza la información de la mesa.' : 'Registra una nueva mesa para la empresa.' }} </small>
                    </div> <button type="button" class="btn-close" :disabled="guardando" @click="cerrarModal"></button>
                </div>
                <form @submit.prevent="guardarMesa">
                    <div class="modal-body-custom">
                        <div class="row g-3">
                            <div class="col-12"> <label class="form-label fw-semibold"> Nombre </label> <input
                                    v-model="form.nombre" type="text" class="form-control" maxlength="255"
                                    placeholder="Ej. Mesa 1" required /> </div>
                            <div class="col-12 col-md-6"> <label class="form-label fw-semibold"> Capacidad </label>
                                <div class="input-group"> <input v-model="form.capacidad" tipo="número" min="1" paso="1"
                                        clase="control-formulario" marcador de posición="Ej. 4" /> <span
                                        class="input-group-text"> personas </span> </div> <small class="text-secondary">
                                    Opcional. </small>
                            </div>
                            <div class="col-12 col-md-6"> <label class="form-label fw-semibold"> Estado </label> <select
                                    v-model="form.estado" class="form-select" required>
                                    <option value="libre"> Libre </option>
                                    <option value="ocupada"> Ocupada </option>
                                    <option value="reservada"> Reservada </option>
                                    <option value="fuera_servicio"> Fuera de servicio </option>
                                </select> </div>
                            <div class="col-12">
                                <div class="form-check form-switch"> <input id="mesaActiva" v-model="form.activo"
                                        type="checkbox" class="form-check-input" /> <label for="mesaActiva"
                                        class="form-check-label fw-semibold"> Mesa activa </label> </div> <small
                                    class="text-secundary"> Las mesas inactivas no deben utilizarse para nuevas ventas.
                                </small>
                            </div>
                            <div class="col-12"> <label class="form-label fw-semibold"> Notas </label> <textarea
                                    v-model="form.notas" class="form-control" rows="3" maxlength="2000"
                                    placeholder="Observaciones opcionales..."></textarea> </div>
                        </div>
                    </div>
                    <div class="modal-footer-custom"> <button type="button" class="btn btn-outline-secondary"
                            :disabled="guardando" @click="cerrarModal"> Cancelar </button> <button type="submit"
                            class="btn btn-primary" :disabled="guardando"> <span v-if="guardando"
                                class="spinner-border spinner-border-sm me-1"></span> <i v-else class="bi"
                                :class="editando ? 'bi-check-circle' : 'bi-plus-circle'"></i> {{ editando ? 'Guardar cambios' : 'Crear mesa' }} </button> </div>
                </form>
            </div>
        </div>
    </div>
</template>
<style scoped>
.mesas-view {
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
}

/* ======================================================= BOTONES ====================================================== */
.mesas-view .btn-primary {
    background-color: var(--color-primary);
    border-color: var(--color-primary);
}

.mesas-view .btn-primary:hover {
    background-color: var(--color-primary-dark);
    border-color: var(--color-primary-dark);
}

.mesas-view .btn-outline-primary {
    color: var(--color-primary);
    border-color: var(--color-primary);
}

.mesas-view .btn-outline-primary:hover {
    color: #fff;
    background-color: var(--color-primary);
    border-color: var(--color-primary);
}

/* ======================================================= RESUMEN ======================================================= */
.summary-card {
    height: 100%;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.summary-card>div:last-child {
    min-width: 0;
}

.summary-card span {
    display: block;
    color: var(--color-text-secondary);
    font-size: 0.8rem;
    margin-bottom: 3px;
}

.summary-card strong {
    display: block;
    font-size: 1.35rem;
    color: var(--color-text);
}

.summary-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 1.2rem;
}

.summary-icon.primary {
    color: var(--color-primary);
    background: color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.summary-icon.success {
    color: var(--color-success);
    background: color-mix(in srgb, var(--color-success) 12%, transparent);
}

.summary-icon.warning {
    color: var(--color-warning);
    background: color-mix(in srgb, var(--color-warning) 12%, transparent);
}

.summary-icon.danger {
    color: var(--color-danger);
    background: color-mix(in srgb, var(--color-danger) 12%, transparent);
}

/* ====================================================== MESAS ====================================================== */
.mesa-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--color-border);
    border-radius: 14px;
    background: var(--color-surface);
    transición: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.mesa-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

.mesa-card.mesa-inactiva {
    opacity: 0.68;
}

.mesa-card-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid var(--color-border);
}

.mesa-number {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 1.1rem;
}

.mesa-card-header.estado-libre .mesa-number {
    color: var(--color-success);
    background: color-mix(in srgb, var(--color-success) 12%, transparent);
}

.mesa-card-header.estado-ocupada .mesa-number {
    color: var(--color-warning);
    background: color-mix(in srgb, var(--color-warning) 12%, transparent);
}

.mesa-card-header.estado-reservada .mesa-number {
    color: var(--color-primary);
    background: color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.mesa-card-header.estado-fuera .mesa-number {
    color: var(--color-danger);
    background: color-mix(in srgb, var(--color-danger) 12%, transparent);
}

.mesa-card-header h5 {
    color: var(--color-text);
}

.estado-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
}

.estado-badge.estado-libre {
    color: var(--color-success);
    background: color-mix(in srgb, var(--color-success) 12%, transparent);
}

.estado-badge.estado-ocupada {
    color: var(--color-warning);
    fondo: color-mix(en srgb, var(--color-warning) 12%, transparente);
}

.estado-badge.estado-reservada {
    color: var(--color-primary);
    fondo: color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.estado-badge.estado-fuera {
    color: var(--color-danger);
    background: color-mix(in srgb, var(--color-danger) 12%, transparent);
}

.estado-badge.estado-default {
    color: var(--color-muted);
    background: color-mix(in srgb, var(--color-muted) 12%, transparent);
}

.active-indicator {
    width: 9px;
    height: 9px;
    flex: 0 0 9px;
    margin-top: 5px;
    border-radius: 50%;
}

.active-indicator.active {
    background: var(--color-success);
}

.active-indicator.inactive {
    background: var(--color-muted);
}

.mesa-card-body {
    flex: 1;
    padding: 16px;
    min-height: 95px;
}

.mesa-detail {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-secondary);
    font-size: 0.84rem;
    margin-bottom: 9px;
}

.mesa-detail:last-child {
    margin-bottom: 0;
}

.mesa-detail i {
    width: 18px;
    color: var(--color-primary);
}

.mesa-detail strong {
    color: var(--color-text);
}

.mesa-card-footer {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 12px 16px;
    border-top: 1px solid var(--color-border);
}

.mesa-card-footer .btn:first-child {
    flex: 1;
}

/* ======================================================= VACÍO ======================================================= */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--color-text-secondary);
}

.empty-state>i {
    font-size: 3rem;
    color: var(--color-primary);
}

/* ======================================================= FORMULARIOS ======================================================= */
.form-control,
.form-select {
    border-color: var(--color-border);
    color: var(--color-text);
}

.form-control:focus,
.form-select:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.input-group-text {
    background: var(--color-background);
    border-color: var(--color-border);
    color: var(--color-text-secondary);
}

.form-check-input:checked {
    background-color: var(--color-primary);
    border-color: var(--color-primary);
}

/* ====================================================== MODAL ====================================================== */
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
    border-top: 1px sólido var(--color-border);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* ======================================================= UTILIDADES ======================================================= */
.text-secondary {
    color: var(--color-text-secondary) !important;
}

.text-success {
    color: var(--color-success) !important;
}

.text-warning {
    color: var(--color-warning) !important;
}

.text-danger {
    color: var(--color-danger) !important;
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

/* ======================================================= RESPONSIVO ======================================================= */
@media (max-width: 767.98px) {
    .modal-backdrop {
        padding: 10px;
    }

    .modal-content-custom {
        ancho: 100%;
        altura máxima: 95vh;
    }

    .modal-header-custom {
        relleno: 14px 16px;
    }

    .modal-body-custom {
        relleno: 16px;
    }

    .modal-footer-custom {
        relleno: 12px 16px;
    }

    .mesa-card-footer {
        flex-wrap: envoltura;
    }

    .mesa-card-footer .btn:primer hijo {
        flex: 1 1 100%;
    }
}
</style>
