'use strict';
const BASE = 'http://localhost:8000';

/* ---------- Función auxiliar ---------- */
async function postJson(url, datos) {
  const resp = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos)
  });
  const texto = await resp.text();
  let json = null;
  try { json = texto ? JSON.parse(texto) : null; }
  catch { json = { parseError: true, raw: texto }; }
  return { ok: resp.ok, status: resp.status, json };
}

/* ---------- ✅ ALERTA MEJORADA - Mostrar alerta si llega ?error=suspended ---------- */
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const error = urlParams.get('error');

  if (error === 'suspended') {
    console.log('🚨 Detectada cuenta suspendida, mostrando alerta');

    // Limpiar URL sin recargar
    const newUrl = window.location.pathname;
    history.replaceState({}, document.title, newUrl);

    // Mostrar alerta flotante
    const alertEl = document.getElementById('suspendAlert');
    if (alertEl) {
      alertEl.classList.remove('d-none');
      alertEl.classList.add('show');

      console.log('✅ Alerta mostrada correctamente');

      // Auto-cerrar después de 8 segundos (más tiempo para leer)
      setTimeout(() => {
        const alertInstance = bootstrap.Alert.getOrCreateInstance(alertEl);
        if (alertInstance && alertEl.classList.contains('show')) {
          alertInstance.close();
          console.log('🔄 Alerta cerrada automáticamente');
        }
      }, 8000);
    } else {
      console.error('❌ No se encontró el elemento de alerta');
    }
  }

  // ✅ IMPORTANTE: NO ocultar botones ni elementos del formulario
  // Mantener toda la funcionalidad disponible
});

/* ---------- Funciones para mostrar/ocultar mensajes ---------- */
function showError(message) {
  let errorDiv = document.getElementById('login-error');
  if (!errorDiv) {
    errorDiv = document.createElement('div');
    errorDiv.id = 'login-error';
    errorDiv.className = 'alert alert-danger mt-3';
    const loginForm = document.querySelector('.login-form');
    if (loginForm) loginForm.appendChild(errorDiv);
  }
  errorDiv.textContent = message;
  errorDiv.style.display = 'block';
}

function showSuccess(message) {
  let successDiv = document.getElementById('register-success');
  if (!successDiv) {
    successDiv = document.createElement('div');
    successDiv.id = 'register-success';
    successDiv.className = 'alert alert-success mt-3';
    const registerForm = document.querySelector('.register-form');
    if (registerForm) registerForm.appendChild(successDiv);
  }
  successDiv.textContent = message;
  successDiv.style.display = 'block';

  // Auto-ocultar después de 5 segundos
  setTimeout(() => {
    successDiv.style.display = 'none';
  }, 5000);
}

function showRegisterError(message) {
  let errorDiv = document.getElementById('register-error');
  if (!errorDiv) {
    errorDiv = document.createElement('div');
    errorDiv.id = 'register-error';
    errorDiv.className = 'alert alert-danger mt-3';
    const registerForm = document.querySelector('.register-form');
    if (registerForm) registerForm.appendChild(errorDiv);
  }
  errorDiv.textContent = message;
  errorDiv.style.display = 'block';
}

function hideError() {
  const errorDiv = document.getElementById('login-error');
  if (errorDiv) errorDiv.style.display = 'none';
}

function hideRegisterError() {
  const errorDiv = document.getElementById('register-error');
  if (errorDiv) errorDiv.style.display = 'none';
  const successDiv = document.getElementById('register-success');
  if (successDiv) successDiv.style.display = 'none';
}

/* ---------- EVENTO DE LOGIN ---------- */
document.addEventListener('DOMContentLoaded', () => {
  // Event listener para el botón de login
  const loginBtn = document.getElementById('btn-login');
  if (loginBtn) {
    loginBtn.addEventListener('click', async () => {
      hideError();
      const identificador = document.getElementById('login-identifier').value.trim();
      const clave = document.getElementById('login-password').value;
      
      if (!identificador || !clave) {
        showError('Completa todos los campos.');
        return;
      }

      const originalHTML = loginBtn.innerHTML;
      loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Iniciando...';
      loginBtn.disabled = true;

      try {
        const { ok, json } = await postJson(`${BASE}/login`, {
          identifier: identificador,
          password: clave
        });

        if (ok && json?.success) {
          const u = json.user;
          localStorage.setItem('userId', u.id);
          localStorage.setItem('userName', u.username);
          localStorage.setItem('userEmail', u.email);
          localStorage.setItem('userAvatar', u.avatar || 'img/isotipoOficial.png');

          // Redirección según rol
          location.href = (u.rol === 'admin') ? '/admin' : '/perfil';
        } else {
          let msg = 'Credenciales incorrectas.';
          if (json?.code === 'ACCOUNT_SUSPENDED') {
            msg = json.message || 'Tu cuenta ha sido suspendida. Contactá al administrador.';
          } else if (json?.message) {
            msg = json.message;
          }
          showError(msg);
        }
      } catch (e) {
        console.error('Error de conexión:', e);
        showError('Error de conexión. Verifica tu internet e intenta nuevamente.');
      } finally {
        loginBtn.innerHTML = originalHTML;
        loginBtn.disabled = false;
      }
    });
  }

  // Event listener para el botón de registro
  const registerBtn = document.getElementById('btn-register');
  if (registerBtn) {
    registerBtn.addEventListener('click', async () => {
      hideRegisterError();
      const username = document.getElementById('reg-username').value.trim();
      const email = document.getElementById('reg-email').value.trim();
      const password = document.getElementById('reg-password').value;
      const confirm = document.getElementById('confirm-password').value;

      if (!username || !email || !password || !confirm) {
        showRegisterError('Completa todos los campos.');
        return;
      }
      if (password !== confirm) {
        showRegisterError('Las contraseñas no coinciden.');
        return;
      }

      const originalHTML = registerBtn.innerHTML;
      registerBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registrando...';
      registerBtn.disabled = true;

      try {
        const { ok, json } = await postJson(`${BASE}/register`, { username, email, password });
        console.log('Respuesta de registro:', { ok, json });

        if (ok && json?.success) {
          // Mostrar mensaje de éxito
          showSuccess('¡Registro exitoso! Ahora puedes iniciar sesión.');

          // Limpiar formulario
          ['reg-username', 'reg-email', 'reg-password', 'confirm-password']
            .forEach(id => document.getElementById(id).value = '');

          // Cambiar a formulario de login después de 2 segundos
          setTimeout(() => {
            const toggleCheckbox = document.getElementById('toggle-form');
            if (toggleCheckbox) toggleCheckbox.checked = false;
          }, 2000);
        } else {
          showRegisterError('Registro fallido: ' + (json?.message || 'Error desconocido.'));
        }
      } catch (e) {
        console.error('Error de conexión:', e);
        showRegisterError('Error de conexión. Verifica tu internet e intenta nuevamente.');
      } finally {
        registerBtn.innerHTML = originalHTML;
        registerBtn.disabled = false;
      }
    });
  }
});

/* ---------- MANTENER FUNCIONALIDAD COMPLETA ---------- */
// ✅ No ocultar elementos, permitir que el usuario intente login con otra cuenta