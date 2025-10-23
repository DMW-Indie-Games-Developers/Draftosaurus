# 🔍 Explicación Completa de Todas las Funciones del Código

## 📋 Índice
1. [Funciones de User.php](#user-php)
2. [Funciones de UserRepository.php](#userrepository-php)
3. [Funciones de AuthService.php](#authservice-php)
4. [Funciones de Partida.php](#partida-php)
5. [Funciones de Jugada.php](#jugada-php)
6. [Funciones de Contacto.php](#contacto-php)
7. [Funciones de Database.php](#database-php)
8. [Funciones PHP Nativas Utilizadas](#funciones-php-nativas)

---

## 🧑‍💼 USER.PHP

### **Constructor**
```php
public function __construct($username = null, $email = null, $password = null)
```
**¿De dónde viene?** Es un **método mágico de PHP** que se ejecuta automáticamente al crear un objeto.
**¿Qué hace?** Inicializa un objeto User con valores opcionales y establece valores por defecto.
**¿Por qué lo usamos?** Para crear usuarios nuevos con configuración inicial automática.

**Ejemplo de uso:**
```php
$user = new User("juan", "juan@email.com", "123456");
// Automáticamente establece: avatar, puntuación=0, rol='usuario', etc.
```

### **Getters (Métodos de Acceso)**
```php
public function getId() { return $this->id; }
public function getUsername() { return $this->username; }
public function getEmail() { return $this->email; }
// ... etc para todas las propiedades
```
**¿De dónde viene?** **Patrón de Encapsulación** de la Programación Orientada a Objetos.
**¿Qué hace?** Permite acceder a propiedades privadas desde fuera de la clase.
**¿Por qué lo usamos?** Para **controlar el acceso** a los datos y mantener la **encapsulación**.

**Ejemplo:**
```php
$user = new User();
$nombre = $user->getUsername(); // ✅ Correcto
$nombre = $user->username;      // ❌ Error - propiedad privada
```

### **Setters (Métodos de Modificación)**
```php
public function setId($id) { $this->id = $id; }
public function setUsername($username) { $this->username = $username; }
// ... etc
```
**¿De dónde viene?** **Patrón de Encapsulación** de POO.
**¿Qué hace?** Permite modificar propiedades privadas de forma controlada.
**¿Por qué lo usamos?** Para **validar datos** antes de asignarlos y mantener **consistencia**.

**Ejemplo:**
```php
$user->setEmail("nuevo@email.com"); // ✅ Controlado
$user->email = "nuevo@email.com";   // ❌ Error - no se puede acceder directamente
```

### **validar()**
```php
public function validar() {
    $errores = [];

    if (empty($this->username)) {
        $errores[] = 'El nombre de usuario es obligatorio';
    } elseif (strlen($this->username) > 50) {
        $errores[] = 'El nombre de usuario no puede tener más de 50 caracteres';
    }
    // ... más validaciones

    return $errores;
}
```
**¿De dónde viene?** **Patrón Domain Model** - la entidad conoce sus propias reglas.
**¿Qué hace?** Verifica que los datos del usuario cumplan las reglas de negocio.
**¿Por qué lo usamos?** Para **centralizar la validación** y asegurar **datos consistentes**.

**Funciones PHP utilizadas:**
- `empty()` - **Función nativa de PHP** que verifica si una variable está vacía
- `strlen()` - **Función nativa de PHP** que cuenta caracteres en un string
- `filter_var()` - **Función nativa de PHP** para validar formatos (email, etc.)

### **isAdmin()**
```php
public function isAdmin() {
    return $this->rol === 'admin';
}
```
**¿De dónde viene?** **Patrón de Métodos de Conveniencia** en POO.
**¿Qué hace?** Verifica si el usuario tiene rol de administrador.
**¿Por qué lo usamos?** Para **encapsular lógica de negocio** y hacer el código más legible.

**Ejemplo:**
```php
if ($user->isAdmin()) {           // ✅ Claro y legible
    // dar acceso admin
}

if ($user->getRol() === 'admin') { // ❌ Menos elegante
    // dar acceso admin
}
```

### **isActive()**
```php
public function isActive() {
    return $this->estado === 'activo';
}
```
**¿De dónde viene?** **Patrón de Métodos de Conveniencia**.
**¿Qué hace?** Verifica si el usuario está activo (no suspendido).
**¿Por qué lo usamos?** Para **encapsular la lógica de estado** y facilitar comprobaciones.

### **hashPassword()**
```php
public function hashPassword() {
    $this->password = password_hash($this->password, PASSWORD_DEFAULT);
}
```
**¿De dónde viene?** **Patrón de Responsabilidad Única** - el usuario maneja su propia contraseña.
**¿Qué hace?** Convierte la contraseña en texto plano a un hash seguro.
**¿Por qué lo usamos?** Para **seguridad** - nunca almacenar contraseñas en texto plano.

**Función PHP utilizada:**
- `password_hash()` - **Función nativa de PHP 5.5+** que crea hashes seguros usando bcrypt

### **verifyPassword()**
```php
public function verifyPassword($password) {
    return password_verify($password, $this->password);
}
```
**¿De dónde viene?** **Complemento de hashPassword()** para verificación.
**¿Qué hace?** Verifica si una contraseña coincide con el hash almacenado.
**¿Por qué lo usamos?** Para **autenticar usuarios** de forma segura.

**Función PHP utilizada:**
- `password_verify()` - **Función nativa de PHP 5.5+** que verifica hashes de forma segura

### **toArray()**
```php
public function toArray() {
    return [
        'id' => $this->id,
        'username' => $this->username,
        'email' => $this->email,
        // ... todos los campos
    ];
}
```
**¿De dónde viene?** **Patrón de Serialización** para convertir objetos a formatos simples.
**¿Qué hace?** Convierte el objeto User a un array asociativo.
**¿Por qué lo usamos?** Para **enviar datos en JSON** o **trabajar con APIs**.

---

## 🗄️ USERREPOSITORY.PHP

### **Patrón Singleton**
```php
private static ?UserRepository $instance = null;

public static function getInstance(): ?UserRepository {
    if (self::$instance === null) {
        self::$instance = new self();
    }
    return self::$instance;
}
```
**¿De dónde viene?** **Patrón de Diseño Singleton** - garantiza una sola instancia.
**¿Qué hace?** Asegura que solo existe un objeto UserRepository en toda la aplicación.
**¿Por qué lo usamos?** Para **reutilizar la conexión a BD** y **ahorrar recursos**.

### **Constructor Privado**
```php
private function __construct() {
    $this->conn = Database::getInstance()->getConnection();
}
```
**¿De dónde viene?** **Parte del patrón Singleton**.
**¿Qué hace?** Inicializa la conexión a base de datos, pero solo puede llamarse internamente.
**¿Por qué lo usamos?** Para **controlar la creación** del objeto y **forzar uso del Singleton**.

### **findByEmail()**
```php
public function findByEmail(string $email): ?User {
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $this->conn->prepare($query);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_assoc() : null;

    $stmt->close();

    if ($data) {
        return $this->createUserFromArray($data);
    }
    return null;
}
```
**¿De dónde viene?** **Patrón Repository** para abstracción de datos.
**¿Qué hace?** Busca un usuario por email y retorna un objeto User.

**Funciones MySQL/PHP utilizadas:**
- `prepare()` - **Método de MySQLi** que prepara consultas seguras
- `bind_param()` - **Método de MySQLi** que vincula parámetros (previene SQL injection)
- `execute()` - **Método de MySQLi** que ejecuta la consulta preparada
- `get_result()` - **Método de MySQLi** que obtiene el resultado
- `fetch_assoc()` - **Método de MySQLi** que obtiene fila como array asociativo
- `close()` - **Método de MySQLi** que cierra el statement

**¿Por qué lo usamos?** Para **buscar usuarios de forma segura** y **retornar objetos**.

### **findById()**
```php
public function findById(int $id): ?User {
    error_log("=== UserRepository::findById($id) ===");

    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $this->conn->prepare($query);

    if (!$stmt) {
        error_log("Error preparando findById: " . $this->conn->error);
        return null;
    }

    $stmt->bind_param("i", $id);
    // ... resto igual que findByEmail
}
```
**¿De dónde viene?** **Mismo patrón que findByEmail** pero para IDs.
**¿Qué hace?** Busca usuario por ID numérico.

**Funciones adicionales:**
- `error_log()` - **Función nativa de PHP** para registrar errores en logs
- `$this->conn->error` - **Propiedad de MySQLi** que contiene el último error

**¿Por qué lo usamos?** Para **depuración** y **trazabilidad** de errores.

### **createUser()**
```php
public function createUser(User $user): User|false {
    $query = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt = $this->conn->prepare($query);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sss",
        $user->getUsername(),
        $user->getEmail(),
        $user->getPassword()
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception("Error al crear usuario: $error");
    }

    $insertId = $stmt->insert_id;
    $stmt->close();

    $user->setId($insertId);
    return $user;
}
```
**¿De dónde viene?** **Patrón Repository** para persistencia.
**¿Qué hace?** Inserta un nuevo usuario en la base de datos.

**Elementos nuevos:**
- `insert_id` - **Propiedad de MySQLi** que contiene el ID del registro insertado
- `throw new Exception()` - **Manejo de excepciones de PHP** para errores críticos

**¿Por qué lo usamos?** Para **crear usuarios** y **manejar errores** de forma robusta.

### **createUserFromArray()**
```php
private function createUserFromArray(array $data): User {
    $user = new User();
    $user->setId($data['id']);
    $user->setUsername($data['username']);
    $user->setEmail($data['email']);
    // ... mapear todos los campos
    return $user;
}
```
**¿De dónde viene?** **Patrón de Mapeo** entre base de datos y objetos.
**¿Qué hace?** Convierte un array de la BD en un objeto User.
**¿Por qué lo usamos?** Para **separar la lógica de mapeo** y **mantener código limpio**.

---

## 🧠 AUTHSERVICE.PHP

### **Patrón Singleton**
```php
private static ?AuthService $instance = null;
private ?UserRepository $userRepository;

private function __construct() {
    $this->userRepository = UserRepository::getInstance();
}

public static function getInstance(): ?AuthService {
    if (self::$instance === null) {
        self::$instance = new self();
    }
    return self::$instance;
}
```
**¿De dónde viene?** **Mismo patrón Singleton** que UserRepository.
**¿Qué hace?** Garantiza una sola instancia del servicio de autenticación.
**¿Por qué lo usamos?** Para **mantener estado consistente** y **reutilizar dependencias**.

### **register()**
```php
public function register(string $username, string $email, string $password): array {
    // 1. Validaciones básicas
    if (empty(trim($username)) || empty(trim($email)) || empty($password)) {
        return ['success' => false, 'message' => 'Todos los campos son requeridos.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El formato del email no es válido.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.'];
    }

    // 2. Verificar duplicados
    if ($this->userRepository->findByUsernameOrEmail($username)) {
        return ['success' => false, 'message' => 'El nombre de usuario ya está en uso.'];
    }

    // 3. Crear usuario y hashear contraseña
    $user = new User($username, $email, $password);
    $user->hashPassword();

    // 4. Persistir usando repositorio
    $newUser = $this->userRepository->createUser($user);

    if (!$newUser) {
        return ['success' => false, 'message' => 'No se pudo completar el registro.'];
    }

    return [
        'success' => true,
        'message' => 'Registro exitoso.',
        'user' => $newUser->toArray()
    ];
}
```
**¿De dónde viene?** **Patrón Service Layer** - lógica de negocio centralizada.
**¿Qué hace?** Gestiona todo el proceso de registro de usuario.

**Funciones PHP utilizadas:**
- `trim()` - **Función nativa** que elimina espacios al inicio y final
- `empty()` - **Función nativa** que verifica si está vacío
- `filter_var($email, FILTER_VALIDATE_EMAIL)` - **Función nativa** para validar emails
- `strlen()` - **Función nativa** que cuenta caracteres

**¿Por qué lo usamos?** Para **centralizar la lógica de registro** y **validar datos**.

### **verifyCredentials()**
```php
private function verifyCredentials(string $identifier, string $password): User|false {
    error_log("=== INICIO verifyCredentials ===");
    error_log("Identifier: $identifier");

    $user = $this->userRepository->findByUsernameOrEmail($identifier);

    if (!$user) {
        error_log("Usuario NO encontrado");
        return false;
    }

    error_log("Usuario encontrado: " . $user->getUsername());
    error_log("Estado del usuario: " . $user->getEstado());

    // Verificar si el usuario está suspendido
    if (!$user->isActive()) {
        error_log("ACCESO DENEGADO: Usuario suspendido");
        return false;
    }

    error_log("Usuario activo, verificando contraseña...");

    if (!$user->verifyPassword($password)) {
        error_log("Password verify: FAIL");
        return false;
    }

    error_log("Password verify: OK");
    return $user;
}
```
**¿De dónde viene?** **Método privado de ayuda** para el login.
**¿Qué hace?** Verifica credenciales y estado del usuario.

**¿Por qué usamos error_log()?**
- Para **depuración** de problemas de login
- Para **auditoría** de intentos de acceso
- Para **monitoreo** del sistema

**¿Por qué lo usamos?** Para **separar la lógica de verificación** del login principal.

### **login()**
```php
public function login(string $identifier, string $password): array {
    error_log("=== INICIO AuthService::login ===");
    error_log("Intentando login para: $identifier");

    $user = $this->verifyCredentials($identifier, $password);

    if ($user === false) {
        // Manejo específico de errores
        $userExists = $this->userRepository->findByUsernameOrEmail($identifier);

        if (!$userExists) {
            return ['success' => false, 'message' => 'Credenciales incorrectas.'];
        }

        if (!$userExists->isActive()) {
            return [
                'success' => false,
                'message' => 'Tu cuenta ha sido suspendida. Contacta al administrador.',
                'code' => 'ACCOUNT_SUSPENDED'
            ];
        }

        return ['success' => false, 'message' => 'Credenciales incorrectas.'];
    }

    error_log("Login exitoso para usuario ID: " . $user->getId());

    return [
        'success' => true,
        'message' => 'Login exitoso.',
        'user' => [
            'id'       => $user->getId(),
            'username' => $user->getUsername(),
            'email'    => $user->getEmail(),
            'rol'      => $user->getRol(),
            'estado'   => $user->getEstado(),
        ]
    ];
}
```
**¿De dónde viene?** **Método público principal** del servicio de autenticación.
**¿Qué hace?** Gestiona el proceso completo de login con manejo de errores específicos.
**¿Por qué lo usamos?** Para **autenticar usuarios** y **proporcionar retroalimentación detallada**.

### **validateUserStatus()**
```php
public function validateUserStatus(int $userId): array {
    $user = $this->userRepository->findById($userId);

    if (!$user) {
        return ['valid' => false, 'reason' => 'USER_NOT_FOUND'];
    }

    if (!$user->isActive()) {
        return ['valid' => false, 'reason' => 'ACCOUNT_SUSPENDED'];
    }

    return ['valid' => true, 'user' => $user->toArray()];
}
```
**¿De dónde viene?** **Método adicional** para validar sesiones existentes.
**¿Qué hace?** Verifica si un usuario sigue siendo válido (no suspendido).
**¿Por qué lo usamos?** Para **validar sesiones activas** y **seguridad en tiempo real**.

---

## 🎮 PARTIDA.PHP

### **Constructor**
```php
public function __construct($jugador1_id = null, $jugador2_id = null) {
    $this->jugador1_id = $jugador1_id;
    $this->jugador2_id = $jugador2_id;
    $this->jugadorActivo = 1;
    $this->ronda = 1;
    $this->turno = 1;
    $this->mano1 = json_encode([]);
    $this->mano2 = json_encode([]);
    $this->puntos_j1 = 0;
    $this->puntos_j2 = 0;
    $this->ultimo_jugador = 1;
    $this->estado_partida = 'activa';
}
```
**¿De dónde viene?** **Constructor específico** para inicializar partidas de Draftosaurus.
**¿Qué hace?** Establece valores iniciales para una nueva partida.

**Función PHP utilizada:**
- `json_encode([])` - **Función nativa** que convierte array a JSON string

**¿Por qué lo usamos?** Para **inicializar partidas** con estado de juego válido.

### **getManoJugador()**
```php
public function getManoJugador($jugador) {
    if ($jugador == 1) {
        return json_decode($this->mano1, true);
    } elseif ($jugador == 2) {
        return json_decode($this->mano2, true);
    }
    return [];
}
```
**¿De dónde viene?** **Método específico del dominio** del juego.
**¿Qué hace?** Obtiene las cartas de un jugador específico.

**Función PHP utilizada:**
- `json_decode($json, true)` - **Función nativa** que convierte JSON a array

**¿Por qué lo usamos?** Para **acceder a las cartas** de forma controlada.

### **setManoJugador()**
```php
public function setManoJugador($jugador, $mano) {
    if ($jugador == 1) {
        $this->mano1 = json_encode($mano);
    } elseif ($jugador == 2) {
        $this->mano2 = json_encode($mano);
    }
}
```
**¿De dónde viene?** **Método complementario** a getManoJugador.
**¿Qué hace?** Actualiza las cartas de un jugador específico.
**¿Por qué lo usamos?** Para **modificar el estado del juego** de forma controlada.

### **isActive() / isFinished()**
```php
public function isActive() {
    return $this->estado_partida === 'activa';
}

public function isFinished() {
    return $this->ganador !== null;
}
```
**¿De dónde viene?** **Métodos de conveniencia** para estado del juego.
**¿Qué hace?** Verifican el estado actual de la partida.
**¿Por qué lo usamos?** Para **lógica de negocio** y **control de flujo** del juego.

---

## 📞 CONTACTO.PHP

### **validar()**
```php
public function validar() {
    $errores = [];

    if (empty($this->nombre)) {
        $errores[] = 'El nombre es obligatorio';
    } elseif (strlen($this->nombre) > 100) {
        $errores[] = 'El nombre no puede tener más de 100 caracteres';
    }

    if (empty($this->email)) {
        $errores[] = 'El email es obligatorio';
    } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no tiene un formato válido';
    }

    return $errores;
}
```
**¿De dónde viene?** **Mismo patrón de validación** que User.php.
**¿Qué hace?** Valida datos del formulario de contacto.
**¿Por qué lo usamos?** Para **asegurar datos válidos** antes de guardar.

---

## 🗃️ DATABASE.PHP

### **Patrón Singleton**
```php
class Database {
    private static ?Database $instance = null;
    private mysqli $connection;

    private function __construct() {
        $this->connection = new mysqli($host, $username, $password, $database);

        if ($this->connection->connect_error) {
            throw new Exception("Error de conexión: " . $this->connection->connect_error);
        }

        $this->connection->set_charset("utf8mb4");
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli {
        return $this->connection;
    }
}
```
**¿De dónde viene?** **Patrón Singleton** para gestión de conexiones.
**¿Qué hace?** Mantiene una sola conexión a MySQL reutilizable.

**Clases PHP utilizadas:**
- `mysqli` - **Clase nativa de PHP** para conexiones MySQL mejoradas
- `Exception` - **Clase nativa de PHP** para manejo de errores

**Métodos mysqli utilizados:**
- `connect_error` - **Propiedad** que contiene errores de conexión
- `set_charset()` - **Método** que establece codificación de caracteres

**¿Por qué lo usamos?** Para **gestionar conexiones** de forma eficiente y **evitar múltiples conexiones**.

---

## 🔧 FUNCIONES PHP NATIVAS UTILIZADAS

### **Funciones de Validación**
- `empty($var)` - Verifica si una variable está vacía
- `strlen($string)` - Cuenta caracteres en un string
- `filter_var($value, $filter)` - Valida y filtra datos
- `trim($string)` - Elimina espacios al inicio y final

### **Funciones de Array y JSON**
- `json_encode($array)` - Convierte array a JSON string
- `json_decode($json, $assoc)` - Convierte JSON a array/objeto

### **Funciones de Password**
- `password_hash($password, $algorithm)` - Crea hash seguro
- `password_verify($password, $hash)` - Verifica hash

### **Funciones de Debug**
- `error_log($message)` - Registra mensajes en log de errores

### **Funciones de Base de Datos (MySQLi)**
- `prepare($query)` - Prepara consulta SQL
- `bind_param($types, $vars)` - Vincula parámetros
- `execute()` - Ejecuta consulta preparada
- `get_result()` - Obtiene resultado
- `fetch_assoc()` - Obtiene fila como array asociativo
- `close()` - Cierra statement

---

## 🎯 RESUMEN DE PATRONES Y CONCEPTOS

### **Patrones de Diseño Utilizados:**
1. **Singleton** - Una sola instancia (Database, Repositories, Services)
2. **Repository** - Abstracción de acceso a datos
3. **Service Layer** - Lógica de negocio centralizada
4. **Entity/Domain Model** - Objetos ricos con comportamiento

### **Conceptos de POO:**
1. **Encapsulación** - Propiedades privadas + getters/setters
2. **Abstracción** - Interfaces simples para operaciones complejas
3. **Responsabilidad Única** - Cada clase tiene un propósito específico
4. **Composición** - Services usan Repositories, etc.

### **Conceptos de Seguridad:**
1. **Prepared Statements** - Prevención de SQL Injection
2. **Password Hashing** - Almacenamiento seguro de contraseñas
3. **Validación de Entrada** - Verificación de datos del usuario
4. **Logging** - Registro de eventos para auditoría

Cada función tiene un **propósito específico** y sigue **principios de buenas prácticas** para crear código **mantenible, seguro y escalable**.