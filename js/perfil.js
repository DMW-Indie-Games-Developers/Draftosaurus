document.addEventListener('DOMContentLoaded', function () {
    // Obtener userId del almacenamiento
    const userId = localStorage.getItem('userId');
    if (!userId) {
        // Si no hay sesión, redirigir al login
        window.location.href = 'login.html';
        return;
    }

    const el = id => document.getElementById(id);

    // Cargar perfil del usuario
    fetch(`/perfil?id=${userId}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                if (el('user-name')) el('user-name').textContent = 'Usuario no encontrado';
                if (el('user-id')) el('user-id').textContent = '';
                return;
            }

            if (el('user-name')) el('user-name').textContent = data.username || '';
            if (el('user-id')) el('user-id').textContent = data.id ? `#${data.id}` : '';

            // Mostrar info adicional si existe
            if (el('user-info')) {
                let infoHtml = `<p><strong>Email:</strong> ${data.email || ''}</p>`;
                if (data.created_at) infoHtml += `<p><strong>Fecha de creación:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>`;
                if (data.updated_at) infoHtml += `<p><strong>Última actualización:</strong> ${new Date(data.updated_at).toLocaleDateString()}</p>`;
                el('user-info').innerHTML = infoHtml;
            }

            // Avatar
            const avatarImg = el('avatar-img');
            if (avatarImg) avatarImg.src = data.avatar || 'img/isotipoOficial.png';
        })
        .catch(err => {
            console.error('Error cargando perfil:', err);
        });

    // Helper seguro para añadir listener si el elemento existe
    const on = (id, ev, fn) => { const node = el(id); if (node) node.addEventListener(ev, fn); };

    // Avatar upload: clic en overlay abre input file
    on('edit-avatar-overlay', 'click', () => {
        const input = el('avatar-input'); if (input) input.click();
    });

    on('avatar-input', 'change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) return alert('Formato no soportado');
        if (file.size > 3 * 1024 * 1024) return alert('Máx 3MB');

        const fd = new FormData();
        fd.append('avatar', file);
        fd.append('userId', userId);

        const btn = el('crear-partida-btn');
        const prev = btn ? btn.textContent : null;
        if (btn) btn.textContent = 'Subiendo...';

        fetch('/upload-avatar.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (btn) btn.textContent = prev;
                if (res.success && res.avatarUrl) {
                    // Persistir URL en perfil
                    return fetch('/perfil/avatar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ userId, avatarUrl: res.avatarUrl })
                    })
                    .then(r2 => r2.json())
                    .then(resp2 => {
                        if (resp2.success && el('avatar-img')) el('avatar-img').src = res.avatarUrl;
                        else alert('No se guardó avatar');
                    });
                }
                alert(res.message || 'Error en la subida');
            })
            .catch(err => { if (btn) btn.textContent = prev; console.error(err); alert('Error de red'); });
    });

    // Inicializar modales y botones (si existen)
    const modalCrearEl = el('modalCrearPartida');
    const modalCrear = modalCrearEl ? new bootstrap.Modal(modalCrearEl) : null;
    on('crear-partida-btn', 'click', () => { if (modalCrear) modalCrear.show(); });

    on('btnInvitado', 'click', () => {
        const mEl = el('modalInvitado'); if (!mEl) return; const m = new bootstrap.Modal(mEl); m.show();
        const loginBox = el('loginJugador2'); if (loginBox) loginBox.classList.add('d-none');
    });

    on('btnUsers', 'click', () => { const loginBox = el('loginJugador2'); if (loginBox) loginBox.classList.remove('d-none'); if (modalCrear) modalCrear.show(); });

    // Formulario invitado
    on('formInvitado', 'submit', function (e) {
        e.preventDefault();
        const nameInput = el('guestName');
        const name = nameInput ? (nameInput.value || '').trim() : '';
        const finalName = name || 'Invitado';
        localStorage.setItem('userId2', 'guest_' + Date.now());
        localStorage.setItem('userName2', finalName);
        const instMain = modalCrearEl ? bootstrap.Modal.getInstance(modalCrearEl) : null; if (instMain) instMain.hide();
        const instInv = el('modalInvitado') ? bootstrap.Modal.getInstance(el('modalInvitado')) : null; if (instInv) instInv.hide();
        window.location.href = 'tablero.html';
    });

    // Login jugador 2
    on('formLoginJugador2', 'submit', function (e) {
        e.preventDefault();
        const email = el('emailJugador2') ? el('emailJugador2').value.trim() : '';
        const password = el('passwordJugador2') ? el('passwordJugador2').value : '';
        fetch('/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ identifier: email, password }) })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.user && res.user.id) {
                    localStorage.setItem('userId2', res.user.id);
                    localStorage.setItem('userName2', res.user.username);
                    window.location.href = 'tablero.html';
                } else {
                    alert('Login fallido');
                }
            })
            .catch(() => alert('Error de red'));
    });
});
