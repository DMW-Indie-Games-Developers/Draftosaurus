document.addEventListener('DOMContentLoaded', function () {
    const el = id => document.getElementById(id);

    // ✅ Llamar al servidor para obtener los datos del usuario logueado
    fetch('/mi-perfil')
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert('No estás logueado o sesión expirada.');
                window.location.href = 'login.html';
                return;
            }

            // Pintar datos
            if (el('user-name')) el('user-name').textContent = data.username;
            if (el('user-id')) el('user-id').textContent = `#${data.id}`;
            if (el('user-info')) {
                let infoHtml = `<p><strong>Email:</strong> ${data.email}</p>`;
                if (data.created_at) infoHtml += `<p><strong>Fecha de creación:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>`;
                if (el('user-info')) el('user-info').innerHTML = infoHtml;
            }
            if (el('avatar-img')) el('avatar-img').src = data.avatar || 'img/isotipoOficial.png';
        })
        .catch(err => {
            console.error('Error cargando perfil:', err);
            window.location.href = 'login.html';
        });

    /* ---------- Avatar upload (igual que antes) ---------- */
    const on = (id, ev, fn) => { const node = el(id); if (node) node.addEventListener(ev, fn); };

    on('edit-avatar-overlay', 'click', () => {
        const input = el('avatar-input'); if (input) input.click();
    });

    on('avatar-input', 'change', function (e) {
        const file = e.target.files?.[0];
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) return alert('Formato no soportado');
        if (file.size > 3 * 1024 * 1024) return alert('Máx 3MB');

        const fd = new FormData();
        fd.append('avatar', file);
        fd.append('userId', data.id); // Usamos el ID que ya tenemos

        fetch('/upload-avatar.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.avatarUrl) {
                    return fetch('/perfil/avatar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ userId: data.id, avatarUrl: res.avatarUrl })
                    })
                        .then(r2 => r2.json())
                        .then(resp2 => {
                            if (resp2.success && el('avatar-img')) {
                                el('avatar-img').src = res.avatarUrl;
                            } else {
                                alert('No se guardó avatar');
                            }
                        });
                } else {
                    alert(res.message || 'Error en la subida');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error de red');
            });
    });

    /* ---------- Cerrar sesión ---------- */
    const logoutBtn = el('btn-logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            const res = await fetch('/logout', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                localStorage.clear();
                window.location.href = 'login.html';
            } else {
                alert('No se pudo cerrar sesión');
            }
        });
    }
});