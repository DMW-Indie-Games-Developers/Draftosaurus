# Separación Frontend y Backend - Draftosaurus

## 📋 Resumen

Tu proyecto ahora está separado en **dos servicios independientes**:

- **Backend (API REST)**: Puerto 4000 - Solo responde JSON
- **Frontend (Archivos estáticos)**: Puerto 3000 - HTML, CSS, JS

---

## 🚀 Cómo Ejecutar

### Requisito previo
Asegúrate de tener PHP instalado. Verifica con:
```bash
php -v
```

### 1. Ejecutar el Backend (API)

Abre una terminal en la raíz del proyecto y ejecuta:

```bash
php -S localhost:4000 -t backend/public
```

**Salida esperada:**
```
PHP 8.x Development Server (http://localhost:4000) started
```

**El backend ahora responde en:** `http://localhost:4000`

**Prueba que funcione:**
- Abre tu navegador en: http://localhost:4000/health
- Deberías ver un JSON con el estado del servidor

---

### 2. Ejecutar el Frontend (Aplicación Web)

Abre **OTRA terminal** (deja la del backend corriendo) y ejecuta:

```bash
php -S localhost:3000 -t frontend frontend/server.php
```

**Salida esperada:**
```
PHP 8.x Development Server (http://localhost:3000) started
```

**El frontend ahora está en:** `http://localhost:3000`

**Prueba que funcione:**
- Abre tu navegador en: http://localhost:3000
- Deberías ver la página de inicio del juego

---

## 📂 Estructura del Proyecto

```
Draftosaurus/
├── backend/                    # 🔧 API REST (Puerto 4000)
│   ├── public/
│   │   └── index.php          # Router de la API (SOLO JSON)
│   ├── api/
│   │   ├── controllers/       # Controladores
│   │   ├── services/          # Lógica de negocio
│   │   └── repositories/      # Acceso a datos
│   └── usermodel/             # Modelos de datos
│
├── frontend/                   # 🎨 Aplicación Web (Puerto 3000)
│   ├── server.php             # Servidor de archivos estáticos
│   ├── views/                 # Páginas HTML
│   │   ├── index.html
│   │   ├── login.html
│   │   ├── perfil.html
│   │   ├── tablero.html
│   │   ├── ranking.html
│   │   └── admin.html
│   ├── js/                    # JavaScript del cliente
│   │   ├── config.js          # ⚙️ Configuración de la API
│   │   ├── login.js
│   │   ├── perfil.js
│   │   ├── tablero.js
│   │   ├── ranking.js
│   │   └── admin.js
│   ├── css/                   # Estilos CSS
│   ├── img/                   # Imágenes
│   └── resources/             # Recursos adicionales
│
└── [archivos originales]      # Se mantienen por compatibilidad
```

---

## 🔗 Comunicación Frontend ↔ Backend

### Frontend (Puerto 3000)
- Sirve archivos HTML, CSS, JS, imágenes
- El JavaScript hace peticiones AJAX/fetch al backend

### Backend (Puerto 4000)
- Recibe peticiones HTTP
- Procesa datos (login, partidas, ranking, etc.)
- Responde con JSON

### Ejemplo de flujo:

1. Usuario abre `http://localhost:3000/login`
2. Frontend sirve `login.html`
3. Usuario ingresa credenciales y hace clic en "Iniciar Sesión"
4. JavaScript ejecuta:
   ```javascript
   fetch(apiUrl('/login'), {
     method: 'POST',
     body: JSON.stringify({ identifier, password })
   })
   ```
5. La función `apiUrl('/login')` convierte la ruta en `http://localhost:4000/login`
6. Backend procesa el login y devuelve JSON:
   ```json
   {
     "success": true,
     "user": { "id": 1, "username": "martin" }
   }
   ```
7. Frontend recibe la respuesta y redirige a `/perfil`

---

## ⚙️ Configuración

### Cambiar puerto del Backend

Edita `frontend/js/config.js`:

```javascript
const API_BASE_URL = 'http://localhost:4000'; // Cambia aquí el puerto
```

### Cambiar puerto del Frontend

