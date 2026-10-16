const $ = (sel, raiz = document) => raiz.querySelector(sel);
const $$ = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];

function hoyISO() {
  const d = new Date();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

function horaActual() {
  const d = new Date();
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
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
  el._timer = setTimeout(() => el.classList.add('oculto'), 4000);
}


function esc(t) {
  return String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}


const DOCENTES = ['Peralta, Gustavo', 'Ríos, Carolina', 'Medina, Ana', 'Suárez, Pablo', 'Torres, Lucía', 'Ramírez, Hernán'];


function iniciarLogin() {
  const form = $('#form-login');
  const msg = $('#mensaje-login');
  form.addEventListener('submit', e => {
    e.preventDefault();
    const usuario = form.usuario.value.trim();
    const pass = form.password.value;
    if (!usuario || !pass) {
      mostrarMensaje(msg, 'Completá el usuario y la contraseña.', 'error');
      return;
    }
    
    window.location.href = 'inicio.html';
  });
}


function iniciarInicio() {
  const hoy = new Date();
  $('#ciclo').textContent = hoy.getFullYear();
  $('#hoy').textContent = hoy.toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' });
}


function iniciarPlanes() {
  let anioActual = '1';
  let filaEditando = null;
  const tbody = $('#tabla-materias tbody');
  const form = $('#form-materia');
  const msg = $('#mensaje-planes');

  function filtrar() {
    $$('tr', tbody).forEach(tr => tr.classList.toggle('oculto', tr.dataset.anio !== anioActual));
    $$('.filtro-anio').forEach(b => b.classList.toggle('seleccionado', b.dataset.anio === anioActual));
    $('#titulo-anio').textContent = `Materias de ${anioActual}.º año`;
  }

  $$('.filtro-anio').forEach(b => b.addEventListener('click', () => { anioActual = b.dataset.anio; filtrar(); }));

 
  $$('.item-plan').forEach(b => b.addEventListener('click', () => {
    $$('.item-plan').forEach(x => x.classList.remove('activo'));
    b.classList.add('activo');
  }));

  
  $('#btn-agregar-materia').addEventListener('click', () => {
    filaEditando = null;
    form.reset();
    form.anio.value = anioActual;
    $('#titulo-form-materia').textContent = 'Nueva materia';
    form.classList.remove('oculto');
    form.nombre.focus();
  });
  $('#btn-cancelar-materia').addEventListener('click', () => form.classList.add('oculto'));

  form.addEventListener('submit', e => {
    e.preventDefault();
    const nombre = form.nombre.value.trim();
    const anio = form.anio.value;
    const carga = form.carga_horaria.value;
    if (!nombre || !carga) {
      mostrarMensaje(msg, 'Completá el nombre y la carga horaria.', 'error');
      return;
    }
    const contenido = `<td>${esc(nombre)}</td><td>${anio}.º</td><td>${carga} hs</td>
      <td><button type="button" class="link accion-editar">Editar</button> · <button type="button" class="link accion-borrar">Borrar</button></td>`;
    if (filaEditando) {
      filaEditando.innerHTML = contenido;
      filaEditando.dataset.anio = anio;
      mostrarMensaje(msg, `Se actualizó la materia ${nombre}.`);
    } else {
      const tr = document.createElement('tr');
      tr.dataset.anio = anio;
      tr.innerHTML = contenido;
      tbody.appendChild(tr);
      mostrarMensaje(msg, `Se agregó la materia ${nombre} a ${anio}.º año.`);
    }
    anioActual = anio;
    form.classList.add('oculto');
    filtrar();
  });

  
  tbody.addEventListener('click', e => {
    const tr = e.target.closest('tr');
    if (e.target.classList.contains('accion-borrar')) {
      const nombre = tr.cells[0].textContent;
      if (confirm(`¿Borrar la materia ${nombre}?`)) {
        tr.remove();
        mostrarMensaje(msg, `Se borró la materia ${nombre}.`);
      }
    }
    if (e.target.classList.contains('accion-editar')) {
      filaEditando = tr;
      form.nombre.value = tr.cells[0].textContent;
      form.anio.value = tr.dataset.anio;
      form.carga_horaria.value = parseInt(tr.cells[2].textContent, 10);
      $('#titulo-form-materia').textContent = 'Editar materia';
      form.classList.remove('oculto');
      form.nombre.focus();
    }
  });

  
  const formPlan = $('#form-plan');
  $('#btn-nuevo-plan').addEventListener('click', () => { formPlan.classList.toggle('oculto'); formPlan.nombre.focus(); });
  $('#btn-cancelar-plan').addEventListener('click', () => formPlan.classList.add('oculto'));
  formPlan.addEventListener('submit', e => {
    e.preventDefault();
    const nombre = formPlan.nombre.value.trim();
    if (!nombre) return;
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'item-lista item-plan';
    b.innerHTML = `<b>${esc(nombre)}</b><small>Res. ${esc(formPlan.resolucion.value || 's/n')} · desde ${esc(formPlan.anio_vigencia.value)}</small>`;
    b.addEventListener('click', () => { $$('.item-plan').forEach(x => x.classList.remove('activo')); b.classList.add('activo'); });
    $('#lista-planes').appendChild(b);
    formPlan.reset();
    formPlan.classList.add('oculto');
    mostrarMensaje(msg, `Se creó el plan ${nombre}.`);
  });

  filtrar();
}


function iniciarCursos() {
  const cursos = {
    '1A': { nombre: '1.º A', turno: 'Mañana', materias: [['Matemática', 'Peralta, Gustavo', 'Lun 7:30–9:30 · Mié 7:30–8:50'], ['Lengua y Literatura', 'Medina, Ana', 'Mar 7:30–9:30'], ['Taller', 'Ríos, Carolina', 'Jue 13:00–17:00'], ['Ciencias Naturales', '', 'Vie 7:30–9:30']],
      alumnos: [['47123456', 'Acosta, Mario', 'Regular'], ['47234567', 'Benítez, Lucas', 'Regular'], ['47345678', 'Castro, Sofía', 'Regular'], ['47456789', 'Díaz, Valentina', 'Pase']] },
    '1B': { nombre: '1.º B', turno: 'Tarde', materias: [['Matemática', 'Suárez, Pablo', 'Lun 13:00–15:00'], ['Lengua y Literatura', 'Medina, Ana', 'Mié 13:00–15:00'], ['Taller', 'Ríos, Carolina', 'Vie 8:00–12:00']],
      alumnos: [['47567890', 'Gómez, Tomás', 'Regular'], ['47678901', 'López, Camila', 'Regular']] },
    '2A': { nombre: '2.º A', turno: 'Mañana', materias: [['Matemática', 'Peralta, Gustavo', 'Mar 9:40–11:40'], ['Física', 'Suárez, Pablo', 'Jue 7:30–9:30'], ['Dibujo Técnico', '', 'Vie 9:40–11:40']],
      alumnos: [['46789012', 'Fernández, Julián', 'Regular'], ['46890123', 'Herrera, Martina', 'Regular']] },
    '3A': { nombre: '3.º A', turno: 'Mañana', materias: [['Matemática', 'Peralta, Gustavo', 'Mié 9:40–11:40'], ['Química', 'Torres, Lucía', 'Lun 9:40–11:40']],
      alumnos: [['45901234', 'Ibarra, Nicolás', 'Regular']] },
  };
  let cursoActual = '1A';
  const msg = $('#mensaje-cursos');

  function opcionesDocente(elegido) {
    return '<option value="">Sin asignar</option>' + DOCENTES.map(d => `<option${d === elegido ? ' selected' : ''}>${esc(d)}</option>`).join('');
  }

  function dibujarLista() {
    $('#lista-cursos').innerHTML = Object.entries(cursos).map(([id, c]) =>
      `<button type="button" class="item-lista${id === cursoActual ? ' activo' : ''}" data-curso="${id}"><b>${esc(c.nombre)}</b> · ${esc(c.turno.toLowerCase())}</button>`).join('');
  }

  function dibujarCurso() {
    const c = cursos[cursoActual];
    $('#titulo-curso').innerHTML = `<b>${esc(c.nombre)}</b> · Plan Técnico 2026 · Turno ${esc(c.turno.toLowerCase())}`;
    $('#tabla-dictados tbody').innerHTML = c.materias.map(([m, d, h], i) =>
      `<tr><td>${esc(m)}</td><td><select data-i="${i}" aria-label="Docente de ${esc(m)}">${opcionesDocente(d)}</select></td><td>${esc(h)}</td></tr>`).join('');
    $('#tabla-inscriptos tbody').innerHTML = c.alumnos.map(([dni, n, e]) =>
      `<tr><td>${esc(dni)}</td><td>${esc(n)}</td><td><span class="etiqueta${e === 'Regular' ? ' ok' : ' alerta'}">${esc(e)}</span></td></tr>`).join('');
    $('#cantidad-inscriptos').textContent = c.alumnos.length;
    const sinDocente = c.materias.filter(m => !m[1]).length;
    $('#aviso-sin-docente').textContent = sinDocente ? `${sinDocente} materia(s) sin docente asignado.` : '';
  }

  $('#lista-cursos').addEventListener('click', e => {
    const b = e.target.closest('[data-curso]');
    if (!b) return;
    cursoActual = b.dataset.curso;
    dibujarLista();
    dibujarCurso();
  });

  
  $('#tabla-dictados').addEventListener('change', e => {
    const i = e.target.dataset.i;
    cursos[cursoActual].materias[i][1] = e.target.value;
    mostrarMensaje(msg, `Docente actualizado en ${cursos[cursoActual].materias[i][0]}.`);
    dibujarCurso();
  });

  
  $$('.pestana').forEach(p => p.addEventListener('click', () => {
    $$('.pestana').forEach(x => { x.classList.remove('activa'); x.setAttribute('aria-selected', 'false'); });
    p.classList.add('activa');
    p.setAttribute('aria-selected', 'true');
    $$('.panel-pestana').forEach(panel => panel.classList.toggle('oculto', panel.id !== p.dataset.panel));
  }));

  
  const formIns = $('#form-inscribir');
  $('#btn-inscribir').addEventListener('click', () => { formIns.classList.toggle('oculto'); formIns.dni.focus(); });
  formIns.addEventListener('submit', e => {
    e.preventDefault();
    const dni = formIns.dni.value.trim();
    const nombre = formIns.alumno.value.trim();
    if (!/^\d{7,8}$/.test(dni) || !nombre) {
      mostrarMensaje(msg, 'Ingresá un DNI de 7 u 8 números y el apellido y nombre.', 'error');
      return;
    }
    const repetido = Object.values(cursos).some(c => c.alumnos.some(a => a[0] === dni && a[2] === 'Regular'));
    if (repetido) {
      mostrarMensaje(msg, 'Ese DNI ya está inscripto como regular en otro curso.', 'error');
      return;
    }
    cursos[cursoActual].alumnos.push([dni, nombre, 'Regular']);
    formIns.reset();
    formIns.classList.add('oculto');
    dibujarCurso();
    mostrarMensaje(msg, `${nombre} quedó inscripto en ${cursos[cursoActual].nombre}.`);
  });

  
  const formCurso = $('#form-curso');
  $('#btn-nuevo-curso').addEventListener('click', () => formCurso.classList.toggle('oculto'));
  $('#btn-cancelar-curso').addEventListener('click', () => formCurso.classList.add('oculto'));
  formCurso.addEventListener('submit', e => {
    e.preventDefault();
    const id = formCurso.anio.value + formCurso.division.value;
    if (cursos[id]) {
      mostrarMensaje(msg, 'Ese curso ya existe en el ciclo lectivo.', 'error');
      return;
    }
    cursos[id] = { nombre: `${formCurso.anio.value}.º ${formCurso.division.value}`, turno: formCurso.turno.value, materias: [], alumnos: [] };
    cursoActual = id;
    formCurso.classList.add('oculto');
    dibujarLista();
    dibujarCurso();
    mostrarMensaje(msg, `Se creó el curso ${cursos[id].nombre}.`);
  });

  dibujarLista();
  dibujarCurso();
}


function iniciarAsistenciaDocentes() {
  const hoy = hoyISO();
  const registros = [
    { docente: 'Peralta, Gustavo', fecha: hoy, entrada: '07:20', salida: '12:30', obs: '' },
    { docente: 'Medina, Ana', fecha: hoy, entrada: '07:45', salida: '', obs: 'Llegó tarde' },
    { docente: 'Suárez, Pablo', fecha: hoy, entrada: '', salida: '', obs: 'Ausente con aviso' },
  ];
  const form = $('#form-asistencia-docente');
  const msg = $('#mensaje-asistencia-docente');

  form.id_docente.innerHTML = '<option value="">Elegí un docente…</option>' + DOCENTES.map(d => `<option>${esc(d)}</option>`).join('');
  form.fecha.value = hoy;
  $('#filtro-desde').value = hoy;
  $('#filtro-hasta').value = hoy;

  function dibujar() {
    const desde = $('#filtro-desde').value;
    const hasta = $('#filtro-hasta').value;
    const lista = registros.filter(r => (!desde || r.fecha >= desde) && (!hasta || r.fecha <= hasta));
    $('#tabla-asistencia-docente tbody').innerHTML = lista.length
      ? lista.map(r => `<tr><td>${esc(r.docente)}</td><td>${formatoFecha(r.fecha)}</td><td>${r.entrada || '—'}</td><td>${r.salida || '—'}</td><td>${esc(r.obs) || '—'}</td></tr>`).join('')
      : '<tr><td colspan="5">No hay registros en el período elegido.</td></tr>';
  }

  function validar() {
    if (!form.id_docente.value || !form.fecha.value) {
      mostrarMensaje(msg, 'Elegí el docente y la fecha.', 'error');
      return false;
    }
    return true;
  }

  $('#btn-entrada').addEventListener('click', () => {
    if (!validar()) return;
    const docente = form.id_docente.value;
    const ya = registros.find(r => r.docente === docente && r.fecha === form.fecha.value);
    if (ya && ya.entrada) {
      mostrarMensaje(msg, `${docente} ya tiene registrada la entrada de ese día.`, 'error');
      return;
    }
    const entrada = form.hora_entrada.value || horaActual();
    if (ya) { ya.entrada = entrada; ya.obs = form.observacion.value.trim(); }
    else registros.push({ docente, fecha: form.fecha.value, entrada, salida: '', obs: form.observacion.value.trim() });
    mostrarMensaje(msg, `Entrada de ${docente} registrada a las ${entrada}.`);
    form.hora_entrada.value = '';
    form.observacion.value = '';
    dibujar();
  });

  $('#btn-salida').addEventListener('click', () => {
    if (!validar()) return;
    const docente = form.id_docente.value;
    const reg = registros.find(r => r.docente === docente && r.fecha === form.fecha.value && r.entrada);
    if (!reg) {
      mostrarMensaje(msg, `${docente} no tiene entrada registrada ese día.`, 'error');
      return;
    }
    const salida = form.hora_salida.value || horaActual();
    if (salida <= reg.entrada) {
      mostrarMensaje(msg, 'La hora de salida tiene que ser mayor que la de entrada.', 'error');
      return;
    }
    reg.salida = salida;
    mostrarMensaje(msg, `Salida de ${docente} registrada a las ${salida}.`);
    form.hora_salida.value = '';
    dibujar();
  });

  $('#filtro-desde').addEventListener('change', dibujar);
  $('#filtro-hasta').addEventListener('change', dibujar);
  $('#btn-imprimir-registro').addEventListener('click', () => window.print());
  dibujar();
}


function iniciarAsistenciaAlumnos() {
  const form = $('#form-lista');
  const msg = $('#mensaje-lista');
  form.fecha.value = hoyISO();

  $('#btn-todos-presentes').addEventListener('click', () => {
    $$('input[type="radio"][value="P"]', form).forEach(r => { r.checked = true; });
  });

  form.addEventListener('submit', e => {
    e.preventDefault();
    const filas = $$('tbody tr', form);
    const sinMarcar = filas.filter(tr => !$('input[type="radio"]:checked', tr));
    if (sinMarcar.length) {
      mostrarMensaje(msg, `Faltan marcar ${sinMarcar.length} alumno(s).`, 'error');
      sinMarcar[0].querySelector('input').focus();
      return;
    }
    const cuenta = { P: 0, A: 0, T: 0, J: 0 };
    filas.forEach(tr => { cuenta[$('input:checked', tr).value]++; });
    mostrarMensaje(msg, `Lista guardada: ${cuenta.P} presentes, ${cuenta.A} ausentes, ${cuenta.T} tarde y ${cuenta.J} justificados.`);
  });
}


function iniciarCalificaciones() {
  const NOTA_APROBACION = 7;
  const tbody = $('#tabla-notas tbody');
  const msg = $('#mensaje-notas');

  function calcularFila(tr) {
    const notas = $$('input.nota', tr).map(i => parseFloat(i.value)).filter(n => !isNaN(n));
    const celdaProm = $('.promedio', tr);
    const celdaCond = $('.condicion', tr);
    if (notas.length < 3) {
      celdaProm.textContent = notas.length ? (notas.reduce((a, b) => a + b, 0) / notas.length).toFixed(2) : '—';
      celdaCond.innerHTML = '<span class="etiqueta">En curso</span>';
      return;
    }
    const prom = notas.reduce((a, b) => a + b, 0) / notas.length;
    celdaProm.textContent = prom.toFixed(2);
    celdaCond.innerHTML = prom >= NOTA_APROBACION
      ? '<span class="etiqueta ok">Aprobada</span>'
      : '<span class="etiqueta alerta">A diciembre</span>';
  }

  tbody.addEventListener('input', e => {
    const input = e.target;
    const v = parseFloat(input.value);
    input.setCustomValidity(input.value && (v < 1 || v > 10) ? 'La nota va de 1 a 10' : '');
    calcularFila(input.closest('tr'));
  });

  $('#form-notas').addEventListener('submit', e => {
    e.preventDefault();
    if (!e.target.checkValidity()) {
      e.target.reportValidity();
      return;
    }
    mostrarMensaje(msg, 'Notas guardadas.');
  });

  $('#btn-imprimir-boletin').addEventListener('click', () => window.print());
  $$('tr', tbody).forEach(calcularFila);
}


document.addEventListener('DOMContentLoaded', () => {
  const inicios = {
    login: iniciarLogin,
    inicio: iniciarInicio,
    planes: iniciarPlanes,
    cursos: iniciarCursos,
    'asistencia-docentes': iniciarAsistenciaDocentes,
    'asistencia-alumnos': iniciarAsistenciaAlumnos,
    calificaciones: iniciarCalificaciones,
  };
  const pagina = document.body.dataset.pagina;
  if (inicios[pagina]) inicios[pagina]();
});
