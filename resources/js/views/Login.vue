```vue
<!-- resources/js/views/Login.vue -->

<template>
  <div class="login-page">

    <!-- Franja superior -->
    <div class="top-bar"></div>

    <!-- Card del Login -->
    <div class="login-card">

      <!-- Logo -->
      <div class="logo-container">
        <img
          :src="logoUrl"
          alt="Vende en FA"
          class="logo"
        />
      </div>

      <!-- Formulario -->
      <form @submit.prevent="handleLogin" class="login-form">

        <!-- Título -->
        <h1 class="login-title">
          Ingresa tu numero de socio
        </h1>

        <!-- Error -->
        <div v-if="error" class="error-message">
          {{ error }}
        </div>

        <!-- Número de socio -->
        <div class="input-group">
          <label for="identificador">
            Número de socio
          </label>

          <div class="input-wrapper">
            <input
              id="identificador"
              name="identificador"
              v-model="form.identificador"
              type="text"
              autocomplete="username"
              required
            />

            <button
              v-if="form.identificador"
              type="button"
              class="clear-button"
              @click="form.identificador = ''"
              aria-label="Limpiar número de socio"
            >
              ×
            </button>
          </div>

          <p class="supporting-text">
            Ingresa tu número de socio
          </p>
        </div>

        <!-- Contraseña -->
        <div class="input-group password-group">
          <label for="password">
            Contraseña
          </label>

          <div class="input-wrapper">
            <input
              id="password"
              name="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              required
            />

            <button
              type="button"
              class="password-button"
              @click="showPassword = !showPassword"
              :aria-label="
                showPassword
                  ? 'Ocultar contraseña'
                  : 'Mostrar contraseña'
              "
            >
              {{ showPassword ? '◉' : '○' }}
            </button>
          </div>

          <p class="supporting-text">
            Ingresa tu contraseña
          </p>
        </div>

        <!-- Botón -->
        <button
          type="submit"
          :disabled="loading"
          class="login-button"
        >
          <span v-if="loading" class="spinner"></span>
          {{ loading ? 'CARGANDO...' : 'ACCEDER' }}
        </button>

        <!-- Registro -->
        <div class="register-container">
          <span>¿No tienes un numero de socio?</span>

          <a
            href="#"
            @click.prevent="handleRegister"
          >
            Da Click aquí
          </a>
        </div>

      </form>
    </div>

  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Login',

  data() {
    return {
      logoUrl: '/img/logo.png',

      form: {
        identificador: '',
        password: ''
      },

      error: '',
      loading: false,
      showPassword: false
    };
  },

  methods: {
    async handleLogin() {
      this.loading = true;
      this.error = '';

      try {
        const response = await axios.post(
          '/api/v1/login',
          {
            identificador: this.form.identificador,
            password: this.form.password
          }
        );

        if (response.data.access_token) {
          localStorage.setItem(
            'token',
            response.data.access_token
          );

          localStorage.setItem(
            'user',
            JSON.stringify(response.data.user)
          );

          this.$router.push('/');
        } else {
          this.error =
            response.data.message ||
            'No se recibió el token de acceso.';
        }

      } catch (error) {
        console.error('Login error:', error);

        this.error =
          error.response?.data?.message ||
          'Credenciales incorrectas.';

      } finally {
        this.loading = false;
      }
    },

    handleRegister() {
      // Cambia esta ruta si tienes una pantalla de registro
      // this.$router.push('/registro');

      console.log('Registro de nuevo socio');
    }
  }
};
</script>

<style scoped>

/* =========================================
   PÁGINA
========================================= */

.login-page {
  min-height: 100vh;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* =========================================
   BARRA VERDE SUPERIOR
   SE MANTIENE IGUAL
========================================= */

.top-bar {
  width: 100%;
  height: 76px;
  background: #9bc53d;
  flex-shrink: 0;
}

/* =========================================
   CARD DEL LOGIN
========================================= */

.login-card {
  width: 100%;
  max-width: 420px;

  margin-top: 42px;
  margin-bottom: 40px;

  padding: 38px 32px 32px;

  box-sizing: border-box;

  background: #ffffff;

  border: 1px solid #e5e7eb;
  border-radius: 10px;

  box-shadow:
    0 4px 12px rgba(0, 0, 0, 0.08),
    0 1px 3px rgba(0, 0, 0, 0.05);
}

/* =========================================
   LOGO
========================================= */

.logo-container {
  width: 150px;
  height: 70px;

  margin: 0 auto 40px;

  display: flex;
  justify-content: center;
  align-items: center;

  border: 1px solid #e5e7eb;

  background: #ffffff;
}

.logo {
  max-width: 120px;
  max-height: 60px;

  width: auto;
  height: auto;

  object-fit: contain;
}

/* =========================================
   FORMULARIO
========================================= */

.login-form {
  width: 100%;
}

.login-title {
  margin: 0 0 7px;

  text-align: center;

  font-size: 16px;
  line-height: 22px;

  font-weight: 400;

  color: #111111;
}

/* =========================================
   ERROR
========================================= */

.error-message {
  margin: 15px 0;

  padding: 10px 12px;

  border-radius: 4px;

  background: #fef2f2;
  border: 1px solid #fecaca;

  color: #dc2626;

  font-size: 12px;

  text-align: center;
}

/* =========================================
   INPUT
========================================= */

.input-group {
  margin-top: 0;
}

.password-group {
  margin-top: 17px;
}

.input-group label {
  display: block;

  font-size: 10px;
  line-height: 14px;

  color: #555555;

  margin-left: 11px;
  margin-bottom: -1px;

  position: relative;
  z-index: 2;
}

.input-wrapper {
  position: relative;

  width: 100%;
}

.input-wrapper input {
  width: 100%;
  height: 39px;

  box-sizing: border-box;

  padding: 0 40px 0 11px;

  border: none;
  border-bottom: 1px solid #77727d;

  border-radius: 3px 3px 0 0;

  background: #e8e2eb;

  color: #222222;

  font-size: 13px;

  outline: none;

  transition: all 0.2s ease;
}

.input-wrapper input:focus {
  border-bottom: 2px solid #9bc53d;

  background: #e2dce5;
}

.input-wrapper input::placeholder {
  color: transparent;
}

/* =========================================
   TEXTO DE APOYO
========================================= */

.supporting-text {
  margin: 4px 0 0 11px;

  font-size: 9px;
  line-height: 12px;

  color: #666666;
}

/* =========================================
   BOTÓN LIMPIAR
========================================= */

.clear-button {
  position: absolute;

  right: 8px;
  top: 50%;

  transform: translateY(-50%);

  width: 20px;
  height: 20px;

  padding: 0;

  border: 1px solid #666666;
  border-radius: 50%;

  background: transparent;

  color: #555555;

  font-size: 16px;
  line-height: 16px;

  cursor: pointer;
}

/* =========================================
   MOSTRAR CONTRASEÑA
========================================= */

.password-button {
  position: absolute;

  right: 8px;
  top: 50%;

  transform: translateY(-50%);

  width: 25px;
  height: 25px;

  border: none;

  background: transparent;

  color: #555555;

  font-size: 17px;

  cursor: pointer;
}

/* =========================================
   BOTÓN ACCEDER
========================================= */

.login-button {
  display: flex;

  align-items: center;
  justify-content: center;

  width: 147px;
  height: 41px;

  margin: 17px auto 0;

  border: none;
  border-radius: 5px;

  background: #2d2d2d;

  color: #ffffff;

  font-size: 11px;
  font-weight: 400;

  cursor: pointer;

  transition:
    background 0.2s ease,
    transform 0.1s ease;
}

.login-button:hover {
  background: #1f1f1f;
}

.login-button:active {
  transform: scale(0.98);
}

.login-button:disabled {
  opacity: 0.65;

  cursor: not-allowed;
}

/* =========================================
   SPINNER
========================================= */

.spinner {
  width: 13px;
  height: 13px;

  margin-right: 7px;

  border: 2px solid rgba(255, 255, 255, 0.4);

  border-top-color: #ffffff;

  border-radius: 50%;

  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================
   REGISTRO
========================================= */

.register-container {
  margin-top: 70px;

  text-align: center;

  font-size: 11px;
  line-height: 15px;

  color: #111111;
}

.register-container a {
  color: #0000ee;

  text-decoration: none;

  margin-left: 3px;
}

.register-container a:hover {
  text-decoration: underline;
}

/* =========================================
   RESPONSIVE
========================================= */

@media (max-width: 480px) {

  .top-bar {
    height: 76px;
  }

  .login-card {
    width: calc(100% - 32px);

    margin-top: 25px;
    margin-bottom: 25px;

    padding: 32px 24px 28px;

    border-radius: 8px;
  }

  .logo-container {
    margin-bottom: 40px;
  }

  .register-container {
    margin-top: 60px;
  }
}

/* =========================================
   PANTALLAS PEQUEÑAS
========================================= */

@media (max-height: 650px) {

  .login-card {
    margin-top: 20px;

    padding-top: 25px;
  }

  .logo-container {
    margin-bottom: 25px;
  }

  .register-container {
    margin-top: 45px;
  }
}

</style>
```
