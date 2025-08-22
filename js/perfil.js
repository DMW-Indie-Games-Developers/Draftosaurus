document.addEventListener('DOMContentLoaded', function() {
    // Suponiendo que tienes el id del usuario logueado
    const userId = localStorage.getItem('userId');
    if (!userId) {
        window.location.href = 'login.html'; // Redirige si no está logueado
        return;
    }
    fetch(`/perfil?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('user-name').textContent = 'Usuario no encontrado';
                document.getElementById('user-id').textContent = '';
                return;
            }
            document.getElementById('user-name').textContent = data.username;
            document.getElementById('user-id').textContent = `#${data.id}`;

            // Mostrar email, fecha de creación y última actualización
            let infoHtml = `<p><strong>Email:</strong> ${data.email}</p>`;
            infoHtml += `<p><strong>Fecha de creación:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>`;
            infoHtml += `<p><strong>Última actualización:</strong> ${new Date(data.updated_at).toLocaleDateString()}</p>`;
            document.getElementById('user-info').innerHTML = infoHtml;

            // Mostrar avatar si existe
            const avatarImg = document.getElementById('avatar-img');
            if (data.avatar) {
                avatarImg.src = data.avatar;
            } else {
                avatarImg.src = 'img/isotipoOficial.png';
            }
        })
        .catch(err => {
            document.getElementById('user-name').textContent = 'Error de conexión';
            document.getElementById('user-id').textContent = '';
        });

    // Cambiar avatar
    document.getElementById('edit-avatar-overlay').addEventListener('click', function() {
        document.getElementById('avatar-input').click();
    });

    document.getElementById('avatar-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('userId', userId);

        // Debes crear upload-avatar.php para guardar la imagen y devolver la URL
        fetch('/upload-avatar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success && result.avatarUrl) {
                fetch('/perfil/avatar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userId: userId, avatarUrl: result.avatarUrl })
                })
                .then(r => r.json())
                .then(r => {
                    if (r.success) {
                        document.getElementById('avatar-img').src = result.avatarUrl;
                    } else {
                        alert('No se pudo actualizar el avatar.');
                    }
                });
            } else {
                alert('No se pudo subir la imagen.');
            }
        })
        .catch(() => alert('Error al subir la imagen.'));
    });

    // Modal Crear Partida
    const modalCrearPartida = new bootstrap.Modal(document.getElementById('modalCrearPartida'));
    document.getElementById('crear-partida-btn').addEventListener('click', function() {
        modalCrearPartida.show();
    });

    // Botones para elegir tipo de jugador
    document.getElementById('btnInvitado').addEventListener('click', function() {
        document.getElementById('loginJugador2').style.display = 'none';
    });
    document.getElementById('btnUsers').addEventListener('click', function() {
        document.getElementById('loginJugador2').style.display = 'block';
    });

    // Login Jugador 2 (solo frontend, puedes conectar con tu API)
    document.getElementById('formLoginJugador2').addEventListener('submit', function(e) {
        e.preventDefault();
        const email = document.getElementById('emailJugador2').value.trim();
        const password = document.getElementById('passwordJugador2').value;
        fetch('/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identifier: email, password: password })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.user && data.user.id) {
                localStorage.setItem('userId2', data.user.id);
                localStorage.setItem('userName2', data.user.username);
                window.location.href = 'tablero.html';
            } else {
                alert('Login fallido: ' + (data.message || 'Credenciales incorrectas.'));
            }
        })
        .catch(() => alert('Error de red al intentar iniciar sesión.'));
    });
});
