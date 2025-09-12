document.addEventListener('DOMContentLoaded', function () {
  // Obtener userId del almacenamiento
  const userId = localStorage.getItem('userId');
  if (!userId) {
    // Si no hay sesión, redirigir al login
    window.location.href = '/login';
    return;
  }

  const el = id => document.getElementById(id);

  // Cargar perfil del usuario
  fetch(`/perfil/me`, { credentials: 'include' })
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
    if (!allowed.includes(file.type)) {
      alert('Formato no soportado. Use JPEG, PNG, WEBP o GIF.');
      return;
    }

    if (file.size > 3 * 1024 * 1024) {
      alert('El archivo es demasiado grande. Máximo 3MB.');
      return;
    }

    const fd = new FormData();
    fd.append('avatar', file);
    fd.append('userId', userId);

    const btn = el('crear-partida-btn');
    const prev = btn ? btn.textContent : null;
    if (btn) btn.textContent = 'Subiendo...';

    // Cambio importante: usar la ruta correcta
    fetch('/api/upload_avatar.php', {
      method: 'POST',
      body: fd,
      credentials: 'include'
    })
      .then(r => r.json())
      .then(res => {
        if (btn) btn.textContent = prev;
        if (res.success && res.avatarUrl) {
          // Actualizar imagen inmediatamente
          const avatarImg = el('avatar-img');
          if (avatarImg) avatarImg.src = res.avatarUrl;
          alert('Avatar actualizado correctamente');
        } else {
          alert(res.message || 'Error en la subida');
        }
      })
      .catch(err => {
        if (btn) btn.textContent = prev;
        console.error(err);
        alert('Error de red');
      });
  });

  // Inicializar modales y botones (si existen)
  const modalCrearEl = el('modalCrearPartida');
  const modalCrear = modalCrearEl ? new bootstrap.Modal(modalCrearEl) : null;
  on('crear-partida-btn', 'click', () => { if (modalCrear) modalCrear.show(); });

  // NUEVA FUNCIONALIDAD: Ver partidas guardadas
  on('btn-mis-partidas', 'click', mostrarPartidasGuardadas);

  on('btnInvitado', 'click', () => {
    const mEl = el('modalInvitado');
    if (!mEl) return;
    const m = new bootstrap.Modal(mEl);
    m.show();
    const loginBox = el('loginJugador2');
    if (loginBox) loginBox.classList.add('d-none');
  });

  on('btnUsers', 'click', () => {
    const loginBox = el('loginJugador2');
    if (loginBox) loginBox.classList.remove('d-none');
    if (modalCrear) modalCrear.show();
  });

  // Formulario invitado
  on('formInvitado', 'submit', function (e) {
    e.preventDefault();
    const nameInput = el('guestName');
    const name = nameInput ? (nameInput.value || '').trim() : '';
    const finalName = name || 'Invitado';
    localStorage.setItem('userId2', 'guest_' + Date.now());
    localStorage.setItem('userName2', finalName);
    const instMain = modalCrearEl ? bootstrap.Modal.getInstance(modalCrearEl) : null;
    if (instMain) instMain.hide();
    const instInv = el('modalInvitado') ? bootstrap.Modal.getInstance(el('modalInvitado')) : null;
    if (instInv) instInv.hide();
    window.location.href = '/tablero';
  });

  // Login jugador 2
  on('formLoginJugador2', 'submit', function (e) {
    e.preventDefault();
    const email = el('emailJugador2') ? el('emailJugador2').value.trim() : '';
    const password = el('passwordJugador2') ? el('passwordJugador2').value : '';

    fetch('/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifier: email, password }),
      credentials: 'include'
    })
      .then(r => r.json())
      .then(res => {
        if (res.success && res.user && res.user.id) {
          localStorage.setItem('userId2', res.user.id);
          localStorage.setItem('userName2', res.user.username);
          window.location.href = '/tablero';
        } else {
          alert('Login fallido: ' + (res.message || 'Credenciales incorrectas'));
        }
      })
      .catch(() => alert('Error de red'));
  });

  // Botón de cerrar sesión corregido
  on('btn-logout', 'click', async function (e) {
    e.preventDefault();
    try {
      const response = await fetch('/logout', {
        method: 'POST',
        credentials: 'include'
      });
      const data = await response.json();

      if (data.success) {
        localStorage.clear();
        window.location.href = '/home';
      } else {
        alert('Error al cerrar sesión');
      }
    } catch (error) {
      console.error('Error:', error);
      // Limpiar localStorage de todas formas
      localStorage.clear();
      window.location.href = '/home';
    }
  });

  // FUNCIONES PARA MANEJO DE PARTIDAS GUARDADAS
  function mostrarPartidasGuardadas() {
    fetch('/misPartidas', {
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json'
      }
    })
      .then(r => r.json())
      .then(partidas => {
        if (partidas.success === false) {
          alert(partidas.message || 'Error al cargar partidas');
          return;
        }

        mostrarModalPartidas(partidas);
      })
      .catch(err => {
        console.error('Error cargando partidas:', err);
        alert('Error de red al cargar partidas');
      });
  }

  function mostrarModalPartidas(partidas) {
    // Crear modal dinámicamente
    const modalHTML = `
            <div class="modal fade" id="modalPartidas" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content draftosaurus-modal">
                        <div class="modal-header draftosaurus-modal">
                            <h5 class="modal-title">Mis Partidas Guardadas</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body draftosaurus-modal">
                            <div id="partidas-list">
                                ${generarListaPartidas(partidas)}
                            </div>
                        </div>
                        <div class="modal-footer draftosaurus-modal">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

    // Eliminar modal anterior si existe
    const existingModal = document.getElementById('modalPartidas');
    if (existingModal) {
      existingModal.remove();
    }

    // Insertar nuevo modal
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalPartidas'));
    modal.show();

    // Limpiar modal al cerrarse
    document.getElementById('modalPartidas').addEventListener('hidden.bs.modal', function () {
      this.remove();
    });
  }

  function generarListaPartidas(partidas) {
    if (!partidas || partidas.length === 0) {
      return '<p class="text-center text-muted">No tienes partidas guardadas</p>';
    }

    return partidas.map(partida => `
            <div class="card mb-3 bg-dark text-light">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="card-title mb-1">Partida #${partida.id}</h6>
                            <p class="card-text mb-1">
                                <small class="text-muted">
                                    Guardada: ${new Date(partida.fecha_guardado).toLocaleDateString()} 
                                    ${new Date(partida.fecha_guardado).toLocaleTimeString()}
                                </small>
                            </p>
                            <p class="card-text mb-0">
                                <small>Ronda: ${partida.ronda_actual || 1} | Turno: ${partida.turno_actual || 1}</small>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-custom btn-sm me-2" onclick="cargarPartida(${partida.id})">
                                Cargar
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="eliminarPartida(${partida.id})">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
  }

  // Funciones globales para los botones
  window.cargarPartida = function (partidaId) {
    if (confirm('¿Cargar esta partida? Se perderá cualquier progreso no guardado.')) {
      // Guardar ID de partida a cargar
      localStorage.setItem('partidaACargar', partidaId);

      // Cerrar modal y redirigir
      const modal = bootstrap.Modal.getInstance(document.getElementById('modalPartidas'));
      if (modal) modal.hide();

      window.location.href = '/tablero';
    }
  };

  window.eliminarPartida = function (partidaId) {
    if (confirm('¿Eliminar esta partida permanentemente?')) {
      fetch(`/api/tablero/eliminarPartida`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ partidaId: partidaId })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            alert('Partida eliminada correctamente');
            // Recargar lista de partidas
            mostrarPartidasGuardadas();
          } else {
            alert(res.message || 'Error al eliminar partida');
          }
        })
        .catch(err => {
          console.error('Error:', err);
          alert('Error de red al eliminar partida');
        });
    }
  };
});