# 📋 Documentación del Proyecto Draftosaurus

## 🎯 Objetivo del Proyecto
Draftosaurus es una aplicación web para jugar el juego de mesa homónimo, implementando un sistema completo de usuarios, partidas y administración.

---

## 🏗️ Arquitectura del Proyecto

### Patrón MVC + Repository + Entity Pattern

El proyecto implementa una **arquitectura en capas** que separa claramente las responsabilidades:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   CONTROLLERS   │───▶│    SERVICES      │───▶│   REPOSITORIES  │
│ (HTTP/Response) │    │ (Business Logic) │    │  (Data Access)  │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│     VIEWS       │    │   USERMODEL      │    │    DATABASE     │
│ (Presentation)  │    │   (Entities)     │    │     (MySQL)     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

---

## 📁 Estructura de Directorios

```
Draftosaurus/
├── usermodel/              # 🎯 MODELOS DE DOMINIO (Entidades)
│   ├── User.php           # Entidad Usuario
│   ├── Partida.php        # Entidad Partida
│   ├── Jugada.php         # Entidad Jugada
│   └── Contacto.php       # Entidad Contacto
│
├── api/
│   ├── controllers/        # 🎮 CONTROLADORES (Manejo HTTP)
│   │   ├── AuthController.php
│   │   ├── TableroController.php
│   │   └── ContactoController.php
│   │
│   ├── services/          # 🧠 SERVICIOS (Lógica de Negocio)
│   │   ├── AuthService.php
│   │   ├── TableroService.php
│   │   └── ContactoService.php
│   │
│   ├── repositories/      # 🗄️ REPOSITORIOS (Acceso a Datos)
│   │   ├── UserRepository.php
│   │   ├── TableroRepository.php
│   │   └── ContactoRepository.php
│   │
│   └── config/           # ⚙️ CONFIGURACIÓN
│       └── Database.php  # Conexión a BD (Singleton)
│
├── views/                # 👁️ VISTAS (Templates HTML)
├── public/              # 🌐 ASSETS PÚBLICOS
│   ├── css/
│   ├── js/
│   └── img/
└── *.php               # 📄 PÁGINAS PRINCIPALES
```

---

## 🎯 Capa de Modelos (usermodel/)

### **¿Por qué usermodel?**
- **Separación de responsabilidades**: Los modelos representan entidades del dominio
- **Reutilización**: Los objetos pueden usarse en diferentes partes del sistema
- **Validación centralizada**: Cada entidad maneja sus propias reglas de negocio
- **Mantenibilidad**: Cambios en la estructura de datos centralizados

### **User.php** - Entidad Usuario
```php
class User {
    // Propiedades privadas (encapsulación)
    private $id, $username, $email, $password, $avatar;
    private $puntuacion_total, $partidas_jugadas, $partidas_ganadas;
    private $rol, $estado, $created_at, $updated_at;

    // Constructor con valores por defecto
    public function __construct($username = null, $email = null, $password = null)

    // Getters y Setters (acceso controlado)
    public function getId() { return $this->id; }
    public function setId($id) { $this->id = $id; }

    // Métodos de negocio
    public function isAdmin() { return $this->rol === 'admin'; }
    public function isActive() { return $this->estado === 'activo'; }
    public function hashPassword() { /* Hash seguro */ }
    public function verifyPassword($password) { /* Verificación */ }

    // Validación de datos
    public function validar() { /* Reglas de negocio */ }

    // Serialización
    public function toArray() { /* Conversión a array */ }
}
```

**Beneficios del modelo User:**
- ✅ **Encapsulación**: Datos privados, acceso controlado
- ✅ **Validación**: Reglas de negocio centralizadas
- ✅ **Seguridad**: Manejo seguro de passwords
- ✅ **Utilidades**: Métodos como `isAdmin()`, `isActive()`

### **Partida.php** - Entidad Partida del Juego
```php
class Partida {
    // Estado del juego
    private $jugador1_id, $jugador2_id, $jugadorActivo;
    private $ronda, $turno, $mano1, $mano2;
    private $restriccion, $recintos, $puntos_j1, $puntos_j2;

    // Métodos específicos del juego
    public function getManoJugador($jugador) { /* Obtener mano JSON */ }
    public function setManoJugador($jugador, $mano) { /* Actualizar mano */ }
    public function isActive() { return $this->estado_partida === 'activa'; }
    public function isFinished() { return $this->ganador !== null; }
}
```

