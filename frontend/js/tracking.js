/* ===== TRACKING.JS - MODO TRACKING DE DRAFTOSAURUS (2 JUGADORES) ===== */

console.log('🦕 Modo Tracking iniciado');

/* ------ VARIABLES GLOBALES ------ */
let jugadorActivo = 1; // Quién está jugando actualmente
let jugadorQueTiroDado = 1; // Quién tiró el dado en este turno
let restriccionActual = null; // Restricción del dado (1-6)
let turno = 1; // Turno actual (1-3)
let ronda = 1; // Ronda actual (1-4)
let fase = 'seleccion'; // Fases: 'seleccion', 'colocar', 'descartar', 'finalizado'

const TOTAL_RONDAS = 4;
const TURNOS_POR_RONDA = 3;
const DINOS_POR_RONDA = 6;
const TOTAL_DINOSAURIOS_COLOCADOS = 12; // 3 por ronda × 4 rondas

let draggedDino = null;
let manos = { 1: [], 2: [] }; // Mano actual de cada jugador
let dinosDescartados = { 1: 0, 2: 0 }; // Contador de descartados
let dinosColocados = { 1: 0, 2: 0 }; // Contador de colocados
let puntuacionesJugadores = { 1: {}, 2: {} };

// Nombres de jugadores
let jugadorActualNombre = localStorage.getItem('jugadorActual') || 'Jugador 1';
let rivalNombre = localStorage.getItem('rival') || 'Jugador 2';

/* ------ ESPECIES Y RESTRICCIONES ------ */
const especies = ['dino1', 'dino2', 'dino3', 'dino4', 'dino5', 'trex'];
const restricciones = {
  1: "Zona izquierda",
  2: "Zona derecha",
  3: "Zona boscosa",
  4: "Recinto vacío",
  5: "Recinto sin T-REX",
  6: "Sin restricción"
};

/* ------ CONFIGURACIÓN DE RECINTOS (IGUAL QUE EN TABLERO.JS) ------ */
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

/* ===== FUNCIONES DE VALIDACIÓN DE RECINTOS ===== */

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

/* ===== FUNCIÓN PRINCIPAL DE VALIDACIÓN ===== */
function puedeColocarDino(recinto, tipoRecinto, especieDino) {
  const dinosauriosEnRecinto = getDinosauriosJugadorEnRecinto(recinto, jugadorActivo);
  const cantidadDinos = dinosauriosEnRecinto.length;
  const config = configRecintos[tipoRecinto];

  // Validar capacidad máxima
  if (config && config.capacidadMax && cantidadDinos >= config.capacidadMax) {
    return false;
  }

  // ✅ REGLA ESPECIAL: El jugador que NO tiró el dado debe respetar la restricción
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
        case 6: break; // Sin restricción
      }
    }
  }

  // Validar reglas específicas del recinto
  switch (tipoRecinto) {
    case 'bosque-semejanza': return validarBosqueSemejanza(recinto, especieDino, jugadorActivo);
    case 'prado-diferencia': return validarPradoDiferencia(recinto, especieDino, jugadorActivo);
    case 'trio-frondoso': return validarTrioFrondoso(recinto, especieDino, jugadorActivo);
    case 'rey-selva': return validarReySelva(recinto, especieDino, jugadorActivo);
    case 'isla-solitario': return validarIslaSolitaria(recinto, especieDino, jugadorActivo);
    case 'rio': return true;
    case 'pradera-amor': return true;
    default: return true;
  }
}

