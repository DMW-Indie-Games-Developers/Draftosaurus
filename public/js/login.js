'use strict';
const BASE = 'http://localhost:8000';

/* ---------- Función auxiliar ---------- */
async function postJson(url, datos) {
  const resp = await fetch(url, {
    method: 'POST',
    credentials: 'include', // ← envía cookies
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos)
  });
  const texto = await resp.text();
  let json = null;
  try { json = texto ? JSON.parse(texto) : null; }
  catch { json = { parseError: true, raw: texto }; }
  return { ok: resp.ok, status: resp.status, json };
}

/* ---------- Si YA está logueado → saltar a perfil ---------- */
(async () => {
  const { json } = await postJson(`${BASE}/mi-perfil`, {});
  if (!json.error) location.replace('/perfil');
})();

/* ---------- Login ---------- */
document.getElementById('btn-login')?.addEventListener('click', async () => {
  const identificador = document.getElementById('login-identifier').value.trim();
  const clave = document.getElementById('login-password').value;

  if (!identificador || !clave) {
    alert('Completá todos los campos.');
    return;
  }

  const { ok, json } = await postJson(`${BASE}/login`, { identifier: identificador, password: clave });

  console.log('Respuesta del login:', { ok, json }); // DEBUG

  if (ok && json?.success) {
    const u = json.user;
    localStorage.setItem('userId', u.id);
    localStorage.setItem('userName', u.username);
    localStorage.setItem('userEmail', u.email);
    localStorage.setItem('userAvatar', u.avatar || 'img/isotipoOficial.png');

    console.log('Usuario logueado:', u); // DEBUG
    console.log('Rol del usuario:', u.rol); // DEBUG

    alert('¡Login exitoso!');
    
    // Verificar el rol con más detalle
    if (u.rol === 'admin') {
      console.log('Redirigiendo a admin...'); // DEBUG
      location.href = '/admin';
    } else {
      console.log('Redirigiendo a perfil...'); // DEBUG
      location.href = '/perfil';
    }
  } else {
    console.error('Error en login:', json); // DEBUG
    alert('Login fallido: ' + (json?.message || 'Credenciales incorrectas.'));
  }
});

/* ---------- Registro ---------- */
document.getElementById('btn-register')?.addEventListener('click', async () => {
  const username = document.getElementById('reg-username').value.trim();
  const email = document.getElementById('reg-email').value.trim();
  const password = document.getElementById('reg-password').value;
  const confirm = document.getElementById('confirm-password').value;

  if (!username || !email || !password || !confirm) {
    alert('Completá todos los campos.');
    return;
  }
  if (password !== confirm) {
    alert('Las contraseñas no coinciden.');
    return;
  }

  const { ok, json } = await postJson(`${BASE}/register`, { username, email, password });
  alert(ok && json?.success ? '¡Registro exitoso!' : 'Registro fallido: ' + (json?.message || 'Error desconocido.'));
});