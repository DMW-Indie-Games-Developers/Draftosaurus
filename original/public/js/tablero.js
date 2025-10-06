/* =====  tablero.js - SISTEMA COMPLETO CON VALIDACIÓN DE REGLAS  ===== */

/* ------ VALIDACIONES ANTI-TRAMPA ------ */

// 1. Bloqueo frontal: sin sesión → login
if (!localStorage.getItem('userId')) {
  localStorage.clear();
  location.replace('/login');
}

// 2. Prevenir navegación hacia atrás durante el juego
let partidaEnCurso = false;
let estadoPartidaGuardado = false;

// Función para prevenir salida accidental/intencional
function prevenirSalidaAccidental() {
  window.addEventListener('beforeunload', function(e) {
    if (partidaEnCurso && !estadoPartidaGuardado) {
      let message = '¿Estás seguro de que quieres salir? Se perderá el progreso de la partida.';

      // Mensaje más estricto durante jugadas críticas
      if (jugadaCritica) {
        message = '🚫 ATENCIÓN: No puedes salir durante una jugada crítica. Se perderá el progreso.';
      }

      e.returnValue = message;
      return message;
    }
  });

  // Manejo del botón "Atrás" del navegador
  window.addEventListener('popstate', function(e) {
    if (partidaEnCurso) {
      let mensaje = '¿Quieres abandonar la partida? Se perderá el progreso actual.';

      // Protección extra durante jugadas críticas
      if (jugadaCritica) {
        alert('🚫 No puedes salir durante una jugada crítica. Espera a completar la acción.');
        history.pushState(null, null, window.location.pathname);
        return;
      }

      const confirmar = confirm(mensaje);
      if (confirmar) {
        // Limpiar datos de partida y permitir salida
        localStorage.removeItem('partidaIdActual');
        localStorage.removeItem('draftosaurus_autosave');
        partidaEnCurso = false;
        history.back();
      } else {
        // Mantener en la página actual
        history.pushState(null, null, window.location.pathname);
      }
    }
  });

  // Agregar estado al historial para detectar navegación
  history.pushState(null, null, window.location.pathname);
}

// 3. Validar integridad de la partida al cargar
function validarIntegridadPartida() {
  console.log('🛡️ Validando integridad de la partida...');

  const partidaId = localStorage.getItem('partidaIdActual');
  const partidaACargar = localStorage.getItem('partidaACargar');
  const autosave = localStorage.getItem('draftosaurus_autosave');

  // Si tenemos una partida a cargar, permitir que se ejecute la carga
  if (partidaACargar || partidaId) {
    console.log('🔄 Partida a cargar detectada, saltando validación inicial:', {
      partidaACargar,
      partidaId
    });
    return true;
  }

  // Solo validar autosave si no hay partida para cargar
  if (!autosave) {
    console.warn('❌ No hay datos de partida válidos');
    if (confirm('No se encontraron datos de partida válidos. ¿Volver al perfil?')) {
      window.location.href = '/perfil';
    }
    return false;
  }

  // Validar estructura del autosave
  try {
    const data = JSON.parse(autosave);
    if (!data.ronda_actual || !data.turno_actual || !data.manos) {
      console.warn('❌ Datos de autosave corruptos');
      localStorage.removeItem('draftosaurus_autosave');

      // Si tenemos partidaId pero autosave corrupto, intentar cargar desde servidor
      if (partidaId) {
        console.log('🔄 Intentando cargar desde servidor debido a autosave corrupto');
        return true;
      }

      return false;
    }
  } catch (e) {
    console.warn('❌ Error al parsear autosave:', e);
    localStorage.removeItem('draftosaurus_autosave');

    // Si tenemos partidaId pero autosave corrupto, intentar cargar desde servidor
    if (partidaId) {
      console.log('🔄 Intentando cargar desde servidor debido a error de parsing');
      return true;
    }

    return false;
  }

  console.log('✅ Integridad de partida validada');
  return true;
}

// 4. Anti-refresh durante jugadas críticas
let jugadaCritica = false;

function marcarJugadaCritica(critica = true) {
  jugadaCritica = critica;
  if (critica) {
    console.log('🔒 Jugada crítica iniciada - anti-refresh activado');
  } else {
    console.log('🔓 Jugada crítica finalizada');
  }
}

// 5. Función para marcar partida como guardada
function marcarPartidaGuardada() {
  estadoPartidaGuardado = true;
  console.log('💾 Partida marcada como guardada');
}

