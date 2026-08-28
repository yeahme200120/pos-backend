<!-- resources/js/views/Empresas.vue -->
<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
            <h1 class="text-2xl font-bold">Empresas</h1>
            <button @click="abrirModal()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"
                v-if="esSuperadmin">
                + Nueva Empresa
            </button>
        </div>

        <!-- Filtros -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex flex-wrap gap-3">
                <div class="flex-1 min-w-[200px]">
                    <input v-model="filtros.search" type="text"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Buscar empresa..." @input="cargarEmpresas" />
                </div>
                <div class="flex gap-2">
                    <button @click="cargarEmpresas"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                        Buscar
                    </button>
                    <button @click="limpiarFiltros"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                        Limpiar
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="cargando" class="text-center py-8">
            <span class="inline-block animate-spin mr-2">⟳</span>
            Cargando empresas...
        </div>

        <!-- Tabla -->
        <div v-else-if="empresas.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No hay empresas registradas
        </div>

        <div v-else class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">RFC</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teléfono</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="empresa in empresas" :key="empresa.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div
                                    class="w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center">
                                    <img :src="empresa.logo_url || '/img/logo.png'" :alt="empresa.nombre"
                                        class="w-full h-full object-cover" @error="handleImageError(empresa)"
                                        loading="lazy" />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium">{{ empresa.nombre }}</td>
                            <td class="px-4 py-3 text-sm">{{ empresa.rfc || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ empresa.telefono || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ empresa.email_contacto || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span class="px-2 py-1 text-xs rounded-full"
                                    :class="empresa.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                    {{ empresa.activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <button @click="abrirModal(empresa)" class="text-blue-600 hover:text-blue-800 mr-2">
                                    ✏️
                                </button>
                                <button v-if="esSuperadmin" @click="eliminarEmpresa(empresa.id)"
                                    class="text-red-600 hover:text-red-800">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="paginacion && paginacion.last_page > 1"
                class="px-4 py-3 border-t flex flex-col sm:flex-row justify-between items-center gap-2">
                <span class="text-sm text-gray-600">
                    Mostrando {{ empresas.length }} de {{ paginacion.total || empresas.length }}
                </span>
                <div class="flex gap-1">
                    <button v-if="paginacion.current_page > 1" @click="cambiarPagina(paginacion.current_page - 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 text-sm">
                        Anterior
                    </button>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                        {{ paginacion.current_page || 1 }}
                    </span>
                    <button v-if="paginacion.current_page < paginacion.last_page"
                        @click="cambiarPagina(paginacion.current_page + 1)"
                        class="px-3 py-1 border rounded hover:bg-gray-100 text-sm">
                        Siguiente
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL EMPRESA CON EDITOR DE IMAGEN            -->
        <!-- ============================================ -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-bold mb-4">{{ editando ? 'Editar' : 'Nueva' }} Empresa</h2>

                <form @submit.prevent="guardarEmpresa">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Datos básicos -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Nombre *</label>
                            <input v-model="form.nombre" type="text" class="w-full px-3 py-2 border rounded-lg"
                                required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">RFC</label>
                            <input v-model="form.rfc" type="text" class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Razón Social</label>
                            <input v-model="form.razon_social" type="text" class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                            <input v-model="form.telefono" type="text" class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email Contacto</label>
                            <input v-model="form.email_contacto" type="email"
                                class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Dirección</label>
                            <textarea v-model="form.direccion" class="w-full px-3 py-2 border rounded-lg"
                                rows="2"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Leyenda del Ticket</label>
                            <input v-model="form.leyenda_ticket" type="text" class="w-full px-3 py-2 border rounded-lg"
                                placeholder="¡Gracias por su compra!" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">WhatsApp</label>
                            <input v-model="form.whatsapp_numero" type="text"
                                class="w-full px-3 py-2 border rounded-lg" />
                        </div>

                        <!-- COLORES - UNIFICADOS -->
                        <div class="md:col-span-2 border-t pt-4 mt-2">
                            <h3 class="text-md font-semibold mb-3">Colores de la Empresa</h3>
                            <p class="text-xs text-gray-500 mb-3">El color principal se aplica al sidebar, menú y header
                            </p>

                            <!-- En la sección de colores del modal -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Color Principal
                                        *</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" v-model="form.colores.primary"
                                            class="w-12 h-12 rounded cursor-pointer border" />
                                        <input type="text" v-model="form.colores.primary"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm" />
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Sidebar · Menú · Header</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Color Secundario</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" v-model="form.colores.secondary"
                                            class="w-12 h-12 rounded cursor-pointer border" />
                                        <input type="text" v-model="form.colores.secondary"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm" />
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Bordes y detalles</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Color de Fondo</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" v-model="form.colores.background"
                                            class="w-12 h-12 rounded cursor-pointer border" />
                                        <input type="text" v-model="form.colores.background"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Color de Texto</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" v-model="form.colores.text"
                                            class="w-12 h-12 rounded cursor-pointer border" />
                                        <input type="text" v-model="form.colores.text"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm" />
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Texto en sidebar y menú</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Color Texto
                                        Navbar</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" v-model="form.colores.text_navbar"
                                            class="w-12 h-12 rounded cursor-pointer border" />
                                        <input type="text" v-model="form.colores.text_navbar"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Color Hover del
                                        Menú</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" v-model="form.colores.menu_hover"
                                            class="w-12 h-12 rounded cursor-pointer border" />
                                        <input type="text" v-model="form.colores.menu_hover"
                                            class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- LOGO CON EDITOR -->
                        <div class="md:col-span-2 border-t pt-4 mt-2">
                            <label class="block text-sm font-medium text-gray-700">Logo</label>

                            <div class="flex flex-col items-center gap-4">
                                <!-- Previsualización -->
                                <div class="relative">
                                    <img :src="logoPreview || '/img/logo.png'" alt="Logo"
                                        class="w-32 h-32 rounded-full object-cover border-4 border-gray-200" />
                                    <div v-if="logoFile"
                                        class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-full">
                                        <span class="text-white text-xs">✏️ Editando</span>
                                    </div>
                                </div>

                                <!-- Controles -->
                                <div class="w-full">
                                    <div class="flex flex-wrap gap-2">
                                        <input type="file" @change="onFileChange" accept="image/*"
                                            class="flex-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                        <button v-if="logoFile" @click="resetLogo" type="button"
                                            class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm">
                                            ✕
                                        </button>
                                    </div>

                                    <!-- ✅ EDITOR DE IMAGEN CON CANVAS -->
                                    <div v-if="logoFile" class="mt-3 bg-gray-50 p-3 rounded-lg">
                                        <p class="text-xs font-medium text-gray-700 mb-2">Ajustar imagen</p>

                                        <!-- Canvas para edición -->
                                        <div class="relative bg-gray-200 rounded-lg overflow-hidden"
                                            style="height: 200px;">
                                            <canvas ref="canvasEditor" class="w-full h-full object-contain cursor-move"
                                                @mousedown="iniciarArrastre" @mousemove="moverArrastre"
                                                @mouseup="terminarArrastre" @mouseleave="terminarArrastre"
                                                @wheel.prevent="zoomConRueda"></canvas>
                                        </div>

                                        <!-- Controles -->
                                        <div class="mt-3 space-y-2">
                                            <div>
                                                <label class="text-xs text-gray-600">Zoom</label>
                                                <div class="flex items-center gap-2">
                                                    <button @click="zoomImagen(-0.1)" type="button"
                                                        class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-xs">
                                                        ➖
                                                    </button>
                                                    <span class="text-xs font-medium w-12 text-center">{{
                                                        Math.round(zoom * 100) }}%</span>
                                                    <button @click="zoomImagen(0.1)" type="button"
                                                        class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-xs">
                                                        ➕
                                                    </button>
                                                    <button @click="rotarImagen(90)" type="button"
                                                        class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs">
                                                        🔄 Rotar 90°
                                                    </button>
                                                    <button @click="restaurarImagen" type="button"
                                                        class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 text-xs">
                                                        ↩️ Restaurar
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between text-xs text-gray-400">
                                                <span>🖱️ Arrastra para mover</span>
                                                <span>🔍 Rueda para zoom</span>
                                                <span>📐 {{ Math.round(imagenInfo.ancho || 0) }}x{{
                                                    Math.round(imagenInfo.alto || 0) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activo -->
                        <div class="flex items-center md:col-span-2">
                            <input v-model="form.activo" type="checkbox" class="mr-2" />
                            <label class="text-sm font-medium text-gray-700">Activo</label>
                        </div>
                    </div>

                    <div v-if="error" class="mt-3 p-2 bg-red-100 text-red-700 rounded text-sm">{{ error }}</div>

                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" @click="cerrarModal"
                            class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="guardando"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            {{ guardando ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../axios';
import Swal from 'sweetalert2';

export default {
    name: 'Empresas',
    data() {
        return {
            empresas: [],
            paginacion: null,
            cargando: false,
            guardando: false,
            error: null,
            modalVisible: false,
            editando: false,
            logoPreview: null,
            logoFile: null,
            esSuperadmin: false,
            filtros: { search: '' },
            form: {
                id: null,
                nombre: '',
                rfc: '',
                razon_social: '',
                telefono: '',
                email_contacto: '',
                direccion: '',
                leyenda_ticket: '',
                whatsapp_numero: '',
                colores: {
                    primary: '#1E293B',
                    secondary: '#108981',
                    background: '#f3f4f6',
                    text: '#FFFFFF',
                    text_navbar: '#FFFFFF',
                    menu_hover: '#2d3748'
                },
                activo: true
            },
            // Variables del editor de imagen
            imagenOriginal: null,
            imagenEditada: null,
            zoom: 1,
            rotacion: 0,
            offsetX: 0,
            offsetY: 0,
            isDragging: false,
            startX: 0,
            startY: 0,
            imagenInfo: {
                ancho: 0,
                alto: 0
            }
        };
    },
    mounted() {
        this.verificarRol();
        this.cargarEmpresas();
    },
    methods: {
        verificarRol() {
            const userData = localStorage.getItem('user');
            if (userData) {
                try {
                    const user = JSON.parse(userData);
                    this.esSuperadmin = user.rol === 'superadmin';
                } catch (e) {
                    console.error('Error parsing user:', e);
                }
            }
        },
        async cargarEmpresas() {
            this.cargando = true;
            try {
                const params = {};
                if (this.filtros.search) params.search = this.filtros.search;

                const res = await api.get('/empresas', { params });
                this.empresas = res.data.data || [];
                this.paginacion = {
                    current_page: res.data.current_page || 1,
                    last_page: res.data.last_page || 1,
                    total: res.data.total || this.empresas.length
                };

                // Corregir URLs de logos
                this.empresas = this.empresas.map(emp => {
                    if (emp.logo_url) {
                        // Corregir URL - asegurar que incluya el puerto
                        if (emp.logo_url.includes('localhost') && !emp.logo_url.includes('localhost:')) {
                            emp.logo_url = emp.logo_url.replace('localhost', 'localhost:8000');
                        }

                        // Si es una URL relativa, hacerla absoluta
                        if (emp.logo_url.startsWith('/')) {
                            emp.logo_url = `${window.location.origin}${emp.logo_url}`;
                        }

                        // Agregar timestamp para evitar caché
                        const timestamp = Date.now();
                        emp.logo_url = `${emp.logo_url}${emp.logo_url.includes('?') ? '&' : '?'}t=${timestamp}`;
                    } else if (emp.logo) {
                        // Construir URL desde la ruta del logo
                        const baseUrl = window.location.origin;
                        const timestamp = Date.now();
                        emp.logo_url = `${baseUrl}/storage/${emp.logo}?t=${timestamp}`;
                    }

                    console.log('Empresa procesada:', {
                        nombre: emp.nombre,
                        logo: emp.logo,
                        logo_url: emp.logo_url
                    });

                    return emp;
                });
            } catch (error) {
                console.error('Error cargando empresas:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron cargar las empresas'
                });
            } finally {
                this.cargando = false;
            }
        },
        async cambiarPagina(page) {
            this.cargando = true;
            try {
                const res = await api.get('/empresas', { params: { page, ...this.filtros } });
                this.empresas = res.data.data || [];
                this.paginacion.current_page = page;
            } catch (error) {
                console.error('Error cargando página:', error);
            } finally {
                this.cargando = false;
            }
        },
        limpiarFiltros() {
            this.filtros.search = '';
            this.cargarEmpresas();
        },
        onFileChange(event) {
            const file = event.target.files[0];
            if (file) {
                this.logoFile = file;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.logoPreview = e.target.result;
                    this.imagenOriginal = e.target.result;
                    this.zoom = 1;
                    this.rotacion = 0;
                    this.offsetX = 0;
                    this.offsetY = 0;
                    this.imagenInfo = { ancho: 0, alto: 0 };

                    this.$nextTick(() => {
                        this.dibujarImagen();
                    });
                };
                reader.readAsDataURL(file);
            }
        },
        resetLogo() {
            this.logoFile = null;
            this.logoPreview = null;
            this.imagenOriginal = null;
            this.imagenEditada = null;
            this.zoom = 1;
            this.rotacion = 0;
            this.offsetX = 0;
            this.offsetY = 0;
        },
        dibujarImagen() {
            const canvas = this.$refs.canvasEditor;
            if (!canvas || !this.imagenOriginal) return;

            const ctx = canvas.getContext('2d');
            const img = new Image();
            img.onload = () => {
                // Calcular dimensiones del canvas
                const containerWidth = canvas.parentElement.clientWidth || 400;
                const containerHeight = canvas.parentElement.clientHeight || 200;

                canvas.width = containerWidth;
                canvas.height = containerHeight;

                // Calcular tamaño de la imagen con zoom
                let ancho = img.width * this.zoom;
                let alto = img.height * this.zoom;

                // Aplicar offset (arrastre)
                const offsetX = this.offsetX || 0;
                const offsetY = this.offsetY || 0;

                // Guardar info
                this.imagenInfo = { ancho, alto };

                // Limpiar canvas
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                // Dibujar fondo
                ctx.fillStyle = '#e5e7eb';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                // Dibujar cuadrícula de guía
                ctx.strokeStyle = 'rgba(0,0,0,0.1)';
                ctx.lineWidth = 1;
                for (let i = 0; i < canvas.width; i += 30) {
                    ctx.beginPath();
                    ctx.moveTo(i, 0);
                    ctx.lineTo(i, canvas.height);
                    ctx.stroke();
                }
                for (let i = 0; i < canvas.height; i += 30) {
                    ctx.beginPath();
                    ctx.moveTo(0, i);
                    ctx.lineTo(canvas.width, i);
                    ctx.stroke();
                }

                // Calcular posición centrada + offset
                const x = (canvas.width - ancho) / 2 + offsetX;
                const y = (canvas.height - alto) / 2 + offsetY;

                // Dibujar imagen con rotación
                ctx.save();
                ctx.translate(canvas.width / 2 + offsetX, canvas.height / 2 + offsetY);
                ctx.rotate((this.rotacion || 0) * Math.PI / 180);
                ctx.drawImage(img, -ancho / 2, -alto / 2, ancho, alto);
                ctx.restore();

                // Dibujar recuadro de recorte (círculo)
                ctx.strokeStyle = 'rgba(59, 130, 246, 0.8)';
                ctx.lineWidth = 2;
                ctx.setLineDash([5, 5]);
                const radio = Math.min(canvas.width, canvas.height) * 0.4;
                ctx.beginPath();
                ctx.arc(canvas.width / 2, canvas.height / 2, radio, 0, 2 * Math.PI);
                ctx.stroke();
                ctx.setLineDash([]);

                // Dibujar texto "Recorte cuadrado"
                ctx.fillStyle = 'rgba(59, 130, 246, 0.6)';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Área de recorte', canvas.width / 2, 20);
            };
            img.src = this.imagenOriginal;
        },
        zoomImagen(valor) {
            this.zoom = Math.max(0.2, Math.min(3, this.zoom + valor));
            this.dibujarImagen();
        },
        zoomConRueda(event) {
            const delta = event.deltaY > 0 ? -0.05 : 0.05;
            this.zoomImagen(delta);
        },
        rotarImagen(angulo) {
            this.rotacion = (this.rotacion + angulo) % 360;
            this.dibujarImagen();
        },
        restaurarImagen() {
            this.zoom = 1;
            this.rotacion = 0;
            this.offsetX = 0;
            this.offsetY = 0;
            this.dibujarImagen();
        },
        iniciarArrastre(event) {
            this.isDragging = true;
            this.startX = event.clientX;
            this.startY = event.clientY;
        },
        moverArrastre(event) {
            if (!this.isDragging) return;
            const dx = event.clientX - this.startX;
            const dy = event.clientY - this.startY;
            this.offsetX += dx;
            this.offsetY += dy;
            this.startX = event.clientX;
            this.startY = event.clientY;
            this.dibujarImagen();
        },
        terminarArrastre() {
            this.isDragging = false;
        },
        obtenerImagenRecortada() {
            const canvas = this.$refs.canvasEditor;
            if (!canvas) return null;

            // Crear un canvas temporal para el recorte
            const tempCanvas = document.createElement('canvas');
            const ctx = tempCanvas.getContext('2d');

            // Tamaño del recorte (cuadrado)
            const size = Math.min(canvas.width, canvas.height) * 0.8;
            const x = (canvas.width - size) / 2;
            const y = (canvas.height - size) / 2;

            tempCanvas.width = size;
            tempCanvas.height = size;

            // Recortar el área circular
            ctx.beginPath();
            ctx.arc(size / 2, size / 2, size / 2, 0, 2 * Math.PI);
            ctx.clip();
            ctx.drawImage(canvas, -x, -y);

            return tempCanvas.toDataURL('image/png');
        },
        abrirModal(empresa = null) {
            if (empresa) {
                this.editando = true;

                // ✅ Parsear colores si viene como string JSON
                let coloresEmpresa = empresa.colores;
                if (typeof coloresEmpresa === 'string') {
                    try {
                        coloresEmpresa = JSON.parse(coloresEmpresa);
                    } catch (e) {
                        console.error('Error parseando colores:', e);
                        coloresEmpresa = {};
                    }
                }

                // ✅ Mapear colores del backend al frontend
                const coloresForm = {
                    primary: coloresEmpresa?.primary || '#1E293B',
                    secondary: coloresEmpresa?.secondary || '#108981',
                    background: coloresEmpresa?.background || '#f3f4f6',
                    text: coloresEmpresa?.text || '#FFFFFF',
                    text_navbar: coloresEmpresa?.text_navbar || '#FFFFFF',
                    menu_hover: coloresEmpresa?.menu_hover || '#2d3748'
                };

                this.form = {
                    ...empresa,
                    colores: coloresForm
                };
                this.logoPreview = empresa.logo_url || null;
                this.logoFile = null;
            } else {
                this.editando = false;
                this.form = {
                    id: null,
                    nombre: '',
                    rfc: '',
                    razon_social: '',
                    telefono: '',
                    email_contacto: '',
                    direccion: '',
                    leyenda_ticket: '',
                    whatsapp_numero: '',
                    colores: {
                        primary: '#1E293B',
                        secondary: '#108981',
                        background: '#f3f4f6',
                        text: '#FFFFFF',
                        text_navbar: '#FFFFFF',
                        menu_hover: '#2d3748'
                    },
                    activo: true
                };
                this.logoPreview = null;
                this.logoFile = null;
            }
            this.error = null;
            this.modalVisible = true;
        },
        cerrarModal() {
            this.modalVisible = false;
            this.logoFile = null;
            this.error = null;
        },
        // En el método guardarEmpresa

        async guardarEmpresa() {
            this.guardando = true;
            this.error = null;

            try {
                const formData = new FormData();

                // Convertir activo a boolean correctamente
                const datos = {
                    ...this.form,
                    activo: this.form.activo === true || this.form.activo === '1' || this.form.activo === 1
                };

                // ✅ Corregir mapeo de colores antes de enviar
                const coloresMapeados = {
                    primary: this.form.colores.primary || this.form.colores.colorPrincipal || '#1E293B',
                    secondary: this.form.colores.secondary || this.form.colores.colorSecundario || '#108981',
                    background: this.form.colores.background || this.form.colores.fondo || '#f3f4f6',
                    text: this.form.colores.text || this.form.colores.colorTexto || '#FFFFFF',
                    text_navbar: this.form.colores.text_navbar || this.form.colores.colorTextoNavbar || '#FFFFFF',
                    menu_hover: this.form.colores.menu_hover || this.form.colores.colorMenu || '#2d3748'
                };

                console.log('Colores mapeados para enviar:', coloresMapeados);

                // Agregar datos al FormData
                Object.keys(datos).forEach(key => {
                    if (key === 'colores') {
                        // Enviar colores mapeados como JSON string
                        formData.append('colores', JSON.stringify(coloresMapeados));
                    } else if (key === 'id' && !this.editando) {
                        // No enviar id si es nuevo
                        return;
                    } else if (datos[key] !== null && datos[key] !== undefined) {
                        if (key === 'activo') {
                            formData.append(key, datos[key] ? '1' : '0');
                        } else {
                            formData.append(key, datos[key]);
                        }
                    }
                });

                // Usar la imagen recortada
                if (this.logoFile && this.$refs.canvasEditor) {
                    const croppedImage = this.obtenerImagenRecortada();
                    if (croppedImage) {
                        const blob = await fetch(croppedImage).then(res => res.blob());
                        formData.append('logo', blob, 'logo.webp');
                    }
                }

                let response;
                if (this.editando) {
                    formData.append('_method', 'PUT');
                    response = await api.post(`/empresas/${this.form.id}`, formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    });
                } else {
                    response = await api.post('/empresas', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    });
                }

                console.log('Respuesta del servidor:', response.data);

                this.cerrarModal();

                // Esperar un momento
                await new Promise(resolve => setTimeout(resolve, 500));

                // Recargar lista
                await this.cargarEmpresas();

                // ✅ Aplicar colores desde la respuesta
                if (response.data && response.data.data && response.data.data.colores) {
                    let coloresGuardados = response.data.data.colores;

                    // Si es string JSON, parsear
                    if (typeof coloresGuardados === 'string') {
                        try {
                            coloresGuardados = JSON.parse(coloresGuardados);
                        } catch (e) {
                            console.error('Error parseando colores:', e);
                        }
                    }

                    console.log('Colores guardados:', coloresGuardados);
                    this.aplicarColores(coloresGuardados);
                }

                // Disparar eventos
                window.dispatchEvent(new CustomEvent('recargar-colores'));
                window.dispatchEvent(new CustomEvent('recargar-logo'));

                Swal.fire({
                    icon: 'success',
                    title: this.editando ? 'Empresa actualizada' : 'Empresa creada',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (error) {
                console.error('Error completo:', error);
                console.error('Error response:', error.response);
                this.error = error.response?.data?.message || 'Error al guardar';
            } finally {
                this.guardando = false;
            }
        },
        async eliminarEmpresa(id) {
            const result = await Swal.fire({
                title: '¿Eliminar empresa?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    await api.delete(`/empresas/${id}`);
                    await this.cargarEmpresas();
                    Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1500, showConfirmButton: false });
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al eliminar' });
                }
            }
        },
        handleImageError(empresa) {
            console.log('Error cargando imagen para:', empresa.nombre, 'URL:', empresa.logo_url);
            // Usar imagen por defecto
            empresa.logo_url = null;
            // Forzar actualización
            this.$forceUpdate();
        },

        getLogoUrl(empresa) {
            if (!empresa.logo_url) {
                return '/img/logo.png';
            }
            return empresa.logo_url;
        },
        aplicarColores(colores) {
            if (!colores) return;

            console.log('Aplicando colores:', colores);

            // ✅ Mapear claves del backend al frontend
            const mapeo = {
                primary: 'colorPrincipal',
                secondary: 'colorSecundario',
                background: 'fondo',
                text: 'colorTexto',
                text_navbar: 'colorTextoNavbar',
                menu_hover: 'colorMenu'
            };

            Object.keys(mapeo).forEach(keyBackend => {
                const keyFrontend = mapeo[keyBackend];
                if (colores[keyBackend]) {
                    localStorage.setItem(keyFrontend, colores[keyBackend]);
                    console.log(`Guardando ${keyFrontend}: ${colores[keyBackend]}`);
                }
            });

            // Disparar evento para actualizar UI
            window.dispatchEvent(new CustomEvent('recargar-colores'));

            // También actualizar localStorage para que otras pestañas se enteren
            localStorage.setItem('colores_actualizados', Date.now().toString());
        }
    }
};
</script>

<style scoped>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}
</style>