/* ===== CÁLCULO DE PUNTUACIONES (IGUAL QUE TABLERO.JS) ===== */
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
      const especiesCount = {};
      dinosauriosJugador.forEach(d => {
        const especie = d.dataset.especie;
        especiesCount[especie] = (especiesCount[especie] || 0) + 1;
      });
      const maxCantidad = Math.max(...Object.values(especiesCount));
      puntos = config.puntosPorDino[maxCantidad] || 0;
      break;

    case 'prado-diferencia':
      const especiesDiferentes = new Set(dinosauriosJugador.map(d => d.dataset.especie)).size;
      puntos = config.puntosPorDino[especiesDiferentes] || 0;
      break;

    case 'pradera-amor':
      const especiesCountAmor = {};
      dinosauriosJugador.forEach(d => {
        const especie = d.dataset.especie;
        especiesCountAmor[especie] = (especiesCountAmor[especie] || 0) + 1;
      });
      Object.values(especiesCountAmor).forEach(count => {
        puntos += Math.floor(count / 2) * config.puntosPorPareja;
      });
      break;

    case 'trio-frondoso':
      puntos = cantidadDinos === 3 ? config.puntosExactos : 0;
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
      }
      break;

    case 'isla-solitario':
      if (cantidadDinos === 1) {
        const especieDino = dinosauriosJugador[0].dataset.especie;
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

  // Bonus T-Rex (excepto en el río)
  if (tipoRecinto !== 'rio') {
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
  [1, 2].forEach(jugador => {
    Object.keys(configRecintos).forEach(recintoId => {
      puntuacionesJugadores[jugador][recintoId] = calcularPuntuacionRecinto(recintoId, jugador);
    });
  });

  const totalJ1 = calcularPuntuacionTotal(1);
  const totalJ2 = calcularPuntuacionTotal(2);

  if (document.getElementById('puntos-j1')) {
    document.getElementById('puntos-j1').textContent = totalJ1;
  }
  if (document.getElementById('puntos-j2')) {
    document.getElementById('puntos-j2').textContent = totalJ2;
  }

  // Actualizar scores visuales de recintos
  actualizarScoresRecintos();
}

function calcularPuntuacionTotal(jugador) {
  let total = 0;
  Object.keys(configRecintos).forEach(recintoId => {
    total += puntuacionesJugadores[jugador][recintoId] || 0;
  });
  return total;
}

function actualizarScoresRecintos() {
  document.querySelectorAll('.recinto').forEach(r => {
    const puntosJ1 = calcularPuntuacionRecinto(r.id, 1);
    const puntosJ2 = calcularPuntuacionRecinto(r.id, 2);
    const scoreElement = r.querySelector('.recinto-score');
    if (scoreElement) {
      scoreElement.textContent = puntosJ1 + puntosJ2;
    }
  });
}

/* ===== GESTIÓN DEL MODAL DE SELECCIÓN ===== */
let dinosSeleccionados = [];

function abrirModalSeleccion() {
  const modal = new bootstrap.Modal(document.getElementById('modalSeleccionDinos'));
  modal.show();

  // Reiniciar selección
  dinosSeleccionados = [];
  actualizarContadorSeleccion();

  // Resetear visualización
  document.querySelectorAll('.dino-selectable').forEach(dino => {
    dino.classList.remove('selected');
  });

  // Actualizar instrucción
  const instruccion = document.getElementById('modal-instruccion');
  if (instruccion) {
    instruccion.textContent = `Ronda ${ronda} - Selecciona 6 dinosaurios de la bolsa`;
  }
}

function toggleSeleccionDino(especie) {
  const index = dinosSeleccionados.indexOf(especie);

  if (index > -1) {
    // Ya estaba seleccionado, remover
    dinosSeleccionados.splice(index, 1);
  } else {
    // No estaba seleccionado, agregar si hay espacio
    if (dinosSeleccionados.length < DINOS_POR_RONDA) {
      dinosSeleccionados.push(especie);
    } else {
      alert(`Solo puedes seleccionar ${DINOS_POR_RONDA} dinosaurios`);
      return;
    }
  }

  actualizarContadorSeleccion();
}

function actualizarContadorSeleccion() {
  const contador = document.getElementById('contador-seleccion');
  if (contador) {
    contador.textContent = dinosSeleccionados.length;
  }

  // Habilitar/deshabilitar botón de confirmar
  const btnConfirmar = document.getElementById('btn-confirmar-seleccion');
  if (btnConfirmar) {
    btnConfirmar.disabled = dinosSeleccionados.length !== DINOS_POR_RONDA;
  }

  // Actualizar clases visuales
  document.querySelectorAll('.dino-selectable').forEach(dino => {
    const especie = dino.dataset.especie;
    if (dinosSeleccionados.includes(especie)) {
      dino.classList.add('selected');
    } else {
      dino.classList.remove('selected');
    }
  });
}

function confirmarSeleccion() {
  if (dinosSeleccionados.length !== DINOS_POR_RONDA) {
    alert(`Debes seleccionar exactamente ${DINOS_POR_RONDA} dinosaurios`);
    return;
  }

  // Asignar manos a ambos jugadores
  manos[1] = [...dinosSeleccionados];
  manos[2] = [...dinosSeleccionados];

  // Resetear contadores para la nueva ronda
  dinosColocados = { 1: 0, 2: 0 };
  dinosDescartados = { 1: 0, 2: 0 };

  console.log('Dinosaurios seleccionados:', dinosSeleccionados);
  console.log('Manos asignadas:', manos);

  // Cerrar modal
  const modalElement = document.getElementById('modalSeleccionDinos');
  const modal = bootstrap.Modal.getInstance(modalElement);
  if (modal) {
    modal.hide();
  }

  // Cambiar a fase de lanzar dado
  fase = 'tirarDado';
  tirarDadoAutomatico();
}

/* ===== LÓGICA DEL DADO ===== */
function tirarDadoAutomatico() {
  const valor = Math.floor(Math.random() * 6) + 1;
  restriccionActual = valor;

  const dadoImg = document.getElementById('tracking-dado-img');
  const restriccionText = document.getElementById('tracking-restriccion-text');

  if (dadoImg) {
    dadoImg.src = `/img/dado/dado${valor}.png?v=${Date.now()}`;
  }

  if (restriccionText) {
    restriccionText.textContent = restricciones[valor];
  }

  console.log(`🎲 Dado lanzado: ${valor} - ${restricciones[valor]}`);
  console.log(`Jugador que tiró el dado: ${jugadorQueTiroDado}`);
  console.log(`Jugador activo: ${jugadorActivo}`);

  // Cambiar a fase de colocar
  fase = 'colocar';
  renderMano(jugadorActivo);
  actualizarUI();
}

/* ===== RENDERIZAR MANO ===== */
function renderMano(jugador) {
  console.log(`🖼️ Renderizando mano del jugador ${jugador}`);
  console.log('  - Mano actual:', manos[jugador]);

  const grid = document.getElementById('tracking-dino-grid');
  if (!grid) {
    console.log('  ❌ No se encontró el grid');
    return;
  }

  grid.innerHTML = '';

  if (!manos[jugador] || manos[jugador].length === 0) {
    console.log('  ⚠️ Mano vacía - mostrando mensaje');
    grid.innerHTML = '<p class="text-center text-muted">No hay dinosaurios disponibles</p>';
    return;
  }

  console.log(`  ✅ Renderizando ${manos[jugador].length} dinosaurios`);

  manos[jugador].forEach((esp, idx) => {
    const img = document.createElement('img');
    img.src = `/img/imagen_Tablero/${esp}.png`;
    img.className = 'dino-img';
    img.draggable = true;
    img.dataset.especie = esp;
    img.dataset.index = idx;
    img.style.cursor = 'grab';
    img.style.touchAction = 'none'; // Prevenir scroll en móviles

    grid.appendChild(img);

    // ===== EVENTOS PARA DESKTOP (Drag and Drop) =====
    img.addEventListener('dragstart', e => {
      draggedDino = { especie: esp, index: idx, jugador: jugadorActivo };
      img.classList.add('dragging');
      img.style.cursor = 'grabbing';
      e.dataTransfer.setData('text/plain', esp);
      highlightRecintos(esp);
    });

    img.addEventListener('dragend', () => {
      img.classList.remove('dragging');
      img.style.cursor = 'grab';
      clearHighlight();
    });

    // ===== EVENTOS PARA MÓVIL (Touch) =====
    let touchStarted = false;
    let clonedImg = null;

    img.addEventListener('touchstart', e => {
      e.preventDefault();
      touchStarted = true;
      draggedDino = { especie: esp, index: idx, jugador: jugadorActivo };
      img.classList.add('dragging');
      highlightRecintos(esp);
      console.log('📱 Touch iniciado:', esp);

      // Crear clon visual
      clonedImg = img.cloneNode(true);
      clonedImg.style.position = 'fixed';
      clonedImg.style.zIndex = '9999';
      clonedImg.style.opacity = '0.8';
      clonedImg.style.pointerEvents = 'none';
      clonedImg.style.width = img.offsetWidth + 'px';
      clonedImg.style.height = img.offsetHeight + 'px';
      document.body.appendChild(clonedImg);

      const touch = e.touches[0];
      // Centrar el dino bajo el dedo restando la mitad del ancho/alto
      clonedImg.style.left = (touch.clientX - img.offsetWidth / 2) + 'px';
      clonedImg.style.top = (touch.clientY - img.offsetHeight / 2) + 'px';
    });

    img.addEventListener('touchmove', e => {
      if (!touchStarted || !clonedImg) return;
      e.preventDefault();

      const touch = e.touches[0];
      const imgWidth = parseInt(clonedImg.style.width);
      const imgHeight = parseInt(clonedImg.style.height);

      // Centrar el dino bajo el dedo
      clonedImg.style.left = (touch.clientX - imgWidth / 2) + 'px';
      clonedImg.style.top = (touch.clientY - imgHeight / 2) + 'px';

      const elementBelow = document.elementFromPoint(touch.clientX, touch.clientY);
      const recinto = elementBelow?.closest('.recinto');

      document.querySelectorAll('.recinto').forEach(r => r.classList.remove('highlight'));

      if (recinto && !recinto.classList.contains('recinto-disabled')) {
        recinto.classList.add('highlight');
      }
    });

    img.addEventListener('touchend', async e => {
      if (!touchStarted) return;
      e.preventDefault();
      touchStarted = false;

      const touch = e.changedTouches[0];
      const elementBelow = document.elementFromPoint(touch.clientX, touch.clientY);
      const recinto = elementBelow?.closest('.recinto');

      if (clonedImg) {
        clonedImg.remove();
        clonedImg = null;
      }

      img.classList.remove('dragging');
      img.style.cursor = 'grab';
      clearHighlight();

      if (recinto && draggedDino) {
        const tipoRecinto = recinto.dataset.tipo;
        const especieDino = draggedDino.especie;

        if (puedeColocarDino(recinto, tipoRecinto, especieDino)) {
          console.log('📱 Touch: Colocando dino');
          await colocarDino(recinto, especieDino);
        } else {
          alert('No puedes colocar el dinosaurio aquí según las reglas del recinto');
        }
      }

      draggedDino = null;
    });

    img.addEventListener('touchcancel', () => {
      if (clonedImg) {
        clonedImg.remove();
        clonedImg = null;
      }
      touchStarted = false;
      img.classList.remove('dragging');
      img.style.cursor = 'grab';
      clearHighlight();
      draggedDino = null;
    });
  });
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

/* ===== COLOCAR DINOSAURIO ===== */
async function colocarDino(recinto, especie) {
  const dinoClone = document.createElement('img');
  dinoClone.src = `/img/imagen_Tablero/${especie}.png`;
  dinoClone.className = 'dino-in-recinto';
  dinoClone.dataset.especie = especie;
  dinoClone.dataset.jugador = jugadorActivo;
  dinoClone.style.pointerEvents = 'none';
  recinto.appendChild(dinoClone);

  // Remover de la mano
  manos[jugadorActivo].splice(draggedDino.index, 1);
  dinosColocados[jugadorActivo]++;

  // Actualizar contadores visuales
  actualizarContadores();
  actualizarPuntuaciones();

  console.log(`Dinosaurio ${especie} colocado por jugador ${jugadorActivo}`);
  console.log(`Mano restante J${jugadorActivo}:`, manos[jugadorActivo]);

  // Verificar si ambos jugadores colocaron
  if (dinosColocados[1] > dinosColocados[2]) {
    // El jugador 1 colocó, ahora le toca al jugador 2
    jugadorActivo = 2;
    renderMano(2);
    actualizarUI();
  } else if (dinosColocados[1] === dinosColocados[2]) {
    // Ambos colocaron, pasar a fase de descarte
    console.log(`Turno ${turno}: Ambos jugadores colocaron`);
    console.log(`Manos restantes - J1: ${manos[1].length}, J2: ${manos[2].length}`);

    fase = 'descartar';
    mostrarDescarte();
  }
}

/* ===== DESCARTE ===== */
function mostrarDescarte() {
  console.log('🗑️ Fase de descarte');
  console.log('Estado ANTES del descarte:');
  console.log('  - Turno:', turno);
  console.log('  - Mano J1:', manos[1]);
  console.log('  - Mano J2:', manos[2]);
  console.log('  - Jugador activo:', jugadorActivo);
  console.log('  - Jugador que tiró dado:', jugadorQueTiroDado);

  // Cada jugador debe descartar 1 dinosaurio
  // Simulamos que cada jugador descarta uno aleatorio
  if (manos[1].length > 0) {
    const indexDescarte1 = Math.floor(Math.random() * manos[1].length);
    const descartado1 = manos[1].splice(indexDescarte1, 1)[0];
    dinosDescartados[1]++;
    console.log(`✂️ Jugador 1 descarta: ${descartado1} (quedan ${manos[1].length})`);
  } else {
    console.log('⚠️ Jugador 1 no tiene dinos para descartar');
  }

  if (manos[2].length > 0) {
    const indexDescarte2 = Math.floor(Math.random() * manos[2].length);
    const descartado2 = manos[2].splice(indexDescarte2, 1)[0];
    dinosDescartados[2]++;
    console.log(`✂️ Jugador 2 descarta: ${descartado2} (quedan ${manos[2].length})`);
  } else {
    console.log('⚠️ Jugador 2 no tiene dinos para descartar');
  }

  actualizarContadores();

  console.log('Estado DESPUÉS del descarte:');
  console.log('  - Mano J1:', manos[1]);
  console.log('  - Mano J2:', manos[2]);

  // Verificar si las manos quedaron vacías (fin de ronda)
  if (manos[1].length === 0 && manos[2].length === 0) {
    console.log('🏁 Manos vacías - Fin de la ronda');
    finalizarRonda();
  } else {
    // Intercambiar manos
    const temp = [...manos[1]];
    manos[1] = [...manos[2]];
    manos[2] = temp;

    console.log('🔄 Manos intercambiadas');
    console.log('  - Nueva mano J1:', manos[1]);
    console.log('  - Nueva mano J2:', manos[2]);

    // Cambiar jugador que tira el dado
    jugadorQueTiroDado = jugadorQueTiroDado === 1 ? 2 : 1;
    jugadorActivo = jugadorQueTiroDado;

    console.log('🎯 Nuevo turno:');
    console.log('  - Jugador que tirará dado:', jugadorQueTiroDado);
    console.log('  - Jugador activo:', jugadorActivo);

    // Avanzar turno
    turno++;
    console.log('  - Turno:', turno);

    // Continuar con siguiente turno
    fase = 'tirarDado';
    tirarDadoAutomatico();
  }
}

/* ===== FINALIZAR RONDA ===== */
function finalizarRonda() {
  console.log(`🏁 Fin de la ronda ${ronda}`);

  ronda++;
  turno = 1;

  if (ronda > TOTAL_RONDAS) {
    // Fin del juego
    finalizarJuego();
  } else {
    // Nueva ronda
    fase = 'seleccion';
    alert(`Ronda ${ronda - 1} completada. Comenzando ronda ${ronda}...`);
    abrirModalSeleccion();
  }
}

/* ===== FINALIZAR JUEGO ===== */
function finalizarJuego() {
  console.log('🏆 Juego finalizado');

  fase = 'finalizado';

  const puntosJ1 = calcularPuntuacionTotal(1);
  const puntosJ2 = calcularPuntuacionTotal(2);

  let ganador = '';
  if (puntosJ1 > puntosJ2) {
    ganador = jugadorActualNombre;
  } else if (puntosJ2 > puntosJ1) {
    ganador = rivalNombre;
  } else {
    // Empate: desempatar por menos T-Rex
    const trexJ1 = contarEspecieEnParque('trex', 1);
    const trexJ2 = contarEspecieEnParque('trex', 2);
    if (trexJ1 < trexJ2) {
      ganador = `${jugadorActualNombre} (menos T-Rex)`;
    } else if (trexJ2 < trexJ1) {
      ganador = `${rivalNombre} (menos T-Rex)`;
    } else {
      ganador = 'Empate perfecto';
    }
  }

  mostrarResultadosFinales(puntosJ1, puntosJ2, ganador);
}

function mostrarResultadosFinales(puntosJ1, puntosJ2, ganador) {
  const mensaje = `
    🏆 ¡JUEGO FINALIZADO! 🏆

    ${jugadorActualNombre}: ${puntosJ1} puntos
    ${rivalNombre}: ${puntosJ2} puntos

    ${ganador === 'Empate perfecto' ? '🤝 Empate perfecto' : '👑 Ganador: ' + ganador}

    ¡Gracias por jugar!
  `;

  alert(mensaje);

  // Opción de volver al perfil
  if (confirm('¿Quieres volver al perfil?')) {
    window.location.href = '/perfil';
  }
}

/* ===== ACTUALIZAR UI ===== */
function actualizarUI() {
  const turnoText = document.getElementById('tracking-turno-text');
  if (turnoText) {
    const nombreTurno = jugadorActivo === 1 ? jugadorActualNombre : rivalNombre;
    const restriccionInfo = jugadorActivo === jugadorQueTiroDado
      ? '(SIN restricción)'
      : `(CON restricción: ${restricciones[restriccionActual]})`;

    turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${nombreTurno} ${restriccionInfo}`;
  }

  const panelTitle = document.getElementById('dino-panel-title');
  if (panelTitle) {
    panelTitle.textContent = `Tus Dinosaurios - ${jugadorActivo === 1 ? jugadorActualNombre : rivalNombre}`;
  }

  const nombreJ1 = document.getElementById('nombre-j1');
  const nombreJ2 = document.getElementById('nombre-j2');
  if (nombreJ1) nombreJ1.textContent = jugadorActualNombre;
  if (nombreJ2) nombreJ2.textContent = rivalNombre;

  clearHighlight();
}

function actualizarContadores() {
  const colocadosEl = document.getElementById('dinos-colocados');
  const descartadosEl = document.getElementById('dinos-descartados');

  if (colocadosEl) {
    colocadosEl.textContent = dinosColocados[1] + dinosColocados[2];
  }

  if (descartadosEl) {
    descartadosEl.textContent = dinosDescartados[1] + dinosDescartados[2];
  }
}

/* ===== INICIALIZACIÓN ===== */
document.addEventListener('DOMContentLoaded', () => {
  console.log('🎮 Inicializando modo tracking...');

  // Inicializar puntuaciones
  document.querySelectorAll('.recinto').forEach(recinto => {
    puntuacionesJugadores[1][recinto.id] = 0;
    puntuacionesJugadores[2][recinto.id] = 0;
  });

  // Configurar eventos del modal de selección
  document.querySelectorAll('.dino-selectable').forEach(dino => {
    dino.addEventListener('click', () => {
      toggleSeleccionDino(dino.dataset.especie);
    });
  });

  const btnConfirmar = document.getElementById('btn-confirmar-seleccion');
  if (btnConfirmar) {
    btnConfirmar.addEventListener('click', confirmarSeleccion);
  }

  const btnElegir = document.getElementById('btn-elegir-dinos');
  if (btnElegir) {
    btnElegir.addEventListener('click', abrirModalSeleccion);
  }

  // Configurar drag & drop en recintos
  const recintos = document.querySelectorAll('.recinto');
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

      if (fase !== 'colocar') {
        alert('No es el momento de colocar dinosaurios');
        return;
      }

      if (recinto.classList.contains('recinto-disabled')) return;
      if (!draggedDino || draggedDino.jugador !== jugadorActivo) return;

      const tipoRecinto = recinto.dataset.tipo;
      const especieDino = draggedDino.especie;

      if (puedeColocarDino(recinto, tipoRecinto, especieDino)) {
        await colocarDino(recinto, especieDino);
      } else {
        alert('No puedes colocar el dinosaurio aquí según las reglas del recinto');
      }
    });
  });

  // Botón volver al perfil
  const btnVolver = document.getElementById('btn-volver-perfil');
  if (btnVolver) {
    btnVolver.addEventListener('click', () => {
      if (confirm('¿Seguro que quieres volver al perfil? Se perderá el progreso.')) {
        window.location.href = '/perfil';
      }
    });
  }

  // Botón de ayuda
  const btnAyuda = document.getElementById('btn-ayuda');
  const rulesModal = document.getElementById('rules-modal');
  const closeBtn = rulesModal?.querySelector('.close-btn');

  if (btnAyuda && rulesModal) {
    btnAyuda.addEventListener('click', () => {
      rulesModal.style.display = 'flex';
    });
  }

  if (closeBtn && rulesModal) {
    closeBtn.addEventListener('click', () => {
      rulesModal.style.display = 'none';
    });
  }

  // Cerrar modal al hacer clic fuera
  if (rulesModal) {
    rulesModal.addEventListener('click', (e) => {
      if (e.target === rulesModal) {
        rulesModal.style.display = 'none';
      }
    });
  }

  // Inicializar UI
  actualizarUI();
  actualizarContadores();

  // Abrir modal de selección al inicio
  console.log('🦕 Abriendo modal de selección inicial...');
  setTimeout(() => {
    abrirModalSeleccion();
  }, 500);
});
