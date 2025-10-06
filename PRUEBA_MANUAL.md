# 🧪 Guía de Prueba Manual

Sigue estos pasos para verificar que todo funciona correctamente:

## 📝 Paso 1: Levantar los servidores

### Terminal 1 - Backend:
```bash
cd C:\Users\martin.perdomo\Documents\GitHub\Draftosaurus
php -S localhost:4000 -t backend/public
```

**Salida esperada:**
```
PHP 8.x.x Development Server (http://localhost:4000) started
```

### Terminal 2 - Frontend:
```bash
cd C:\Users\martin.perdomo\Documents\GitHub\Draftosaurus
php -S localhost:3000 -t frontend frontend/server.php
```

**Salida esperada:**
```
PHP 8.x.x Development Server (http://localhost:3000) started
```

---

## ✅ Paso 2: Verificar Backend (API)

Abre tu navegador y prueba estos endpoints:

### 1. Health Check
**URL:** http://localhost:4000/health

**Resultado esperado:**
```json
{
  "success": true,
  "status": "OK",
  "timestamp": "2025-01-06T...",
  "services": {
    "database": {
      "status": "up"
    }
  },
  "endpoints": [...]
}
```

✅ Si ves JSON con `"status": "OK"` → **Backend funcionando**

---

## ✅ Paso 3: Verificar Frontend

### 1. Página de inicio
**URL:** http://localhost:3000

**Resultado esperado:**
- Deberías ver la página de inicio del juego Draftosaurus
- Con imágenes, estilos CSS aplicados

### 2. Página de login
**URL:** http://localhost:3000/login

**Resultado esperado:**
- Formulario de login con campos de email y contraseña
- Botón de "Registrarse"
- Estilos visuales correctos

---

## ✅ Paso 4: Verificar Comunicación Frontend ↔ Backend

### 1. Abrir DevTools
- En la página de login, presiona **F12**
- Ve a la pestaña **Network** (Red)

### 2. Intentar login
- Ingresa cualquier email/contraseña
- Haz clic en "Iniciar Sesión"

### 3. Verificar petición
En la pestaña Network deberías ver:

**Request:**
- URL: `http://localhost:4000/login`
- Method: `POST`
- Status: `200` o `401` (según si las credenciales son correctas)

**Response:**
```json
{
  "success": false,
  "message": "Credenciales incorrectas"
}
```

✅ Si ves la petición a `localhost:4000` → **Comunicación funcionando**

---

## ✅ Paso 5: Probar login real (opcional)

Si tienes un usuario en la base de datos, intenta hacer login:

1. Ve a http://localhost:3000/login
2. Ingresa credenciales válidas
3. Deberías ser redirigido a http://localhost:3000/perfil
4. Verifica en DevTools → Network que se hicieron peticiones a:
   - `http://localhost:4000/login` (POST)
   - `http://localhost:4000/perfil/me` (GET)

---

## 🐛 Troubleshooting

### Problema: "Connection refused" o "Failed to fetch"

**Solución:** Verifica que AMBOS servidores estén corriendo en las terminales.

---

### Problema: "CORS error"

**Solución:** Verifica que el backend tenga configurado:
```php
header('Access-Control-Allow-Origin: http://localhost:3000');
```

En `backend/public/index.php` línea 40.

---

### Problema: Las imágenes no cargan

**Solución:** Verifica que la carpeta `frontend/img/` exista y contenga las imágenes.

Revisa en DevTools → Network si hay errores 404.

---

### Problema: JavaScript no funciona

**Solución:** Verifica que `frontend/js/config.js` se cargue ANTES que los otros scripts.

En el HTML debe aparecer:
```html
<script src="js/config.js"></script>
<script src="js/login.js"></script>
```

---

## 📊 Checklist de Verificación

Marca con ✅ cada item cuando lo verifiques:

- [ ] Backend corriendo en puerto 4000
- [ ] Frontend corriendo en puerto 3000
- [ ] Endpoint `/health` devuelve JSON con status OK
- [ ] Página de inicio carga correctamente
- [ ] Página de login carga con estilos
- [ ] DevTools muestra peticiones a `localhost:4000`
- [ ] Login funciona (o muestra error correcto en JSON)
- [ ] No hay errores CORS en consola

---

## ✅ Todo funcionó?

Si todos los checks pasaron, tu proyecto está correctamente separado en Frontend y Backend! 🎉

Puedes mostrarle a tu profesor:
1. **Dos terminales** con servidores en puertos diferentes
2. **DevTools → Network** mostrando peticiones REST API
3. **Código separado** en carpetas `backend/` y `frontend/`

---

## 🔚 Detener servidores

Presiona `Ctrl + C` en cada terminal donde estén corriendo los servidores.
