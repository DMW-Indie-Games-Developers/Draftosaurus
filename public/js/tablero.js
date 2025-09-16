/* =====  tablero.js - SISTEMA COMPLETO CON VALIDACIÓN DE REGLAS  ===== */

/* ------ Bloqueo frontal: sin sesión → login ------ */
if (!localStorage.getItem('userId')) {
  localStorage.clear();
  location.replace('/login');
}

/* ------ Nombres reales ------ */
let jugadorActualNombre = localStorage.getItem('jugadorActual') || 'Yo';
let rivalNombre = localStorage.getItem('rival') || 'Invitado';

/* ------ VARIABLES GLOBALES ------ */
let draggedDino = null;
let jugadorQueTiroDado = Math.random() < 0.5 ? 1 : 2;
let jugadorActivo = jugadorQueTiroDado;
let restriccionActual = null;
let turno = 1;
let ronda = 1;
let ID_PARTIDA = null;
const TOTAL_RONDAS = 4;
const TOTAL_DINOSAURIOS = 12;
let colocadosEnTurno = 0;

let manos = { 1: [], 2: [] };
let puntuacionesJugadores = { 1: {}, 2: {} };

/* ------ CONFIGURACIÓN DEL JUEGO ------ */
const especies = ['dino1', 'dino2', 'dino3', 'dino4', 'dino5', 'trex'];
const restricciones = {
  1: "Zona izquierda",
  2: "Zona derecha",
  3: "Zona boscosa",
  4: "Recinto vacío",
  5: "Recinto sin T-REX",
  6: "Sin restricción"
};

/* ------ CONFIGURACIÓN DE RECINTOS ------ */
const configRecintos = {
  'bosque-semejanza': {
    nombre: 'El Bosque de la Semejanza',
    tipo: 'semejanza',
    ambiente: 'boscoso',
    zona: 'izquierda',
    capacidadMax: null,
    puntosPorDino: { 1: 1, 2: 3, 3: 6, 4: 10, 5: 15, 6: 21 }
  },
  'prado-diferencia': {
    nombre: 'El Prado de la Diferencia',
    tipo: 'diferencia',
    ambiente: 'rocoso',
    zona: 'derecha',
    capacidadMax: 6,
    puntosPorDino: { 1: 1, 2: 3, 3: 6, 4: 10, 5: 15, 6: 21 }
  },
  'pradera-amor': {
    nombre: 'La Pradera del Amor',
    tipo: 'parejas',
    ambiente: 'rocoso',
    zona: 'izquierda',
    capacidadMax: null,
    puntosPorPareja: 5
  },
  'trio-frondoso': {
    nombre: 'El Trío Frondoso',
    tipo: 'trio',
    ambiente: 'boscoso',
    zona: 'izquierda',
    capacidadMax: 3,
    puntosExactos: 7
  },
  'rey-selva': {
    nombre: 'El Rey de la Selva',
    tipo: 'rey',
    ambiente: 'boscoso',
    zona: 'derecha',
    capacidadMax: 1,
    puntosGanador: 7
  },
  'isla-solitario': {
    nombre: 'La Isla Solitaria',
    tipo: 'solitario',
    ambiente: 'rocoso',
    zona: 'derecha',
    capacidadMax: 1,
    puntosSolitario: 7
  },
  'rio': {
    nombre: 'El Río',
    tipo: 'rio',
    ambiente: 'neutral',
    zona: 'centro',
    capacidadMax: null,
    puntosPorDino: 1
  }
};

/* ------ AUTO-SAVE MEJORADO ------ */
const LS_KEY = 'draftosaurus_autosave';

async function autoSave() {
  if (!ID_PARTIDA) return;

  const colocaciones = [...document.querySelectorAll('.dino-in-recinto')].map(d => ({
    recinto: d.parentElement.id,
    jugador: +d.dataset.jugador,
    especie: d.dataset.especie
  }));

  const payload = {
    id: ID_PARTIDA,
    partidaId: ID_PARTIDA,
    ronda,
    turno,
    jugadorActivo,
    jugadorQueTiroDado,
    restriccion: restriccionActual,
    colocadosEnTurno,
    mano1: manos[1],
    mano2: manos[2],
    colocaciones,
    jugador2: rivalNombre
  };

  try {
    const response = await fetch('/api/tablero/guardarEstadoPartida', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'include'
    });

    const result = await response.json();
    if (result.success) {
      localStorage.setItem(LS_KEY, JSON.stringify({ ...payload, ts: Date.now() }));
      localStorage.setItem('partidaIdActual', ID_PARTIDA.toString());
      console.log('✅ Partida guardada automáticamente');
    } else {
      console.error('❌ Error guardando partida:', result.error);
    }
  } catch (error) {
    console.error('❌ Error guardando partida:', error);
  }
}

