/* =====  tablero.js - SISTEMA COMPLETO CON VALIDACIÓN DE REGLAS  ===== */

/* ------ Bloqueo frontal: sin sesión → login ------ */
if (!localStorage.getItem('userId')) {
  localStorage.clear();
  location.replace('/login');
}

/* ------ Nombres reales ------ */
let jugadorActualNombre = localStorage.getItem('jugadorActual') || 'Yo';
let rivalNombre         = localStorage.getItem('rival') || 'Invitado';

/* ------ VARIABLES GLOBALES ------ */
let draggedDino           = null;
let jugadorQueTiroDado    = Math.random() < 0.5 ? 1 : 2;
let jugadorActivo         = jugadorQueTiroDado;
let restriccionActual     = null;
let turno                 = 1;
let ronda                 = 1;
let ID_PARTIDA            = null;
const TOTAL_RONDAS        = 4;
const TOTAL_DINOSAURIOS   = 12;
let colocadosEnTurno      = 0;

let manos                 = { 1: [], 2: [] };
let puntuacionesJugadores = { 1: {}, 2: {} };

/* ------ CONFIGURACIÓN DEL JUEGO ------ */
const especies = ['dino1','dino2','dino3','dino4','dino5','trex'];
const restricciones = {
  1: "Zona izquierda (Cafetería)",
  2: "Zona derecha (Baños)",
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
    puntosPorDino: { 1: 1, 2: 3, 3: 6, 4: 10, 5: 15, 6: 21 } // según cantidad
  },
  'prado-diferencia': {
    nombre: 'El Prado de la Diferencia',
    tipo: 'diferencia', 
    ambiente: 'rocoso',
    zona: 'derecha',
    capacidadMax: 6, // máximo 6 especies diferentes
    puntosPorDino: { 1: 1, 2: 3, 3: 6, 4: 10, 5: 15, 6: 21 }
  },
  'pradera-amor': {
    nombre: 'La Pradera del Amor',
    tipo: 'parejas',
    ambiente: 'rocoso',
    zona: 'derecha',
    capacidadMax: null,
    puntosPorPareja: 5
  },
  'trio-frondoso': {
    nombre: 'El Trío Frondoso',
    tipo: 'trio',
    ambiente: 'boscoso',
    zona: 'izquierda',
    capacidadMax: 3,
    puntosExactos: 7 // solo si hay exactamente 3
  },
  'rey-selva': {
    nombre: 'El Rey de la Selva',
    tipo: 'rey',
    ambiente: 'boscoso',
    zona: 'izquierda',
    capacidadMax: 1,
    puntosGanador: 7
  },
  'isla-solitaria': {
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

/* ------ AUTO-SAVE ------ */
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
    await fetch('/api/tablero/guardarEstadoPartida', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify(payload),
      credentials: 'include'
    });
    localStorage.setItem(LS_KEY, JSON.stringify({ ...payload, ts: Date.now() }));
    localStorage.setItem('partidaIdActual', ID_PARTIDA.toString());
    console.log('Partida guardada automáticamente');
  } catch (error) {
    console.error('Error guardando partida:', error);
  }
}

/* ===== GUARDADO AUTOMÁTICO AL SALIR/CAMBIAR PÁGINA ===== */
window.addEventListener('beforeunload', function(e) {
  if (ID_PARTIDA) {
    const payload = {
      id: ID_PARTIDA,
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
      const data = new FormData();
      data.append('payload', JSON.stringify(payload));
      navigator.sendBeacon('/api/tablero/guardarEstadoPartida', data);
    }
  }
});