// 6. Función para deshabilitar dinosaurios si no se tiró el dado
function deshabilitarDinosauriosSinDado() {
  const dinosPanel = document.querySelector('.dino-panel');
  const dinosImages = document.querySelectorAll('.dino-selectable, .dino-grid img');

  if (!dinosPanel) return;

  // Limpiar overlay existente
  const existingOverlay = dinosPanel.querySelector('.dino-panel-overlay');
  if (existingOverlay) {
    existingOverlay.remove();
  }

  if (restriccionActual === null) {
    // 🚫 DESHABILITAR: No se tiró el dado
    console.log('🚫 Dinosaurios deshabilitados - dado no tirado');

    // Agregar clase CSS de deshabilitado
    dinosPanel.classList.add('disabled');
    dinosPanel.style.pointerEvents = 'none';
    dinosPanel.title = '🎲 Primero debes tirar el dado';

    // Crear overlay visual con transición
    const overlay = document.createElement('div');
    overlay.className = 'dino-panel-overlay';
    overlay.style.opacity = '0';
    overlay.innerHTML = `
      <div class="icon">🎲</div>
      <div>TIRA EL DADO PRIMERO</div>
      <div style="font-size: 12px; margin-top: 5px;">Los dinosaurios están bloqueados</div>
    `;
    dinosPanel.appendChild(overlay);

    // Transición suave del overlay
    setTimeout(() => {
      overlay.style.transition = 'opacity 0.3s ease';
      overlay.style.opacity = '1';
    }, 50);

    // Deshabilitar individualmente cada dinosaurio
    dinosImages.forEach(dino => {
      dino.classList.add('disabled');
      dino.style.filter = 'grayscale(100%) brightness(0.3)';
      dino.style.cursor = 'not-allowed';
      dino.style.pointerEvents = 'none';
      dino.draggable = false;

      // Remover eventos existentes
      dino.removeEventListener('dragstart', handleDragStart);
      dino.removeEventListener('click', handleDinoClick);
    });

  } else {
    // ✅ HABILITAR: Ya se tiró el dado
    console.log('✅ Dinosaurios habilitados - dado ya tirado');

    // Remover clase CSS de deshabilitado
    dinosPanel.classList.remove('disabled');
    dinosPanel.style.pointerEvents = 'auto';
    dinosPanel.title = '';

    // Habilitar individualmente cada dinosaurio
    dinosImages.forEach(dino => {
      dino.classList.remove('disabled');
      dino.style.filter = 'none';
      dino.style.cursor = 'grab';
      dino.style.pointerEvents = 'auto';
      dino.draggable = true;

      // Re-agregar eventos
      dino.addEventListener('dragstart', handleDragStart);
      dino.addEventListener('click', handleDinoClick);
    });
  }
}

// Función auxiliar para manejar drag start
function handleDragStart(e) {
  if (restriccionActual === null) {
    e.preventDefault();
    alert('🎲 Primero debes tirar el dado antes de arrastrar dinosaurios');
    console.warn('🚫 Intento de drag sin tirar dado - BLOQUEADO');
    return false;
  }

  // Extraer información del elemento
  const img = e.target;
  const especie = img.dataset.especie || img.getAttribute('data-especie');
  const index = Array.from(img.parentNode.children).indexOf(img);

  draggedDino = { especie, index, jugador: jugadorActivo };
  img.classList.add('dragging');
  e.dataTransfer.setData('text/plain', especie);
  highlightRecintos(especie);
}

