# 🌐 Sistema de Internacionalización (i18n)

## Idiomas Soportados
- 🇪🇸 **Español** (por defecto)
- 🇬🇧 **Inglés**

---

## 📁 Estructura de Archivos

```
frontend/
├── js/
│   ├── i18n.js              # Sistema principal de traducciones
│   └── i18n-helper.js       # Funciones helper para JavaScript
└── lang/
    ├── es.json              # Traducciones en español
    └── en.json              # Traducciones en inglés
```

---

## 🚀 Cómo Usar

### 1. En HTML - Traducciones Automáticas

Usa el atributo `data-i18n` para textos:

```html
<h1 data-i18n="login.title">Iniciar Sesión</h1>
<button data-i18n="common.save">Guardar</button>
```

Para placeholders:

```html
<input type="text" data-i18n-placeholder="login.emailPlaceholder" placeholder="Ingresa tu email">
```

Para títulos (tooltips):

```html
<button data-i18n-title="common.edit" title="Editar">✏️</button>
```

### 2. En JavaScript - Traducciones Dinámicas

```javascript
// Obtener traducción
const texto = window.i18n.t('common.welcome');

// O usar la función abreviada
const texto = t('common.welcome');

// Ejemplo en alertas
alert(t('messages.avatarUpdated'));
```

### 3. Cambiar Idioma Programáticamente

```javascript
// Cambiar a inglés
window.i18n.setLanguage('en');

// Cambiar a español
window.i18n.setLanguage('es');
```

### 4. Selector de Idioma

El selector ya está implementado en:
- ✅ Login (`/login`)
- ✅ Perfil (`/perfil`)
- ✅ Home (`/`)

Para agregarlo en otra vista HTML:

```html
<!-- En el HTML -->
<select id="language-selector" class="form-select form-select-sm">
    <option value="es">🇪🇸 ES</option>
    <option value="en">🇬🇧 EN</option>
</select>

<!-- En el JavaScript (antes del cierre de </body>) -->
<script src="js/i18n.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selector = document.getElementById('language-selector');
    if (selector && window.i18n) {
        selector.value = window.i18n.getCurrentLanguage();
        selector.addEventListener('change', function(e) {
            window.i18n.setLanguage(e.target.value);
        });
    }
});
</script>
```

---

## 📝 Agregar Nuevas Traducciones

### 1. Actualizar los archivos JSON

**frontend/lang/es.json:**
```json
{
  "miNuevaSeccion": {
    "titulo": "Mi Título",
    "descripcion": "Mi descripción"
  }
}
```

**frontend/lang/en.json:**
```json
{
  "miNuevaSeccion": {
    "titulo": "My Title",
    "descripcion": "My description"
  }
}
```

### 2. Usar en HTML

```html
<h2 data-i18n="miNuevaSeccion.titulo">Mi Título</h2>
<p data-i18n="miNuevaSeccion.descripcion">Mi descripción</p>
```

### 3. Usar en JavaScript

```javascript
const titulo = t('miNuevaSeccion.titulo');
console.log(titulo); // "Mi Título" o "My Title" según idioma
```

---

## 🔑 Claves de Traducción Disponibles

### Common (Comunes)
- `common.welcome` - Bienvenido / Welcome
- `common.login` - Iniciar Sesión / Login
- `common.logout` - Cerrar Sesión / Logout
- `common.save` - Guardar / Save
- `common.cancel` - Cancelar / Cancel
- `common.delete` - Eliminar / Delete
- `common.back` - Volver / Back

### Login
- `login.title` - Iniciar Sesión / Login
- `login.emailPlaceholder` - Ingresa tu email... / Enter your email...
- `login.passwordPlaceholder` - Ingresa tu contraseña... / Enter your password...

### Register
- `register.title` - Registrarse / Register
- `register.usernamePlaceholder` - Elige un nombre de usuario / Choose a username

### Profile
- `profile.title` - Mi Perfil / My Profile
- `profile.stats` - Estadísticas / Statistics
- `profile.avatar` - Avatar / Avatar

### Messages
- `messages.avatarUpdated` - Avatar actualizado correctamente / Avatar updated successfully
- `messages.confirm` - ¿Estás seguro? / Are you sure?

*(Ver archivos `es.json` y `en.json` para la lista completa)*

---

## 💾 Persistencia

El idioma seleccionado se guarda en **localStorage** con la clave `language`.

```javascript
// Ver idioma actual
console.log(localStorage.getItem('language')); // "es" o "en"

// El usuario no pierde su preferencia al recargar la página
```

---

## 🎯 Eventos Personalizados

Escucha cuando el idioma cambia:

```javascript
window.addEventListener('languageChanged', function(e) {
    console.log('Nuevo idioma:', e.detail.lang);
    // Hacer algo cuando cambia el idioma
});
```

---

## ✅ Páginas con i18n Implementado

- ✅ **Home** (`/`) - Selector en navbar
- ✅ **Login** (`/login`) - Selector + formularios traducidos
- ✅ **Perfil** (`/perfil`) - Selector flotante

### 📋 Pendientes

Si quieres agregar i18n a otras páginas:

1. Incluir los scripts:
```html
<script src="js/config.js"></script>
<script src="js/i18n.js"></script>
```

2. Agregar selector de idioma (ver sección 4 arriba)

3. Agregar atributos `data-i18n` a los elementos HTML

4. Traducir las claves en `es.json` y `en.json`

---

## 🐛 Debugging

```javascript
// Ver idioma actual
console.log(window.i18n.getCurrentLanguage());

// Ver todas las traducciones cargadas
console.log(window.i18n.translations);

// Probar una traducción
console.log(t('common.welcome'));
```

---

## 📚 Ejemplo Completo

**HTML:**
```html
<!DOCTYPE html>
<html>
<head>
    <title>Mi Página</title>
</head>
<body>
    <!-- Selector -->
    <select id="language-selector">
        <option value="es">🇪🇸 ES</option>
        <option value="en">🇬🇧 EN</option>
    </select>

    <!-- Contenido traducible -->
    <h1 data-i18n="home.title">Draftosaurus</h1>
    <p data-i18n="home.description">Descripción del juego</p>

    <!-- Scripts -->
    <script src="js/config.js"></script>
    <script src="js/i18n.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selector = document.getElementById('language-selector');
        if (selector && window.i18n) {
            selector.value = window.i18n.getCurrentLanguage();
            selector.addEventListener('change', function(e) {
                window.i18n.setLanguage(e.target.value);
            });
        }
    });
    </script>
</body>
</html>
```

---

## 🎨 Estilos del Selector

El selector tiene estilos inline, pero puedes personalizarlo:

```css
#language-selector {
    background-color: rgba(255,255,255,0.1);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
}

#language-selector:hover {
    background-color: rgba(255,255,255,0.2);
}
```

---

**¡Listo!** 🎉 Tu aplicación ahora soporta múltiples idiomas de forma fácil y escalable.
