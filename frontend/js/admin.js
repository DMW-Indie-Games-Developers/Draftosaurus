// admin.js - Versión con debugging y correcciones

// Variable global para almacenar el token CSRF
let csrfToken = null;

document.addEventListener("DOMContentLoaded", async () => {
    console.log("=== Admin panel cargado ===");

    // SEGURIDAD: Verificar que el usuario sea admin ANTES de hacer nada
    await verifyAdminAccess();

    // SEGURIDAD: Obtener token CSRF
    await getCsrfToken();

    // Logout
    document.getElementById("logoutBtn")?.addEventListener("click", async () => {
        try {
            const res = await fetch(apiUrl('/logout'), { method: 'POST', credentials: 'include' });
            const data = await res.json();
            if (data.success) {
                localStorage.clear();
                window.location.href = "/login";
            } else {
                alert('Error al cerrar sesión');
            }
        } catch (error) {
            console.error('Error:', error);
            localStorage.clear();
            window.location.href = "/login";
        }
    });

    // Cargar datos iniciales
    loadSessionStats();
    loadOnlineUsers();
    loadRecentSessions();
    loadUsers();
    loadMessages();

    // Auto-refresh de estadísticas cada 30 segundos
    setInterval(() => {
        loadSessionStats();
        loadOnlineUsers();
    }, 30000);

    // Listeners de botones - PREVENIR DOBLE CLICK
    const saveNewBtn = document.getElementById("saveNewUser");
    const saveEditBtn = document.getElementById("saveEditUser");

    if (saveNewBtn) {
        saveNewBtn.addEventListener("click", async (e) => {
            e.preventDefault();
            if (saveNewBtn.disabled) {
                console.log("Botón ya está procesando, ignorando click");
                return;
            }
            await saveNewUser();
        });
    }

    if (saveEditBtn) {
        saveEditBtn.addEventListener("click", async (e) => {
            e.preventDefault();
            if (saveEditBtn.disabled) {
                console.log("Botón ya está procesando, ignorando click");
                return;
            }
            await saveEditUser();
        });
    }

    // Limpiar formularios cuando se abren los modales
    const newUserModal = document.getElementById('newUserModal');
    const editUserModal = document.getElementById('editUserModal');

    if (newUserModal) {
        newUserModal.addEventListener('shown.bs.modal', () => {
            console.log("=== Modal nuevo usuario abierto ===");
            const form = document.getElementById("newUserForm");
            if (form) {
                form.reset();
                console.log("Formulario reseteado");
            }
            
            // Limpiar valores manualmente por seguridad
            document.getElementById("newName").value = "";
            document.getElementById("newEmail").value = "";
            document.getElementById("newPassword").value = "";
        });

        newUserModal.addEventListener('hidden.bs.modal', () => {
            console.log("=== Modal nuevo usuario cerrado ===");
            cleanupModal();
        });
    }

    if (editUserModal) {
        editUserModal.addEventListener('hidden.bs.modal', () => {
            console.log("=== Modal editar usuario cerrado ===");
            cleanupModal();
        });
    }
});

// Función para limpiar cualquier residuo de modal
function cleanupModal() {
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());

    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');

    console.log("Modal cleanup completado");
}