/* ===== GUARDADO AUTOMÁTICO AL SALIR/CAMBIAR PÁGINA ===== */
window.addEventListener('beforeunload', function (e) {
  if (ID_PARTIDA) {
    const payload = {
      id: ID_PARTIDA,
      partidaId: ID_PARTIDA,
      ronda,
      turno,
      jugadorActivo,
      jugadorQueTiroDado,
      restriccion: restriccionActual,
      colocadosEnTurno,
      mano1: manos[1],
      mano2: manos[2],
      colocaciones: [...document.querySelectorAll('.dino-in-recinto')].map(d => ({
        recinto: d.parentElement.id,
        jugador: +d.dataset.jugador,
        especie: d.dataset.especie
      })),
      jugador2: rivalNombre
    };

    localStorage.setItem(LS_KEY, JSON.stringify({ ...payload, ts: Date.now() }));
    localStorage.setItem('partidaIdActual', ID_PARTIDA.toString());

    if (navigator.sendBeacon) {
      const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
      navigator.sendBeacon('/api/tablero/guardarEstadoPartida', blob);
    }
  }
});

document.addEventListener('visibilitychange', function () {
  if (document.visibilityState === 'hidden' && ID_PARTIDA) {
    autoSave();
  }
});

setInterval(() => {
  if (ID_PARTIDA) autoSave();
}, 30000);

/* ------ CARGAR AVATAR DEL USUARIO ------ */
async function cargarAvatarUsuario() {
  try {
    const response = await fetch('/perfil/me', {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });
    const userData = await response.json();

    if (userData.success !== false && userData.avatar) {
      localStorage.setItem('userAvatar', userData.avatar);
      return userData.avatar;
    } else {
      return 'img/isotipoOficial.png';
    }
  } catch (error) {
    console.error('Error cargando avatar:', error);
    return 'img/isotipoOficial.png';
  }
}

async function actualizarAvatarEnUI() {
  const avatarImg = document.getElementById('tablero-avatar');
  if (avatarImg) {
    const esJugador1 = jugadorActivo === 1;
    if (esJugador1) {
      const userAvatar = localStorage.getItem('userAvatar') || await cargarAvatarUsuario();
      avatarImg.src = userAvatar;
    } else {
      avatarImg.src = 'img/isotipoOficial.png';
    }
  }
}

/* ===== VALIDACIONES DE REGLAS ESPECÍFICAS ===== */

function getDinosauriosJugadorEnRecinto(recinto, jugador) {
  return [...recinto.querySelectorAll('.dino-in-recinto')]
    .filter(d => +d.dataset.jugador === jugador);
}

function validarBosqueSemejanza(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  if (dinosauriosJugador.length === 0) return true;
  const especieExistente = dinosauriosJugador[0].dataset.especie;
  return especieExistente === especieDino;
}

function validarPradoDiferencia(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  return !dinosauriosJugador.some(d => d.dataset.especie === especieDino);
}

function validarPraderaAmor(recinto, especieDino, jugador) {
  return true;
}

function validarTrioFrondoso(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  return dinosauriosJugador.length < 3;
}

function validarReySelva(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  return dinosauriosJugador.length === 0 && especieDino === 'trex';
}

function validarIslaSolitaria(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  return dinosauriosJugador.length === 0;
}

function validarRio(recinto, especieDino, jugador) {
  return true;
}

/* ===== FUNCIÓN PRINCIPAL DE VALIDACIÓN ===== */

function puedeColocarDino(recinto, tipoRecinto, especieDino) {
  const dinosauriosEnRecinto = getDinosauriosJugadorEnRecinto(recinto, jugadorActivo);
  const cantidadDinos = dinosauriosEnRecinto.length;
  const config = configRecintos[tipoRecinto];

  if (config && config.capacidadMax && cantidadDinos >= config.capacidadMax) {
    return false;
  }

  if (jugadorActivo !== jugadorQueTiroDado && restriccionActual) {
    if (tipoRecinto === 'rio') {
      // El río siempre está disponible
    } else {
      const zona = recinto.dataset.zona;
      const ambiente = recinto.dataset.ambiente;

      switch (restriccionActual) {
        case 1: if (zona !== 'izquierda') return false; break;
        case 2: if (zona !== 'derecha') return false; break;
        case 3: if (ambiente !== 'boscoso') return false; break;
        case 4: if (cantidadDinos > 0) return false; break;
        case 5:
          const tieneTrex = dinosauriosEnRecinto.some(d => d.dataset.especie === 'trex');
          if (tieneTrex) return false;
          break;
        case 6: break;
      }
    }
  }

  switch (tipoRecinto) {
    case 'bosque-semejanza': return validarBosqueSemejanza(recinto, especieDino, jugadorActivo);
    case 'prado-diferencia': return validarPradoDiferencia(recinto, especieDino, jugadorActivo);
    case 'pradera-amor': return validarPraderaAmor(recinto, especieDino, jugadorActivo);
    case 'trio-frondoso': return validarTrioFrondoso(recinto, especieDino, jugadorActivo);
    case 'rey-selva': return validarReySelva(recinto, especieDino, jugadorActivo);
    case 'isla-solitario': return validarIslaSolitaria(recinto, especieDino, jugadorActivo);
    case 'rio': return validarRio(recinto, especieDino, jugadorActivo);
    default: return true;
  }
}

