<?php

require __DIR__ . '/conexion.php';

ejecutar(function () {
    switch (metodo()) {
        case 'GET':
            responder(requerirSesion());

        case 'POST':
            $datos = leerJSON();
            $username = trim($datos['usuario'] ?? '');
            $password = (string) ($datos['password'] ?? '');
            if ($username === '' || $password === '') {
                responderError('Completá el usuario y la contraseña.');
            }

            $consulta = db()->prepare(
                'SELECT u.id_usuario, u.username, u.password_hash, u.activo, r.nombre AS rol,
                        d.id_docente, d.nombre, d.apellido
                 FROM usuario u
                 JOIN rol r ON r.id_rol = u.id_rol
                 LEFT JOIN docente d ON d.id_usuario = u.id_usuario
                 WHERE u.username = ?'
            );
            $consulta->execute([$username]);
            $u = $consulta->fetch();

            if (!$u || !password_verify($password, $u['password_hash'])) {
                responderError('Usuario o contraseña incorrectos.', 401);
            }
            if (!$u['activo']) {
                responderError('El usuario está dado de baja. Consultá con el administrador.', 403);
            }

            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id_usuario' => (int) $u['id_usuario'],
                'username'   => $u['username'],
                'rol'        => $u['rol'],
                'id_docente' => $u['id_docente'] ? (int) $u['id_docente'] : null,
                'nombre'     => $u['id_docente'] ? $u['nombre'] . ' ' . $u['apellido'] : $u['username'],
            ];
            responder($_SESSION['usuario']);

        case 'DELETE':
            $_SESSION = [];
            session_destroy();
            responder(['ok' => true]);

        default:
            responderError('Método no permitido.', 405);
    }
});