/* ---------- USUARIOS ---------- */
async function loadUsers() {
    try {
        console.log("=== Cargando usuarios ===");
        const res = await fetch(apiUrl('/api/admin/users'), {
            credentials: 'include'
        });

        console.log("Response status:", res.status);

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const users = await res.json();
        console.log("Usuarios recibidos:", users.length, "usuarios");

        const tbody = document.getElementById("usersTable");
        if (!tbody) {
            console.error("Elemento usersTable no encontrado");
            return;
        }

        tbody.innerHTML = "";

        if (!Array.isArray(users)) {
            console.error("Los datos recibidos no son un array:", users);
            return;
        }

        users.forEach(u => {
            const tr = document.createElement("tr");
            tr.innerHTML = `
        <td>${u.id}</td>
        <td>${u.name || 'N/A'}</td>
        <td>${u.email || 'N/A'}</td>
        <td><span class="badge ${u.status === 'activo' ? 'bg-success' : 'bg-danger'}">${u.status || 'activo'}</span></td>
        <td>
          <button class="btn btn-sm btn-primary me-1" onclick="openEdit(${u.id})">Editar</button>
          <button class="btn btn-sm ${u.status === 'activo' ? 'btn-warning' : 'btn-success'}" onclick="toggleStatus(${u.id}, '${u.status || 'activo'}')">
            ${u.status === 'activo' ? 'Suspender' : 'Reactivar'}
          </button>
        </td>`;
            tbody.appendChild(tr);
        });

        console.log(`${users.length} usuarios cargados exitosamente`);

    } catch (error) {
        console.error('Error cargando usuarios:', error);
        alert('Error al cargar usuarios: ' + error.message);
    }
}

async function saveNewUser() {
    console.log("=== INICIO saveNewUser ===");

    // Obtener y validar campos
    const nameField = document.getElementById("newName");
    const emailField = document.getElementById("newEmail");
    const passwordField = document.getElementById("newPassword");
    
    if (!nameField || !emailField || !passwordField) {
        console.error("Campos del formulario no encontrados");
        alert("Error: No se pueden encontrar los campos del formulario");
        return;
    }

    const name = nameField.value.trim();
    const email = emailField.value.trim();
    const password = passwordField.value;

    console.log("=== DATOS DEL FORMULARIO ===");
    console.log("Name:", name);
    console.log("Email:", email);
    console.log("Password length:", password.length);

    if (!name || !email || !password) {
        console.warn("Campos vacíos detectados");
        alert('Por favor completa todos los campos');
        return;
    }

    // Bloquear botón INMEDIATAMENTE
    const saveBtn = document.getElementById("saveNewUser");
    if (!saveBtn) {
        console.error("Botón guardar no encontrado");
        return;
    }

    const originalText = saveBtn.textContent;
    saveBtn.textContent = "Guardando...";
    saveBtn.disabled = true;

    const data = {
        name,
        email,
        password,
        csrf_token: csrfToken // Incluir token CSRF
    };
    console.log("=== DATOS A ENVIAR ===");
    console.log(JSON.stringify({ name, email, password: "[HIDDEN]", csrf_token: csrfToken ? "[PRESENT]" : "[MISSING]" }));

    try {
        console.log("Enviando petición POST...");

        const res = await fetch(apiUrl('/api/admin/users'), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken // También enviar en header
            },
            credentials: 'include',
            body: JSON.stringify(data)
        });

        console.log("=== RESPONSE ===");
        console.log("Status:", res.status);
        console.log("Status Text:", res.statusText);
        console.log("Headers:", [...res.headers.entries()]);

        // Leer respuesta como texto primero para debugging
        const responseText = await res.text();
        console.log("Response Text:", responseText);

        let responseData;
        try {
            responseData = JSON.parse(responseText);
        } catch (parseError) {
            console.error("Error parsing JSON:", parseError);
            console.error("Raw response:", responseText);
            throw new Error("Respuesta del servidor no es JSON válido");
        }

        console.log("=== RESPONSE DATA ===");
        console.log(JSON.stringify(responseData, null, 2));

        if (responseData.success) {
            console.log("✓ Usuario creado exitosamente");
            
            // Limpiar formulario
            nameField.value = "";
            emailField.value = "";
            passwordField.value = "";
            console.log("✓ Formulario limpiado");

            // Cerrar modal
            const modalElement = document.getElementById("newUserModal");
            if (modalElement) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                    console.log("✓ Modal cerrado");
                }
            }

            // Recargar tabla después de un delay
            setTimeout(async () => {
                console.log("Recargando tabla de usuarios...");
                await loadUsers();
                console.log("✓ Tabla recargada");
            }, 500);

            // Mostrar mensaje de éxito
            if (responseData.message) {
                alert(responseData.message);
            }

        } else {
            console.error("✗ Error del servidor:", responseData.message);
            alert(responseData.message || "Error al crear usuario");
        }

    } catch (error) {
        console.error("=== ERROR COMPLETO ===");
        console.error("Message:", error.message);
        console.error("Stack:", error.stack);
        alert("Error al crear usuario: " + error.message);
    } finally {
        // Restaurar botón SIEMPRE
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
        console.log("✓ Botón restaurado");
        console.log("=== FIN saveNewUser ===");
    }
}