/* ===== FUNCIÓN DE MENSAJE DE ERROR MEJORADA ===== */

function obtenerMensajeError(recinto, especieDino) {
  const recintos = document.querySelectorAll('.recinto');
  const recintosValidos = Array.from(recintos).filter(r =>
    puedeColocarDino(r, r.dataset.tipo, especieDino)
  );

  const nombresValidos = recintosValidos.map(r => {
    const config = configRecintos[r.dataset.tipo];
    return config ? config.nombre : r.dataset.nombre || r.id;
  }).join(', ');

  let razonError = '';
  if (jugadorActivo === jugadorQueTiroDado) {
    razonError = 'No puedes colocar aquí por las reglas del recinto.';
  } else if (restriccionActual) {
    razonError = `No puedes colocar aquí por la restricción del dado: ${restricciones[restriccionActual]}`;
  } else {
    razonError = 'No puedes colocar aquí.';
  }

  return `${razonError}\n\nRecintos válidos: ${nombresValidos || 'Ninguno disponible'}`;
}

/* ===== CÁLCULO DE PUNTUACIONES CORREGIDO ===== */

function calcularPuntuacionRecinto(recintoId, jugador) {
  const recinto = document.getElementById(recintoId);
  if (!recinto) return 0;

  const tipoRecinto = recinto.dataset.tipo;
  const config = configRecintos[tipoRecinto];
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  const cantidadDinos = dinosauriosJugador.length;

  if (cantidadDinos === 0) return 0;

  let puntos = 0;
  console.log(`🔍 Calculando ${tipoRecinto} para jugador ${jugador}:`, {
    cantidadDinos,
    especies: dinosauriosJugador.map(d => d.dataset.especie)
  });

  switch (tipoRecinto) {
    case 'bosque-semejanza':
      // Solo cuenta la especie mayoritaria
      const especiesCount = {};
      dinosauriosJugador.forEach(d => {
        const especie = d.dataset.especie;
        especiesCount[especie] = (especiesCount[especie] || 0) + 1;
      });
      const maxCantidad = Math.max(...Object.values(especiesCount));
      puntos = config.puntosPorDino[maxCantidad] || 0;
      console.log(`Bosque Semejanza: max cantidad ${maxCantidad} = ${puntos} puntos`);
      break;

    case 'prado-diferencia':
      // Cuenta especies diferentes
      const especiesDiferentes = new Set(dinosauriosJugador.map(d => d.dataset.especie)).size;
      puntos = config.puntosPorDino[especiesDiferentes] || 0;
      console.log(`Prado Diferencia: ${especiesDiferentes} especies = ${puntos} puntos`);
      break;

    case 'pradera-amor':
      // Parejas de cada especie
      const especiesCountAmor = {};
      dinosauriosJugador.forEach(d => {
        const especie = d.dataset.especie;
        especiesCountAmor[especie] = (especiesCountAmor[especie] || 0) + 1;
      });
      Object.values(especiesCountAmor).forEach(count => {
        puntos += Math.floor(count / 2) * config.puntosPorPareja;
      });
      console.log(`Pradera Amor: parejas = ${puntos} puntos`);
      break;

    case 'trio-frondoso':
      puntos = cantidadDinos === 3 ? config.puntosExactos : 0;
      console.log(`Trío Frondoso: ${cantidadDinos} dinos = ${puntos} puntos`);
      break;

    case 'rey-selva':
      if (cantidadDinos === 1) {
        const especieDino = dinosauriosJugador[0].dataset.especie;
        const totalEspecieJ1 = contarEspecieEnParque(especieDino, 1);
        const totalEspecieJ2 = contarEspecieEnParque(especieDino, 2);

        if (jugador === 1 && totalEspecieJ1 >= totalEspecieJ2) {
          puntos = config.puntosGanador;
        } else if (jugador === 2 && totalEspecieJ2 >= totalEspecieJ1) {
          puntos = config.puntosGanador;
        }
        console.log(`Rey Selva: especie ${especieDino}, J1=${totalEspecieJ1}, J2=${totalEspecieJ2}, puntos=${puntos}`);
      }
      break;

    case 'isla-solitario':
      if (cantidadDinos === 1) {
        const especieDino = dinosauriosJugador[0].dataset.especie;
        const totalEspecie = contarEspecieEnParque(especieDino, jugador);
        if (totalEspecie === 1) {
          puntos = config.puntosSolitario;
        }
        console.log(`Isla Solitaria: especie ${especieDino}, total=${totalEspecie}, puntos=${puntos}`);
      }
      break;

    case 'rio':
      puntos = cantidadDinos * config.puntosPorDino;
      console.log(`Río: ${cantidadDinos} dinos = ${puntos} puntos`);
      break;
  }

  // ✅ CORREGIDO: Bonus T-Rex (excepto en el río)
  if (tipoRecinto !== 'rio') {
    const tieneTrex = dinosauriosJugador.some(d => d.dataset.especie === 'trex');
    if (tieneTrex) {
      puntos += 1;
      console.log(`Bonus T-Rex: +1 punto`);
    }
  }

  console.log(`Total recinto ${tipoRecinto}: ${puntos} puntos para jugador ${jugador}`);
  return puntos;
}

