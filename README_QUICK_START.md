# 🚀 Inicio Rápido - Draftosaurus

## Ejecutar el proyecto (2 pasos simples)

### Opción 1: Usando scripts (Windows)

1. **Doble clic en `start-backend.bat`**
   - Se abrirá una terminal con el backend en puerto 4000

2. **Doble clic en `start-frontend.bat`**
   - Se abrirá otra terminal con el frontend en puerto 3000

3. **Abre tu navegador en:** http://localhost:3000

---

### Opción 2: Comandos manuales

#### Terminal 1 - Backend:
```bash
php -S localhost:4000 -t backend/public
```

#### Terminal 2 - Frontend:
```bash
php -S localhost:3000 -t frontend frontend/server.php
```

#### Accede a:
http://localhost:3000

---

## ✅ Verificar que funciona

1. Backend funcionando: http://localhost:4000/health
   - Deberías ver JSON con `"status": "OK"`

2. Frontend funcionando: http://localhost:3000
   - Deberías ver la página de inicio del juego

---

## 📖 Documentación completa

Lee `SEPARACION_FRONTEND_BACKEND.md` para entender la arquitectura completa.

---

## 🛑 Detener los servidores

Presiona `Ctrl + C` en cada terminal donde estén corriendo.