async function openEdit(id) {
    try {
        console.log("=== EDITANDO USUARIO ===", id);
        const res = await fetch(apiUrl(`/api/admin/users/${id}`), {
            credentials: 'include'
        });

        const u = await res.json();
        console.log("Usuario para editar:", u);

        if (u.error) {
            alert(u.error);
            return;
        }

        // Llenar el formulario
        document.getElementById("editId").value = id;
        document.getElementById("editName").value = u.name || '';
        document.getElementById("editEmail").value = u.email || '';
        document.getElementById("editPassword").value = '';

        // Abrir modal
        const modalElement = document.getElementById("editUserModal");
        const modal = new bootstrap.Modal(modalElement);
        modal.show();

    } catch (error) {
        console.error('Error cargando usuario para editar:', error);
        alert("Error al cargar usuario: " + error.message);
    }
}

async function saveEditUser() {
    console.log("=== INICIO saveEditUser ===");

    // Bloquear botón INMEDIATAMENTE
    const saveBtn = document.getElementById("saveEditUser");
    const originalText = saveBtn.textContent;
    saveBtn.textContent = "Guardando...";
    saveBtn.disabled = true;

    const id = document.getElementById("editId").value;
    
    // CORRECCIÓN IMPORTANTE: usar 'name' no 'username'
    const data = {
        name: document.getElementById("editName").value.trim(),  // ← CORREGIDO
        email: document.getElementById("editEmail").value.trim(),
        csrf_token: csrfToken // Incluir token CSRF
    };

    const pwd = document.getElementById("editPassword").value;
    if (pwd) {
        data.password = pwd;
    }

    console.log("=== DATOS ACTUALIZACIÓN ===");
    console.log("ID:", id);
    console.log("Data:", JSON.stringify({...data, password: data.password ? "[HIDDEN]" : undefined}));

    try {
        const res = await fetch(apiUrl(`/api/admin/users/${id}`), {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken // También enviar en header
            },
            credentials: 'include',
            body: JSON.stringify(data)
        });

        console.log("Response status:", res.status);
        
        const responseText = await res.text();
        console.log("Response text:", responseText);
        
        const r = JSON.parse(responseText);
        console.log("Response data:", r);

        if (r.success) {
            console.log("✓ Usuario actualizado exitosamente");
            
            // Cerrar modal
            const modalElement = document.getElementById("editUserModal");
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }

            // Recargar tabla
            setTimeout(async () => {
                await loadUsers();
            }, 500);

            if (r.message) {
                alert(r.message);
            }

        } else {
            console.error("✗ Error al actualizar:", r.message);
            alert(r.message || "Error al actualizar usuario");
        }
    } catch (error) {
        console.error('=== ERROR EN saveEditUser ===');
        console.error(error);
        alert("Error al actualizar usuario: " + error.message);
    } finally {
        // Restaurar botón
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
        console.log("=== FIN saveEditUser ===");
    }
}

async function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus === 'activo' ? 'suspendido' : 'activo';

    console.log("=== CAMBIO DE ESTADO ===");
    console.log("ID:", id, "De:", currentStatus, "A:", newStatus);

    try {
        const res = await fetch(apiUrl(`/api/admin/users/${id}/status`), {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken // También enviar en header
            },
            credentials: 'include',
            body: JSON.stringify({
                status: newStatus,
                csrf_token: csrfToken
            })
        });

        const r = await res.json();
        console.log("Response cambio estado:", r);

        if (r.success) {
            console.log("✓ Estado cambiado exitosamente");
            await loadUsers();
        } else {
            console.error("✗ Error cambiando estado:", r.message);
            alert(r.message || "Error al cambiar estado");
        }
    } catch (error) {
        console.error('Error cambiando estado:', error);
        alert("Error al cambiar estado: " + error.message);
    }
}