function contarEspecieEnParque(especie, jugador) {
  return [...document.querySelectorAll('.dino-in-recinto')]
    .filter(d => +d.dataset.jugador === jugador && d.dataset.especie === especie)
    .length;
}

function actualizarPuntuaciones() {
  console.log('🔄 Actualizando puntuaciones...');
  [1, 2].forEach(jugador => {
    Object.keys(configRecintos).forEach(recintoId => {
      puntuacionesJugadores[jugador][recintoId] = calcularPuntuacionRecinto(recintoId, jugador);
    });
  });

  const totalJ1 = calcularPuntuacionTotal(1);
  const totalJ2 = calcularPuntuacionTotal(2);
  console.log(`📊 Puntuaciones totales: J1=${totalJ1}, J2=${totalJ2}`);
}

function calcularPuntuacionTotal(jugador) {
  let total = 0;
  Object.keys(configRecintos).forEach(recintoId => {
    total += puntuacionesJugadores[jugador][recintoId] || 0;
  });
  return total;
}

/* ===== VALIDACIÓN DE FIN DE JUEGO ===== */

function validarFinDeJuego() {
  if (ronda > TOTAL_RONDAS) return true;
  const dinosJ1 = [...document.querySelectorAll('.dino-in-recinto')].filter(d => +d.dataset.jugador === 1).length;
  const dinosJ2 = [...document.querySelectorAll('.dino-in-recinto')].filter(d => +d.dataset.jugador === 2).length;
  return dinosJ1 >= TOTAL_DINOSAURIOS || dinosJ2 >= TOTAL_DINOSAURIOS;
}

function determinarGanador() {
  const puntosJ1 = calcularPuntuacionTotal(1);
  const puntosJ2 = calcularPuntuacionTotal(2);

  if (puntosJ1 > puntosJ2) return { ganador: jugadorActualNombre, puntos: [puntosJ1, puntosJ2] };
  if (puntosJ2 > puntosJ1) return { ganador: rivalNombre, puntos: [puntosJ1, puntosJ2] };

  const trexJ1 = contarEspecieEnParque('trex', 1);
  const trexJ2 = contarEspecieEnParque('trex', 2);

  if (trexJ1 < trexJ2) return { ganador: jugadorActualNombre, puntos: [puntosJ1, puntosJ2], empate: 'menos_trex' };
  if (trexJ2 < trexJ1) return { ganador: rivalNombre, puntos: [puntosJ1, puntosJ2], empate: 'menos_trex' };

  return { ganador: null, puntos: [puntosJ1, puntosJ2], empate: 'total' };
}

/* ===== HIGHLIGHT RECINTOS ===== */

function highlightRecintos(especie) {
  document.querySelectorAll('.recinto').forEach(r => {
    const ok = puedeColocarDino(r, r.dataset.tipo, especie);
    r.classList.toggle('recinto-available', ok);
    r.classList.toggle('recinto-disabled', !ok);
  });
}

function clearHighlight() {
  document.querySelectorAll('.recinto').forEach(r => {
    r.classList.remove('recinto-available', 'recinto-disabled', 'highlight');
  });
}

/* ===== FUNCIONES AUXILIARES ===== */

function generarMano() {
  return Array.from({ length: 6 }, () => especies[Math.floor(Math.random() * especies.length)]);
}

function renderMano(jugador) {
  const grid = document.querySelector('.dino-grid');
  grid.innerHTML = '';
  manos[jugador].forEach((esp, idx) => {
    const img = document.createElement('img');
    img.src = `/img/imagen_Tablero/${esp}.png`;
    img.className = 'dino-img';
    img.draggable = true;
    img.dataset.especie = esp;
    img.dataset.index = idx;
    grid.appendChild(img);

    img.addEventListener('dragstart', e => {
      draggedDino = { especie: esp, index: idx, jugador: jugadorActivo };
      img.classList.add('dragging');
      e.dataTransfer.setData('text/plain', esp);
      highlightRecintos(esp);
    });
    img.addEventListener('dragend', () => {
      img.classList.remove('dragging');
      clearHighlight();
    });
  });
}