// Función auxiliar para manejar click en dinosaurio
function handleDinoClick(e) {
  if (restriccionActual === null) {
    e.preventDefault();
    alert('🎲 Primero debes tirar el dado antes de seleccionar dinosaurios');
    console.warn('🚫 Intento de click sin tirar dado - BLOQUEADO');
    return false;
  }

  // Aquí se puede agregar lógica adicional de click si es necesaria
  console.log('✅ Click en dinosaurio permitido:', e.target.dataset.especie);
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

/* ------ PROPIEDADES FÍSICAS DE LOS DINOSAURIOS ------ */
const propiedadesFisicas = {
  'dino1': { nombre: 'Compsognathus', masa: 2.5 },     // kg
  'dino2': { nombre: 'Velociraptor', masa: 15.0 },     // kg
  'dino3': { nombre: 'Parasaurolophus', masa: 3500.0 }, // kg
  'dino4': { nombre: 'Triceratops', masa: 6000.0 },    // kg
  'dino5': { nombre: 'Brontosaurus', masa: 15000.0 },  // kg
  'trex': { nombre: 'Tyrannosaurus Rex', masa: 7000.0 } // kg
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
  if (!ID_PARTIDA) {
    console.warn('⚠️ No hay ID_PARTIDA para guardar');
    return;
  }

  // No guardar si la partida ya terminó
  if (ronda > TOTAL_RONDAS) {
    console.warn('⚠️ No se puede guardar: partida terminada (ronda > TOTAL_RONDAS)');
    return;
  }

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
      console.log('✅ Partida guardada automáticamente - ID:', ID_PARTIDA);
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

/* ===== FUNCIONES DE PESO ===== */

function calcularPesoRecinto(recintoId) {
  const recinto = document.getElementById(recintoId);
  if (!recinto) return 0;

  const dinosauriosEnRecinto = [...recinto.querySelectorAll('.dino-in-recinto')];
  let pesoTotal = 0;

  dinosauriosEnRecinto.forEach(dino => {
    const especie = dino.dataset.especie;
    const propiedades = propiedadesFisicas[especie];
    if (propiedades) {
      pesoTotal += propiedades.masa;
    }
  });

  return pesoTotal;
}

function formatearPeso(peso) {
  if (peso === 0) return '0kg';

  if (peso >= 1000) {
    return `${(peso / 1000).toFixed(1)}t`; // Toneladas
  } else {
    return `${peso.toFixed(1)}kg`; // Kilogramos
  }
}

function calcularPesoTotalJugador(jugador) {
  const dinosauriosJugador = [...document.querySelectorAll('.dino-in-recinto')]
    .filter(d => +d.dataset.jugador === jugador);

  let pesoTotal = 0;
  dinosauriosJugador.forEach(dino => {
    const especie = dino.dataset.especie;
    const propiedades = propiedadesFisicas[especie];
    if (propiedades) {
      pesoTotal += propiedades.masa;
    }
  });

  return pesoTotal;
}

function obtenerRecintoMasPesado() {
  const recintos = Object.keys(configRecintos);
  let pesoMaximo = 0;
  let detalleRecinto = null;

  console.log('🔍 Analizando recintos:', recintos);

  recintos.forEach(recintoId => {
    const recinto = document.getElementById(recintoId);
    if (!recinto) {
      console.log(`⚠️ No se encontró el recinto: ${recintoId}`);
      return;
    }

    const peso = calcularPesoRecinto(recintoId);
    const config = configRecintos[recintoId];
    const dinosaurios = [...recinto.querySelectorAll('.dino-in-recinto')];

    console.log(`📊 ${recintoId}: ${formatearPeso(peso)} (${dinosaurios.length} dinosaurios)`);

    if (peso > pesoMaximo) {
      pesoMaximo = peso;

      detalleRecinto = {
        id: recintoId,
        nombre: config ? config.nombre : recintoId,
        peso: peso,
        pesoFormateado: formatearPeso(peso),
        dinosaurios: dinosaurios.map(dino => ({
          especie: dino.dataset.especie,
          jugador: dino.dataset.jugador,
          nombre: propiedadesFisicas[dino.dataset.especie]?.nombre || dino.dataset.especie,
          masa: propiedadesFisicas[dino.dataset.especie]?.masa || 0
        }))
      };

      console.log(`🏆 Nuevo líder: ${detalleRecinto.nombre} con ${formatearPeso(peso)}`);
    }
  });

  console.log('🏋️ Recinto ganador final:', detalleRecinto);
  return detalleRecinto;
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

/* ===== FUNCIÓN AUXILIAR PARA DETERMINAR GANADOR LOCAL ===== */
function determinarGanadorLocal() {
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

    // Agregar tooltip con información de peso
    const propiedades = propiedadesFisicas[esp];
    if (propiedades) {
      img.title = `${propiedades.nombre} - ${formatearPeso(propiedades.masa)}`;
    }

    grid.appendChild(img);

    img.addEventListener('dragstart', e => {
      // 🛡️ VALIDACIÓN: No permitir drag si no se tiró el dado
      if (restriccionActual === null) {
        e.preventDefault();
        alert('🎲 Primero debes tirar el dado antes de arrastrar dinosaurios');
        console.warn('🚫 Intento de drag sin tirar dado - BLOQUEADO');
        return false;
      }

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
  // CALCULAR PUNTUACIONES ANTES DE CUALQUIER MODIFICACIÓN
  const puntosFinalesJ1 = calcularPuntuacionTotal(1);
  const puntosFinalesJ2 = calcularPuntuacionTotal(2);
  console.log(`Puntos finales calculados ANTES de procesar: J1=${puntosFinalesJ1}, J2=${puntosFinalesJ2}`);
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

  // 🛡️ VALIDACIÓN: Deshabilitar dinosaurios si no se tiró el dado
  deshabilitarDinosauriosSinDado();

  if (!draggedDino) clearHighlight();
}

function actualizarPuntuacionJugadorActivo() {
  // Actualizar ambos jugadores, no solo el activo
  actualizarPuntuacionesAmbosJugadores();
}

function actualizarPuntuacionesAmbosJugadores() {
  // Actualizar jugador 1
  const totalJ1 = calcularPuntuacionTotal(1);
  const pesoJ1 = calcularPesoTotalJugador(1);
  const puntosJ1Element = document.getElementById('puntos-j1');
  const nombreJ1Element = document.getElementById('nombre-j1');

  if (puntosJ1Element) {
    puntosJ1Element.textContent = totalJ1;
    puntosJ1Element.title = `Puntos: ${totalJ1}\nPeso total: ${formatearPeso(pesoJ1)}`;
  }

  if (nombreJ1Element) {
    nombreJ1Element.textContent = jugadorActualNombre;
  }

  // Actualizar jugador 2
  const totalJ2 = calcularPuntuacionTotal(2);
  const pesoJ2 = calcularPesoTotalJugador(2);
  const puntosJ2Element = document.getElementById('puntos-j2');
  const nombreJ2Element = document.getElementById('nombre-j2');

  if (puntosJ2Element) {
    puntosJ2Element.textContent = totalJ2;
    puntosJ2Element.title = `Puntos: ${totalJ2}\nPeso total: ${formatearPeso(pesoJ2)}`;
  }

  if (nombreJ2Element) {
    nombreJ2Element.textContent = rivalNombre;
  }

  // Resaltar jugador activo
  const rowJ1 = puntosJ1Element?.parentElement;
  const rowJ2 = puntosJ2Element?.parentElement;

  if (rowJ1 && rowJ2) {
    rowJ1.classList.toggle('jugador-activo', jugadorActivo === 1);
    rowJ2.classList.toggle('jugador-activo', jugadorActivo === 2);
  }
}

function actualizarPuntuacionesVisualesRecintos() {
  document.querySelectorAll('.recinto').forEach(r => {
    const peso = calcularPesoRecinto(r.id);
    const scoreElement = r.querySelector('.recinto-score');
    if (scoreElement) {
      scoreElement.textContent = formatearPeso(peso);

      // Agregar tooltip con información detallada
      const dinosauriosEnRecinto = [...r.querySelectorAll('.dino-in-recinto')];
      if (dinosauriosEnRecinto.length > 0) {
        let tooltipText = `Peso total: ${formatearPeso(peso)}\n\nDinosaurios:\n`;
        dinosauriosEnRecinto.forEach(dino => {
          const especie = dino.dataset.especie;
          const jugador = dino.dataset.jugador;
          const nombreJugador = jugador == 1 ? jugadorActualNombre : rivalNombre;
          const props = propiedadesFisicas[especie];
          if (props) {
            tooltipText += `• ${props.nombre} (${nombreJugador}): ${formatearPeso(props.masa)}\n`;
          }
        });
        scoreElement.title = tooltipText;
      } else {
        scoreElement.title = `Recinto vacío - ${formatearPeso(peso)}`;
      }
    }
  });
}

function actualizarVisibilidadDinos() {
  document.querySelectorAll('.dino-in-recinto').forEach(d => {
    d.style.display = (+d.dataset.jugador === jugadorActivo) ? 'block' : 'none';
  });
}

/* ===== INICIALIZAR PARTIDA ===== */

async function inicializarPartidaNueva() {
  console.log('🆕 Inicializando partida nueva...');

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

  // ✅ CREAR NUEVA PARTIDA EN EL SERVIDOR Y ASIGNAR ID_PARTIDA
  try {
    const response = await fetch('/api/tablero/crearPartida', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        jugador1: jugadorActualNombre,
        jugador2: rivalNombre
      })
    });

    const result = await response.json();
    if (result.success && result.partidaId) {
      ID_PARTIDA = result.partidaId;
      localStorage.setItem('partidaIdActual', ID_PARTIDA.toString());
      console.log('✅ Partida nueva creada con ID:', ID_PARTIDA);
    } else {
      console.error('❌ Error creando partida:', result.error);
    }
  } catch (error) {
    console.error('❌ Error creando partida:', error);
  }

  localStorage.removeItem('esPartidaNueva');

  // 🛡️ VALIDACIÓN INICIAL: Aplicar restricciones al iniciar
  deshabilitarDinosauriosSinDado();

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
    console.log('🔄 Cargando partida ID:', idPartida);

    console.log('📡 Enviando petición GET a:', `/api/tablero/cargarPartida?id=${idPartida}`);
    const response = await fetch(`/api/tablero/cargarPartida?id=${idPartida}`, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });

    console.log('📨 Respuesta recibida:', {
      status: response.status,
      statusText: response.statusText,
      ok: response.ok,
      headers: Object.fromEntries(response.headers.entries())
    });

    if (!response.ok) {
      console.error('❌ Error HTTP:', response.status, response.statusText);
      throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
    }

    const data = await response.json();
    console.log('📦 Datos cargados desde servidor:', data);

    if (!data.success) {
      throw new Error(data.message || 'Error al cargar partida');
    }

    const partida = data.data;

    // ✅ ASIGNAR ID_PARTIDA CORRECTAMENTE
    ID_PARTIDA = partida.id;
    console.log('✅ ID_PARTIDA asignado:', ID_PARTIDA);

    // Establecer nombres
    jugadorActualNombre = partida.jugador1 || 'Jugador 1';
    rivalNombre = partida.jugador2 || partida.name_invitado || 'Invitado';
    localStorage.setItem('jugadorActual', jugadorActualNombre);
    localStorage.setItem('rival', rivalNombre);

    // Establecer estado del juego
    console.log('✅ Partida cargada correctamente');
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

    // ✅ GUARDAR ID_PARTIDA EN LOCALSTORAGE
    localStorage.setItem('partidaIdActual', ID_PARTIDA.toString());
    console.log('✅ Partida cargada correctamente con ID:', ID_PARTIDA);

    // Actualizar pesos después de restaurar colocaciones
    actualizarPuntuacionesVisualesRecintos();
    actualizarPuntuacionesAmbosJugadores();

    // 🛡️ VALIDACIÓN: Aplicar restricciones visuales según estado del dado
    deshabilitarDinosauriosSinDado();

    actualizarUI();

  } catch (error) {
    console.error('❌ Error cargando partida:', error);
    console.error('Stack trace:', error.stack);
    console.error('Partida ID que falló:', idPartida);

    // Limpiar datos corruptos
    localStorage.removeItem('partidaACargar');
    localStorage.removeItem('draftosaurus_autosave');

    const continuar = confirm(`Error al cargar la partida ${idPartida}:\n${error.message}\n\n¿Iniciar una nueva partida?`);

    if (continuar) {
      await inicializarPartidaNueva();
    } else {
      window.location.href = '/perfil';
    }
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
  const lsPartidaIdActual = localStorage.getItem('partidaIdActual');
  const lsAutosave = localStorage.getItem(LS_KEY);

  console.log('🔍 Buscando partida a cargar:', {
    queryId,
    lsPartidaACargar,
    lsPartidaId,
    lsPartidaIdActual,
    lsAutosave: !!lsAutosave
  });

  // 3. Prioridad: query string > partidaACargar > partidaIdActual > partidaId > autosave
  let partidaIdACargar = null;

  if (queryId && !isNaN(queryId)) {
    partidaIdACargar = parseInt(queryId);
    console.log('🔗 Usando ID de query string:', partidaIdACargar);
  } else if (lsPartidaACargar && !isNaN(lsPartidaACargar)) {
    partidaIdACargar = parseInt(lsPartidaACargar);
    console.log('💾 Usando partidaACargar:', partidaIdACargar);
  } else if (lsPartidaIdActual && !isNaN(lsPartidaIdActual)) {
    partidaIdACargar = parseInt(lsPartidaIdActual);
    console.log('🆔 Usando partidaIdActual:', partidaIdACargar);
  } else if (lsPartidaId && !isNaN(lsPartidaId)) {
    partidaIdACargar = parseInt(lsPartidaId);
    console.log('💿 Usando partidaId:', partidaIdACargar);
  } else if (lsAutosave) {
    try {
      const data = JSON.parse(lsAutosave);
      if (data.id && !isNaN(data.id)) {
        partidaIdACargar = parseInt(data.id);
        console.log('🔄 Usando ID de autosave:', partidaIdACargar);
      }
    } catch (e) {
      console.warn('⚠️ Error parsing autosave:', e);
    }
  }

  // 4. Cargar partida o inicializar nueva
  if (partidaIdACargar) {
    console.log(`🔄 Intentando cargar partida ID: ${partidaIdACargar}`);
    try {
      await cargarPartidaYRestaurar(partidaIdACargar);
      console.log(`✅ Partida ${partidaIdACargar} cargada exitosamente`);

      // Limpiar flags de carga (excepto partidaIdActual)
      localStorage.removeItem('partidaACargar');
      localStorage.removeItem('partidaId');
    } catch (error) {
      console.error(`❌ Error fatal cargando partida ${partidaIdACargar}:`, error);
      console.error('Redirigiendo a partida nueva...');
      await inicializarPartidaNueva();
    }
  } else {
    console.log('🆕 No hay partida para cargar, iniciando partida nueva');
    await inicializarPartidaNueva();
  }
})();