/* ---------- MENSAJES ---------- */
async function loadMessages() {
    try {
        console.log("=== Cargando mensajes ===");
        const res = await fetch(apiUrl('/api/admin/messages'), {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const msgs = await res.json();
        console.log("Mensajes recibidos:", msgs.length, "mensajes");

        const tbody = document.getElementById("messagesTable");
        if (!tbody) {
            console.error("Elemento messagesTable no encontrado");
            return;
        }

        tbody.innerHTML = "";

        if (!Array.isArray(msgs)) {
            console.error("Los datos de mensajes recibidos no son un array:", msgs);
            return;
        }

        msgs.forEach(m => {
            const tr = document.createElement("tr");
        tr.innerHTML = `
        <td>${m.id}</td>
        <td>${m.nombre || 'N/A'}</td>
        <td>${m.email || 'N/A'}</td>
        <td>${m.asunto || 'N/A'}</td>
        <td>${(m.mensaje || 'N/A').substring(0, 50)}${m.mensaje && m.mensaje.length > 50 ? '...' : ''}</td>
        <td>${m.fecha_envio || 'N/A'}</td>`;

        tr.style.cursor = 'pointer';
        tr.addEventListener('click', () => openMessageModal(m));
            tbody.appendChild(tr);
        });

        console.log(`${msgs.length} mensajes cargados exitosamente`);

    } catch (error) {
        console.error('Error cargando mensajes:', error);
        alert('Error al cargar mensajes: ' + error.message);
    }
    // Abrir modal con detalles del mensaje
function openMessageModal(mensaje) {
  document.getElementById('msgNombre').textContent = mensaje.nombre || 'N/A';
  document.getElementById('msgEmail').textContent = mensaje.email || 'N/A';
  document.getElementById('msgAsunto').textContent = mensaje.asunto || 'N/A';
  document.getElementById('msgFecha').textContent = mensaje.fecha_envio || 'N/A';
  document.getElementById('msgMensaje').textContent = mensaje.mensaje || 'N/A';

  const modal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
  modal.show();
}
}

/* ---------- SEGURIDAD ---------- */

/**
 * Obtiene el token CSRF del servidor
 */
async function getCsrfToken() {
    console.log("=== Obteniendo token CSRF ===");

    try {
        const res = await fetch(apiUrl('/csrf-token'), {
            credentials: 'include'
        });

        if (!res.ok) {
            console.error("Error al obtener token CSRF:", res.status);
            return;
        }

        const data = await res.json();
        csrfToken = data.csrf_token;
        console.log("✓ Token CSRF obtenido correctamente");

    } catch (error) {
        console.error("Error al obtener token CSRF:", error);
    }
}

/**
 * Verifica que el usuario actual tenga permisos de administrador
 * Si no es admin, redirige inmediatamente
 */
async function verifyAdminAccess() {
    console.log("=== Verificando permisos de administrador ===");

    try {
        const res = await fetch(apiUrl('/perfil/me'), {
            credentials: 'include'
        });

        if (!res.ok) {
            console.error("Error al verificar usuario:", res.status);
            console.log("No hay sesión activa, redirigiendo a login...");
            window.location.href = '/login';
            return;
        }

        const data = await res.json();
        console.log("Usuario actual:", data);

        // Verificar si el usuario tiene rol de admin
        // IMPORTANTE: Usamos el rol del servidor, NO del localStorage (que puede ser manipulado)
        const userRol = data.rol;

        console.log("Rol desde servidor:", userRol);

        if (userRol !== 'admin') {
            console.error("⚠️ ACCESO DENEGADO: El usuario no es administrador");
            console.log("Redirigiendo a perfil de usuario...");

            alert('Acceso denegado: No tienes permisos de administrador');

            // Redirigir al perfil normal
            window.location.href = '/perfil';
            return;
        }

        console.log("✓ Acceso de administrador verificado correctamente");

    } catch (error) {
        console.error("Error al verificar permisos:", error);
        alert('Error al verificar permisos. Redirigiendo al login...');
        window.location.href = '/login';
    }
}

/* ---------- ESTADÍSTICAS Y SESIONES ---------- */

/**
 * Carga las estadísticas de sesiones
 */
async function loadSessionStats() {
    try {
        console.log("=== Cargando estadísticas de sesiones ===");

        const res = await fetch(apiUrl('/api/admin/session-stats'), {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const data = await res.json();
        console.log("Estadísticas recibidas:", data);

        if (data.success && data.stats) {
            const stats = data.stats;

            // Actualizar dashboard
            document.getElementById('statOnlineUsers').textContent = stats.online_now || 0;
            document.getElementById('statSessionsToday').textContent = stats.sessions_today || 0;
            document.getElementById('statUniqueUsers').textContent = stats.unique_users_today || 0;
            document.getElementById('statAvgDuration').textContent = stats.avg_session_duration || '0';
        }

    } catch (error) {
        console.error('Error cargando estadísticas de sesiones:', error);
    }
}

/**
 * Carga los usuarios actualmente online
 */
async function loadOnlineUsers() {
    try {
        console.log("=== Cargando usuarios online ===");

        const res = await fetch(apiUrl('/api/admin/online-users'), {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const data = await res.json();
        console.log("Usuarios online recibidos:", data);

        const tbody = document.getElementById('onlineUsersTable');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (data.success && data.online_users && data.online_users.length > 0) {
            data.online_users.forEach(user => {
                const tr = document.createElement('tr');

                const loginTime = new Date(user.login_time).toLocaleString('es-ES');
                const lastActivity = new Date(user.last_activity).toLocaleString('es-ES');

                tr.innerHTML = `
                    <td><strong>${user.username}</strong></td>
                    <td>${user.email}</td>
                    <td><span class="badge ${user.rol === 'admin' ? 'bg-danger' : 'bg-info'}">${user.rol}</span></td>
                    <td><code>${user.ip_address}</code></td>
                    <td>${loginTime}</td>
                    <td>${lastActivity}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay usuarios online actualmente</td></tr>';
        }

    } catch (error) {
        console.error('Error cargando usuarios online:', error);
        const tbody = document.getElementById('onlineUsersTable');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar usuarios online</td></tr>';
        }
    }
}

/**
 * Carga el historial de sesiones recientes
 */
async function loadRecentSessions() {
    try {
        console.log("=== Cargando sesiones recientes ===");

        const res = await fetch(apiUrl('/api/admin/recent-sessions?limit=50'), {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const data = await res.json();
        console.log("Sesiones recientes recibidas:", data);

        const tbody = document.getElementById('recentSessionsTable');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (data.success && data.sessions && data.sessions.length > 0) {
            data.sessions.forEach(session => {
                const tr = document.createElement('tr');

                const loginTime = new Date(session.login_time).toLocaleString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const logoutTime = session.logout_time
                    ? new Date(session.logout_time).toLocaleString('es-ES', {
                        day: '2-digit',
                        month: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    })
                    : '-';

                const duration = session.duration_minutes
                    ? `${session.duration_minutes} min`
                    : '-';

                const statusBadge = session.is_active == 1
                    ? '<span class="badge bg-success">Activa</span>'
                    : '<span class="badge bg-secondary">Cerrada</span>';

                tr.innerHTML = `
                    <td>${session.username}</td>
                    <td><small><code>${session.ip_address}</code></small></td>
                    <td><small>${loginTime}</small></td>
                    <td><small>${logoutTime}</small></td>
                    <td>${duration}</td>
                    <td>${statusBadge}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay sesiones recientes</td></tr>';
        }

    } catch (error) {
        console.error('Error cargando sesiones recientes:', error);
        const tbody = document.getElementById('recentSessionsTable');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar sesiones</td></tr>';
        }
    }
}
