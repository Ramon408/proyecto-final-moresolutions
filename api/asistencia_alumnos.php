<?php

require __DIR__ . '/conexion.php';

const ROLES_CARGAN_ASISTENCIA = ['administrador', 'preceptor'];

function controlarDictado(array $usuario, int $idDictado): void
{
    $consulta = db()->prepare('SELECT id_docente FROM dictado WHERE id_dictado = ?');
    $consulta->execute([$idDictado]);
    $idDocente = $consulta->fetchColumn();
    if ($idDocente === false) responderError('La materia no existe en ese curso.', 404);
    if ($usuario['rol'] === 'docente' && (int) $idDocente !== (int) $usuario['id_docente']) {
        responderError('Solo podés ver la asistencia de tus materias.', 403);
    }
}


function diasDelDictado(int $idDictado): array
{
    $consulta = db()->prepare('SELECT DISTINCT dia_semana FROM horario WHERE id_dictado = ? ORDER BY dia_semana');
    $consulta->execute([$idDictado]);
    return array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
}


function nombresDias(array $dias): string
{
    $nombres = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábados', 7 => 'domingos'];
    $lista = array_map(function ($d) use ($nombres) { return $nombres[$d]; }, $dias);
    if (count($lista) <= 1) return implode('', $lista);
    return implode(', ', array_slice($lista, 0, -1)) . ' y ' . end($lista);
}


function motivoSinClase(array $dias, string $fecha): ?string
{
    if (!$dias) {
        return 'Esta materia no tiene horario cargado: no se puede tomar lista.';
    }
    $diaFecha = (int) date('N', strtotime($fecha));
    if (!in_array($diaFecha, $dias, true)) {
        return 'Esta materia no se dicta los ' . nombresDias([$diaFecha]) . '. Se dicta los ' . nombresDias($dias) . '.';
    }
    return null;
}

ejecutar(function () {
    $usuario = requerirSesion(['administrador', 'directivo', 'preceptor', 'docente']);

    if (metodo() === 'GET') {
        $idDictado = (int) ($_GET['id_dictado'] ?? 0);
        $fecha = fechaValida($_GET['fecha'] ?? '') ? $_GET['fecha'] : date('Y-m-d');
        controlarDictado($usuario, $idDictado);

        $consulta = db()->prepare('SELECT id_clase, tema FROM clase WHERE id_dictado = ? AND fecha = ?');
        $consulta->execute([$idDictado, $fecha]);
        $clase = $consulta->fetch() ?: null;

        $consulta = db()->prepare(
            "SELECT a.id_alumno, a.apellido, a.nombre,
                    (SELECT aa.estado FROM asistencia_alumno aa WHERE aa.id_clase = ? AND aa.id_alumno = a.id_alumno) AS estado,
                    (SELECT ROUND(100 * (COUNT(*) - SUM(aa.estado = 'A') - 0.5 * SUM(aa.estado = 'T')) / COUNT(*))
                     FROM asistencia_alumno aa JOIN clase cl ON cl.id_clase = aa.id_clase
                     WHERE cl.id_dictado = d.id_dictado AND aa.id_alumno = a.id_alumno) AS porcentaje
             FROM dictado d
             JOIN inscripcion i ON i.id_curso = d.id_curso AND i.estado = 'regular'
             JOIN alumno a ON a.id_alumno = i.id_alumno
             WHERE d.id_dictado = ?
             ORDER BY a.apellido, a.nombre"
        );
        $consulta->execute([$clase['id_clase'] ?? 0, $idDictado]);
        $alumnos = array_map(function ($a) {
            $a['porcentaje'] = $a['porcentaje'] === null ? null : (int) $a['porcentaje'];
            $a['cerca_limite'] = $a['porcentaje'] !== null && $a['porcentaje'] < LIMITE_ASISTENCIA;
            return $a;
        }, $consulta->fetchAll());

        $dias = diasDelDictado($idDictado);
        responder([
            'clase'       => $clase,
            'alumnos'     => $alumnos,
            'limite'      => LIMITE_ASISTENCIA,
            'dias'        => $dias,
            'sin_clase'   => motivoSinClase($dias, $fecha),
            'puede_cargar' => in_array($usuario['rol'], ROLES_CARGAN_ASISTENCIA, true),
        ]);
    }

    if (metodo() !== 'POST') responderError('Método no permitido.', 405);
    if (!in_array($usuario['rol'], ROLES_CARGAN_ASISTENCIA, true)) {
        responderError('Solo el preceptor puede cargar la asistencia de los alumnos. Vos podés consultarla.', 403);
    }

    $d = leerJSON();
    $idDictado = (int) ($d['id_dictado'] ?? 0);
    $fecha = $d['fecha'] ?? '';
    $lista = $d['lista'] ?? [];
    controlarDictado($usuario, $idDictado);
    if (!fechaValida($fecha)) responderError('La fecha de la clase no es válida.');
    if ($fecha > date('Y-m-d')) responderError('No se puede tomar lista de una fecha futura.');
    $motivo = motivoSinClase(diasDelDictado($idDictado), $fecha);
    if ($motivo !== null) responderError($motivo);
    if (!is_array($lista) || !$lista) responderError('La lista está vacía.');
    foreach ($lista as $fila) {
        if (!in_array($fila['estado'] ?? '', ['P', 'A', 'T', 'J'], true)) {
            responderError('Hay alumnos sin marcar.');
        }
    }

    db()->beginTransaction();
    $consulta = db()->prepare(
        'INSERT INTO clase (id_dictado, fecha, tema) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE tema = VALUES(tema), id_clase = LAST_INSERT_ID(id_clase)'
    );
    $consulta->execute([$idDictado, $fecha, texto($d['tema'] ?? '', 200)]);
    $idClase = (int) db()->lastInsertId();

    $consulta = db()->prepare(
        'INSERT INTO asistencia_alumno (id_clase, id_alumno, estado) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE estado = VALUES(estado)'
    );
    foreach ($lista as $fila) {
        $consulta->execute([$idClase, (int) $fila['id_alumno'], $fila['estado']]);
    }
    db()->commit();
    responder(['id_clase' => $idClase]);
});
