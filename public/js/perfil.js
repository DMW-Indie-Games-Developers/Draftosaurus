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
      console.log('Datos del perfil recibidos:', data); // Debug

      if (data.error) {
        if (el('user-name')) el('user-name').textContent = 'Usuario no encontrado';
        if (el('user-id')) el('user-id').textContent = '';
        return;
      }

      // Información básica del usuario - mostrar nickname si existe, si no username
      const displayName = data.nickname || data.username || 'Usuario';
      if (el('user-name')) {
        el('user-name').textContent = displayName;
        el('user-name').setAttribute('data-username', data.username || '');
        el('user-name').setAttribute('data-nickname', data.nickname || '');
      }
      if (el('user-id')) el('user-id').textContent = data.id ? `#${data.id}` : '';

      // ✅ ESTADÍSTICAS DEL JUEGO desde la tabla users
      if (el('user-puntos')) el('user-puntos').textContent = data.puntuacion_total || 0;
      if (el('user-jugadas')) el('user-jugadas').textContent = data.partidas_jugadas || 0;
      if (el('user-ganadas')) el('user-ganadas').textContent = data.partidas_ganadas || 0;

      // Calcular ratio de victorias
      const jugadas = data.partidas_jugadas || 0;
      const ganadas = data.partidas_ganadas || 0;
      const ratio = jugadas > 0 ? Math.round((ganadas / jugadas) * 100) : 0;
      if (el('user-ratio')) el('user-ratio').textContent = ratio + '%';

      // Información adicional (email, fechas)
      if (el('user-info')) {
        let infoHtml = `<p><strong>Email:</strong> ${data.email || ''}</p>`;
        if (data.created_at) {
          infoHtml += `<p><strong>Fecha de creación:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>`;
        }
        if (data.updated_at) {
          infoHtml += `<p><strong>Última actualización:</strong> ${new Date(data.updated_at).toLocaleDateString()}</p>`;
        }
        el('user-info').innerHTML = infoHtml;
      }

      // Avatar
      const avatarImg = el('avatar-img');
      if (avatarImg) avatarImg.src = data.avatar || 'img/isotipoOficial.png';

      // Cargar datos de ranking
      cargarRankingData();
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

    fetch('/api/upload_avatar.php', {
      method: 'POST',
      body: fd,
      credentials: 'include'
    })
      .then(r => r.json())
      .then(res => {
        if (btn) btn.textContent = prev;
        if (res.success && res.avatarUrl) {
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

  // ✅ CORREGIDO: El botón "Crear Partida" ahora verifica partidas guardadas
  on('crear-partida-btn', 'click', verificarPartidaAntesDeCrear);

  // (Botón de partidas guardadas removido)

  // Botón de cerrar sesión
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
      localStorage.clear();
      window.location.href = '/home';
    }
  });

  /* ===== FUNCIÓN PRINCIPAL: VERIFICAR PARTIDA ANTES DE CREAR ===== */
  async function verificarPartidaAntesDeCrear() {
    console.log('Verificando si hay partidas guardadas...');

    try {
      // 1. Verificar localStorage primero (más rápido)
      const partidaActual = localStorage.getItem('partidaIdActual');
      const autosave = localStorage.getItem('draftosaurus_autosave');

      console.log('LS partidaActual:', partidaActual);
      console.log('LS autosave:', autosave ? 'Sí' : 'No');

      if (partidaActual || autosave) {
        let partidaInfo = null;

        if (autosave) {
          try {
            const data = JSON.parse(autosave);
            partidaInfo = {
              id: data.partidaId || partidaActual,
              ronda_actual: data.ronda_actual || 1,
              turno_actual: data.turno_actual || 1,
              jugador1: localStorage.getItem('jugadorActual') || 'Tú',
              jugador2: data.jugador2 || localStorage.getItem('rival') || 'Invitado',
              updated_at: new Date(data.ts || Date.now()).toISOString()
            };
          } catch (e) {
            console.warn('Error parsing autosave:', e);
            partidaInfo = {
              id: partidaActual,
              jugador1: localStorage.getItem('jugadorActual') || 'Tú',
              jugador2: localStorage.getItem('rival') || 'Invitado',
              ronda_actual: 1,
              turno_actual: 1,
              updated_at: new Date().toISOString()
            };
          }
        }

        mostrarModalReanudacion(partidaInfo);
        return;
      }

      // 2. Si no hay en localStorage, consultar servidor
      const response = await fetch('/api/tablero/obtenerPartidasEnProgreso', {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' }
      });

      const data = await response.json();
      console.log('Partidas del servidor:', data);

      if (data.success && data.partidas && data.partidas.length > 0) {
        // Hay partidas en el servidor, mostrar modal de reanudación
        mostrarModalReanudacion(data.partidas[0]); // Mostrar la más reciente
      } else {
        // No hay partidas, ir directo a crear nueva
        mostrarModalCrearPartida();
      }

    } catch (error) {
      console.error('Error verificando partidas:', error);
      // En caso de error, permitir crear partida nueva
      mostrarModalCrearPartida();
    }
  }

  /* ===== MODAL DE REANUDACIÓN ===== */
  function mostrarModalReanudacion(partida) {
    console.log('Mostrando modal de reanudación para:', partida);

    const fechaGuardado = partida.updated_at || new Date().toISOString();
    const ronda = partida.ronda_actual || partida.ronda || 1;
    const turno = partida.turno_actual || partida.turno || 1;

    // ✅ MEJORADO: Mostrar el nombre real del invitado
    const nombreJugador1 = partida.jugador1 || 'Tú';
    const nombreJugador2 = partida.jugador2 || partida.name_invitado || 'Invitado';
    const esInvitadoPersonalizado = nombreJugador2 !== 'Invitado' && nombreJugador2 !== 'CPU';

    const modalHTML = `
      <div class="modal fade" id="modalReanudacion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content draftosaurus-modal">
            <div class="modal-header">
              <h5 class="modal-title">¿Reanudar partida guardada?</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
              <div class="mb-3">
                <strong>Partida #${partida.id}</strong>
              </div>
              <div class="mb-2">
                <span class="badge ${esInvitadoPersonalizado ? 'bg-info' : 'bg-secondary'}">
                  ${nombreJugador1} vs ${nombreJugador2}
                </span>
                ${esInvitadoPersonalizado ? '<br><small class="text-muted">(Jugador invitado)</small>' : ''}
              </div>
              <div class="mb-2">
                <small class="text-muted">
                  Guardada: ${new Date(fechaGuardado).toLocaleDateString()} 
                  ${new Date(fechaGuardado).toLocaleTimeString()}
                </small>
              </div>
              <div class="mb-3">
                <small>Ronda: ${ronda} | Turno: ${turno}</small>
              </div>
              <p>¿Quieres continuar esta partida con <strong>${nombreJugador2}</strong> o crear una nueva?</p>
            </div>
            <div class="modal-footer d-flex justify-content-center gap-3">
              <button class="btn btn-custom" onclick="reanudarPartidaExistente(${partida.id})">
                Continuar con ${nombreJugador2}
              </button>
              <button class="btn btn-outline-light" onclick="crearPartidaNuevaDespuesModal()">
                Crear nueva partida
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    // Eliminar modal existente si existe
    const existingModal = document.getElementById('modalReanudacion');
    if (existingModal) existingModal.remove();

    // Agregar nuevo modal
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = new bootstrap.Modal(document.getElementById('modalReanudacion'));
    modal.show();

    // Limpiar modal al cerrarse
    document.getElementById('modalReanudacion').addEventListener('hidden.bs.modal', function () {
      this.remove();
    });
  }

  /* ===== FUNCIONES GLOBALES PARA LOS BOTONES DEL MODAL ===== */
  window.reanudarPartidaExistente = function (partidaId) {
    console.log('Reanudando partida ID:', partidaId);

    // ✅ CORREGIDO: usar partidaACargar para que tablero.js lo reconozca
    localStorage.setItem('partidaACargar', partidaId.toString());

    const modal = bootstrap.Modal.getInstance(document.getElementById('modalReanudacion'));
    if (modal) modal.hide();

    window.location.href = '/tablero';
  };

  window.crearPartidaNuevaDespuesModal = function () {
    console.log('Creando nueva partida después de cerrar modal');

    // ✅ LIMPIAR todas las referencias a partidas anteriores
    localStorage.removeItem('partidaIdActual');
    localStorage.removeItem('partidaACargar');
    localStorage.removeItem('partidaId');
    localStorage.removeItem('draftosaurus_autosave');
    localStorage.removeItem('esPartidaNueva');

    const modal = bootstrap.Modal.getInstance(document.getElementById('modalReanudacion'));
    if (modal) modal.hide();

    // Pequeño delay para evitar conflictos entre modales
    setTimeout(() => {
      mostrarModalCrearPartida();
    }, 300);
  };

  /* ===== FUNCIÓN PARA MOSTRAR EL MODAL DE CREAR PARTIDA ===== */
  function mostrarModalCrearPartida() {
    const modalCrear = new bootstrap.Modal(document.getElementById('modalCrearPartida'));
    modalCrear.show();
  }

  /* ===== FUNCIONES PARA MANEJO DE PARTIDAS GUARDADAS ===== */
  function mostrarPartidasGuardadas() {
    console.log('Cargando partidas guardadas...');

    fetch('/misPartidas', {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    })
      .then(r => r.text())
      .then(text => {
        console.log('Response text:', text);
        try {
          const partidas = JSON.parse(text);
          console.log('Partidas parseadas:', partidas);

          if (partidas.success === false) {
            alert(partidas.message || 'Error al cargar partidas');
            return;
          }

          const partidasArray = Array.isArray(partidas) ? partidas : (partidas.data || []);
          console.log('Array de partidas a mostrar:', partidasArray);

          mostrarModalPartidas(partidasArray);

        } catch (e) {
          console.error('Error parsing JSON:', e);
          alert('Error procesando respuesta del servidor');
        }
      })
      .catch(err => {
        console.error('Error cargando partidas:', err);
        alert('Error de red al cargar partidas');
      });
  }

  function mostrarModalPartidas(partidas) {
    console.log('Mostrando modal con partidas:', partidas);

    const modalHTML = `
      <div class="modal fade" id="modalPartidas" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content draftosaurus-modal">
            <div class="modal-header">
              <h5 class="modal-title">Mis Partidas Guardadas</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div id="partidas-list">
                ${generarListaPartidas(partidas)}
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
          </div>
        </div>
      </div>
    `;

    const existingModal = document.getElementById('modalPartidas');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = new bootstrap.Modal(document.getElementById('modalPartidas'));
    modal.show();

    document.getElementById('modalPartidas').addEventListener('hidden.bs.modal', function () {
      this.remove();
    });
  }

  function generarListaPartidas(partidas) {
    if (!partidas || partidas.length === 0) {
      return '<p class="text-center text-muted">No tienes partidas guardadas</p>';
    }

    return partidas.map(partida => {
      const fechaGuardado = partida.fecha_guardado || partida.updated_at || new Date().toISOString();
      const ronda = partida.ronda_actual || partida.ronda || 1;
      const turno = partida.turno_actual || partida.turno || 1;
      const jugador2 = partida.jugador2 || partida.name_invitado || 'Invitado';

      return `
        <div class="card mb-3 bg-dark text-light">
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h6 class="card-title mb-1">Partida #${partida.id}</h6>
                <p class="card-text mb-1">
                  <small class="text-muted">
                    ${partida.jugador1} vs ${jugador2}
                  </small>
                </p>
                <p class="card-text mb-1">
                  <small class="text-muted">
                    Guardada: ${new Date(fechaGuardado).toLocaleDateString()} 
                    ${new Date(fechaGuardado).toLocaleTimeString()}
                  </small>
                </p>
                <p class="card-text mb-0">
                  <small>Ronda: ${ronda} | Turno: ${turno}</small>
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
      `;
    }).join('');
  }

  /* ===== FUNCIONES GLOBALES PARA MANEJO DE PARTIDAS ===== */
  window.cargarPartida = function (partidaId) {
    if (confirm('¿Cargar esta partida? Se perderá cualquier progreso no guardado.')) {
      console.log('Cargando partida ID:', partidaId);

      // ✅ CORREGIDO: limpiar localStorage y usar partidaACargar
      localStorage.removeItem('partidaIdActual');
      localStorage.removeItem('draftosaurus_autosave');
      localStorage.setItem('partidaACargar', partidaId.toString());

      const modal = bootstrap.Modal.getInstance(document.getElementById('modalPartidas'));
      if (modal) modal.hide();

      window.location.href = '/tablero';
    }
  };

  window.eliminarPartida = function (partidaId) {
    if (confirm('¿Eliminar esta partida permanentemente?')) {
      console.log('Eliminando partida ID:', partidaId);

      fetch(`/api/tablero/eliminarPartida`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ partidaId: partidaId })
      })
        .then(r => r.json())
        .then(res => {
          console.log('Respuesta eliminar:', res);
          if (res.success) {
            alert('Partida eliminada correctamente');

            // ✅ LIMPIAR localStorage si se elimina la partida actual
            if (localStorage.getItem('partidaIdActual') === partidaId.toString()) {
              localStorage.removeItem('partidaIdActual');
              localStorage.removeItem('draftosaurus_autosave');
            }

            mostrarPartidasGuardadas(); // Recargar lista
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

  /* ===== FUNCIÓN CREAR PARTIDA ===== */
  window.crearPartida = async function (esInvitado, nombreJugador2) {
    try {
      const userResponse = await fetch('/perfil/me', { credentials: 'include' });
      const userData = await userResponse.json();

      if (!userData.success) {
        alert('Error al obtener datos del usuario');
        return;
      }

      const nombreJugador1 = userData.username;

      const res = await fetch('/api/crearPartida', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          esInvitado,
          nombre_jugador2: nombreJugador2 || 'Invitado',
          total_rondas: 4
        }),
        credentials: 'include'
      });

      const data = await res.json();

      if (data.success) {
        // Cerrar modales si están abiertos
        const modalCrear = bootstrap.Modal.getInstance(el('modalCrearPartida'));
        const modalInvitado = bootstrap.Modal.getInstance(el('modalInvitado'));
        if (modalCrear) modalCrear.hide();
        if (modalInvitado) modalInvitado.hide();

        // ✅ GUARDAR información necesaria (partidaACargar para carga inmediata)
        const idPartida = data.id ?? data.partidaId;
        if (!idPartida) {
          alert('El servidor no devolvió un ID de partida');
          return;
        }
        localStorage.setItem('partidaACargar', idPartida.toString());
        localStorage.setItem('jugadorActual', nombreJugador1);
        localStorage.setItem('rival', nombreJugador2 || 'Invitado');
        localStorage.setItem('esPartidaNueva', 'true');

        console.log('Partida creada:', {
          id: data.id,
          jugador1: nombreJugador1,
          jugador2: nombreJugador2 || 'Invitado'
        });

        window.location.href = '/tablero';
      } else {
        alert('Error al crear partida: ' + (data.message || 'Desconocido'));
      }
    } catch (err) {
      console.error('Error creando partida:', err);
      alert('Error de red al crear partida');
    }
  };

  /* ===== EVENTOS DE MODALES EXISTENTES ===== */
  const btnInvitado = document.getElementById('btnInvitado');
  const btnUsers = document.getElementById('btnUsers');
  const formInvitado = document.getElementById('formInvitado');
  const formLoginJugador2 = document.getElementById('formLoginJugador2');

  if (btnInvitado) {
    btnInvitado.addEventListener('click', () => {
      const modal = new bootstrap.Modal(document.getElementById('modalInvitado'));
      modal.show();
    });
  }

  if (btnUsers) {
    btnUsers.addEventListener('click', () => {
      const loginBox = document.getElementById('loginJugador2');
      if (loginBox) loginBox.classList.remove('d-none');
    });
  }

  if (formInvitado) {
    formInvitado.addEventListener('submit', (e) => {
      e.preventDefault();
      const nameInput = document.getElementById('guestName');
      const nombre = nameInput ? (nameInput.value || '').trim() || 'Invitado' : 'Invitado';
      crearPartida(true, nombre);
    });
  }

  if (formLoginJugador2) {
    formLoginJugador2.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('emailJugador2')?.value.trim();
      const password = document.getElementById('passwordJugador2')?.value;

      if (!email || !password) {
        alert('Completa email y contraseña');
        return;
      }

      try {
        const res = await fetch('/verify-user', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ identifier: email, password }),
          credentials: 'include'
        });

        const data = await res.json();

        if (data.success && data.user) {
          // Usar display_name (nickname si existe, sino username)
          const displayName = data.user.display_name || data.user.username;
          crearPartida(false, displayName);
        } else {
          alert('Login fallido: ' + (data.message || 'Credenciales incorrectas'));
        }
      } catch (err) {
        console.error('Error login jugador 2:', err);
        alert('Error de red al iniciar sesión');
      }
    });
  }

  /* ===== EDICIÓN DE NICKNAME CON DOBLE CLICK ===== */

  // Doble click para editar nombre principal
  on('user-name', 'dblclick', function() {
    const displaySection = el('user-name-display');
    const editSection = el('user-name-edit');
    const userName = el('user-name');

    if (displaySection && editSection && userName) {
      displaySection.classList.add('d-none');
      editSection.classList.remove('d-none');

      const input = el('nickname-input');
      if (input) {
        // Mostrar el nickname actual (si existe) para editar
        const currentNickname = userName.getAttribute('data-nickname') || '';
        input.value = currentNickname;
        input.focus();
        input.select(); // Seleccionar todo el texto
      }
    }
  });

  // Botón cancelar edición
  on('cancel-nickname-btn', 'click', function() {
    const displaySection = el('user-name-display');
    const editSection = el('user-name-edit');

    if (displaySection && editSection) {
      displaySection.classList.remove('d-none');
      editSection.classList.add('d-none');
    }
  });

  // Botón guardar nickname
  on('save-nickname-btn', 'click', async function() {
    const input = el('nickname-input');
    const displaySection = el('user-name-display');
    const editSection = el('user-name-edit');
    const userName = el('user-name');
    const saveBtn = el('save-nickname-btn');

    if (!input || !displaySection || !editSection || !userName || !saveBtn) {
      return;
    }

    const newNickname = input.value.trim();
    const usernameData = userName.getAttribute('data-username') || '';

    // Validación del lado del cliente
    if (newNickname.length > 50) {
      alert('El nickname no puede tener más de 50 caracteres');
      return;
    }

    if (newNickname !== '' && newNickname.length < 2) {
      alert('El nickname debe tener al menos 2 caracteres');
      return;
    }

    // Validar que el nickname no sea igual al username
    if (newNickname !== '' && newNickname === usernameData) {
      alert('El nickname no puede ser igual a tu nombre de usuario');
      return;
    }

    // Deshabilitar botón mientras se procesa
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Guardando...';
    saveBtn.disabled = true;

    try {
      const response = await fetch('/perfil/nickname', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({ nickname: newNickname })
      });

      const data = await response.json();

      if (data.success) {
        // Actualizar los atributos de datos
        const usernameData = userName.getAttribute('data-username') || '';
        userName.setAttribute('data-nickname', newNickname);

        // Mostrar nickname si existe, si no mostrar username
        const displayName = newNickname || usernameData || 'Usuario';
        userName.textContent = displayName;

        // Ocultar sección de edición y mostrar display
        displaySection.classList.remove('d-none');
        editSection.classList.add('d-none');

        alert('Nickname actualizado correctamente');
      } else {
        alert(data.message || 'Error al actualizar nickname');
      }
    } catch (error) {
      console.error('Error al actualizar nickname:', error);
      alert('Error de conexión al actualizar nickname');
    } finally {
      // Restaurar botón
      saveBtn.textContent = originalText;
      saveBtn.disabled = false;
    }
  });

  // Permitir guardar con Enter
  on('nickname-input', 'keypress', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const saveBtn = el('save-nickname-btn');
      if (saveBtn) saveBtn.click();
    }
  });

  /* ===== FUNCIONES DE RANKING ===== */

  // Cargar datos de ranking del usuario
  async function cargarRankingData() {
    try {
      // Cargar posición del usuario
      const userRankingResponse = await fetch('/perfil/user-ranking', { credentials: 'include' });
      const userRankingData = await userRankingResponse.json();

      if (userRankingData.success) {
        // Actualizar posición del usuario
        if (el('user-position')) el('user-position').textContent = userRankingData.position;
        if (el('total-players')) el('total-players').textContent = userRankingData.total_players;

        // Calcular puntos necesarios para subir
        if (userRankingData.next_player_points) {
          const currentPoints = parseInt(el('user-puntos')?.textContent || 0);
          const pointsNeeded = userRankingData.next_player_points - currentPoints + 1;
          if (el('points-needed')) el('points-needed').textContent = pointsNeeded;

          // Calcular progreso (asumiendo que necesita X puntos para subir)
          const progress = Math.min((currentPoints / userRankingData.next_player_points) * 100, 95);
          const progressBar = el('rank-progress');
          if (progressBar) progressBar.style.width = progress + '%';
        } else {
          // Es el #1, no hay siguiente
          if (el('points-needed')) el('points-needed').textContent = '¡Eres el #1!';
          const progressBar = el('rank-progress');
          if (progressBar) progressBar.style.width = '100%';
        }
      }

      // Cargar top 3 jugadores
      const rankingResponse = await fetch('/perfil/ranking?limit=3', { credentials: 'include' });
      const rankingData = await rankingResponse.json();

      if (rankingData.success && rankingData.ranking) {
        mostrarTop3(rankingData.ranking);
      }

    } catch (error) {
      console.error('Error cargando ranking:', error);
    }
  }

  // Mostrar top 3 jugadores
  function mostrarTop3(topPlayers) {
    const container = el('top-players-list');
    if (!container) return;

    const medals = ['🥇', '🥈', '🥉'];
    const html = topPlayers.map((player, index) => `
      <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-dark rounded">
        <div class="d-flex align-items-center">
          <span class="me-2">${medals[index] || '#' + player.position}</span>
          <img src="${player.avatar || 'img/isotipoOficial.png'}" alt="Avatar"
               class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
          <span class="text-light">${player.display_name}</span>
        </div>
        <div class="text-end">
          <small class="text-warning fw-bold">${player.puntuacion_total} pts</small>
          <br>
          <small class="text-muted">${player.partidas_ganadas}/${player.partidas_jugadas}</small>
        </div>
      </div>
    `).join('');

    container.innerHTML = html;
  }

  /* ===== FUNCIÓN DE VERIFICACIÓN AL CARGAR LA PÁGINA ===== */

  // ✅ VERIFICAR automáticamente si hay partidas al cargar el perfil
  // Esto es opcional - solo se ejecuta si el usuario no ha interactuado aún
  setTimeout(() => {
    const partidaActual = localStorage.getItem('partidaIdActual');
    const autosave = localStorage.getItem('draftosaurus_autosave');

    if ((partidaActual || autosave) && !document.getElementById('modalReanudacion')) {
      console.log('Hay partida guardada, pero no se ha mostrado modal aún');
      // Podrías mostrar una notificación sutil aquí en lugar de modal automático
      // verificarPartidaAntesDeCrear();
    }
  }, 2000);

});