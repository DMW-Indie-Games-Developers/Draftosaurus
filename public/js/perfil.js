/* =====  perfil.js  ===== */
/* ------ Si NO hay sesión local → login ------ */
if (!localStorage.getItem('userId')) {
  localStorage.clear();
  location.replace('/login');
}

document.addEventListener('DOMContentLoaded', async () => {
  const el = id => document.getElementById(id);

  /* 1. Pedimos datos del usuario logueado */
  const resp = await fetch('/perfil/me', { credentials: 'include' });
  const data = await resp.json();

  /* 2. Si PHP dice que no hay sesión → fuera */
  if (data.error || !data.success) {
    alert('No estás logueado o la sesión expiró.');
    location.href = '/login';
    return;
  }

  /* 3. Pintar datos */
  if (el('user-name')) el('user-name').textContent = data.username;
  if (el('user-id')) el('user-id').textContent = `#${data.id}`;
  if (el('user-puntos')) el('user-puntos').textContent = data.puntuacion_total ?? 0;
  if (el('user-jugadas')) el('user-jugadas').textContent = data.partidas_jugadas ?? 0;
  if (el('user-ganadas')) el('user-ganadas').textContent = data.partidas_ganadas ?? 0;
  if (el('user-info')) {
    let html = `<p><strong>Email:</strong> ${data.email}</p>`;
    if (data.created_at) html += `<p><strong>Fecha:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>`;
    el('user-info').innerHTML = html;
  }
  if (el('user-puntos')) el('user-puntos').textContent = data.puntuacion_total ?? 0;
  if (el('avatar-img')) el('avatar-img').src = data.avatar || 'img/isotipoOficial.png';

  /* 4. Cambiar avatar */
  const on = (id, ev, fn) => { const n = el(id); if (n) n.addEventListener(ev, fn); };

  on('edit-avatar-overlay', 'click', () => el('avatar-input')?.click());

  on('avatar-input', 'change', async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!permitidos.includes(file.type)) return alert('Formato no permitido');
    if (file.size > 3 * 1024 * 1024) return alert('Máximo 3 MB');

    const fd = new FormData();
    fd.append('avatar', file);
    fd.append('userId', data.id);

    const res = await fetch('/api/upload-avatar.php', { method: 'POST', body: fd });
    const r = await res.json();
    if (r.success && r.avatarUrl) {
      el('avatar-img').src = r.avatarUrl;
      localStorage.setItem('userAvatar', r.avatarUrl);
    } else {
      alert(r.message || 'Error al subir imagen');
    }
  });

  /* 5. Cerrar sesión */
  on('btn-logout', 'click', async () => {
    const res = await fetch('/logout', { method: 'POST', credentials: 'include' });
    const d = await res.json();
    if (d.success) {
      localStorage.clear();
      location.href = '/login';
    } else alert('No se pudo cerrar sesión');
  });

  /* 6. Modal Crear Partida */
  on('crear-partida-btn', 'click', () => {
    const modal = new bootstrap.Modal(el('modalCrearPartida'));
    modal.show();
  });

  /* 7. Elegir modo Invitado */
  on('btnInvitado', 'click', () => {
    bootstrap.Modal.getInstance(el('modalCrearPartida')).hide();
    const modalInv = new bootstrap.Modal(el('modalInvitado'));
    modalInv.show();
  });

  on('formInvitado', 'submit', (e) => {
    e.preventDefault();
    const nombre = el('guestName').value.trim() || 'Anónimo';
    const yo = localStorage.getItem('userName') || 'Yo';
    localStorage.setItem('jugadorActual', yo);
    localStorage.setItem('rival', nombre);
    localStorage.setItem('modo', 'invitado');
    location.href = '/tablero.html';
  });

  /* 8. Elegir modo Usuario registrado */
  on('btnUsers', 'click', () => el('loginJugador2').classList.remove('d-none'));

  on('formLoginJugador2', 'submit', async (e) => {
    e.preventDefault();
    const email = el('emailJugador2').value.trim();
    const pass = el('passwordJugador2').value;

    const res = await fetch('/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifier: email, password: pass })
    });
    const data = await res.json();

    if (data.success) {
      const yo = localStorage.getItem('userName') || 'Yo';
      localStorage.setItem('jugadorActual', yo);
      localStorage.setItem('rival', data.user.username);
      localStorage.setItem('modo', 'registrado');
      location.href = '/tablero.html';
    } else {
      alert(data.message || 'Credenciales incorrectas');
    }
  });
});