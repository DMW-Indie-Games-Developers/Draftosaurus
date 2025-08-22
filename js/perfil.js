document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('crear-partida-btn').addEventListener('click', function() {
        window.location.href = 'tablero.html';
    });
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
});