/* ===== FUNCIÓN DE FINALIZACIÓN CORREGIDA ===== */
async function mostrarResultadosFinal() {
  console.log('🏁 Iniciando finalización de partida...');
  console.log('🆔 ID_PARTIDA actual:', ID_PARTIDA);

  try {
    // ASEGURAR QUE LAS PUNTUACIONES ESTÉN ACTUALIZADAS
    actualizarPuntuaciones();

    // PREPARAR DATOS PARA EL SERVIDOR
    const colocaciones = [...document.querySelectorAll('.dino-in-recinto')].map(d => ({
      recinto: d.parentElement.id,
      jugador: parseInt(d.dataset.jugador),
      especie: d.dataset.especie
    }));

    console.log('📊 Colocaciones finales:', colocaciones);

    // GUARDAR ESTADO FINAL ANTES DE FINALIZAR
    if (ID_PARTIDA) {
      const estadoFinal = {
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

      // Guardar estado final
      try {
        const saveResponse = await fetch('/api/tablero/guardarEstadoPartida', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(estadoFinal),
          credentials: 'include'
        });
        const saveResult = await saveResponse.json();
        console.log('✅ Estado final guardado:', saveResult.success);
      } catch (e) {
        console.warn('⚠️ Error guardando estado final:', e);
      }
    }

    const puntosLocalesJ1 = calcularPuntuacionTotal(1);
    const puntosLocalesJ2 = calcularPuntuacionTotal(2);
    console.log(`Puntos calculados localmente: J1=${puntosLocalesJ1}, J2=${puntosLocalesJ2}`);

    let resultado = null;

    if (ID_PARTIDA) {
      console.log('🎯 Finalizando partida ID:', ID_PARTIDA);

      try {
        const response = await fetch('/api/tablero/finalizarPartida', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            partidaId: ID_PARTIDA
          })
        });

        const data = await response.json();
        console.log('📊 Respuesta del servidor:', data);

        if (data.success) {
          // Usar datos del servidor
          const puntos = data.puntos || [puntosLocalesJ1, puntosLocalesJ2];
          const nombreGanador = data.nombreGanador;

          resultado = {
            puntos: puntos,
            ganador: nombreGanador,
            esServidor: true
          };

          console.log('✅ Usando puntuación del servidor:', resultado);
          console.log('🎯 Puntos del servidor:', puntos);
          console.log('🏆 Ganador del servidor:', nombreGanador);
        } else {
          console.error('❌ Error del servidor:', data.error);
          throw new Error(data.error || 'Error finalizando partida');
        }

      } catch (error) {
        console.error('❌ Error finalizando partida:', error);
        // Fallback: usar cálculo local si falla el servidor
        resultado = determinarGanadorLocal();
        resultado.esServidor = false;
        console.log('⚠️ Usando cálculo local como fallback:', resultado);
      }
    } else {
      // Si no hay ID_PARTIDA, usar cálculo local
      console.warn('⚠️ No hay ID_PARTIDA, usando cálculo local');
      resultado = determinarGanadorLocal();
      resultado.esServidor = false;
      console.log('🔧 Usando cálculo local (sin ID_PARTIDA):', resultado);
    }

    // LIMPIAR DATOS DESPUÉS DE FINALIZAR
    console.log('🧹 Limpiando datos de localStorage después de finalizar...');
    localStorage.removeItem(LS_KEY);
    localStorage.removeItem('partidaIdActual');
    localStorage.removeItem('partidaACargar');
    localStorage.removeItem('partidaId');
    localStorage.removeItem('esPartidaNueva');

    // También limpiar ID_PARTIDA global para evitar referencias a partida finalizada
    console.log(`🏁 Partida ${ID_PARTIDA} finalizada - limpiando ID_PARTIDA global`);
    ID_PARTIDA = null;

    // MOSTRAR MODAL MEJORADO
    console.log('📋 A punto de mostrar modal con resultado:', resultado);

    try {
      mostrarModalResultados(resultado);
      console.log('✅ Modal llamado exitosamente');
    } catch (modalError) {
      console.error('❌ Error al llamar mostrarModalResultados:', modalError);
      console.error('Stack trace modal:', modalError.stack);
      // Fallback: alert con información
      alert(`¡Partida Finalizada!\n\n${resultado.ganador ? 'Ganador: ' + resultado.ganador : 'Empate'}\n\nPuntos: ${resultado.puntos[0]} vs ${resultado.puntos[1]}`);
    }

  } catch (error) {
    console.error('❌ Error al finalizar partida:', error);
    console.error('Stack trace:', error.stack);

    // Fallback: mostrar modal con cálculo local
    console.log('🔧 Usando fallback con cálculo local de puntos');
    const puntosLocalesJ1 = calcularPuntuacionTotal(1);
    const puntosLocalesJ2 = calcularPuntuacionTotal(2);

    const resultadoFallback = {
      puntos: [puntosLocalesJ1, puntosLocalesJ2],
      ganador: puntosLocalesJ1 > puntosLocalesJ2 ? jugadorActualNombre :
               puntosLocalesJ2 > puntosLocalesJ1 ? rivalNombre : null,
      esServidor: false
    };

    mostrarModalResultados(resultadoFallback);
  }

  // 🛡️ VALIDACIÓN ANTI-TRAMPA: Marcar partida como finalizada y guardada
  marcarPartidaGuardada();
  partidaEnCurso = false;
  console.log('🛡️ Partida finalizada - protecciones desactivadas');
}

