const $ = (sel, raiz = document) => raiz.querySelector(sel);
const $$ = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];

let USUARIO = null;

const NOMBRE_ROL = { administrador: 'Administrador', directivo: 'Directivo', preceptor: 'Preceptor', docente: 'Docente' };
const INSTANCIAS = ['1.º trimestre', '2.º trimestre', '3.º trimestre'];


function hoyISO() {
  const d = new Date();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

function esFinDeSemana(fecha) {
  const dia = new Date(`${fecha}T12:00:00`).getDay();
  return dia === 0 || dia === 6;
}

function diaHabilAnterior(fecha) {
  const d = new Date(`${fecha}T12:00:00`);
  while (d.getDay() === 0 || d.getDay() === 6) d.setDate(d.getDate() - 1);
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

function formatoFecha(iso) {
  const [a, m, d] = iso.split('-');
  return `${d}/${m}/${a}`;
}

function mostrarMensaje(el, texto, tipo = 'ok') {
  el.textContent = texto;
  el.classList.toggle('error', tipo === 'error');
  el.classList.remove('oculto');
  clearTimeout(el._timer);
  el._timer = setTimeout(() => el.classList.add('oculto'), 5000);
}

function esc(t) {
  return String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function puede(roles) {
  return USUARIO && roles.includes(USUARIO.rol);
}

function nombreCurso(c) {
  return `${c.anio}.º ${c.division}`;
}

async function api(url, { method = 'GET', body } = {}) {
  let respuesta;
  try {
    respuesta = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new Error('No se pudo conectar con el servidor. Abrí el sistema desde http://localhost con Apache y MySQL iniciados.');
  }
  const datos = await respuesta.json().catch(() => ({}));
  if (respuesta.status === 401 && document.body.dataset.pagina !== 'login' && url !== 'api/sesion.php') {
    window.location.href = 'index.html';
  }
  if (!respuesta.ok) {
    throw new Error(datos.error || `Error del servidor (${respuesta.status}).`);
  }
  return datos;
}


function aplicarPermisos(raiz = document) {
  $$('[data-roles]', raiz).forEach(el => {
    el.classList.toggle('oculto', !puede(el.dataset.roles.split(',')));
  });
}

function iniciarLogin() {
  const form = $('#form-login');
  const msg = $('#mensaje-login');
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const usuario = form.usuario.value.trim();
    const password = form.password.value;
    if (!usuario || !password) {
      mostrarMensaje(msg, 'Completá el usuario y la contraseña.', 'error');
      return;
    }
    try {
      await api('api/sesion.php', { method: 'POST', body: { usuario, password } });
      window.location.href = 'inicio.html';
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  });
}

function mostrarUsuarioActual() {
  const lugar = $('#usuario-actual');
  if (!lugar || !USUARIO) return;
  lugar.textContent = `${USUARIO.nombre || USUARIO.username} · ${NOMBRE_ROL[USUARIO.rol] || USUARIO.rol}`;
  lugar.title = `Usuario: ${USUARIO.username}`;
}

async function iniciarInicio() {
  $('#hoy').textContent = new Date().toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' });
  const d = await api('api/inicio.php');
  $('#ciclo').textContent = d.ciclo;
  $('#dato-presentes').textContent = `Presentes hoy: ${d.presentes_hoy} de ${d.total_docentes}`;
  $('#dato-sin-lista').textContent = `Clases sin lista hoy: ${d.clases_sin_lista}`;
  $('#dato-instancia').textContent = `Instancia actual: ${d.instancia}`;
}

function iniciarPlanes() {
  let planActual = null;
  let anioActual = '1';
  let idEditando = null;
  const tbody = $('#tabla-materias tbody');
  const form = $('#form-materia');
  const formPlan = $('#form-plan');
  const msg = $('#mensaje-planes');
  const editable = puede(['administrador', 'directivo']);

  function filtrar() {
    $$('tr', tbody).forEach(tr => tr.classList.toggle('oculto', tr.dataset.anio !== anioActual));
    $$('.filtro-anio').forEach(b => b.classList.toggle('seleccionado', b.dataset.anio === anioActual));
    $('#titulo-anio').textContent = `Materias de ${anioActual}.º año`;
  }

  async function cargarPlanes(seleccionar) {
    const planes = await api('api/planes.php');
    planActual = seleccionar ?? planActual ?? planes[0]?.id_plan;
    $('#lista-planes').innerHTML = planes.map(p => `
      <button type="button" class="item-lista item-plan${p.id_plan === planActual ? ' activo' : ''}" data-plan="${p.id_plan}">
        <b>${esc(p.nombre)}${p.activo ? ' (vigente)' : ''}</b>
        <small>Res. ${esc(p.resolucion || 's/n')} · ${p.activo ? 'desde ' + p.anio_vigencia : 'inactivo'}</small>
      </button>`).join('');
    await cargarMaterias();
  }

  async function cargarMaterias() {
    if (!planActual) return;
    const materias = await api(`api/materias.php?id_plan=${planActual}`);
    tbody.innerHTML = materias.map(m => `
      <tr data-id="${m.id_materia}" data-anio="${m.anio}" data-nombre="${esc(m.nombre)}" data-carga="${m.carga_horaria}">
        <td>${esc(m.nombre)}</td><td>${m.anio}.º</td><td>${m.carga_horaria} hs</td>
        ${editable ? '<td><button type="button" class="link accion-editar">Editar</button> · <button type="button" class="link accion-borrar">Borrar</button></td>' : ''}
      </tr>`).join('');
    filtrar();
  }

  $$('.filtro-anio').forEach(b => b.addEventListener('click', () => { anioActual = b.dataset.anio; filtrar(); }));

  $('#lista-planes').addEventListener('click', e => {
    const b = e.target.closest('[data-plan]');
    if (!b) return;
    planActual = Number(b.dataset.plan);
    $$('.item-plan').forEach(x => x.classList.toggle('activo', x === b));
    cargarMaterias().catch(err => mostrarMensaje(msg, err.message, 'error'));
  });

  $('#btn-agregar-materia').addEventListener('click', () => {
    idEditando = null;
    form.reset();
    form.anio.value = anioActual;
    $('#titulo-form-materia').textContent = 'Nueva materia';
    form.classList.remove('oculto');
    form.nombre.focus();
  });
  $('#btn-cancelar-materia').addEventListener('click', () => form.classList.add('oculto'));

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const datos = { id_plan: planActual, nombre: form.nombre.value.trim(), anio: form.anio.value, carga_horaria: form.carga_horaria.value };
    if (!datos.nombre || !datos.carga_horaria) {
      mostrarMensaje(msg, 'Completá el nombre y la carga horaria.', 'error');
      return;
    }
    try {
      if (idEditando) {
        await api(`api/materias.php?id=${idEditando}`, { method: 'PUT', body: datos });
        mostrarMensaje(msg, `Se actualizó la materia ${datos.nombre}.`);
      } else {
        await api('api/materias.php', { method: 'POST', body: datos });
        mostrarMensaje(msg, `Se agregó la materia ${datos.nombre} a ${datos.anio}.º año.`);
      }
      anioActual = datos.anio;
      form.classList.add('oculto');
      await cargarMaterias();
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  });

  tbody.addEventListener('click', async e => {
    const tr = e.target.closest('tr');
    if (e.target.classList.contains('accion-borrar')) {
      if (!confirm(`¿Borrar la materia ${tr.dataset.nombre}?`)) return;
      try {
        await api(`api/materias.php?id=${tr.dataset.id}`, { method: 'DELETE' });
        mostrarMensaje(msg, `Se borró la materia ${tr.dataset.nombre}.`);
        await cargarMaterias();
      } catch (err) {
        mostrarMensaje(msg, err.message, 'error');
      }
    }
    if (e.target.classList.contains('accion-editar')) {
      idEditando = tr.dataset.id;
      form.nombre.value = tr.dataset.nombre;
      form.anio.value = tr.dataset.anio;
      form.carga_horaria.value = tr.dataset.carga;
      $('#titulo-form-materia').textContent = 'Editar materia';
      form.classList.remove('oculto');
      form.nombre.focus();
    }
  });

  $('#btn-nuevo-plan').addEventListener('click', () => { formPlan.classList.toggle('oculto'); formPlan.nombre.focus(); });
  $('#btn-cancelar-plan').addEventListener('click', () => formPlan.classList.add('oculto'));
  formPlan.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      const nuevo = await api('api/planes.php', {
        method: 'POST',
        body: { nombre: formPlan.nombre.value, resolucion: formPlan.resolucion.value, anio_vigencia: formPlan.anio_vigencia.value },
      });
      mostrarMensaje(msg, `Se creó el plan ${formPlan.nombre.value}.`);
      formPlan.reset();
      formPlan.classList.add('oculto');
      await cargarPlanes(nuevo.id_plan);
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  });

  return cargarPlanes();
}

