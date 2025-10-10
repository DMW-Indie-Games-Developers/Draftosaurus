# 🔒 Guía de Seguridad - Draftosaurus

## 📋 Gestión de Credenciales

### ⚠️ IMPORTANTE: Nunca subas credenciales a GitHub

Este proyecto utiliza variables de entorno para proteger información sensible como:
- Credenciales de base de datos
- Claves API
- Tokens de sesión
- Configuraciones privadas

---

## 🛡️ Configuración Segura

### 1. Archivo `.env` (PRIVADO - NO SUBIR)

El archivo `.env` contiene tus credenciales reales y **NUNCA** debe subirse a GitHub.

```env
# .env (TUS CREDENCIALES REALES)
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=mi_contraseña_secreta
DB_NAME=draftosaurus
```

### 2. Archivo `.env.example` (PÚBLICO - PLANTILLA)

Este archivo SÍ se sube a GitHub y sirve como plantilla para otros desarrolladores:

```env
# .env.example (PLANTILLA PÚBLICA)
DB_HOST=localhost
DB_USER=tu_usuario_aqui
DB_PASSWORD=tu_contraseña_aqui
DB_NAME=draftosaurus
```

### 3. Archivo `.gitignore`

Asegura que `.env` esté incluido para evitar commits accidentales:

```gitignore
# Archivos de configuración con credenciales
.env

# Archivos de logs
*.log

# Archivos del IDE
.idea/
.vscode/
```

---

## 🚀 Configuración para Nuevos Desarrolladores

Si clonas este repositorio por primera vez:

### Paso 1: Copiar plantilla
```bash
cp .env.example .env
```

### Paso 2: Editar `.env` con tus credenciales
```bash
# En Windows
notepad .env

# En Linux/Mac
nano .env
```

### Paso 3: Actualizar valores
```env
DB_HOST=localhost
DB_USER=tu_usuario_mysql
DB_PASSWORD=tu_contraseña_mysql
DB_NAME=draftosaurus
```

---

## 🔍 Verificar que `.env` NO se suba a GitHub

### Antes de hacer commit:
```bash
# Verificar qué archivos se van a subir
git status

# Asegurarse de que .env NO aparezca en la lista
```

### Si accidentalmente agregaste `.env`:
```bash
# Remover del staging
git rm --cached .env

# Verificar que esté en .gitignore
cat .gitignore | grep .env
```

---

## ⚡ Cómo Funciona en el Código

### En PHP (Database.php)

El archivo `backend/api/config/Database.php` carga automáticamente las variables desde `.env`:

```php
private function loadEnv(): void
{
    $envPath = __DIR__ . '/../../../.env';

    if (!file_exists($envPath)) {
        error_log("⚠️ Archivo .env no encontrado");
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
```

Luego se usan así:
```php
$this->host = getenv('DB_HOST') ?: 'localhost';
$this->user = getenv('DB_USER') ?: 'root';
$this->password = getenv('DB_PASSWORD') ?: '';
$this->dbname = getenv('DB_NAME') ?: 'draftosaurus';
```

---

## 🌐 Configuración para Producción

### Hosting Compartido (cPanel, Hostinger, etc.)

1. **No uses archivo `.env`** en producción compartida
2. Define variables de entorno desde el panel de control
3. Usa configuraciones específicas del hosting

### Servidores VPS/Dedicados

Agrega variables al archivo de configuración del servidor:

**Apache (.htaccess):**
```apache
SetEnv DB_HOST localhost
SetEnv DB_USER usuario
SetEnv DB_PASSWORD contraseña
SetEnv DB_NAME draftosaurus
```

**Nginx:**
```nginx
fastcgi_param DB_HOST localhost;
fastcgi_param DB_USER usuario;
fastcgi_param DB_PASSWORD contraseña;
fastcgi_param DB_NAME draftosaurus;
```

---

## 🔐 Buenas Prácticas

### ✅ HACER:
- Usar variables de entorno para credenciales
- Incluir `.env` en `.gitignore`
- Crear `.env.example` como plantilla
- Documentar variables requeridas
- Cambiar contraseñas periódicamente

### ❌ NO HACER:
- Subir `.env` a GitHub
- Hardcodear credenciales en el código
- Compartir `.env` por email/WhatsApp
- Usar la misma contraseña en desarrollo y producción
- Commitear archivos con credenciales

---

## 🆘 ¿Qué Hacer si Subiste Credenciales por Error?

### 1. Cambiar INMEDIATAMENTE las credenciales comprometidas
```sql
-- Cambiar contraseña de MySQL
ALTER USER 'root'@'localhost' IDENTIFIED BY 'nueva_contraseña_segura';
FLUSH PRIVILEGES;
```

### 2. Remover del historial de Git (cuidado)
```bash
# Remover archivo del historial completo
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .env" \
  --prune-empty --tag-name-filter cat -- --all

# Forzar push (⚠️ solo si estás seguro)
git push origin --force --all
```

### 3. Considerar el repositorio comprometido
Si el repositorio es público, considera:
- Cambiar todas las credenciales
- Crear un nuevo repositorio limpio
- Notificar al equipo

---

## 📚 Recursos Adicionales

- [12 Factor App - Config](https://12factor.net/config)
- [GitHub: Remove Sensitive Data](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)
- [OWASP: Configuration Management](https://owasp.org/www-project-top-ten/)

---

**Última actualización:** 2025-10-10
