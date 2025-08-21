'use strict'

// URL base de la API (ajusta esto a tu servidor, el puerto 80 es el predeterminado para HTTP)
    const BASE = 'http://localhost:8000';

    // Función para manejar el POST y devolver la respuesta JSON
    async function postJson(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            mode: 'cors',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        });
        const text = await res.text();
        let json = null;
        try {
            json = text ? JSON.parse(text) : null;
        } catch {
            json = { parseError: true, raw: text };
        }
        return { ok: res.ok, status: res.status, json };
    }

    // Maneja clic en el botón de login
    document.getElementById('btn-login').addEventListener('click', async () => {
        const identifier = document.getElementById('login-identifier').value.trim();
        const password = document.getElementById('login-password').value;
        
        if (!identifier || !password) {
            alert('Por favor, ingresa tu correo y contraseña.');
            return;
        }

        try {
            const res = await postJson(`${BASE}/login`, { identifier, password });
            
            if (res.ok && res.json && res.json.success) {
                alert('¡Login exitoso!');
                window.location.href = 'tablero.html';
            } else {
                alert('Login fallido: ' + (res.json?.message || 'Credenciales incorrectas.'));
            }
        } catch (err) {
            alert('Error de red al intentar iniciar sesión.');
        }
    });

    // Maneja clic en el botón de registro
    document.getElementById('btn-register').addEventListener('click', async () => {
        const username = document.getElementById('reg-username').value.trim();
        const email = document.getElementById('reg-email').value.trim();
        const password = document.getElementById('reg-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        if (!username || !email || !password || !confirmPassword) {
            alert('Por favor, completa todos los campos.');
            return;
        }

        if (password !== confirmPassword) {
            alert('Las contraseñas no coinciden.');
            return;
        }

        try {
            const res = await postJson(`${BASE}/register`, { username, email, password });
            
            if (res.ok && res.json && res.json.success) {
                alert('¡Registro exitoso!');
            } else {
                alert('Registro fallido: ' + (res.json?.message || 'Error desconocido.'));
            }
        } catch (err) {
            alert('Error de red al intentar registrarse.');
        }
    });