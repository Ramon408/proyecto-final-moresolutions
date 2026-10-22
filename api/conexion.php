<?php

const DB_HOST = 'localhost';
const DB_NOMBRE = 'gestion_escolar';
const DB_USUARIO = 'root';
const DB_CLAVE = '';


const NOTA_APROBACION = 7;
const LIMITE_ASISTENCIA = 80; 

date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();
header('Content-Type: application/json; charset=utf-8');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . (getenv('DB_HOST') ?: DB_HOST) . ';dbname=' . DB_NOMBRE . ';charset=utf8mb4',
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
            responderError('No se pudo conectar a la base de datos. Revisá que MySQL esté iniciado y que la base gestion_escolar exista.', 500);
        }
    }
    return $pdo;
}

function responder($datos, int $codigo = 200): void
{
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

function requerirSesion(array $roles = []): array
{
    if (empty($_SESSION['usuario'])) {
        responderError('Tenés que iniciar sesión.', 401);
    }
    if ($roles && !in_array($_SESSION['usuario']['rol'], $roles, true)) {
        responderError('Tu usuario no tiene permiso para esta acción.', 403);
    }
    return $_SESSION['usuario'];
}

function texto($valor, int $max): ?string
{
    $valor = trim((string) ($valor ?? ''));
    return $valor === '' ? null : mb_substr($valor, 0, $max);
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
