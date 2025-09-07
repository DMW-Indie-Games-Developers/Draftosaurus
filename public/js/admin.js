// admin.js
document.addEventListener("DOMContentLoaded", () => {
    // Corregir esta línea - cambiar de logout.php a usar fetch
    document.getElementById("logoutBtn")?.addEventListener("click", async () => {
        try {
            const res = await fetch('/logout', { 
                method: 'POST', 
                credentials: 'include' 
            });
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
    
    loadUsers();
    loadMessages();
    document.getElementById("saveNewUser").addEventListener("click", saveNewUser);
    document.getElementById("saveEditUser").addEventListener("click", saveEditUser);
});

// Resto del código se mantiene igual...
// Traer todos los usuarios
async function loadUsers(){
    const res = await fetch("/api/get_users.php");
    const users = await res.json();
    const tbody = document.getElementById("usersTable");
    tbody.innerHTML = "";
    users.forEach(u=>{
        const tr = document.createElement("tr");
        tr.innerHTML = `
        <td>${u.id}</td>
        <td>${u.nombre}</td>
        <td>${u.email}</td>
        <td><span class="badge ${u.estado==='activo'?'bg-success':'bg-danger'}">${u.estado}</span></td>
        <td>
            <button class="btn btn-sm btn-primary me-1" onclick="openEdit(${u.id})">Editar</button>
            <button class="btn btn-sm ${u.estado==='activo'?'btn-warning':'btn-success'}" onclick="toggleStatus(${u.id}, '${u.estado}')">
                ${u.estado==='activo'?'Suspender':'Reactivar'}
            </button>
        </td>`;
        tbody.appendChild(tr);
    });
}

// Traer mensajes de contacto
async function loadMessages(){
    const res = await fetch("/api/get_messages.php");
    const msgs = await res.json();
    const tbody = document.getElementById("messagesTable");
    tbody.innerHTML = "";
    msgs.forEach(m=>{
        const tr = document.createElement("tr");
        tr.innerHTML = `
        <td>${m.id}</td>
        <td>${m.nombre}</td>
        <td>${m.email}</td>
        <td>${m.asunto}</td>
        <td>${m.mensaje}</td>
        <td>${m.fecha_envio}</td>`;
        tbody.appendChild(tr);
    });
}

// Crear nuevo usuario
async function saveNewUser(){
    const data = {
        name: document.getElementById("newName").value,
        email: document.getElementById("newEmail").value,
        password: document.getElementById("newPassword").value
    };
    const res = await fetch("/api/create_user.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify(data)
    });
    const r = await res.json();
    if(r.success){
        document.getElementById("newUserForm").reset();
        bootstrap.Modal.getInstance(document.getElementById("newUserModal")).hide();
        loadUsers();
    } else alert(r.error||"Error al crear usuario");
}

// Abrir modal de edición de usuario
async function openEdit(id){
    const res = await fetch(`/api/get_user.php?id=${id}`);
    const u = await res.json();
    if(u.error){ alert(u.error); return; }
    document.getElementById("editId").value = u.id;
    document.getElementById("editName").value = u.nombre;
    document.getElementById("editEmail").value = u.email;
    document.getElementById("editPassword").value = "";
    new bootstrap.Modal(document.getElementById("editUserModal")).show();
}

// Guardar cambios de usuario
async function saveEditUser(){
    const id = document.getElementById("editId").value;
    const data = { id, name: document.getElementById("editName").value, email: document.getElementById("editEmail").value };
    const pwd = document.getElementById("editPassword").value;
    if(pwd) data.password = pwd;

    const res = await fetch(`/api/update_user.php?id=${id}`, {
        method:"PUT",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify(data)
    });
    const r = await res.json();
    if(r.success){
        bootstrap.Modal.getInstance(document.getElementById("editUserModal")).hide();
        loadUsers();
    } else alert(r.error||"Error al actualizar usuario");
}

// Cambiar estado activo/inactivo
async function toggleStatus(id, estado){
    const newStatus = estado==='activo'?'inactivo':'activo';
    const res = await fetch(`/api/update_user.php?id=${id}&action=status`, {
        method:"PATCH",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({estado:newStatus})
    });
    const r = await res.json();
    if(r.success) loadUsers();
    else alert(r.error||"Error al cambiar estado");
}