function iniciarCursos() {
  let ciclo = null;
  let cursoActual = null;
  let docentes = [];
  const msg = $('#mensaje-cursos');
  const editable = puede(['administrador', 'directivo']);

  async function cargarCursos(seleccionar) {
    const d = await api(`api/cursos.php${ciclo ? '?ciclo=' + ciclo : ''}`);
    ciclo = d.ciclo;
    $('#ciclo').innerHTML = d.ciclos.map(c => `<option${c === ciclo ? ' selected' : ''}>${c}</option>`).join('');
    cursoActual = seleccionar ?? (d.cursos.some(c => c.id_curso === cursoActual) ? cursoActual : d.cursos[0]?.id_curso);
    $('#lista-cursos').innerHTML = d.cursos.map(c =>
      `<button type="button" class="item-lista${c.id_curso === cursoActual ? ' activo' : ''}" data-curso="${c.id_curso}"><b>${nombreCurso(c)}</b> · ${esc(c.turno)}</button>`).join('')
      || '<p class="ayuda">No hay cursos en este ciclo.</p>';
    await dibujarCurso();
  }

  async function dibujarCurso() {
    if (!cursoActual) {
      $('#titulo-curso').textContent = '';
      $('#tabla-dictados tbody').innerHTML = '';
      $('#tabla-inscriptos tbody').innerHTML = '';
      $('#cantidad-inscriptos').textContent = 0;
      return;
    }
    const { curso, dictados, alumnos } = await api(`api/cursos.php?id=${cursoActual}`);
    $('#titulo-curso').innerHTML = `<b>${nombreCurso(curso)}</b> · ${esc(curso.plan)} · Turno ${esc(curso.turno)}`;

    $('#tabla-dictados tbody').innerHTML = dictados.map(d => {
      const opciones = '<option value="">Sin asignar</option>' + docentes.map(doc =>
        `<option value="${doc.id_docente}"${doc.id_docente === d.id_docente ? ' selected' : ''}>${esc(doc.nombre)}</option>`).join('');
      return `<tr><td>${esc(d.materia)}</td>
        <td><select data-dictado="${d.id_dictado}" data-materia="${esc(d.materia)}" aria-label="Docente de ${esc(d.materia)}"${editable ? '' : ' disabled'}>${opciones}</select></td>
        <td>${esc(d.horario)}</td></tr>`;
    }).join('') || '<tr><td colspan="3">El curso no tiene materias.</td></tr>';

    const regulares = alumnos.filter(a => a.estado === 'regular').length;
    $('#tabla-inscriptos tbody').innerHTML = alumnos.map(a =>
      `<tr><td>${esc(a.dni)}</td><td>${esc(a.apellido)}, ${esc(a.nombre)}</td>
       <td><span class="etiqueta${a.estado === 'regular' ? ' ok' : ' alerta'}">${esc(a.estado[0].toUpperCase() + a.estado.slice(1))}</span></td></tr>`).join('')
      || '<tr><td colspan="3">Todavía no hay alumnos inscriptos.</td></tr>';
    $('#cantidad-inscriptos').textContent = regulares;

    const sinDocente = dictados.filter(d => !d.id_docente).length;
    $('#aviso-sin-docente').textContent = sinDocente ? `${sinDocente} materia(s) sin docente asignado.` : '';
  }

  $('#ciclo').addEventListener('change', e => {
    ciclo = Number(e.target.value);
    cursoActual = null;
    cargarCursos().catch(err => mostrarMensaje(msg, err.message, 'error'));
  });

  $('#lista-cursos').addEventListener('click', e => {
    const b = e.target.closest('[data-curso]');
    if (!b) return;
    cursoActual = Number(b.dataset.curso);
    $$('#lista-cursos .item-lista').forEach(x => x.classList.toggle('activo', x === b));
    dibujarCurso().catch(err => mostrarMensaje(msg, err.message, 'error'));
  });

  $('#tabla-dictados').addEventListener('change', async e => {
    const sel = e.target;
    try {
      await api(`api/dictados.php?id=${sel.dataset.dictado}`, { method: 'PUT', body: { id_docente: sel.value || null } });
      mostrarMensaje(msg, `Docente actualizado en ${sel.dataset.materia}.`);
      await dibujarCurso();
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  });

  $$('.pestana').forEach(p => p.addEventListener('click', () => {
    $$('.pestana').forEach(x => { x.classList.remove('activa'); x.setAttribute('aria-selected', 'false'); });
    p.classList.add('activa');
    p.setAttribute('aria-selected', 'true');
    $$('.panel-pestana').forEach(panel => panel.classList.toggle('oculto', panel.id !== p.dataset.panel));
  }));

  const formIns = $('#form-inscribir');
  $('#btn-inscribir').addEventListener('click', () => { formIns.classList.toggle('oculto'); formIns.dni.focus(); });
  formIns.addEventListener('submit', async e => {
    e.preventDefault();
    const dni = formIns.dni.value.trim();
    if (!/^\d{7,8}$/.test(dni)) {
      mostrarMensaje(msg, 'Ingresá un DNI de 7 u 8 números, sin puntos.', 'error');
      return;
    }
    try {
      await api('api/inscripciones.php', {
        method: 'POST',
        body: { id_curso: cursoActual, dni, alumno: formIns.alumno.value, fecha_nac: formIns.fecha_nac.value },
      });
      mostrarMensaje(msg, 'El alumno quedó inscripto.');
      formIns.reset();
      formIns.classList.add('oculto');
      await dibujarCurso();
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  });

  const formCurso = $('#form-curso');
  $('#btn-nuevo-curso').addEventListener('click', () => formCurso.classList.toggle('oculto'));
  $('#btn-cancelar-curso').addEventListener('click', () => formCurso.classList.add('oculto'));
  formCurso.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      const nuevo = await api('api/cursos.php', {
        method: 'POST',
        body: { anio: formCurso.anio.value, division: formCurso.division.value, turno: formCurso.turno.value, ciclo_lectivo: ciclo },
      });
      formCurso.classList.add('oculto');
      mostrarMensaje(msg, `Se creó el curso ${formCurso.anio.value}.º ${formCurso.division.value}.`);
      await cargarCursos(nuevo.id_curso);
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  });

  return api('api/docentes.php').then(lista => { docentes = lista; return cargarCursos(); });
}