**¿Por qué es importante?**
- 🎮 **Lógica del juego**: Encapsula reglas específicas de Draftosaurus
- 📊 **Estado consistente**: Mantiene coherencia en los datos del juego
- 🔄 **Serialización JSON**: Manejo correcto de manos de cartas

---

## 🗄️ Capa de Repositorios

### **Patrón Repository**
Los repositorios **abstraen el acceso a datos** y **retornan objetos del dominio**.

### **UserRepository.php** - Ejemplo de Implementación
```php
class UserRepository {
    // Singleton para reutilizar conexión
    private static ?UserRepository $instance = null;
    private mysqli $conn;

    // Método que retorna OBJETO en lugar de array
    public function findByEmail(string $email): ?User {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        // ... ejecutar query ...

        if ($data) {
            return $this->createUserFromArray($data); // ← OBJETO
        }
        return null;
    }

    // Crear usuario acepta OBJETO
    public function createUser(User $user): User|false {
        $query = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $stmt->bind_param("sss",
            $user->getUsername(),    // ← Métodos del objeto
            $user->getEmail(),
            $user->getPassword()
        );
        // ... ejecutar ...
        $user->setId($insertId);
        return $user; // ← Retorna OBJETO
    }

    // Método auxiliar para conversión
    private function createUserFromArray(array $data): User {
        $user = new User();
        $user->setId($data['id']);
        $user->setUsername($data['username']);
        // ... mapear todos los campos ...
        return $user;
    }
}
```

**Ventajas del Repository Pattern:**
- ✅ **Abstracción**: Oculta detalles de la base de datos
- ✅ **Testabilidad**: Fácil crear mocks para testing
- ✅ **Mantenibilidad**: Cambios en BD no afectan lógica de negocio
- ✅ **Reutilización**: Métodos reutilizables en diferentes servicios

---

## 🧠 Capa de Servicios

### **AuthService.php** - Lógica de Autenticación
```php
class AuthService {
    // Registro usando objetos
    public function register(string $username, string $email, string $password): array {
        // 1. Validaciones
        if (empty(trim($username))) {
            return ['success' => false, 'message' => 'Username requerido'];
        }

        // 2. Verificar duplicados usando repositorio
        if ($this->userRepository->findByUsernameOrEmail($username)) {
            return ['success' => false, 'message' => 'Username en uso'];
        }

        // 3. Crear objeto User
        $user = new User($username, $email, $password);
        $user->hashPassword(); // ← Método del objeto

        // 4. Persistir usando repositorio
        $newUser = $this->userRepository->createUser($user);

        return [
            'success' => true,
            'user' => $newUser->toArray() // ← Serialización
        ];
    }

    // Login con validación de estado
    private function verifyCredentials(string $identifier, string $password): User|false {
        $user = $this->userRepository->findByUsernameOrEmail($identifier);

        if (!$user || !$user->isActive()) { // ← Métodos del objeto
            return false;
        }

        if (!$user->verifyPassword($password)) { // ← Método del objeto
            return false;
        }

        return $user; // ← Retorna OBJETO
    }
}
```

**Responsabilidades del Service:**
- ✅ **Lógica de negocio**: Reglas complejas de la aplicación
- ✅ **Coordinación**: Orquesta repositorios y modelos
- ✅ **Validaciones**: Verifica reglas antes de persistir
- ✅ **Transformaciones**: Convierte entre formatos

---

## 🎮 Capa de Controladores

Los controladores **manejan HTTP** y **coordinan servicios**:

```php
class AuthController {
    public function register() {
        // 1. Obtener datos HTTP
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // 2. Llamar al servicio (lógica de negocio)
        $result = $this->authService->register($username, $email, $password);

        // 3. Retornar respuesta HTTP
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
```

---

## 🗃️ Base de Datos

### **Tablas Principales:**

#### **users** - Usuarios del sistema
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'img/isotipoOficial.png',
    puntuacion_total INT DEFAULT 0,
    partidas_jugadas INT DEFAULT 0,
    partidas_ganadas INT DEFAULT 0,
    rol VARCHAR(20) DEFAULT 'usuario',
    estado ENUM('activo','suspendido') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### **partidas** - Partidas del juego