function actualizarUI() {
  const nombreTurno = jugadorActivo === 1 ? jugadorActualNombre : rivalNombre;
  document.getElementById('dino-panel-title').textContent = `Tus Dinosaurios - ${nombreTurno}`;

  if (manos[1].length === 0 && manos[2].length === 0) {
    console.log('Generando manos por primera vez');
    manos[1] = generarMano();
    manos[2] = generarMano();
  }

  renderMano(jugadorActivo);
  actualizarPuntuaciones();
  actualizarPuntuacionJugadorActivo();
  actualizarPuntuacionesVisualesRecintos();
  actualizarVisibilidadDinos();
  actualizarAvatarEnUI();

  const turnoText = document.getElementById('turno-text');
  const dadoBtn = document.getElementById('tirar-dado-btn');

  if (restriccionActual === null) {
    turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${nombreTurno} debe tirar dado`;
  } else if (jugadorActivo === jugadorQueTiroDado) {
    turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${nombreTurno} debe colocar (SIN restricciones)`;
  } else {
    turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${nombreTurno} debe colocar (CON restricción: ${restricciones[restriccionActual]})`;
  }

  dadoBtn.style.display = restriccionActual === null ? 'inline-block' : 'none';
  if (!draggedDino) clearHighlight();
}

function actualizarPuntuacionJugadorActivo() {
  const total = calcularPuntuacionTotal(jugadorActivo);
  document.querySelector('.player-score').textContent = total;
}

function actualizarPuntuacionesVisualesRecintos() {
  document.querySelectorAll('.recinto').forEach(r => {
    const p = puntuacionesJugadores[jugadorActivo][r.id] || 0;
    const scoreElement = r.querySelector('.recinto-score');
    if (scoreElement) {
      scoreElement.textContent = p;
    }
  });
}

function actualizarVisibilidadDinos() {
  document.querySelectorAll('.dino-in-recinto').forEach(d => {
    d.style.display = (+d.dataset.jugador === jugadorActivo) ? 'block' : 'none';
  });
}

/* ===== INICIALIZAR PARTIDA ===== */

function inicializarPartidaNueva() {
  jugadorActualNombre = localStorage.getItem('jugadorActual') || 'Jugador 1';
  rivalNombre = localStorage.getItem('rival') || 'Jugador 2';
  ronda = 1; turno = 1;
  jugadorQueTiroDado = Math.random() < 0.5 ? 1 : 2;
  jugadorActivo = jugadorQueTiroDado;
  restriccionActual = null;
  colocadosEnTurno = 0;
  manos[1] = generarMano();
  manos[2] = generarMano();

  document.querySelectorAll('.recinto').forEach(recinto => {
    puntuacionesJugadores[1][recinto.id] = 0;
    puntuacionesJugadores[2][recinto.id] = 0;
  });

  localStorage.removeItem('esPartidaNueva');
  actualizarUI();
}

/* ===== CARGAR PARTIDA MEJORADO ===== */

async function cargarPartidaYRestaurar(idPartida) {
  if (!idPartida || isNaN(idPartida)) {
    console.warn('ID de partida inválido - se creará una partida nueva.');
    await inicializarPartidaNueva();
    return;
  }

  try {
    console.log('📄 Cargando partida ID:', idPartida);

    const response = await fetch(`/api/tablero/cargarPartida?id=${idPartida}`, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });

    if (!response.ok) {
      console.error('❌ Error HTTP:', response.status, response.statusText);
      throw new Error(`Error HTTP: ${response.status}`);
    }

    const data = await response.json();
    console.log('📦 Datos cargados:', data);

    if (!data.success) {
      throw new Error(data.message || 'Error al cargar partida');
    }

    const partida = data.data;

    // Establecer nombres
    jugadorActualNombre = partida.jugador1 || 'Jugador 1';
    rivalNombre = partida.jugador2 || partida.name_invitado || 'Invitado';
    localStorage.setItem('jugadorActual', jugadorActualNombre);
    localStorage.setItem('rival', rivalNombre);

    // Establecer estado del juego
    ID_PARTIDA = partida.id;
    ronda = partida.ronda || 1;
    turno = partida.turno || 1;
    jugadorActivo = partida.jugadorActivo || 1;
    jugadorQueTiroDado = partida.jugadorQueTiroDado || 1;
    restriccionActual = partida.restriccion || null;
    colocadosEnTurno = partida.colocadosEnTurno || 0;
    manos[1] = Array.isArray(partida.mano1) ? partida.mano1 : [];
    manos[2] = Array.isArray(partida.mano2) ? partida.mano2 : [];

    // Limpiar tablero
    document.querySelectorAll('.dino-in-recinto').forEach(d => d.remove());

    // Restaurar colocaciones
    if (partida.colocaciones && Array.isArray(partida.colocaciones)) {
      partida.colocaciones.forEach(c => {
        const recinto = document.getElementById(c.recinto);
        if (!recinto) return;

        const img = document.createElement('img');
        img.src = `/img/imagen_Tablero/${c.especie}.png`;
        img.className = 'dino-in-recinto';
        img.dataset.especie = c.especie;
        img.dataset.jugador = c.jugador;
        img.style.pointerEvents = 'none';
        recinto.appendChild(img);
      });
    }

    // Restaurar estado del dado
    if (restriccionActual !== null) {
      const dadoImg = document.getElementById('dado-img');
      const restriccionText = document.getElementById('restriccion-text');
      if (dadoImg) dadoImg.src = `/img/dado/dado${restriccionActual}.png`;
      if (restriccionText) restriccionText.textContent = restricciones[restriccionActual];
    }

    localStorage.setItem('partidaIdActual', ID_PARTIDA.toString());
    console.log('✅ Partida cargada correctamente');
    actualizarUI();

  } catch (error) {
    console.error('❌ Error cargando partida:', error);
    alert('Error al cargar la partida. Se iniciará una nueva.');
    await inicializarPartidaNueva();
  }
}

/* ===== RESTAURAR AL REFRESCAR ===== */

(async () => {
  console.log('🚀 Inicializando tablero...');

  // 1. Verificar si hay ID en query string
  const urlParams = new URLSearchParams(window.location.search);
  const queryId = urlParams.get('id');

  // 2. Verificar localStorage
  const lsPartidaACargar = localStorage.getItem('partidaACargar');
  const lsPartidaId = localStorage.getItem('partidaId');
  const lsAutosave = localStorage.getItem(LS_KEY);

  console.log('🔍 Buscando partida a cargar:', {
    queryId,
    lsPartidaACargar,
    lsPartidaId,
    lsAutosave: !!lsAutosave
  });

  // 3. Prioridad: query string > partidaACargar > partidaId > autosave
  let partidaIdACargar = null;

  if (queryId && !isNaN(queryId)) {
    partidaIdACargar = parseInt(queryId);
    console.log('🔗 Usando ID de query string:', partidaIdACargar);
  } else if (lsPartidaACargar && !isNaN(lsPartidaACargar)) {
    partidaIdACargar = parseInt(lsPartidaACargar);
    console.log('💾 Usando partidaACargar:', partidaIdACargar);
  } else if (lsPartidaId && !isNaN(lsPartidaId)) {
    partidaIdACargar = parseInt(lsPartidaId);
    console.log('💿 Usando partidaId:', partidaIdACargar);
  } else if (lsAutosave) {
    try {
      const data = JSON.parse(lsAutosave);
      if (data.id && !isNaN(data.id)) {
        partidaIdACargar = parseInt(data.id);
        console.log('📄 Usando ID de autosave:', partidaIdACargar);
      }
    } catch (e) {
      console.warn('⚠️ Error parsing autosave:', e);
    }
  }

  // 4. Cargar partida o inicializar nueva
  if (partidaIdACargar) {
    await cargarPartidaYRestaurar(partidaIdACargar);
    // Limpiar flags de carga
    localStorage.removeItem('partidaACargar');
    localStorage.removeItem('partidaId');
  } else {
    console.log('🆕 Iniciando partida nueva');
    await inicializarPartidaNueva();
  }
})();

/* ===== RESTO DEL FLUJO DE JUEGO ===== */

document.addEventListener('DOMContentLoaded', () => {
  cargarAvatarUsuario();

  const recintos = document.querySelectorAll('.recinto');
  const dadoBtn = document.getElementById('tirar-dado-btn');
  const dadoImg = document.getElementById('dado-img');
  const restriccionText = document.getElementById('restriccion-text');

  async function tirarDado() {
    if (restriccionActual !== null) return;
    const valor = Math.floor(Math.random() * 6) + 1;
    dadoImg.src = `/img/dado/dado${valor}.png`;
    restriccionActual = valor;
    restriccionText.textContent = restricciones[valor];
    actualizarUI();
    await autoSave();
  }

  async function colocarDino(recinto, especie) {
    const dinoClone = document.createElement('img');
    dinoClone.src = `/img/imagen_Tablero/${especie}.png`;
    dinoClone.className = 'dino-in-recinto';
    dinoClone.dataset.especie = especie;
    dinoClone.dataset.jugador = jugadorActivo;
    dinoClone.style.pointerEvents = 'none';
    recinto.appendChild(dinoClone);

    manos[jugadorActivo].splice(draggedDino.index, 1);
    colocadosEnTurno++;
    await autoSave();

    if (colocadosEnTurno === 2) {
      const ultimo = jugadorActivo;
      colocadosEnTurno = 0;
      restriccionActual = null;
      restriccionText.textContent = "Esperando...";
      dadoImg.src = "/img/dado/dado.png";

      turno++;
      if (turno > 3) {
        ronda++;
        if (ronda > TOTAL_RONDAS || validarFinDeJuego()) {
          mostrarResultadosFinal();
          return;
        }
        turno = 1;
        manos[1] = generarMano();
        manos[2] = generarMano();
      } else {
        const manosRestantes = {
          1: [...manos[1]],
          2: [...manos[2]]
        };
        manos[1] = manosRestantes[2];
        manos[2] = manosRestantes[1];
      }

      jugadorQueTiroDado = ultimo;
      jugadorActivo = jugadorQueTiroDado;
      await autoSave();
    } else {
      jugadorActivo = jugadorActivo === 1 ? 2 : 1;
    }
    actualizarUI();
  }

  /* ===== FUNCIÓN DE FINALIZACIÓN CORREGIDA ===== */
  async function mostrarResultadosFinal() {
    console.log('🏁 Iniciando finalización de partida...');

    try {
      localStorage.removeItem(LS_KEY);
      localStorage.removeItem('partidaIdActual');
      localStorage.removeItem('partidaACargar');

      // ✅ ASEGURAR QUE LAS PUNTUACIONES ESTÉN ACTUALIZADAS
      actualizarPuntuaciones();

      // ✅ DEBUG: Mostrar estado actual del tablero
      console.log('📊 Estado actual del tablero:');
      const estadoRecintos = {};
      document.querySelectorAll('.recinto').forEach(recinto => {
        const dinos = [...recinto.querySelectorAll('.dino-in-recinto')].map(d => ({
          especie: d.dataset.especie,
          jugador: parseInt(d.dataset.jugador)
        }));
        if (dinos.length > 0) {
          estadoRecintos[recinto.id] = dinos;
        }
      });
      console.log('Estado recintos:', estadoRecintos);

      const puntosLocalesJ1 = calcularPuntuacionTotal(1);
      const puntosLocalesJ2 = calcularPuntuacionTotal(2);
      console.log(`Puntos calculados localmente: J1=${puntosLocalesJ1}, J2=${puntosLocalesJ2}`);

      let resultado = null;

      if (ID_PARTIDA) {
        console.log('🏁 Finalizando partida ID:', ID_PARTIDA);

        try {
          const response = await fetch('/api/tablero/finalizarPartida', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              partidaId: ID_PARTIDA,
              puntosJ1: puntosLocalesJ1,
              puntosJ2: puntosLocalesJ2
            })
          });

          const data = await response.json();
          console.log('📊 Respuesta del servidor:', data);

          if (data.success) {
            // ✅ Usar datos del servidor
            const puntos = data.puntos || [puntosLocalesJ1, puntosLocalesJ2];
            const nombreGanador = data.nombreGanador;

            resultado = {
              puntos: puntos,
              ganador: nombreGanador,
              esServidor: true
            };

            console.log('✅ Usando puntuación del servidor:', resultado);
          } else {
            console.error('❌ Error del servidor:', data.error);
            throw new Error(data.error || 'Error finalizando partida');
          }

        } catch (error) {
          console.error('❌ Error finalizando partida:', error);
          // Fallback: usar cálculo local si falla el servidor
          resultado = determinarGanador();
          resultado.esServidor = false;
          console.log('⚠️ Usando cálculo local como fallback:', resultado);
        }
      } else {
        // Si no hay ID_PARTIDA, usar cálculo local
        resultado = determinarGanador();
        resultado.esServidor = false;
        console.log('🔧 Usando cálculo local (sin ID_PARTIDA):', resultado);
      }

      // ✅ MOSTRAR MODAL MEJORADO EN LUGAR DE ALERT
      mostrarModalResultados(resultado);

    } catch (error) {
      console.error('❌ Error al finalizar partida:', error);
      alert(`¡Juego terminado!\n\n${jugadorActualNombre} vs ${rivalNombre}\n\nGracias por jugar.`);
      location.href = '/perfil';
    }
  }

  /* ===== MODAL DE RESULTADOS MEJORADO ===== */
  function mostrarModalResultados(resultado) {
    const puntosJ1 = resultado.puntos[0];
    const puntosJ2 = resultado.puntos[1];

    let estadoGanador = '';
    let iconoGanador = '';

    if (resultado.ganador) {
      estadoGanador = `🏆 Ganador: ${resultado.ganador}`;
      iconoGanador = '🏆';
      if (resultado.empate === 'menos_trex') {
        estadoGanador += ` (desempate por menos T-Rex)`;
      }
    } else {
      estadoGanador = `🤝 ¡Empate perfecto!`;
      iconoGanador = '🤝';
    }

    const modalHTML = `
      <div class="modal fade" id="modalResultados" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content draftosaurus-modal text-center">
            <div class="modal-header">
              <h4 class="modal-title w-100">${iconoGanador} ¡Partida Finalizada!</h4>
            </div>
            <div class="modal-body">
              <div class="mb-4">
                <h5>${estadoGanador}</h5>
              </div>
              
              <div class="row text-center mb-4">
                <div class="col-6">
                  <div class="card bg-dark border-primary">
                    <div class="card-body">
                      <h6 class="card-title">${jugadorActualNombre}</h6>
                      <h3 class="text-primary">${puntosJ1}</h3>
                      <small class="text-muted">puntos</small>
                    </div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="card bg-dark border-secondary">
                    <div class="card-body">
                      <h6 class="card-title">${rivalNombre}</h6>
                      <h3 class="text-secondary">${puntosJ2}</h3>
                      <small class="text-muted">puntos</small>
                    </div>
                  </div>
                </div>
              </div>

              ${resultado.esServidor ?
        '<small class="text-success">✅ Puntuación guardada en tu perfil</small>' :
        '<small class="text-warning">⚠️ Puntuación calculada localmente</small>'
      }
              
              <div class="mt-3">
                <small class="text-muted">Gracias por jugar Draftosaurus</small>
              </div>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-custom" onclick="irAPerfil()">
                Ver mi Perfil
              </button>
              <button type="button" class="btn btn-outline-light" onclick="nuevaPartida()">
                Nueva Partida
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    // Eliminar modal existente y crear nuevo
    const existingModal = document.getElementById('modalResultados');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = new bootstrap.Modal(document.getElementById('modalResultados'), {
      backdrop: 'static',
      keyboard: false
    });
    modal.show();
  }

  /* ===== FUNCIONES GLOBALES PARA EL MODAL ===== */
  window.irAPerfil = function () {
    location.href = '/perfil';
  };

  window.nuevaPartida = function () {
    // Limpiar todo el estado
    localStorage.removeItem(LS_KEY);
    localStorage.removeItem('partidaIdActual');
    localStorage.removeItem('partidaACargar');
    localStorage.removeItem('partidaId');

    location.href = '/perfil'; // Ir al perfil para crear nueva partida
  };

  dadoBtn.addEventListener('click', tirarDado);

  recintos.forEach(recinto => {
    recinto.addEventListener('dragover', e => {
      e.preventDefault();
      if (!recinto.classList.contains('recinto-disabled')) {
        recinto.classList.add('highlight');
      }
    });

    recinto.addEventListener('dragleave', () => {
      recinto.classList.remove('highlight');
    });

    recinto.addEventListener('drop', async e => {
      e.preventDefault();
      recinto.classList.remove('highlight');

      if (recinto.classList.contains('recinto-disabled')) return;
      if (!draggedDino || draggedDino.jugador !== jugadorActivo) return;

      const tipoRecinto = recinto.dataset.tipo;
      const especieDino = draggedDino.especie;

      if (restriccionActual === null) {
        alert("Primero debe tirar el dado.");
        return;
      }

      if (puedeColocarDino(recinto, tipoRecinto, especieDino)) {
        await colocarDino(recinto, especieDino);
      } else {
        alert(obtenerMensajeError(recinto, especieDino));
      }
    });
  });

  document.getElementById('btn-guardar')?.addEventListener('click', autoSave);

  const btnAyuda = document.getElementById('btn-ayuda');
  if (btnAyuda) {
    btnAyuda.addEventListener('click', mostrarAyudaReglas);
  }
});

