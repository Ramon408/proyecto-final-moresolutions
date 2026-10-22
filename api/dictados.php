<?php

require __DIR__ . '/conexion.php';

ejecutar(function () {
    if (metodo() === 'GET') {
        $usuario = requerirSesion();
        $sql = "SELECT d.id_dictado, d.id_curso, m.nombre AS materia,
                       CONCAT(c.anio, '.º ', c.division) AS curso,
                       CONCAT(doc.apellido, ', ', doc.nombre) AS docente
                FROM dictado d
                JOIN materia m ON m.id_materia = d.id_materia
                JOIN curso c ON c.id_curso = d.id_curso
                LEFT JOIN docente doc ON doc.id_docente = d.id_docente";
        $parametros = [];

        if (isset($_GET['curso'])) {
            $sql .= ' WHERE d.id_curso = ?';
            $parametros[] = (int) $_GET['curso'];
        } else {
            $sql .= ' WHERE c.ciclo_lectivo = ?';
            $parametros[] = cicloActual();
            if ($usuario['rol'] === 'docente') {
                $sql .= ' AND d.id_docente = ?';
                $parametros[] = $usuario['id_docente'] ?? 0;
            }
        }
        $sql .= ' ORDER BY c.anio, c.division, m.id_materia';
        $consulta = db()->prepare($sql);
        $consulta->execute($parametros);
        responder($consulta->fetchAll());
    }

    if (metodo() === 'PUT') {
        requerirSesion(['administrador', 'directivo']);
        $idDocente = leerJSON()['id_docente'] ?? null;
        $idDocente = $idDocente ? (int) $idDocente : null;
        $consulta = db()->prepare('UPDATE dictado SET id_docente = ? WHERE id_dictado = ?');
        $consulta->execute([$idDocente, (int) ($_GET['id'] ?? 0)]);
        responder(['ok' => true]);
    }

    responderError('Método no permitido.', 405);
});
