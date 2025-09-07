// admin.js
document.addEventListener("DOMContentLoaded", () => {
    console.log("Admin panel cargado");

    // Logout
    document.getElementById("logoutBtn")?.addEventListener("click", async () => {
        try {
            const res = await fetch('/logout', { method: 'POST', credentials: 'include' });
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
    loadUsers();
    loadMessages();

    // Listeners de modales - CAMBIO IMPORTANTE: usar eventos de formulario
    document.getElementById("saveNewUser")?.addEventListener("click", (e) => {
        e.preventDefault();
        saveNewUser();
    });

    document.getElementById("saveEditUser")?.addEventListener("click", (e) => {
        e.preventDefault();
        saveEditUser();
    });

    // NUEVO: Limpiar formularios cuando se abren los modales
    const newUserModal = document.getElementById('newUserModal');
    const editUserModal = document.getElementById('editUserModal');

    if (newUserModal) {
        newUserModal.addEventListener('shown.bs.modal', () => {
            console.log("Modal de nuevo usuario abierto");
            document.getElementById("newUserForm").reset();
        });

        newUserModal.addEventListener('hidden.bs.modal', () => {
            console.log("Modal de nuevo usuario cerrado");
            // Asegurar limpieza completa
            cleanupModal();
        });
    }

    if (editUserModal) {
        editUserModal.addEventListener('hidden.bs.modal', () => {
            console.log("Modal de editar usuario cerrado");
            cleanupModal();
        });
    }
});

// Función para limpiar cualquier residuo de modal
function cleanupModal() {
    // Remover todos los backdrops que puedan haber quedado
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());

    // Restaurar el body
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');

    console.log("Modal cleanup completado");
}

/* ---------- USUARIOS ---------- */
async function loadUsers() {
    try {
        console.log("Cargando usuarios...");
        const res = await fetch("/api/admin/users", {
            credentials: 'include'
        });

        console.log("Response status:", res.status);

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const users = await res.json();
        console.log("Usuarios recibidos:", users);

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

        console.log(`${users.length} usuarios cargados`);

    } catch (error) {
        console.error('Error cargando usuarios:', error);
        alert('Error al cargar usuarios: ' + error.message);
    }
}

async function saveNewUser() {
    console.log("Iniciando saveNewUser...");

    // Validar campos
    const name = document.getElementById("newName").value.trim();
    const email = document.getElementById("newEmail").value.trim();
    const password = document.getElementById("newPassword").value;

    if (!name || !email || !password) {
        alert('Por favor completa todos los campos');
        return;
    }

    // Mostrar indicador de carga
    const saveBtn = document.getElementById("saveNewUser");
    const originalText = saveBtn.textContent;
    saveBtn.textContent = "Guardando...";
    saveBtn.disabled = true;

    const data = { name, email, password };
    console.log("Datos a enviar:", data);

    try {
        const res = await fetch("/api/admin/users", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: 'include',
            body: JSON.stringify(data)
        });

        console.log("Respuesta del servidor:", res.status);
        const r = await res.json();
        console.log("Datos de respuesta:", r);

        if (r.success) {
            console.log("Usuario creado exitosamente");

            // 1. Limpiar el formulario
            document.getElementById("newUserForm").reset();

            // 2. Cerrar modal usando Bootstrap estándar
            const modalElement = document.getElementById("newUserModal");
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }

            // 3. Recargar la tabla después de un breve delay
            setTimeout(async () => {
                await loadUsers();
                console.log("Tabla recargada - proceso completado");
            }, 300);

        } else {
            alert(r.message || "Error al crear usuario");
        }
    } catch (error) {
        console.error('Error creando usuario:', error);
        alert("Error al crear usuario: " + error.message);
    } finally {
        // Restaurar el botón
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    }
}

async function openEdit(id) {
    try {
        console.log("Editando usuario:", id);
        const res = await fetch(`/api/admin/users/${id}`, {
            credentials: 'include'
        });

        const u = await res.json();
        if (u.error) {
            alert(u.error);
            return;
        }

        // Llenar el formulario
        document.getElementById("editId").value = id;
        document.getElementById("editName").value = u.name || '';
        document.getElementById("editEmail").value = u.email || '';
        document.getElementById("editPassword").value = '';

        // Abrir modal usando Bootstrap estándar
        const modalElement = document.getElementById("editUserModal");
        const modal = new bootstrap.Modal(modalElement);
        modal.show();

    } catch (error) {
        console.error('Error cargando usuario para editar:', error);
        alert("Error al cargar usuario: " + error.message);
    }
}

async function saveEditUser() {
    console.log("Iniciando saveEditUser...");

    // Mostrar indicador de carga
    const saveBtn = document.getElementById("saveEditUser");
    const originalText = saveBtn.textContent;
    saveBtn.textContent = "Guardando...";
    saveBtn.disabled = true;

    const id = document.getElementById("editId").value;
    const data = {
        username: document.getElementById("editName").value.trim(),
        email: document.getElementById("editEmail").value.trim()
    };
    const pwd = document.getElementById("editPassword").value;
    if (pwd) data.password = pwd;

    console.log("Actualizando usuario:", id, data);

    try {
        const res = await fetch(`/api/admin/users/${id}`, {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            credentials: 'include',
            body: JSON.stringify(data)
        });

        const r = await res.json();
        console.log("Respuesta actualizar usuario:", r);

        if (r.success) {
            // Cerrar modal usando Bootstrap estándar
            const modalElement = document.getElementById("editUserModal");
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }

            // Recargar tabla después de un breve delay
            setTimeout(async () => {
                await loadUsers();
            }, 300);

        } else {
            alert(r.message || "Error al actualizar usuario");
        }
    } catch (error) {
        console.error('Error actualizando usuario:', error);
        alert("Error al actualizar usuario: " + error.message);
    } finally {
        // Restaurar el botón
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    }
}

async function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus === 'activo' ? 'suspendido' : 'activo';

    console.log("Cambiando estado usuario:", id, "de", currentStatus, "a", newStatus);

    try {
        const res = await fetch(`/api/admin/users/${id}/status`, {
            method: "PATCH",
            headers: { "Content-Type": "application/json" },
            credentials: 'include',
            body: JSON.stringify({ status: newStatus })
        });

        const r = await res.json();
        console.log("Respuesta cambio estado:", r);

        if (r.success) {
            await loadUsers();
        } else {
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
        console.log("Cargando mensajes...");
        const res = await fetch("/api/admin/messages", {
            credentials: 'include'
        });

        console.log("Response status mensajes:", res.status);

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const msgs = await res.json();
        console.log("Mensajes recibidos:", msgs);

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
        <td>${m.mensaje || 'N/A'}</td>
        <td>${m.fecha_envio || 'N/A'}</td>`;
            tbody.appendChild(tr);
        });

        console.log(`${msgs.length} mensajes cargados`);

    } catch (error) {
        console.error('Error cargando mensajes:', error);
        alert('Error al cargar mensajes: ' + error.message);
    }
}