Al ejecutar el servidor, cambia el número:

```bash
php -S localhost:8080 -t frontend frontend/server.php
```

---

## 🔍 Endpoints de la API

### Autenticación
- `POST /login` - Iniciar sesión
- `POST /register` - Registrar usuario
- `POST /logout` - Cerrar sesión

### Perfil
- `GET /perfil/me` - Obtener perfil del usuario logueado
- `GET /perfil/{id}` - Obtener perfil por ID
- `POST /perfil/nickname` - Actualizar nickname
- `POST /perfil/avatar` - Actualizar avatar

### Partidas
- `POST /api/crearPartida` - Crear nueva partida
- `GET /api/tablero/cargarPartida?id=X` - Cargar partida guardada
- `POST /api/tablero/guardarEstadoPartida` - Guardar progreso
- `POST /api/tablero/finalizarPartida` - Finalizar partida

### Ranking
- `GET /api/ranking` - Obtener ranking general
- `GET /perfil/ranking?limit=10` - Top N jugadores
- `GET /perfil/user-ranking` - Posición del usuario

### Admin
- `GET /api/admin/users` - Listar usuarios
- `POST /api/admin/users` - Crear usuario
- `PUT /api/admin/users/{id}` - Actualizar usuario
- `PATCH /api/admin/users/{id}/status` - Suspender/activar usuario

### Health Check
- `GET /health` - Estado del servidor y base de datos

---

## ✅ Ventajas de esta separación

1. **Separación de responsabilidades**
   - Frontend: presentación y UX
   - Backend: lógica de negocio y datos

2. **Escalabilidad**
   - Puedes desplegar cada servicio en servidores diferentes
   - Frontend en CDN/hosting estático
   - Backend en servidor con PHP

3. **Desarrollo independiente**
   - Frontend puede trabajar con datos mock
   - Backend puede probarse con Postman/cURL

4. **CORS configurado**
   - El backend permite peticiones desde `localhost:3000`
   - Listo para producción (solo cambiar origen en producción)

---

## 🐛 Troubleshooting

### Error: "Failed to fetch"
- Verifica que AMBOS servidores estén corriendo
- Revisa que los puertos sean 3000 (frontend) y 4000 (backend)

### Error: CORS
- Verifica que el backend tenga configurado el origen correcto en `backend/public/index.php` línea 40:
  ```php
  header('Access-Control-Allow-Origin: http://localhost:3000');
  ```

### Error: "Archivo no encontrado"
- Verifica que los archivos estén en las carpetas correctas
- CSS/JS deben estar en `frontend/css` y `frontend/js`

### La sesión no funciona
- CORS debe permitir credenciales (ya configurado)
- Las cookies deben enviarse con `credentials: 'include'` en fetch (ya implementado)

---

## 🎓 Para tu profesor

**Demostración de separación Frontend/Backend:**

1. **Backend puro API REST:**
   - `backend/public/index.php` es un Router que SOLO devuelve JSON
   - NO tiene código HTML mezclado
   - Todas las respuestas son `application/json`

2. **Frontend SPA:**
   - `frontend/` contiene archivos estáticos (HTML, CSS, JS)
   - JavaScript consume la API REST mediante fetch
   - Servidor simple solo para servir archivos estáticos

3. **Comunicación:**
   - Frontend y Backend en puertos separados
   - CORS configurado correctamente
   - Peticiones HTTP con JSON

**Ejecuta ambos comandos en terminales separadas para demostrarlo:**

Terminal 1 (Backend):
```bash
php -S localhost:4000 -t backend/public
```

Terminal 2 (Frontend):
```bash
php -S localhost:3000 -t frontend frontend/server.php
```

Luego accede a `http://localhost:3000` y muestra las peticiones en las Herramientas de Desarrollador (F12 → Network tab).

---

## 📝 Notas Adicionales

- Los archivos originales en la raíz del proyecto se mantienen por compatibilidad
- La nueva estructura en `backend/` y `frontend/` es la recomendada
- Puedes eliminar los archivos de la raíz una vez confirmes que todo funciona

**¡Buena suerte con tu presentación! 🎮**
