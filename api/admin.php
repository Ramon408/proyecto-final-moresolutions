<?php

require __DIR__ . '/conexion.php';

const TABLAS = ['rol', 'usuario', 'docente', 'alumno', 'plan_estudio', 'materia', 'curso', 'dictado',
                'horario', 'inscripcion', 'clase', 'asistencia_alumno', 'asistencia_docente', 'calificacion'];

function columnas(string $tabla): array
{
    
    return db()->query("SHOW COLUMNS FROM `$tabla`")->fetchAll();
}


function valoresDe(string $tabla, array $columnas, array $datos): array
{
    $valores = [];
    foreach ($columnas as $c) {
        $campo = $c['Field'];
        if ($c['Key'] === 'PRI' || !array_key_exists($campo, $datos)) {
            continue;
        }
        $valor = is_string($datos[$campo]) ? trim($datos[$campo]) : $datos[$campo];
        if (($valor === '' || $valor === 'NULL') && $c['Null'] === 'YES') {
            $valor = null;
        }
        if ($tabla === 'usuario' && $campo === 'password_hash' && $valor !== null && !preg_match('/^\$2[aby]\$/', $valor)) {
            $valor = password_hash($valor, PASSWORD_DEFAULT);
        }
        $valores[$campo] = $valor;
    }
    if (!$valores) responderError('No llegaron datos para guardar.');
    return $valores;
}

ejecutar(function () {
    requerirSesion(['administrador']);

    if (metodo() === 'GET' && !isset($_GET['tabla'])) {
        $lista = [];
        foreach (TABLAS as $t) {
            $lista[] = ['tabla' => $t, 'registros' => (int) db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn()];
        }
        responder($lista);
    }

    $tabla = $_GET['tabla'] ?? '';
    if (!in_array($tabla, TABLAS, true)) responderError('La tabla no existe.', 404);
    $columnas = columnas($tabla);
    $pk = $columnas[0]['Field'];
    $id = (int) ($_GET['id'] ?? 0);

    switch (metodo()) {
        case 'GET':
            $filas = db()->query("SELECT * FROM `$tabla` ORDER BY `$pk` LIMIT 1000")->fetchAll();
            responder([
                'tabla'    => $tabla,
                'clave'    => $pk,
                'columnas' => array_map(fn ($c) => ['nombre' => $c['Field'], 'tipo' => $c['Type'], 'nulo' => $c['Null'] === 'YES'], $columnas),
                'filas'    => $filas,
            ]);

        case 'POST':
            $valores = valoresDe($tabla, $columnas, leerJSON()['datos'] ?? []);
            $campos = '`' . implode('`, `', array_keys($valores)) . '`';
            $marcas = implode(', ', array_fill(0, count($valores), '?'));
            db()->prepare("INSERT INTO `$tabla` ($campos) VALUES ($marcas)")->execute(array_values($valores));
            responder(['id' => (int) db()->lastInsertId()], 201);

        case 'PUT':
            $valores = valoresDe($tabla, $columnas, leerJSON()['datos'] ?? []);
            $asignaciones = implode(', ', array_map(fn ($c) => "`$c` = ?", array_keys($valores)));
            db()->prepare("UPDATE `$tabla` SET $asignaciones WHERE `$pk` = ?")->execute([...array_values($valores), $id]);
            responder(['ok' => true]);

        case 'DELETE':
            db()->prepare("DELETE FROM `$tabla` WHERE `$pk` = ?")->execute([$id]);
            responder(['ok' => true]);

        default:
            responderError('Método no permitido.', 405);
    }
});