document.addEventListener('visibilitychange', function() {
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
  
  // Si está vacío, se puede colocar cualquier especie
  if (dinosauriosJugador.length === 0) return true;
  
  // Solo se pueden colocar dinosaurios de la misma especie
  const especieExistente = dinosauriosJugador[0].dataset.especie;
  return especieExistente === especieDino;
}

function validarPradoDiferencia(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  
  // No se pueden colocar dinosaurios de especies que ya están
  return !dinosauriosJugador.some(d => d.dataset.especie === especieDino);
}

function validarPraderaAmor(recinto, especieDino, jugador) {
  // En la pradera del amor se puede colocar cualquier dinosaurio
  return true;
}

function validarTrioFrondoso(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  // Solo se pueden colocar hasta 3 dinosaurios
  return dinosauriosJugador.length < 3;
}

function validarReySelva(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  // Solo se puede colocar 1 dinosaurio
  return dinosauriosJugador.length === 0;
}

function validarIslaSolitaria(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  // Solo se puede colocar 1 dinosaurio
  return dinosauriosJugador.length === 0;
}

function validarRio(recinto, especieDino, jugador) {
  // En el río se puede colocar cualquier dinosaurio
  return true;
}

/* ===== VALIDACIONES DE RESTRICCIONES DEL DADO ===== */

function validarZonaIzquierda(recinto) {
  return recinto.dataset.zona === 'izquierda';
}

function validarZonaDerecha(recinto) {
  return recinto.dataset.zona === 'derecha';
}

function validarZonaBoscosa(recinto) {
  return recinto.dataset.ambiente === 'boscoso';
}

function validarRecintoVacio(recinto, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  return dinosauriosJugador.length === 0;
}

function validarSinTrex(recinto, especieDino, jugador) {
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  const tieneTrex = dinosauriosJugador.some(d => d.dataset.especie === 'trex');
  
  // No se puede colocar en recintos que ya tienen T-Rex
  // Tampoco se puede colocar T-Rex en recintos que ya tienen T-Rex
  if (tieneTrex) return false;
  if (especieDino === 'trex' && tieneTrex) return false;
  
  return true;
}

/* ===== FUNCIÓN PRINCIPAL DE VALIDACIÓN ===== */

function puedeColocarDino(recinto, tipoRecinto, especieDino) {
  const dinosauriosEnRecinto = getDinosauriosJugadorEnRecinto(recinto, jugadorActivo);
  const cantidadDinos = dinosauriosEnRecinto.length;
  const config = configRecintos[tipoRecinto];

  // Validar capacidad máxima
  if (config && config.capacidadMax && cantidadDinos >= config.capacidadMax) {
    return false;
  }

  // Validar restricciones del dado (solo para quien NO tiró el dado)
  if (jugadorActivo !== jugadorQueTiroDado && restriccionActual) {
    switch (restriccionActual) {
      case 1: // Zona izquierda
        if (!validarZonaIzquierda(recinto)) return false;
        break;
      case 2: // Zona derecha  
        if (!validarZonaDerecha(recinto)) return false;
        break;
      case 3: // Zona boscosa
        if (!validarZonaBoscosa(recinto)) return false;
        break;
      case 4: // Recinto vacío
        if (!validarRecintoVacio(recinto, jugadorActivo)) return false;
        break;
      case 5: // Sin T-Rex
        if (!validarSinTrex(recinto, especieDino, jugadorActivo)) return false;
        break;
      case 6: // Sin restricción
        break;
    }
  }

  // Validar reglas específicas por tipo de recinto
  switch (tipoRecinto) {
    case 'bosque-semejanza':
      return validarBosqueSemejanza(recinto, especieDino, jugadorActivo);
    case 'prado-diferencia':
      return validarPradoDiferencia(recinto, especieDino, jugadorActivo);
    case 'pradera-amor':
      return validarPraderaAmor(recinto, especieDino, jugadorActivo);
    case 'trio-frondoso':
      return validarTrioFrondoso(recinto, especieDino, jugadorActivo);
    case 'rey-selva':
      return validarReySelva(recinto, especieDino, jugadorActivo);
    case 'isla-solitaria':
      return validarIslaSolitaria(recinto, especieDino, jugadorActivo);
    case 'rio':
      return validarRio(recinto, especieDino, jugadorActivo);
    default:
      return true;
  }
}

/* ===== CÁLCULO DE PUNTUACIONES ===== */

function calcularPuntuacionRecinto(recintoId, jugador) {
  const recinto = document.getElementById(recintoId);
  if (!recinto) return 0;

  const tipoRecinto = recinto.dataset.tipo;
  const config = configRecintos[tipoRecinto];
  const dinosauriosJugador = getDinosauriosJugadorEnRecinto(recinto, jugador);
  const cantidadDinos = dinosauriosJugador.length;

  if (cantidadDinos === 0) return 0;

  let puntos = 0;

  switch (tipoRecinto) {
    case 'bosque-semejanza':
    case 'prado-diferencia':
      puntos = config.puntosPorDino[cantidadDinos] || 0;
      break;
      
    case 'pradera-amor':
      // 5 puntos por cada pareja de la misma especie
      const especiesCount = {};
      dinosauriosJugador.forEach(d => {
        const especie = d.dataset.especie;
        especiesCount[especie] = (especiesCount[especie] || 0) + 1;
      });
      
      Object.values(especiesCount).forEach(count => {
        puntos += Math.floor(count / 2) * config.puntosPorPareja;
      });
      break;
      
    case 'trio-frondoso':
      // 7 puntos solo si hay exactamente 3 dinosaurios
      puntos = cantidadDinos === 3 ? config.puntosExactos : 0;
      break;
      
    case 'rey-selva':
      if (cantidadDinos === 1) {
        const especieDino = dinosauriosJugador[0].dataset.especie;
        // Verificar si tiene la mayoría de esa especie en todo el parque
        const totalEspecieJ1 = contarEspecieEnParque(especieDino, 1);
        const totalEspecieJ2 = contarEspecieEnParque(especieDino, 2);
        
        if (jugador === 1 && totalEspecieJ1 >= totalEspecieJ2) {
          puntos = config.puntosGanador;
        } else if (jugador === 2 && totalEspecieJ2 >= totalEspecieJ1) {
          puntos = config.puntosGanador;
        }
      }
      break;
      
    case 'isla-solitaria':
      if (cantidadDinos === 1) {
        const especieDino = dinosauriosJugador[0].dataset.especie;
        // Verificar si es el único de su especie en el parque del jugador
        const totalEspecie = contarEspecieEnParque(especieDino, jugador);
        
        if (totalEspecie === 1) {
          puntos = config.puntosSolitario;
        }
      }
      break;
      
    case 'rio':
      puntos = cantidadDinos * config.puntosPorDino;
      break;
  }

  // Bonus T-Rex: +1 punto por recinto que contenga al menos un T-Rex (excepto río)
  if (tipoRecinto !== 'rio') { // El río no aplica para el bonus T-Rex según las reglas
    const tieneTrex = dinosauriosJugador.some(d => d.dataset.especie === 'trex');
    if (tieneTrex) {
      puntos += 1;
    }
  }

  return puntos;
}

function contarEspecieEnParque(especie, jugador) {
  return [...document.querySelectorAll('.dino-in-recinto')]
    .filter(d => +d.dataset.jugador === jugador && d.dataset.especie === especie)
    .length;
}

function actualizarPuntuaciones() {
  // Actualizar puntuaciones para ambos jugadores
  [1, 2].forEach(jugador => {
    Object.keys(configRecintos).forEach(recintoId => {
      puntuacionesJugadores[jugador][recintoId] = calcularPuntuacionRecinto(recintoId, jugador);
    });
  });
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
  // El juego termina después de 4 rondas
  if (ronda > TOTAL_RONDAS) return true;
  
  // O si algún jugador ya colocó 12 dinosaurios
  const dinosJ1 = [...document.querySelectorAll('.dino-in-recinto')].filter(d => +d.dataset.jugador === 1).length;
  const dinosJ2 = [...document.querySelectorAll('.dino-in-recinto')].filter(d => +d.dataset.jugador === 2).length;
  
  return dinosJ1 >= TOTAL_DINOSAURIOS || dinosJ2 >= TOTAL_DINOSAURIOS;
}

function determinarGanador() {
  const puntosJ1 = calcularPuntuacionTotal(1);
  const puntosJ2 = calcularPuntuacionTotal(2);
  
  if (puntosJ1 > puntosJ2) return { ganador: jugadorActualNombre, puntos: [puntosJ1, puntosJ2] };
  if (puntosJ2 > puntosJ1) return { ganador: rivalNombre, puntos: [puntosJ1, puntosJ2] };
  
  // En caso de empate, gana quien tiene menos T-Rex
  const trexJ1 = contarEspecieEnParque('trex', 1);
  const trexJ2 = contarEspecieEnParque('trex', 2);
  
  if (trexJ1 < trexJ2) return { ganador: jugadorActualNombre, puntos: [puntosJ1, puntosJ2], empate: 'menos_trex' };
  if (trexJ2 < trexJ1) return { ganador: rivalNombre, puntos: [puntosJ1, puntosJ2], empate: 'menos_trex' };
  
  // Empate total
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
    r.classList.remove('recinto-available','recinto-disabled','highlight');
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
    console.log('🔄 Generando manos por primera vez');
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
  const dadoBtn   = document.getElementById('tirar-dado-btn');
  
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
  rivalNombre         = localStorage.getItem('rival') || 'Jugador 2';
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

/* ===== CARGAR PARTIDA ===== */

async function cargarPartidaYRestaurar(idPartida) {
  const r = await fetch(`/api/tablero/cargarPartida?id=${idPartida}`, {
    credentials: 'include', 
    headers: { 'Content-Type': 'application/json' }
  });
  const response = await r.json();
  if (!response.success) { 
    alert(response.message || 'Error al cargar'); 
    return; 
  }
  const p = response.data;

  jugadorActualNombre = p.jugador1 || 'Jugador 1';
  rivalNombre         = p.jugador2 || 'Invitado';
  localStorage.setItem('jugadorActual', jugadorActualNombre);
  localStorage.setItem('rival', rivalNombre);

  ID_PARTIDA          = p.id;
  ronda               = p.ronda || 1;
  turno               = p.turno || 1;
  jugadorActivo       = p.jugadorActivo || 1;
  jugadorQueTiroDado  = p.jugadorQueTiroDado || 1;
  restriccionActual   = p.restriccion || null;
  colocadosEnTurno    = p.colocadosEnTurno || 0;
  manos[1]            = Array.isArray(p.mano1) ? p.mano1 : [];
  manos[2]            = Array.isArray(p.mano2) ? p.mano2 : [];

  document.querySelectorAll('.dino-in-recinto').forEach(d => d.remove());
  (p.colocaciones || []).forEach(c => {
    const r = document.getElementById(c.recinto);
    if (!r) return;
    const img = document.createElement('img');
    img.src = `/img/imagen_Tablero/${c.especie}.png`;
    img.className = 'dino-in-recinto';
    img.dataset.especie = c.especie;
    img.dataset.jugador = c.jugador;
    img.style.pointerEvents = 'none';
    r.appendChild(img);
  });

  if (restriccionActual !== null) {
    const dadoImg = document.getElementById('dado-img');
    const restriccionText = document.getElementById('restriccion-text');
    if (dadoImg) dadoImg.src = `/img/dado/dado${restriccionActual}.png`;
    if (restriccionText) restriccionText.textContent = restricciones[restriccionActual];
  }
  
  localStorage.setItem('partidaIdActual', ID_PARTIDA.toString());
  actualizarUI();
}

/* ===== RESTAURAR AL REFRESCAR ===== */

(async () => {
  const id = localStorage.getItem('partidaACargar') || localStorage.getItem('partidaId');
  if (id) {
    await cargarPartidaYRestaurar(+id);
    localStorage.removeItem('partidaACargar');
    localStorage.removeItem('partidaId');
    return;
  }
  const ls = localStorage.getItem(LS_KEY);
  if (ls) {
    try {
      const data = JSON.parse(ls);
      if (data.id) { 
        await cargarPartidaYRestaurar(data.id); 
        return; 
      }
    } catch {}
  }
  await inicializarPartidaNueva();
})();

/* ===== RESTO DEL FLUJO DE JUEGO ===== */

document.addEventListener('DOMContentLoaded', () => {
  // Cargar avatar del usuario al iniciar
  cargarAvatarUsuario();
  
  const recintos = document.querySelectorAll('.recinto');
  const dadoBtn  = document.getElementById('tirar-dado-btn');
  const dadoImg  = document.getElementById('dado-img');
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
        // Generar nuevas manos para la próxima ronda
        manos[1] = generarMano();
        manos[2] = generarMano();
      } else {
        // Intercambio de manos restantes según reglas 2 jugadores
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

  async function mostrarResultadosFinal() {
    try {
      // Limpiar localStorage primero
      localStorage.removeItem(LS_KEY);
      localStorage.removeItem('partidaIdActual');
      localStorage.removeItem('partidaACargar');
      
      // Calcular resultado final
      const resultado = determinarGanador();
      
      // Si hay una partida guardada en la BD, marcarla como finalizada
      if (ID_PARTIDA) {
        console.log('Finalizando partida ID:', ID_PARTIDA);
        
        await fetch('/api/tablero/finalizarPartida', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            partidaId: ID_PARTIDA,
            resultadoFinal: {
              ganador: resultado.ganador,
              puntos_j1: resultado.puntos[0],
              puntos_j2: resultado.puntos[1]
            }
          })
        }).catch(err => {
          console.error('Error finalizando partida:', err);
          // Fallback: eliminar la partida si no se puede finalizar
          return fetch('/api/tablero/eliminarPartida', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ partidaId: ID_PARTIDA })
          });
        });
      }
      
      // Mostrar resultado final detallado
      let mensaje = `¡Juego terminado!\n\n`;
      mensaje += `${jugadorActualNombre}: ${resultado.puntos[0]} puntos\n`;
      mensaje += `${rivalNombre}: ${resultado.puntos[1]} puntos\n\n`;
      
      if (resultado.ganador) {
        mensaje += `🏆 Ganador: ${resultado.ganador}`;
        if (resultado.empate === 'menos_trex') {
          mensaje += ` (desempate por menos T-Rex)`;
        }
      } else {
        mensaje += `🤝 ¡Empate perfecto!`;
      }
      
      mensaje += `\n\nGracias por jugar Draftosaurus.`;
      
      alert(mensaje);
      
      // Redirigir al perfil
      location.href = '/perfil';
      
    } catch (error) {
      console.error('Error al finalizar partida:', error);
      // Aunque haya error, igual redirigimos al perfil
      alert(`¡Juego terminado!\n\n${jugadorActualNombre} vs ${rivalNombre}\n\nGracias por jugar.`);
      location.href = '/perfil';
    }
  }

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
        // Validación adicional del servidor
        try {
          const validacion = await fetch('/api/tablero/validarJugada', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              partidaId: ID_PARTIDA,
              recinto: recinto.dataset.tipo,   // ✅ CLAVE CORRECTA
              dinosaurio: especieDino,
              jugador: jugadorActivo,
              restriccion: restriccionActual
            })
          });
          
          const resultado = await validacion.json();
          
          if (resultado.valid) {
            await colocarDino(recinto, especieDino);
          } else {
            alert(`Jugada no válida: ${resultado.errors?.join(', ') || 'Error desconocido'}`);
          }
          
        } catch (error) {
          console.error('Error validando jugada:', error);
          // Si falla la validación del servidor, usar validación local
          await colocarDino(recinto, especieDino);
        }
      } else {
        const validos = [...recintos].filter(r => 
          puedeColocarDino(r, r.dataset.tipo, especieDino)
        );
        const nombres = validos.map(r => {
          const config = configRecintos[r.dataset.tipo];
          return config ? config.nombre : r.dataset.nombre || r.id;
        }).join(', ');
        
        alert(`No puedes colocar aquí.\nRecintos válidos: ${nombres || 'Ninguno'}`);
      }
    });
  });

  document.getElementById('btn-guardar')?.addEventListener('click', autoSave);
  
  // Botón de ayuda para mostrar reglas
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
• Bosque de la Semejanza: Solo misma especie, de izq. a der.
• Prado de la Diferencia: Solo especies distintas, de izq. a der.
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

