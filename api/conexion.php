<?php

const DB_HOST = '127.0.0.1';
const DB_PUERTO = 3306;
const DB_NOMBRE = 'gestion_escolar';
const DB_USUARIO = 'root';
const DB_CLAVE = '';


const NOTA_APROBACION = 7;
const LIMITE_ASISTENCIA = 80; 

date_default_timezone_set('America/Argentina/Buenos_Aires');


ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

set_error_handler(function ($nivel, $mensaje, $archivo, $linea) {
    
    if (!(error_reporting() & $nivel) || in_array($nivel, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE], true)) {
        return true;
    }
    responderError('Error de PHP: ' . $mensaje . ' (' . basename($archivo) . ', línea ' . $linea . ')', 500);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Error de PHP: ' . $error['message'] . ' (' . basename($error['file']) . ', línea ' . $error['line'] . ')'], JSON_UNESCAPED_UNICODE);
    }
});

if (!extension_loaded('pdo_mysql')) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Falta la extensión pdo_mysql de PHP. Activala en php.ini (extension=pdo_mysql) y reiniciá Apache.'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();
header('Content-Type: application/json; charset=utf-8');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . (getenv('DB_HOST') ?: DB_HOST) . ';port=' . (getenv('DB_PUERTO') ?: DB_PUERTO) . ';dbname=' . (getenv('DB_NOMBRE') ?: DB_NOMBRE) . ';charset=utf8mb4',
                getenv('DB_USUARIO') ?: DB_USUARIO,
                getenv('DB_CLAVE') !== false ? getenv('DB_CLAVE') : DB_CLAVE,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'",
                ]
            );
        } catch (PDOException $e) {
            responderError(motivoErrorConexion($e), 500);
        }
    }
    return $pdo;
}


function motivoErrorConexion(PDOException $e): string
{
    $codigo = (int) $e->getCode();
    $mensaje = $e->getMessage();
    $servidor = (getenv('DB_HOST') ?: DB_HOST) . ':' . (getenv('DB_PUERTO') ?: DB_PUERTO);
    if ($codigo === 1045 || stripos($mensaje, 'Access denied') !== false) {
        return 'MySQL rechazó el usuario o la contraseña. Poné en api/conexion.php (DB_USUARIO y DB_CLAVE) los mismos datos con los que entrás a MySQL.';
    }
    if ($codigo === 1049 || stripos($mensaje, 'Unknown database') !== false) {
        return 'No existe la base ' . (getenv('DB_NOMBRE') ?: DB_NOMBRE) . ' en este MySQL. Importá database/gestion_escolar.sql en el mismo servidor que usa el sistema (' . $servidor . ').';
    }
    if ($codigo === 2002 || $codigo === 2003 || stripos($mensaje, 'refused') !== false || stripos($mensaje, 'actively refused') !== false) {
        return 'No se pudo llegar a MySQL en ' . $servidor . '. Revisá que MySQL esté iniciado en XAMPP y que el puerto (DB_PUERTO en api/conexion.php) sea el que muestra el panel.';
    }
    return 'No se pudo conectar a la base de datos: ' . $mensaje;
}

function responder($datos, int $codigo = 200): void
{
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderError(string $mensaje, int $codigo = 400): void
{
    responder(['error' => $mensaje], $codigo);
}

function leerJSON(): array
{
    $datos = json_decode(file_get_contents('php://input'), true);
    return is_array($datos) ? $datos : [];
}

function metodo(): string
{
    return $_SERVER['REQUEST_METHOD'];
}

function requerirSesion(array $roles = [], bool $sinRolElegido = false): array
{
    if (empty($_SESSION['usuario'])) {
        responderError('Tenés que iniciar sesión.', 401);
    }
    if (!$sinRolElegido && empty($_SESSION['usuario']['rol'])) {
        responderError('Elegí con qué rol querés ingresar.', 409);
    }
    if ($roles && !in_array($_SESSION['usuario']['rol'], $roles, true)) {
        responderError('Tu usuario no tiene permiso para esta acción.', 403);
    }
    return $_SESSION['usuario'];
}

function texto($valor, int $max): ?string
{
    $valor = trim((string) ($valor ?? ''));
    if ($valor === '') {
        return null;
    }
    return function_exists('mb_substr') ? mb_substr($valor, 0, $max) : substr($valor, 0, $max);
}

function fechaValida($fecha): bool
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

function horaValida($hora): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $hora);
}

function mensajeErrorBD(PDOException $e): string
{
    $codigo = $e->errorInfo[1] ?? 0;
    switch ($codigo) {
        case 1062: return 'Ya existe un registro con esos datos.';
        case 1451: return 'No se puede borrar: otros registros dependen de este.';
        case 1452: return 'El valor de la clave foránea no existe en la tabla relacionada.';
        case 3819:
        case 4025: return 'Algún dato no cumple las reglas de la tabla (rango o valores permitidos).';
        case 1048: return 'Falta completar un campo obligatorio.';
        case 1366:
        case 1292: return 'Algún dato tiene un formato incorrecto.';
        default:   return 'Error de la base de datos: ' . ($e->errorInfo[2] ?? $e->getMessage());
    }
}

function ejecutar(callable $accion): void
{
    try {
        $accion();
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        responderError(mensajeErrorBD($e), 409);
    }
}

function instanciaActual(): string
{
    $mes = (int) date('n');
    if ($mes === 12) return 'diciembre';
    if ($mes <= 2) return 'febrero';
    if ($mes <= 5) return '1.º trimestre';
    if ($mes <= 8) return '2.º trimestre';
    return '3.º trimestre';
}

function cicloActual(): int
{
    $anio = (int) date('Y');
    $consulta = db()->prepare('SELECT COUNT(*) FROM curso WHERE ciclo_lectivo = ?');
    $consulta->execute([$anio]);
    if ($consulta->fetchColumn() > 0) {
        return $anio;
    }
    $ultimo = db()->query('SELECT MAX(ciclo_lectivo) FROM curso')->fetchColumn();
    return $ultimo ? (int) $ultimo : $anio;
}
