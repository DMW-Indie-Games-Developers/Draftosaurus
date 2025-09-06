'use strict';

const BASE = 'http://localhost:8000';

/* ---------- helpers ---------- */
async function postJson(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        mode: 'cors',
        body: JSON.stringify(data),
        headers: { 'Content-Type': 'application/json' }
    });
    const text = await res.text();
    let json = null;
    try { json = text ? JSON.parse(text) : null; }
    catch { json = { parseError: true, raw: text }; }
    return { ok: res.ok, status: res.status, json };
}

/* ---------- login ---------- */
document.getElementById('btn-login')?.addEventListener('click', async () => {
    const identifier = document.getElementById('login-identifier').value.trim();
    const password = document.getElementById('login-password').value;

    if (!identifier || !password) {
        alert('Completá todos los campos.');
        return;
    }

    const { ok, json } = await postJson(`${BASE}/login`, { identifier, password });

    if (ok && json?.success) {
        /* >>>>>>>  ACÁ GUARDAMOS TODO  <<<<<<< */
        const u = json.user;
        localStorage.setItem('userId', u.id);
        localStorage.setItem('userName', u.username);
        localStorage.setItem('userEmail', u.email);
        localStorage.setItem('userAvatar', u.avatar || 'img/isotipoOficial.png');

        alert('¡Login exitoso!');
        window.location.href = 'perfil.html';
    } else {
        alert('Login fallido: ' + (json?.message || 'Credenciales incorrectas.'));
    }
});

/* ---------- registro (sin cambios funcionales) ---------- */
document.getElementById('btn-register')?.addEventListener('click', async () => {
    const username = document.getElementById('reg-username').value.trim();
    const email    = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-password').value;
    const confirm  = document.getElementById('confirm-password').value;

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