/* ===== FUNCIONES PARA CONTROLAR LA CONTINUIDAD DE PARTIDAS ===== */

window.verificarPartidaGuardada = function() {
  const partidaGuardada = localStorage.getItem('partidaIdActual') || localStorage.getItem('partidaACargar');
  
  if (partidaGuardada) {
    const modalHTML = `
      <div class="modal fade" id="modalContinuarPartida" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content draftosaurus-modal">
            <div class="modal-header">
              <h5 class="modal-title">¿Querés continuar tu partida?</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
              <p>Tenés una partida en curso. ¿Querés continuarla o empezar una nueva?</p>
            </div>
            <div class="modal-footer d-flex justify-content-center gap-3">
              <button class="btn btn-custom" onclick="continuarPartidaGuardada()">Continuar</button>
              <button class="btn btn-outline-light" onclick="nuevaPartidaDesdePerfil()">Nueva partida</button>
            </div>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = new bootstrap.Modal(document.getElementById('modalContinuarPartida'));
    modal.show();
  }
};

window.continuarPartidaGuardada = function() {
  const modal = bootstrap.Modal.getInstance(document.getElementById('modalContinuarPartida'));
  modal.hide();
  window.location.href = '/tablero';
};

window.nuevaPartidaDesdePerfil = function() {
  // Limpiar partida actual
  localStorage.removeItem('partidaIdActual');
  localStorage.removeItem('partidaACargar');
  localStorage.removeItem('esPartidaNueva');
  localStorage.removeItem(LS_KEY);

  const modal = bootstrap.Modal.getInstance(document.getElementById('modalContinuarPartida'));
  modal.hide();

  // Mostrar modal para elegir tipo de jugador
  const modalCrear = new bootstrap.Modal(document.getElementById('modalCrearPartida'));
  modalCrear.show();
};