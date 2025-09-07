/* ------ Si NO hay sesión local → login ------ */
if (!localStorage.getItem('userId')) {
  localStorage.clear();
  location.replace('/login'); // ← sin .html
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
    if (!permitidos.includes(file.type)) return alert('Formato no soportado');
    if (file.size > 3 * 1024 * 1024) return alert('Máx 3 MB');

    const fd = new FormData();
    fd.append('avatar', file);
    fd.append('userId', data.id);

    const res1 = await fetch('/upload-avatar.php', { method: 'POST', body: fd });
    const r1 = await res1.json();
    if (r1.success && r1.avatarUrl) {
      const res2 = await fetch('/perfil/avatar', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId: data.id, avatarUrl: r1.avatarUrl })
      }).then(r => r.json());
      if (res2.success && el('avatar-img')) el('avatar-img').src = r1.avatarUrl;
      else alert('No se guardó el avatar');
    } else alert(r1.message || 'Error al subir');
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
});