/* ===== FUNCIÓN DE AYUDA ===== */

function mostrarAyudaReglas() {
  const mensaje = `
🦕 REGLAS DE DRAFTOSAURUS (2 Jugadores)

📋 RECINTOS:
• Bosque de la Semejanza: Solo misma especie
• Prado de la Diferencia: Solo especies distintas
• Pradera del Amor: 5 puntos por cada pareja de misma especie
• Trío Frondoso: 7 puntos si hay exactamente 3 dinosaurios
• Rey de la Selva: 7 puntos si tienes mayoría de esa especie
• Isla Solitaria: 7 puntos si es único de su especie en tu parque
• Río: 1 punto por dinosaurio

🎲 DADO:
• Zona izquierda: Solo recintos del lado izquierdo
• Zona derecha: Solo recintos del lado derecho  
• Zona boscosa: Solo recintos en ambiente boscoso
• Recinto vacío: Solo en recintos sin dinosaurios
• Sin T-Rex: Solo en recintos que no tengan T-Rex
• Sin restricción: Cualquier recinto válido

🦖 T-REX BONUS:
+1 punto adicional por cada recinto que contenga al menos un T-Rex

🏆 FIN DEL JUEGO:
• 4 rondas completas (12 dinosaurios por jugador)
• Gana quien tenga más puntos
• Empate: gana quien tenga menos T-Rex
  `;

  alert(mensaje);
}

/* ===== FUNCIÓN PARA ENVIAR PUNTUACIONES AL SERVIDOR (OPCIONAL) ===== */

async function enviarPuntuacionAlServidor() {
  if (!ID_PARTIDA) return;

  try {
    const response = await fetch('/api/tablero/obtenerPuntuaciones', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        partidaId: ID_PARTIDA,
        puntosJ1: calcularPuntuacionTotal(1),
        puntosJ2: calcularPuntuacionTotal(2)
      }),
      credentials: 'include'
    });

    const data = await response.json();
    if (data.success) {
      console.log('✅ Puntuaciones enviadas al servidor:', data.puntos);
    } else {
      console.error('❌ Error al enviar puntuaciones:', data.error);
    }
  } catch (error) {
    console.error('❌ Error al enviar puntuaciones:', error);
  }
}