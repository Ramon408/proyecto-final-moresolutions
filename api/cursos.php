<?php

require __DIR__ . '/conexion.php';

const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie'];

function textoHorario(?string $horarios): string
{
    if (!$horarios) return 'Sin horario';
    $partes = [];
    foreach (explode(',', $horarios) as $h) {
        [$dia, $inicio, $fin] = explode('|', $h);
        $partes[] = DIAS[(int) $dia] . ' ' . ltrim(substr($inicio, 0, 5), '0') . '–' . ltrim(substr($fin, 0, 5), '0');
    }
    return implode(' · ', $partes);
}

ejecutar(function () {
    if (metodo() === 'GET' && isset($_GET['id'])) {
        requerirSesion();
        $id = (int) $_GET['id'];

        $consulta = db()->prepare(
            'SELECT c.id_curso, c.anio, c.division, c.turno, c.ciclo_lectivo, p.nombre AS plan
             FROM curso c JOIN plan_estudio p ON p.id_plan = c.id_plan WHERE c.id_curso = ?'
        );
        $consulta->execute([$id]);
        $curso = $consulta->fetch();
        if (!$curso) responderError('El curso no existe.', 404);

        $consulta = db()->prepare(
            "SELECT d.id_dictado, m.nombre AS materia, d.id_docente,
                    CONCAT(doc.apellido, ', ', doc.nombre) AS docente,
                    GROUP_CONCAT(CONCAT(h.dia_semana, '|', h.hora_inicio, '|', h.hora_fin)
                                 ORDER BY h.dia_semana, h.hora_inicio) AS horarios
             FROM dictado d
             JOIN materia m ON m.id_materia = d.id_materia
             LEFT JOIN docente doc ON doc.id_docente = d.id_docente
             LEFT JOIN horario h ON h.id_dictado = d.id_dictado
             WHERE d.id_curso = ?
             GROUP BY d.id_dictado, m.nombre, d.id_docente, doc.apellido, doc.nombre, m.id_materia
             ORDER BY m.id_materia"
        );
        $consulta->execute([$id]);
        $dictados = array_map(function ($d) {
            $d['horario'] = textoHorario($d['horarios']);
            unset($d['horarios']);
            return $d;
        }, $consulta->fetchAll());

        $consulta = db()->prepare(
            'SELECT a.id_alumno, a.dni, a.apellido, a.nombre, i.estado
             FROM inscripcion i JOIN alumno a ON a.id_alumno = i.id_alumno
             WHERE i.id_curso = ? ORDER BY a.apellido, a.nombre'
        );
        $consulta->execute([$id]);

        responder(['curso' => $curso, 'dictados' => $dictados, 'alumnos' => $consulta->fetchAll()]);
    }

    if (metodo() === 'GET') {
        requerirSesion();
        $ciclo = (int) ($_GET['ciclo'] ?? 0) ?: cicloActual();
        $consulta = db()->prepare(
            'SELECT id_curso, anio, division, turno FROM curso
             WHERE ciclo_lectivo = ? ORDER BY anio, division'
        );
        $consulta->execute([$ciclo]);
        $ciclos = db()->query('SELECT DISTINCT ciclo_lectivo FROM curso ORDER BY ciclo_lectivo DESC')->fetchAll(PDO::FETCH_COLUMN);
        responder(['ciclo' => $ciclo, 'ciclos' => array_map('intval', $ciclos), 'cursos' => $consulta->fetchAll()]);
    }

    if (metodo() === 'POST') {
        requerirSesion(['administrador', 'directivo']);
        $d = leerJSON();
        $anio = (int) ($d['anio'] ?? 0);
        $division = strtoupper(trim($d['division'] ?? ''));
        $turno = mb_strtolower(trim($d['turno'] ?? ''));
        $ciclo = (int) ($d['ciclo_lectivo'] ?? 0) ?: cicloActual();
        if ($anio < 1 || $anio > 7 || !preg_match('/^[A-Z]$/', $division) || !in_array($turno, ['mañana', 'tarde', 'noche'], true)) {
            responderError('Revisá el año, la división y el turno.');
        }

        
        $idPlan = db()->query('SELECT id_plan FROM plan_estudio WHERE activo ORDER BY anio_vigencia DESC LIMIT 1')->fetchColumn();
        if (!$idPlan) responderError('No hay un plan de estudios vigente.');

        db()->beginTransaction();
        $consulta = db()->prepare('INSERT INTO curso (id_plan, anio, division, turno, ciclo_lectivo) VALUES (?, ?, ?, ?, ?)');
        try {
            $consulta->execute([$idPlan, $anio, $division, $turno, $ciclo]);
        } catch (PDOException $e) {
            db()->rollBack();
            responderError('Ese curso ya existe en el ciclo lectivo.', 409);
        }
        $idCurso = (int) db()->lastInsertId();

        
        $consulta = db()->prepare('INSERT INTO dictado (id_curso, id_materia) SELECT ?, id_materia FROM materia WHERE id_plan = ? AND anio = ?');
        $consulta->execute([$idCurso, $idPlan, $anio]);
        db()->commit();
        responder(['id_curso' => $idCurso], 201);
    }

    responderError('Método no permitido.', 405);
});