function iniciarAsistenciaDocentes() {
  const hoy = hoyISO();
  const form = $('#form-asistencia-docente');
  const msg = $('#mensaje-asistencia-docente');

  form.fecha.value = hoy;
  form.fecha.max = hoy;
  $('#filtro-desde').value = hoy;
  $('#filtro-hasta').value = hoy;

  async function dibujar() {
    const registros = await api(`api/asistencia_docente.php?desde=${$('#filtro-desde').value}&hasta=${$('#filtro-hasta').value}`);
    $('#tabla-asistencia-docente tbody').innerHTML = registros.length
      ? registros.map(r => `<tr><td>${esc(r.docente)}</td><td>${formatoFecha(r.fecha)}</td><td>${r.hora_entrada || '—'}</td><td>${r.hora_salida || '—'}</td><td>${esc(r.observacion) || '—'}</td></tr>`).join('')
      : '<tr><td colspan="5">No hay registros en el período elegido.</td></tr>';
  }

  async function registrar(accion) {
    if (!form.id_docente.value || !form.fecha.value) {
      mostrarMensaje(msg, 'Elegí el docente y la fecha.', 'error');
      return;
    }
    const campoHora = accion === 'entrada' ? form.hora_entrada : form.hora_salida;
    try {
      const r = await api('api/asistencia_docente.php', {
        method: 'POST',
        body: { accion, id_docente: form.id_docente.value, fecha: form.fecha.value, hora: campoHora.value, observacion: form.observacion.value },
      });
      const nombre = form.id_docente.selectedOptions[0].textContent;
      mostrarMensaje(msg, `${accion === 'entrada' ? 'Entrada' : 'Salida'} de ${nombre} registrada a las ${r.hora}.`);
      campoHora.value = '';
      form.observacion.value = '';
      await dibujar();
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  }

  $('#btn-entrada').addEventListener('click', () => registrar('entrada'));
  $('#btn-salida').addEventListener('click', () => registrar('salida'));
  $('#filtro-desde').addEventListener('change', () => dibujar().catch(err => mostrarMensaje(msg, err.message, 'error')));
  $('#filtro-hasta').addEventListener('change', () => dibujar().catch(err => mostrarMensaje(msg, err.message, 'error')));
  $('#btn-imprimir-registro').addEventListener('click', () => window.print());

  return api('api/docentes.php').then(docentes => {
    form.id_docente.innerHTML = '<option value="">Elegí un docente…</option>' +
      docentes.map(d => `<option value="${d.id_docente}">${esc(d.nombre)}</option>`).join('');
    return dibujar();
  });
}

function prepararSelectsDictado(form, dictados, alCambiar) {
  const cursos = [...new Map(dictados.map(d => [d.id_curso, d.curso])).entries()];
  form.id_curso.innerHTML = cursos.map(([id, nombre]) => `<option value="${id}">${esc(nombre)}</option>`).join('');

  function llenarMaterias() {
    const delCurso = dictados.filter(d => String(d.id_curso) === form.id_curso.value);
    form.id_dictado.innerHTML = delCurso.map(d => `<option value="${d.id_dictado}">${esc(d.materia)}</option>`).join('');
  }

  form.id_curso.addEventListener('change', () => { llenarMaterias(); alCambiar(); });
  form.id_dictado.addEventListener('change', alCambiar);
  llenarMaterias();
  return cursos.length > 0;
}

function iniciarAsistenciaAlumnos() {
  const form = $('#form-lista');
  const msg = $('#mensaje-lista');
  const tbody = $('#tabla-lista tbody');
  form.fecha.value = diaHabilAnterior(hoyISO());
  form.fecha.max = hoyISO();
  let fechaElegida = form.fecha.value;

  async function cargarLista() {
    if (!form.id_dictado.value) return;
    try {
      const d = await api(`api/asistencia_alumnos.php?id_dictado=${form.id_dictado.value}&fecha=${form.fecha.value}`);
      form.tema.value = d.clase?.tema ?? '';
      const sinClase = d.sin_clase;
      const soloLectura = !d.puede_cargar;
      const bloqueada = Boolean(sinClase) || soloLectura;
      $('#estado-lista').textContent = sinClase
        ? `${sinClase} Elegí otra fecha.`
        : soloLectura
          ? (d.clase
            ? 'Solo consulta: la asistencia la carga el preceptor.'
            : 'Solo consulta: el preceptor todavía no cargó la lista de esta clase.')
          : d.clase
            ? 'Esta clase ya tiene lista cargada. Si cambiás algo y guardás, se corrige.'
            : `Asistencia acumulada en la materia. Se avisa a quienes están por debajo del ${d.limite} %.`;
      $('#estado-lista').classList.toggle('sin-clase', Boolean(sinClase));
      $('#btn-todos-presentes').disabled = bloqueada;
      $('button[type="submit"]', form).disabled = bloqueada;
      form.tema.disabled = bloqueada;
      tbody.innerHTML = d.alumnos.map(a => {
        const nombre = `${esc(a.apellido)}, ${esc(a.nombre)}`;
        const radios = ['P', 'A', 'T', 'J'].map((v, i) => {
          const texto = ['Presente', 'Ausente', 'Tarde', 'Justificado'][i];
          if (soloLectura) {
            return `<td class="centro marca-asistencia">${a.estado === v ? `<span title="${texto}" aria-label="${texto}">✓</span>` : ''}</td>`;
          }
          return `<td class="centro"><input type="radio" name="alumno${a.id_alumno}" value="${v}" aria-label="${texto}: ${nombre}"${a.estado === v ? ' checked' : ''}${bloqueada ? ' disabled' : ''}></td>`;
        }).join('');
        const pct = a.porcentaje === null ? 'Sin clases' : `${a.porcentaje} %`;
        const aviso = a.cerca_limite ? ' <span class="etiqueta alerta">Cerca del límite</span>' : '';
        return `<tr data-alumno="${a.id_alumno}"><td>${nombre}</td>${radios}<td>${pct}${aviso}</td></tr>`;
      }).join('') || '<tr><td colspan="6">No hay alumnos regulares en este curso.</td></tr>';
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  }

  $('#btn-todos-presentes').addEventListener('click', () => {
    $$('input[type="radio"][value="P"]', form).forEach(r => { r.checked = true; });
  });

  form.fecha.addEventListener('change', () => {
    if (form.fecha.value && esFinDeSemana(form.fecha.value)) {
      mostrarMensaje(msg, 'Los sábados y domingos no hay clases. Elegí un día de lunes a viernes.', 'error');
      form.fecha.value = fechaElegida;
      return;
    }
    fechaElegida = form.fecha.value;
    cargarLista();
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const filas = $$('tbody tr[data-alumno]', form);
    const sinMarcar = filas.filter(tr => !$('input[type="radio"]:checked', tr));
    if (!filas.length) return;
    if (sinMarcar.length) {
      mostrarMensaje(msg, `Faltan marcar ${sinMarcar.length} alumno(s).`, 'error');
      sinMarcar[0].querySelector('input').focus();
      return;
    }
    const lista = filas.map(tr => ({ id_alumno: Number(tr.dataset.alumno), estado: $('input:checked', tr).value }));
    try {
      await api('api/asistencia_alumnos.php', {
        method: 'POST',
        body: { id_dictado: form.id_dictado.value, fecha: form.fecha.value, tema: form.tema.value, lista },
      });
      const cuenta = { P: 0, A: 0, T: 0, J: 0 };
      lista.forEach(l => { cuenta[l.estado]++; });
      mostrarMensaje(msg, `Lista guardada: ${cuenta.P} presentes, ${cuenta.A} ausentes, ${cuenta.T} tarde y ${cuenta.J} justificados.`);
      await cargarLista();
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  });

  return api('api/dictados.php?mios=1').then(dictados => {
    if (!prepararSelectsDictado(form, dictados, cargarLista)) {
      $('#estado-lista').textContent = 'No tenés materias asignadas en el ciclo actual.';
      return;
    }
    return cargarLista();
  });
}

function iniciarCalificaciones() {
  let notaAprobacion = 7;
  const form = $('#form-notas');
  const tbody = $('#tabla-notas tbody');
  const msg = $('#mensaje-notas');

  function valorNota(texto) {
    const t = texto.trim().toUpperCase();
    if (t === 'A') return 0;
    return t === '' ? NaN : Number(t.replace(',', '.'));
  }

  function notaValida(texto) {
    const t = texto.trim().toUpperCase();
    const v = valorNota(texto);
    return t === '' || t === 'A' || (v >= 1 && v <= 10);
  }

  function calcularFila(tr) {
    const notas = $$('input.nota', tr).map(i => valorNota(i.value)).filter(n => !isNaN(n));
    const celdaProm = $('.promedio', tr);
    const celdaCond = $('.condicion', tr);
    const prom = notas.length ? notas.reduce((a, b) => a + b, 0) / notas.length : null;
    celdaProm.textContent = prom === null ? '—' : prom.toFixed(2);
    if (notas.length < INSTANCIAS.length) {
      celdaCond.innerHTML = '<span class="etiqueta">En curso</span>';
    } else {
      celdaCond.innerHTML = prom >= notaAprobacion
        ? '<span class="etiqueta ok">Aprobada</span>'
        : '<span class="etiqueta alerta">A diciembre</span>';
    }
  }

  async function cargarNotas() {
    if (!form.id_dictado.value) return;
    try {
      const d = await api(`api/calificaciones.php?id_dictado=${form.id_dictado.value}`);
      notaAprobacion = d.nota_aprobacion;
      $('#nota-aprobacion').textContent = notaAprobacion;
      $('#docente-dictado').value = d.dictado.docente || 'Sin asignar';
      tbody.innerHTML = d.alumnos.map(a => {
        const nombre = `${esc(a.apellido)}, ${esc(a.nombre)}`;
        const celdas = INSTANCIAS.map(inst =>
          `<td><input type="text" inputmode="decimal" maxlength="5" class="nota${a.notas[inst] === 0 ? ' ausente' : ''}" data-instancia="${inst}" value="${a.notas[inst] === 0 ? 'A' : (a.notas[inst] ?? '')}" aria-label="Nota ${inst} de ${nombre} (A si estuvo ausente)"></td>`).join('');
        return `<tr data-alumno="${a.id_alumno}"><td>${nombre}</td>${celdas}<td class="promedio">—</td><td class="condicion"></td></tr>`;
      }).join('') || '<tr><td colspan="6">No hay alumnos regulares en este curso.</td></tr>';
      $$('tr[data-alumno]', tbody).forEach(calcularFila);
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  }

  tbody.addEventListener('input', e => {
    const input = e.target;
    input.setCustomValidity(notaValida(input.value) ? '' : 'La nota va de 1 a 10, o A si estuvo ausente');
    input.classList.toggle('ausente', input.value.trim().toUpperCase() === 'A');
    calcularFila(input.closest('tr'));
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    const notas = $$('input.nota', tbody).map(i => ({
      id_alumno: Number(i.closest('tr').dataset.alumno),
      instancia: i.dataset.instancia,
      nota: i.value.trim() === '' ? null : (i.value.trim().toUpperCase() === 'A' ? 'A' : valorNota(i.value)),
    }));
    try {
      await api('api/calificaciones.php', { method: 'POST', body: { id_dictado: form.id_dictado.value, notas } });
      mostrarMensaje(msg, 'Notas guardadas.');
    } catch (err) {
      mostrarMensaje(msg, err.message, 'error');
    }
  });

  $('#btn-imprimir-boletin').addEventListener('click', () => window.print());

  return api('api/dictados.php?mios=1').then(dictados => {
    if (!prepararSelectsDictado(form, dictados, cargarNotas)) {
      mostrarMensaje(msg, 'No tenés materias asignadas en el ciclo actual.', 'error');
      return;
    }
    return cargarNotas();
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  const inicios = {
    inicio: iniciarInicio,
    planes: iniciarPlanes,
    cursos: iniciarCursos,
    'asistencia-docentes': iniciarAsistenciaDocentes,
    'asistencia-alumnos': iniciarAsistenciaAlumnos,
    calificaciones: iniciarCalificaciones,
  };
  const pagina = document.body.dataset.pagina;

  if (pagina === 'login') {
    iniciarLogin();
    return;
  }

  try {
    USUARIO = await api('api/sesion.php');
  } catch (err) {
    if (err.message.startsWith('No se pudo conectar')) alert(err.message);
    window.location.href = 'index.html';
    return;
  }
  aplicarPermisos();
  document.body.classList.add('con-sesion');
  mostrarUsuarioActual();
  
  $('.menu a.salir')?.addEventListener('click', async e => {
    e.preventDefault();
    await api('api/sesion.php', { method: 'DELETE' }).catch(() => {});
    window.location.href = 'index.html';
  });

  if (inicios[pagina]) {
    try {
      await inicios[pagina]();
    } catch (err) {
      const msg = $('.mensaje');
      if (msg) mostrarMensaje(msg, err.message, 'error');
      else alert(err.message);
    }
  }
});
