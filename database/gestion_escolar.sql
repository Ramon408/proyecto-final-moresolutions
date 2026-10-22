
SET NAMES utf8mb4;
SET time_zone = '-03:00';

DROP DATABASE IF EXISTS gestion_escolar;
CREATE DATABASE gestion_escolar CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE gestion_escolar;


CREATE TABLE rol (
  id_rol INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE usuario (
  id_usuario    INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  id_rol        INT NOT NULL,
  activo        BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES rol (id_rol)
);



CREATE TABLE docente (
  id_docente INT AUTO_INCREMENT PRIMARY KEY,
  dni        VARCHAR(10) NOT NULL UNIQUE,
  nombre     VARCHAR(50) NOT NULL,
  apellido   VARCHAR(50) NOT NULL,
  email      VARCHAR(100) NULL,
  telefono   VARCHAR(20) NULL,
  id_usuario INT NULL UNIQUE,
  CONSTRAINT fk_docente_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE SET NULL
);

CREATE TABLE alumno (
  id_alumno  INT AUTO_INCREMENT PRIMARY KEY,
  dni        VARCHAR(10) NOT NULL UNIQUE,
  nombre     VARCHAR(50) NOT NULL,
  apellido   VARCHAR(50) NOT NULL,
  fecha_nac  DATE NOT NULL,
  email      VARCHAR(100) NULL,
  id_usuario INT NULL UNIQUE,
  CONSTRAINT fk_alumno_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE SET NULL
);



CREATE TABLE plan_estudio (
  id_plan       INT AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(100) NOT NULL,
  resolucion    VARCHAR(30) NULL,
  anio_vigencia INT NOT NULL,
  activo        BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE materia (
  id_materia    INT AUTO_INCREMENT PRIMARY KEY,
  id_plan       INT NOT NULL,
  nombre        VARCHAR(100) NOT NULL,
  anio          TINYINT NOT NULL,
  carga_horaria TINYINT NOT NULL,
  CONSTRAINT fk_materia_plan FOREIGN KEY (id_plan) REFERENCES plan_estudio (id_plan),
  CONSTRAINT chk_materia_anio CHECK (anio BETWEEN 1 AND 7),
  CONSTRAINT chk_materia_carga CHECK (carga_horaria BETWEEN 1 AND 20)
);

CREATE TABLE curso (
  id_curso      INT AUTO_INCREMENT PRIMARY KEY,
  id_plan       INT NOT NULL,
  anio          TINYINT NOT NULL,
  division      CHAR(1) NOT NULL,
  turno         VARCHAR(10) NOT NULL,
  ciclo_lectivo INT NOT NULL,
  CONSTRAINT fk_curso_plan FOREIGN KEY (id_plan) REFERENCES plan_estudio (id_plan),
  CONSTRAINT uq_curso UNIQUE (anio, division, ciclo_lectivo),
  CONSTRAINT chk_curso_anio CHECK (anio BETWEEN 1 AND 7),
  CONSTRAINT chk_curso_turno CHECK (turno IN ('mañana', 'tarde', 'noche'))
);


CREATE TABLE inscripcion (
  id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
  id_alumno      INT NOT NULL,
  id_curso       INT NOT NULL,
  fecha          DATE NOT NULL,
  estado         VARCHAR(15) NOT NULL DEFAULT 'regular',
  CONSTRAINT fk_inscripcion_alumno FOREIGN KEY (id_alumno) REFERENCES alumno (id_alumno),
  CONSTRAINT fk_inscripcion_curso FOREIGN KEY (id_curso) REFERENCES curso (id_curso),
  CONSTRAINT uq_inscripcion UNIQUE (id_alumno, id_curso),
  CONSTRAINT chk_inscripcion_estado CHECK (estado IN ('regular', 'pase', 'baja'))
);


CREATE TABLE dictado (
  id_dictado INT AUTO_INCREMENT PRIMARY KEY,
  id_curso   INT NOT NULL,
  id_materia INT NOT NULL,
  id_docente INT NULL,
  CONSTRAINT fk_dictado_curso FOREIGN KEY (id_curso) REFERENCES curso (id_curso),
  CONSTRAINT fk_dictado_materia FOREIGN KEY (id_materia) REFERENCES materia (id_materia),
  CONSTRAINT fk_dictado_docente FOREIGN KEY (id_docente) REFERENCES docente (id_docente) ON DELETE SET NULL,
  CONSTRAINT uq_dictado UNIQUE (id_curso, id_materia)
);

CREATE TABLE horario (
  id_horario  INT AUTO_INCREMENT PRIMARY KEY,
  id_dictado  INT NOT NULL,
  dia_semana  TINYINT NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin    TIME NOT NULL,
  CONSTRAINT fk_horario_dictado FOREIGN KEY (id_dictado) REFERENCES dictado (id_dictado) ON DELETE CASCADE,
  CONSTRAINT chk_horario_dia CHECK (dia_semana BETWEEN 1 AND 5),
  CONSTRAINT chk_horario_horas CHECK (hora_fin > hora_inicio)
);



CREATE TABLE asistencia_docente (
  id_asist_doc INT AUTO_INCREMENT PRIMARY KEY,
  id_docente   INT NOT NULL,
  fecha        DATE NOT NULL,
  hora_entrada TIME NULL,
  hora_salida  TIME NULL,
  observacion  VARCHAR(200) NULL,
  CONSTRAINT fk_asistdoc_docente FOREIGN KEY (id_docente) REFERENCES docente (id_docente),
  CONSTRAINT uq_asistdoc UNIQUE (id_docente, fecha),
  CONSTRAINT chk_asistdoc_horas CHECK (hora_salida IS NULL OR hora_entrada IS NULL OR hora_salida > hora_entrada)
);

CREATE TABLE clase (
  id_clase   INT AUTO_INCREMENT PRIMARY KEY,
  id_dictado INT NOT NULL,
  fecha      DATE NOT NULL,
  tema       VARCHAR(200) NULL,
  CONSTRAINT fk_clase_dictado FOREIGN KEY (id_dictado) REFERENCES dictado (id_dictado),
  CONSTRAINT uq_clase UNIQUE (id_dictado, fecha)
);

CREATE TABLE asistencia_alumno (
  id_asist_alu INT AUTO_INCREMENT PRIMARY KEY,
  id_clase     INT NOT NULL,
  id_alumno    INT NOT NULL,
  estado       CHAR(1) NOT NULL,
  CONSTRAINT fk_asistalu_clase FOREIGN KEY (id_clase) REFERENCES clase (id_clase) ON DELETE CASCADE,
  CONSTRAINT fk_asistalu_alumno FOREIGN KEY (id_alumno) REFERENCES alumno (id_alumno),
  CONSTRAINT uq_asistalu UNIQUE (id_clase, id_alumno),
  CONSTRAINT chk_asistalu_estado CHECK (estado IN ('P', 'A', 'T', 'J'))
);

CREATE TABLE calificacion (
  id_calificacion INT AUTO_INCREMENT PRIMARY KEY,
  id_alumno       INT NOT NULL,
  id_dictado      INT NOT NULL,
  instancia       VARCHAR(20) NOT NULL,
  nota            DECIMAL(4,2) NOT NULL,
  fecha           DATE NOT NULL,
  CONSTRAINT fk_calif_alumno FOREIGN KEY (id_alumno) REFERENCES alumno (id_alumno),
  CONSTRAINT fk_calif_dictado FOREIGN KEY (id_dictado) REFERENCES dictado (id_dictado),
  CONSTRAINT uq_calificacion UNIQUE (id_alumno, id_dictado, instancia),
  CONSTRAINT chk_calif_instancia CHECK (instancia IN ('1.º trimestre', '2.º trimestre', '3.º trimestre', 'diciembre', 'febrero', 'final')),
  CONSTRAINT chk_calif_nota CHECK (nota BETWEEN 1 AND 10)
);


INSERT INTO rol (id_rol, nombre) VALUES
  (1, 'administrador'), (2, 'directivo'), (3, 'preceptor'), (4, 'docente');

INSERT INTO usuario (id_usuario, username, password_hash, id_rol, activo) VALUES
  (1, 'admin',      '$2y$10$8a.58uxKfcAFp2FRS/lG7uxVyBciTPeBuVwxMe.x3XQty.fq1W096', 1, TRUE),
  (2, 'lfernandez', '$2y$10$88.drnRAYkn0llNXyxG7yuipYRpqbmZwVdh5Q2rH3dqgrLvxGwV/W', 2, TRUE),
  (3, 'nsosa',      '$2y$10$88.drnRAYkn0llNXyxG7yuipYRpqbmZwVdh5Q2rH3dqgrLvxGwV/W', 3, TRUE),
  (4, 'gperalta',   '$2y$10$88.drnRAYkn0llNXyxG7yuipYRpqbmZwVdh5Q2rH3dqgrLvxGwV/W', 4, TRUE),
  (5, 'crios',      '$2y$10$88.drnRAYkn0llNXyxG7yuipYRpqbmZwVdh5Q2rH3dqgrLvxGwV/W', 4, TRUE);

INSERT INTO docente (id_docente, dni, nombre, apellido, email, telefono, id_usuario) VALUES
  (1, '28123456', 'Gustavo',  'Peralta', 'gperalta@escuela.edu.ar', '11-4555-1234', 4),
  (2, '30234567', 'Carolina', 'Ríos',    'crios@escuela.edu.ar',    '11-4555-2345', 5),
  (3, '27345678', 'Ana',      'Medina',  NULL, NULL, NULL),
  (4, '31456789', 'Pablo',    'Suárez',  NULL, NULL, NULL),
  (5, '29567890', 'Lucía',    'Torres',  NULL, NULL, NULL),
  (6, '26678901', 'Hernán',   'Ramírez', NULL, NULL, NULL);

INSERT INTO alumno (id_alumno, dni, nombre, apellido, fecha_nac, email, id_usuario) VALUES
  (1,  '47123456', 'Mario',    'Acosta',    '2013-03-14', NULL, NULL),
  (2,  '47234567', 'Lucas',    'Benítez',   '2013-07-02', NULL, NULL),
  (3,  '47345678', 'Sofía',    'Castro',    '2013-11-21', NULL, NULL),
  (4,  '47456789', 'Valentina','Díaz',      '2013-05-09', NULL, NULL),
  (5,  '47567890', 'Tomás',    'Gómez',     '2013-08-30', NULL, NULL),
  (6,  '47678901', 'Camila',   'López',     '2013-01-17', NULL, NULL),
  (7,  '47789012', 'Lautaro',  'Ortiz',     '2013-06-25', NULL, NULL),
  (8,  '47890123', 'Agustina', 'Molina',    '2013-10-03', NULL, NULL),
  (9,  '47901234', 'Bruno',    'Navarro',   '2013-12-12', NULL, NULL),
  (10, '46789012', 'Julián',   'Fernández', '2012-04-19', NULL, NULL),
  (11, '46890123', 'Martina',  'Herrera',   '2012-09-07', NULL, NULL),
  (12, '45901234', 'Nicolás',  'Ibarra',    '2011-02-28', NULL, NULL);

INSERT INTO plan_estudio (id_plan, nombre, resolucion, anio_vigencia, activo) VALUES
  (1, 'Plan Técnico 2026', '1234/25', 2026, TRUE),
  (2, 'Plan Técnico 2018', '0567/17', 2018, FALSE);

INSERT INTO materia (id_materia, id_plan, nombre, anio, carga_horaria) VALUES
  (1,  1, 'Matemática', 1, 5), (2, 1, 'Lengua y Literatura', 1, 4), (3, 1, 'Ciencias Naturales', 1, 3), (4, 1, 'Taller', 1, 6),
  (5,  1, 'Matemática', 2, 5), (6, 1, 'Física', 2, 3), (7, 1, 'Dibujo Técnico', 2, 3), (8, 1, 'Taller', 2, 6),
  (9,  1, 'Matemática', 3, 4), (10, 1, 'Química', 3, 3), (11, 1, 'Inglés', 3, 3), (12, 1, 'Taller', 3, 6),
  (13, 1, 'Matemática', 4, 4), (14, 1, 'Electrotecnia', 4, 4), (15, 1, 'Programación', 4, 4),
  (16, 1, 'Análisis Matemático', 5, 4), (17, 1, 'Sistemas Digitales', 5, 4), (18, 1, 'Laboratorio', 5, 6),
  (19, 1, 'Proyecto', 6, 4), (20, 1, 'Redes', 6, 4), (21, 1, 'Laboratorio', 6, 6),
  (22, 1, 'Prácticas Profesionalizantes', 7, 8), (23, 1, 'Gestión de Proyectos', 7, 3),
  (24, 2, 'Matemática', 1, 5), (25, 2, 'Lengua y Literatura', 1, 5);

INSERT INTO curso (id_curso, id_plan, anio, division, turno, ciclo_lectivo) VALUES
  (1, 1, 1, 'A', 'mañana', 2026),
  (2, 1, 1, 'B', 'tarde',  2026),
  (3, 1, 2, 'A', 'mañana', 2026),
  (4, 1, 3, 'A', 'mañana', 2026),
  (5, 1, 1, 'A', 'mañana', 2025);

INSERT INTO inscripcion (id_alumno, id_curso, fecha, estado) VALUES
  (1, 1, '2026-02-20', 'regular'), (2, 1, '2026-02-20', 'regular'), (3, 1, '2026-02-20', 'regular'),
  (4, 1, '2026-02-21', 'regular'), (5, 1, '2026-02-21', 'regular'), (6, 1, '2026-02-23', 'regular'),
  (7, 1, '2026-02-23', 'pase'),
  (8, 2, '2026-02-20', 'regular'), (9, 2, '2026-02-20', 'regular'),
  (10, 3, '2026-02-19', 'regular'), (11, 3, '2026-02-19', 'regular'),
  (12, 4, '2026-02-18', 'regular');


INSERT INTO dictado (id_dictado, id_curso, id_materia, id_docente) VALUES
  (1, 1, 1, 1),  (2, 1, 2, 3),  (3, 1, 3, NULL), (4, 1, 4, 2),
  (5, 2, 1, 4),  (6, 2, 2, 3),  (7, 2, 3, 5),    (8, 2, 4, 2),
  (9, 3, 5, 1),  (10, 3, 6, 4), (11, 3, 7, NULL), (12, 3, 8, 6),
  (13, 4, 9, 1), (14, 4, 10, 5), (15, 4, 11, 3), (16, 4, 12, 6);


INSERT INTO horario (id_dictado, dia_semana, hora_inicio, hora_fin) VALUES
  (1, 1, '07:30', '09:30'), (1, 3, '07:30', '08:50'), (2, 2, '07:30', '09:30'), (3, 5, '07:30', '09:30'),
  (4, 4, '13:00', '17:00'), (5, 1, '13:00', '15:00'), (6, 3, '13:00', '15:00'), (7, 4, '13:00', '15:00'),
  (8, 5, '08:00', '12:00'), (9, 2, '09:40', '11:40'), (10, 4, '07:30', '09:30'), (11, 5, '09:40', '11:40'),
  (12, 3, '13:00', '17:00'), (13, 3, '09:40', '11:40'), (14, 1, '09:40', '11:40'), (15, 2, '07:30', '09:30'),
  (16, 4, '13:00', '17:00');


INSERT INTO asistencia_docente (id_docente, fecha, hora_entrada, hora_salida, observacion) VALUES
  (1, CURDATE(), '07:20', '12:30', NULL),
  (3, CURDATE(), '07:45', NULL, 'Llegó tarde'),
  (4, CURDATE(), NULL, NULL, 'Ausente con aviso'),
  (1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:25', '12:30', NULL),
  (2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:50', '17:10', NULL);


INSERT INTO clase (id_clase, id_dictado, fecha, tema) VALUES
  (1, 1, '2026-09-23', 'Números enteros'),
  (2, 1, '2026-09-28', 'Operaciones con enteros'),
  (3, 1, '2026-09-30', 'Ejercitación'),
  (4, 1, '2026-10-05', 'Fracciones'),
  (5, 1, '2026-10-07', 'Fracciones equivalentes'),
  (6, 1, '2026-10-12', 'Operaciones con fracciones'),
  (7, 1, '2026-10-14', 'Ecuaciones de primer grado'),
  (8, 1, '2026-10-19', 'Problemas con ecuaciones');


INSERT INTO asistencia_alumno (id_clase, id_alumno, estado)
  SELECT c.id_clase, a.id_alumno, 'P'
  FROM clase c CROSS JOIN alumno a
  WHERE c.id_dictado = 1 AND a.id_alumno BETWEEN 1 AND 6;

UPDATE asistencia_alumno SET estado = 'A' WHERE (id_alumno = 2 AND id_clase IN (2, 6)) OR (id_alumno = 5 AND id_clase IN (1, 5));
UPDATE asistencia_alumno SET estado = 'T' WHERE (id_alumno = 3 AND id_clase = 4) OR (id_alumno = 4 AND id_clase = 7) OR (id_alumno = 5 AND id_clase = 8);
UPDATE asistencia_alumno SET estado = 'J' WHERE id_alumno = 4 AND id_clase = 3;


INSERT INTO calificacion (id_alumno, id_dictado, instancia, nota, fecha) VALUES
  (1, 1, '1.º trimestre', 8, '2026-05-29'), (1, 1, '2.º trimestre', 7, '2026-08-28'), (1, 1, '3.º trimestre', 9, '2026-10-16'),
  (2, 1, '1.º trimestre', 5, '2026-05-29'), (2, 1, '2.º trimestre', 6, '2026-08-28'), (2, 1, '3.º trimestre', 6, '2026-10-16'),
  (3, 1, '1.º trimestre', 9, '2026-05-29'), (3, 1, '2.º trimestre', 8, '2026-08-28'),
  (4, 1, '1.º trimestre', 7, '2026-05-29'), (4, 1, '2.º trimestre', 7, '2026-08-28'), (4, 1, '3.º trimestre', 7, '2026-10-16'),
  (5, 1, '1.º trimestre', 6, '2026-05-29');