// MODAL DE RESULTADOS MEJORADO
function mostrarModalResultados(resultado) {
  console.log('🎯 INICIO mostrarModalResultados con resultado:', resultado);
  console.log('🔍 Tipo de resultado:', typeof resultado);
  console.log('🔍 Propiedades resultado:', Object.keys(resultado || {}));

  // Asegurar que tenemos puntos válidos
  let puntosJ1 = 0;
  let puntosJ2 = 0;

  if (resultado && resultado.puntos && Array.isArray(resultado.puntos)) {
    puntosJ1 = resultado.puntos[0] || 0;
    puntosJ2 = resultado.puntos[1] || 0;
  } else {
    // Fallback: calcular puntos localmente si no vienen del servidor
    console.warn('⚠️ No se recibieron puntos válidos del servidor, calculando localmente');
    puntosJ1 = calcularPuntuacionTotal(1);
    puntosJ2 = calcularPuntuacionTotal(2);
  }

  console.log(`📊 Puntos finales a mostrar: J1=${puntosJ1}, J2=${puntosJ2}`);

  // Obtener información del recinto más pesado
  const recintoMasPesado = obtenerRecintoMasPesado();
  console.log('🏋️ Recinto más pesado completo:', recintoMasPesado);

  if (recintoMasPesado) {
    console.log('🏋️ ID:', recintoMasPesado.id);
    console.log('🏋️ Nombre:', recintoMasPesado.nombre);
    console.log('🏋️ Peso:', recintoMasPesado.peso);
    console.log('🏋️ Dinosaurios:', recintoMasPesado.dinosaurios);
  }

  // Obtener pesos totales de cada jugador
  const pesoTotalJ1 = calcularPesoTotalJugador(1);
  const pesoTotalJ2 = calcularPesoTotalJugador(2);
  console.log(`⚖️ Pesos totales: J1=${formatearPeso(pesoTotalJ1)}, J2=${formatearPeso(pesoTotalJ2)}`);

  let estadoGanador = '';
  let iconoGanador = '';

  // Determinar ganador basado en los puntos finales
  if (puntosJ1 > puntosJ2) {
    estadoGanador = `Ganador: ${jugadorActualNombre}`;
    iconoGanador = '🏆';
  } else if (puntosJ2 > puntosJ1) {
    estadoGanador = `Ganador: ${rivalNombre}`;
    iconoGanador = '🏆';
  } else {
    // En caso de empate, verificar criterio de desempate
    const trexJ1 = contarEspecieEnParque('trex', 1);
    const trexJ2 = contarEspecieEnParque('trex', 2);

    if (trexJ1 < trexJ2) {
      estadoGanador = `Ganador: ${jugadorActualNombre} (desempate por menos T-Rex)`;
      iconoGanador = '🏆';
    } else if (trexJ2 < trexJ1) {
      estadoGanador = `Ganador: ${rivalNombre} (desempate por menos T-Rex)`;
      iconoGanador = '🏆';
    } else {
      estadoGanador = `¡Empate perfecto!`;
      iconoGanador = '🤝';
    }
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
                    <div class="mt-1">
                      <small class="text-info">⚖️ ${formatearPeso(pesoTotalJ1)}</small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-6">
                <div class="card bg-dark border-secondary">
                  <div class="card-body">
                    <h6 class="card-title">${rivalNombre}</h6>
                    <h3 class="text-secondary">${puntosJ2}</h3>
                    <small class="text-muted">puntos</small>
                    <div class="mt-1">
                      <small class="text-info">⚖️ ${formatearPeso(pesoTotalJ2)}</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            ${recintoMasPesado && recintoMasPesado.peso > 0 ? `
            <div class="mt-4 p-3 bg-dark border border-warning rounded">
              <h6 class="text-warning mb-2">🏋️ Recinto Más Pesado</h6>
              <div class="text-center">
                <strong class="text-light">${recintoMasPesado.nombre || recintoMasPesado.id}</strong><br>
                <span class="text-info fs-5">${recintoMasPesado.pesoFormateado}</span>
              </div>
              ${recintoMasPesado.dinosaurios && recintoMasPesado.dinosaurios.length > 0 ? `
              <div class="mt-2">
                <small class="text-muted">Dinosaurios:</small><br>
                ${recintoMasPesado.dinosaurios.map(dino => {
                  const nombreJugador = dino.jugador == 1 ? jugadorActualNombre : rivalNombre;
                  return `<span class="badge bg-secondary me-1 mb-1">
                    ${dino.nombre} (${nombreJugador}) - ${formatearPeso(dino.masa)}
                  </span>`;
                }).join('')}
              </div>
              ` : ''}
            </div>
            ` : ''}

            ${resultado.esServidor ?
      '<small class="text-success">Puntuación guardada en tu perfil</small>' :
      '<small class="text-warning">Puntuación calculada localmente</small>'
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
    `;

  // Eliminar modal existente y crear nuevo
  const existingModal = document.getElementById('modalResultados');
  if (existingModal) existingModal.remove();

  document.body.insertAdjacentHTML('beforeend', modalHTML);
  console.log('📝 HTML del modal insertado en DOM');

  try {
    // Intentar mostrar con Bootstrap
    console.log('🔧 Verificando Bootstrap...');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      console.log('✅ Bootstrap disponible, creando modal...');
      const modal = new bootstrap.Modal(document.getElementById('modalResultados'), {
        backdrop: 'static',
        keyboard: false
      });
      modal.show();
      console.log('✅ Modal mostrado con Bootstrap');
    } else {
      throw new Error('Bootstrap no disponible');
    }
  } catch (modalError) {
    console.error('❌ Error mostrando modal Bootstrap:', modalError);

    // Fallback: mostrar modal manualmente
    const modalElement = document.getElementById('modalResultados');
    if (modalElement) {
      modalElement.style.display = 'block';
      modalElement.classList.add('show');
      modalElement.setAttribute('aria-modal', 'true');
      modalElement.setAttribute('role', 'dialog');

      // Agregar backdrop manualmente
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      backdrop.id = 'modal-backdrop-manual';
      document.body.appendChild(backdrop);

      console.log('✅ Modal mostrado manualmente como fallback');
    } else {
      console.error('❌ No se pudo encontrar el elemento modal');
      // Último recurso: alert con los puntos
      alert(`¡Partida Finalizada!\n\n${estadoGanador}\n\n${jugadorActualNombre}: ${puntosJ1} puntos\n${rivalNombre}: ${puntosJ2} puntos`);
    }
  }

  console.log('🏁 FIN mostrarModalResultados - función completada');
}

/* ===== FUNCIÓN DE PRUEBA PARA MODAL (TEMPORAL) ===== */
window.testModal = function() {
  console.log('🧪 PROBANDO MODAL DIRECTAMENTE...');

  const resultadoPrueba = {
    puntos: [29, 23],
    ganador: "martin",
    esServidor: true
  };

  console.log('🎯 Llamando mostrarModalResultados con:', resultadoPrueba);
  mostrarModalResultados(resultadoPrueba);
};

console.log('🔧 Función testModal() disponible - úsala en la consola escribiendo: testModal()');

/* ===== RESTO DEL FLUJO DE JUEGO ===== */

document.addEventListener('DOMContentLoaded', () => {
  // 🛡️ ACTIVAR VALIDACIONES ANTI-TRAMPA
  console.log('🛡️ Iniciando validaciones de seguridad...');

  // Validar integridad de datos al cargar
  if (!validarIntegridadPartida()) {
    return; // Salir si los datos no son válidos
  }

  // Activar protecciones contra navegación
  prevenirSalidaAccidental();
  partidaEnCurso = true;

  console.log('✅ Validaciones de seguridad activadas');

  cargarAvatarUsuario();

  const recintos = document.querySelectorAll('.recinto');
  const dadoBtn = document.getElementById('tirar-dado-btn');
  const dadoImg = document.getElementById('dado-img');
  const restriccionText = document.getElementById('restriccion-text');

  async function tirarDado() {
    if (restriccionActual !== null) return;

    // 🛡️ VALIDACIÓN ANTI-TRAMPA: Marcar como jugada crítica
    marcarJugadaCritica(true);

    // No permitir tirar dado si la partida ya terminó
    if (ronda > TOTAL_RONDAS) {
      console.warn('⚠️ No se puede tirar dado: partida terminada');
      marcarJugadaCritica(false); // Desactivar protección
      mostrarResultadosFinal();
      return;
    }

    const valor = Math.floor(Math.random() * 6) + 1;
    dadoImg.src = `/img/dado/dado${valor}.png`;
    restriccionActual = valor;
    restriccionText.textContent = restricciones[valor];
    actualizarUI();
    await autoSave();

    // 🛡️ VALIDACIÓN ANTI-TRAMPA: Desactivar protección después del dado
    marcarJugadaCritica(false);
  }

  async function colocarDino(recinto, especie) {
    // 🛡️ VALIDACIÓN ANTI-TRAMPA: Marcar como jugada crítica
    marcarJugadaCritica(true);

    // 🛡️ VALIDACIÓN BACKEND: Verificar con el servidor antes de colocar
    try {
      // Validar que tenemos datos requeridos
      if (!ID_PARTIDA) {
        console.error('❌ ERROR: No hay ID_PARTIDA para validación');
        alert('Error: Datos de partida no disponibles');
        marcarJugadaCritica(false);
        return;
      }

      const datosValidacion = {
        partidaId: parseInt(ID_PARTIDA),
        restriccion: restriccionActual,
        recintoId: recinto.id || '',
        especie: especie || ''
      };

      console.log('🛡️ Enviando validación backend:', datosValidacion);

      const validacionBackend = await fetch('/api/tablero/validarColocacionDino', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(datosValidacion),
        credentials: 'include'
      });

      if (!validacionBackend.ok) {
        console.error('❌ HTTP Error:', validacionBackend.status);
        throw new Error(`HTTP ${validacionBackend.status}: ${validacionBackend.statusText}`);
      }

      const validacionResult = await validacionBackend.json();
      console.log('📨 Respuesta validación backend:', validacionResult);

      if (!validacionResult.success || !validacionResult.esValida) {
        console.error('🚫 VALIDACIÓN BACKEND FALLIDA:', validacionResult);
        alert(validacionResult.mensaje || '🚫 No se puede colocar el dinosaurio');
        marcarJugadaCritica(false);
        return;
      }

      console.log('✅ VALIDACIÓN BACKEND EXITOSA:', validacionResult.mensaje);
    } catch (error) {
      console.error('❌ ERROR COMPLETO en validación backend:', error);
      console.error('Stack trace:', error.stack);

      // Tolerancia a fallos: Si hay error de conectividad, usar validación local
      if (restriccionActual === null) {
        alert('🎲 Primero debes tirar el dado antes de colocar dinosaurios (validación local)');
        marcarJugadaCritica(false);
        return;
      }

      console.warn('⚠️ Usando validación local debido a error de conectividad');
      // Continuar con la colocación usando validación local
    }

    const dinoClone = document.createElement('img');
    dinoClone.src = `/img/imagen_Tablero/${especie}.png`;
    dinoClone.className = 'dino-in-recinto';
    dinoClone.dataset.especie = especie;
    dinoClone.dataset.jugador = jugadorActivo;
    dinoClone.style.pointerEvents = 'none';
    recinto.appendChild(dinoClone);

    // Actualizar inmediatamente el peso de este recinto
    const peso = calcularPesoRecinto(recinto.id);
    const scoreElement = recinto.querySelector('.recinto-score');
    if (scoreElement) {
      scoreElement.textContent = formatearPeso(peso);
    }

    // Actualizar puntuaciones inmediatamente
    actualizarPuntuaciones();
    actualizarPuntuacionesAmbosJugadores();

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
      // ✅ VALIDACIÓN CRÍTICA: Si turno > 3, nueva ronda
      if (turno > 3) {
        ronda++;
        turno = 1; // Reset turno

        // ✅ VALIDACIÓN: Si ronda > 4, finalizar
        if (ronda > TOTAL_RONDAS || validarFinDeJuego()) {
          console.log('🏁 Finalizando partida: ronda=', ronda, 'TOTAL_RONDAS=', TOTAL_RONDAS);
          mostrarResultadosFinal();
          return;
        }

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

      // Solo guardar si la partida no ha terminado
      if (ronda <= TOTAL_RONDAS) {
        await autoSave();
      }
    } else {
      jugadorActivo = jugadorActivo === 1 ? 2 : 1;
    }
    actualizarUI();

    // 🛡️ VALIDACIÓN ANTI-TRAMPA: Desactivar protección después de colocar dinosaurio
    marcarJugadaCritica(false);
  }

  /* ===== FUNCIONES GLOBALES PARA EL MODAL ===== */
  window.irAPerfil = function () {
    location.href = '/perfil';
  };

  window.nuevaPartida = function () {
    console.log('🆕 Iniciando nueva partida - limpiando estado...');

    // Limpiar TODOS los datos de localStorage relacionados con partidas
    localStorage.removeItem(LS_KEY);
    localStorage.removeItem('partidaIdActual');
    localStorage.removeItem('partidaACargar');
    localStorage.removeItem('partidaId');
    localStorage.removeItem('esPartidaNueva');
    localStorage.removeItem('userAvatar'); // Opcional: mantener avatar

    // Limpiar variables globales del juego
    ID_PARTIDA = null;
    ronda = 1;
    turno = 1;
    jugadorActivo = 1;
    jugadorQueTiroDado = 1;
    restriccionActual = null;
    colocadosEnTurno = 0;
    manos = { 1: [], 2: [] };
    puntuacionesJugadores = { 1: {}, 2: {} };

    // Limpiar el tablero visual
    document.querySelectorAll('.dino-in-recinto').forEach(dino => dino.remove());

    // Cerrar modal si está abierto
    const modal = document.getElementById('modalResultados');
    if (modal) {
      modal.remove();
    }

    // Eliminar backdrop manual si existe
    const backdrop = document.getElementById('modal-backdrop-manual');
    if (backdrop) {
      backdrop.remove();
    }

    console.log('✅ Estado limpiado completamente');
    console.log('🔄 Redirigiendo a perfil...');

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

      // 🛡️ VALIDACIÓN CRÍTICA: No permitir colocar dinosaurios sin tirar dado
      if (restriccionActual === null) {
        alert("🎲 ATENCIÓN: Primero debe tirar el dado antes de colocar dinosaurios.");
        console.warn('🚫 Intento de colocar dinosaurio sin tirar dado - BLOQUEADO');
        return;
      }

      // 🛡️ VALIDACIÓN ADICIONAL: Verificar estado visual de los dinosaurios
      const dinosPanel = document.querySelector('.dino-panel');
      if (dinosPanel && dinosPanel.style.pointerEvents === 'none') {
        alert("🚫 Los dinosaurios están deshabilitados. Tira el dado primero.");
        console.warn('🚫 Intento de colocar dinosaurio con panel deshabilitado - BLOQUEADO');
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

  // CÓDIGO TEMPORAL PARA DEBUG
  setTimeout(() => {
    console.log('IDs de recintos disponibles:');
    document.querySelectorAll('.recinto').forEach(r => console.log(r.id));

    // Limpiar dinosaurios existentes
    document.querySelectorAll('.dino-in-recinto').forEach(d => d.remove());

    // Intentar colocar dinosaurio en bosque-semejanza
    const bosque = document.getElementById('bosque-semejanza');
    if (bosque) {
      const dino = document.createElement('img');
      dino.src = '/img/imagen_Tablero/dino1.png';
      dino.className = 'dino-in-recinto';
      dino.dataset.especie = 'dino1';
      dino.dataset.jugador = '1';
      bosque.appendChild(dino);
      console.log('Dinosaurio colocado en bosque-semejanza');
    } else {
      console.log('No se encontró el recinto bosque-semejanza');
    }
  }, 3000);
}