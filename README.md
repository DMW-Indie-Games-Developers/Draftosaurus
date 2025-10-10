# 🦕 DraftosaurusITI - Juego de Mesa

## 🚀 Inicio Rápido

### Configuración Inicial (Primera vez)

1. **Configurar variables de entorno:**
   ```bash
   # Copiar el archivo de ejemplo
   cp .env.example .env

   # Editar .env con tus credenciales reales
   # DB_HOST=localhost
   # DB_USER=tu_usuario
   # DB_PASSWORD=tu_contraseña
   # DB_NAME=draftosaurus
   ```

2. **Importar la base de datos:**
   ```bash
   mysql -u root -p < draftosaurus.sql
   ```

### Ejecutar el proyecto:

**Windows:**
1. Doble clic en `start-backend.bat`
2. Doble clic en `start-frontend.bat`
3. Abrir: http://localhost:3000

**Manual:**
```bash
# Terminal 1 - Backend (Puerto 4000)
php -S localhost:4000 -t backend/public

# Terminal 2 - Frontend (Puerto 3000)
php -S localhost:3000 -t frontend frontend/server.php
```

📖 **Ver documentación completa:** [SEPARACION_FRONTEND_BACKEND.md](SEPARACION_FRONTEND_BACKEND.md)

---

## 🎮 ¿Qué es Draftosaurus?

**Draftosaurus** es un juego de mesa familiar, rápido y ligero en el que los jugadores compiten por construir el parque de dinosaurios más atractivo y rentable.

---

## 🎲 Mecánica del Juego

La mecánica central es el **draft de dinosaurios**:

- Cada jugador toma un puñado de dinosaurios de una bolsa.
- Elige uno y lo coloca en su parque.
- Luego pasa el resto al siguiente jugador.

---

## 🎲🔁 Dado de Colocación

El giro clave del juego es el **Dado de Colocación**:

- En cada turno, un jugador lanza un dado que impone una **restricción de colocación** para los demás jugadores.
- El jugador que lanzó el dado **está exento de la restricción**.

### Posibles restricciones:

- Colocar un dinosaurio en una zona **boscosa** 🌲
- Colocar un dinosaurio en una zona de **pastizales** 🌾
- Colocar en la **parte izquierda** o **derecha** del parque

---

## 🏞️ Objetivo del Juego

Cada jugador debe colocar dinosaurios en distintos recintos de su tablero personal, teniendo en cuenta las **reglas de puntuación** de cada uno:

- Algunos recintos puntúan por tener **dinosaurios de la misma especie**.
- Otros por tener **diferentes especies**.
- Algunos por formar **pares** o **combinaciones específicas**.

---

## ⏱️ Duración y Estructura

- El juego se desarrolla en **2 rondas**.
- Al final de cada ronda, cada jugador habrá colocado **12 dinosaurios**.
- Al finalizar la segunda ronda, se realiza una **puntuación final**.

---

## 🧠 ¿Por qué jugarlo?

Draftosaurus combina:

✅ La diversión de **coleccionar adorables dinosaurios** 🦖  
✅ Una **mecánica de draft** sencilla pero ingeniosa  
✅ **Restricciones estratégicas** gracias al dado  
✅ **Reglas claras y rápidas** de aprender  

Es **ideal para todas las edades** y perfecto tanto para **sesiones casuales** como para quienes disfrutan de **juegos con decisiones interesantes** y **rejugabilidad**.

----

## 📂 Estructura del Proyecto

```
Draftosaurus/
├── backend/              # API REST (Puerto 4000)
│   ├── public/           # Router principal
│   ├── api/              # Controladores, servicios, repositorios
│   └── usermodel/        # Modelos de datos
│
├── frontend/             # Aplicación Web (Puerto 3000)
│   ├── views/            # Páginas HTML
│   ├── js/               # JavaScript del cliente
│   ├── css/              # Estilos
│   └── img/              # Recursos gráficos
│
├── original/             # Archivos de la versión monolítica (backup)
├── start-backend.bat     # Script para iniciar backend
├── start-frontend.bat    # Script para iniciar frontend
└── SEPARACION_FRONTEND_BACKEND.md  # Documentación técnica
```

**Arquitectura:** Frontend-Backend separados con comunicación REST API

----

## 📌 Créditos

Este juego **Draftosaurus**, fue diseñado por **Antoine Bauza, Corentin Lebrat, Ludovic Maublanc y Théo Rivière**, publicado originalmente por **Ankama** y **Board Game Box**.

**Implementación digital:** Proyecto ITI - Universidad Nacional del Sur