```sql
CREATE TABLE partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jugador1_id INT,
    jugador2_id INT,
    jugadorActivo TINYINT(1),
    ronda TINYINT(1) DEFAULT 1,
    turno TINYINT(1) DEFAULT 1,
    mano1 JSON NOT NULL,      -- Cartas del jugador 1
    mano2 JSON NOT NULL,      -- Cartas del jugador 2
    restriccion TINYINT(1),   -- Restricción actual del dado
    recintos TEXT,            -- Estado del tablero
    puntos_j1 INT DEFAULT 0,
    puntos_j2 INT DEFAULT 0,
    estado_partida VARCHAR(20) DEFAULT 'activa'
);
```

---

## 🔧 Patrones de Diseño Implementados

### **1. Singleton Pattern**
```php
class Database {
    private static ?Database $instance = null;

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```
**Uso**: Garantiza una sola conexión a base de datos.

### **2. Repository Pattern**
**Abstrae el acceso a datos** y retorna objetos del dominio.

### **3. Service Layer Pattern**
**Encapsula lógica de negocio** y coordina entre repositorios.

### **4. Entity Pattern**
**Modelos ricos** con comportamiento y validación.

---

## 🛡️ Aspectos de Seguridad

### **1. Prevención de SQL Injection**
```php
$stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email); // ← Parámetros seguros
```

### **2. Hash de Contraseñas**
```php
public function hashPassword() {
    $this->password = password_hash($this->password, PASSWORD_DEFAULT);
}

public function verifyPassword($password) {
    return password_verify($password, $this->password);
}
```

### **3. Validación de Entrada**
```php
public function validar() {
    $errores = [];

    if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Email inválido';
    }

    if (strlen($this->password) < 6) {
        $errores[] = 'Password muy corta';
    }

    return $errores;
}
```

### **4. Control de Estados de Usuario**
```php
public function isActive() {
    return $this->estado === 'activo';
}
```

---

## 📊 Flujo de Datos

### **Ejemplo: Registro de Usuario**

1. **Usuario** envía formulario → `AuthController::register()`
2. **Controller** obtiene datos POST → `AuthService::register()`
3. **Service** crea objeto `User` y valida
4. **Service** llama `UserRepository::createUser($user)`
5. **Repository** ejecuta INSERT y retorna `User` con ID
6. **Service** retorna resultado → **Controller**
7. **Controller** retorna JSON → **Cliente**

```
Cliente → Controller → Service → Repository → Database
   ↑         ↓          ↓         ↓          ↓
   ←─────────←──────────←─────────←──────────←
```

---

## 🎯 Ventajas de esta Arquitectura

### **1. Separación de Responsabilidades**
- **Modelos**: Representan entidades del dominio
- **Repositorios**: Manejan persistencia
- **Servicios**: Implementan lógica de negocio
- **Controladores**: Manejan HTTP

### **2. Mantenibilidad**
- Cambios localizados en capas específicas
- Código reutilizable y modular
- Fácil testing de componentes aislados

### **3. Escalabilidad**
- Nuevas funcionalidades siguen patrones establecidos
- Fácil agregar nuevos modelos/servicios
- Arquitectura preparada para crecimiento

### **4. Profesionalismo**
- Sigue mejores prácticas de la industria
- Código empresarial y mantenible
- Patrones reconocidos globalmente

---

## 🚀 Tecnologías Utilizadas

- **Backend**: PHP 8+ con tipado estricto
- **Base de Datos**: MySQL con JSON para datos complejos
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **Arquitectura**: MVC + Repository + Service Layer
- **Seguridad**: Prepared Statements, Password Hashing
- **Patrones**: Singleton, Repository, Entity

---

## 📝 Conclusión

Este proyecto implementa una **arquitectura profesional y escalable** que:

✅ **Separa claramente las responsabilidades** en capas bien definidas
✅ **Utiliza patrones de diseño** reconocidos en la industria
✅ **Implementa seguridad robusta** contra vulnerabilidades comunes
✅ **Facilita el mantenimiento** y la extensión del código
✅ **Sigue principios SOLID** de desarrollo orientado a objetos
✅ **Proporciona una base sólida** para el crecimiento del proyecto

La carpeta **`usermodel/`** representa el **corazón del dominio**, conteniendo entidades ricas que encapsulan tanto datos como comportamiento, siguiendo las mejores prácticas de programación orientada a objetos.