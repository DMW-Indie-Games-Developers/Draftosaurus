/* ===== TRACKING.JS - MODO TRACKING DE DRAFTOSAURUS (1 JUGADOR) ===== */

console.log('🦕 Modo Tracking iniciado (1 jugador)');

/* ------ VARIABLES GLOBALES ------ */
let turno = 1; // Turno actual (1-3)
let ronda = 1; // Ronda actual (1-4)
let fase = 'seleccion'; // Fases: 'seleccion', 'colocar', 'descartar', 'finalizado'

const TOTAL_RONDAS = 4;
const TURNOS_POR_RONDA = 3;
const DINOS_POR_RONDA = 6;
const TOTAL_DINOSAURIOS_COLOCADOS = 12; // 3 por ronda × 4 rondas

let draggedDino = null;
let mano = []; // Mano actual del jugador
let dinosColocados = 0; // Contador de colocados
let dinosDescartados = 0; // Contador de descartados
let puntuacionesJugador = {};
let cantidadesPorEspecie = {}; // { dino1: 2, dino2: 1, ... }

// Nombre del jugador
let jugadorNombre = localStorage.getItem('jugadorActual') || 'Mi Tablero';

/* ===== FUNCIONES DE PERSISTENCIA ===== */
const STORAGE_KEY = 'draftosaurus_tracking_state';

function guardarEstadoJuego() {
  // Guardar estado del tablero (dinosaurios en recintos)
  const tablero = {};
  document.querySelectorAll('.recinto').forEach(recinto => {
    const dinosaurios = [...recinto.querySelectorAll('.dino-in-recinto')].map(dino => ({
      especie: dino.dataset.especie
    }));
    if (dinosaurios.length > 0) {
      tablero[recinto.id] = dinosaurios;
    }
  });

  const estadoJuego = {
    turno,
    ronda,
    fase,
    mano,
    dinosColocados,
    dinosDescartados,
    puntuacionesJugador,
    tablero,
    jugadorNombre,
    timestamp: Date.now()
  };

  localStorage.setItem(STORAGE_KEY, JSON.stringify(estadoJuego));
  console.log('💾 Estado del juego guardado:', estadoJuego);
}

function cargarEstadoJuego() {
  const estadoGuardado = localStorage.getItem(STORAGE_KEY);

  if (!estadoGuardado) {
    console.log('📂 No hay estado guardado previo');
    return false;
  }

  try {
    const estado = JSON.parse(estadoGuardado);

    // Verificar que el estado no sea muy antiguo (opcional, 24 horas)
    const horasDesdeGuardado = (Date.now() - estado.timestamp) / (1000 * 60 * 60);
    if (horasDesdeGuardado > 24) {
      console.log('⏰ Estado guardado muy antiguo, iniciando juego nuevo');
      limpiarEstadoGuardado();
      return false;
    }

    // Restaurar variables
    turno = estado.turno || 1;
    ronda = estado.ronda || 1;
    fase = estado.fase || 'seleccion';
    mano = estado.mano || [];
    dinosColocados = estado.dinosColocados || 0;
    dinosDescartados = estado.dinosDescartados || 0;
    puntuacionesJugador = estado.puntuacionesJugador || {};
    jugadorNombre = estado.jugadorNombre || 'Mi Tablero';

    // Restaurar tablero (dinosaurios en recintos)
    if (estado.tablero) {
      Object.keys(estado.tablero).forEach(recintoId => {
        const recinto = document.getElementById(recintoId);
        if (recinto) {
          // Limpiar recinto primero
          recinto.querySelectorAll('.dino-in-recinto').forEach(d => d.remove());

          // Restaurar dinosaurios
          estado.tablero[recintoId].forEach(dinoData => {
            const dinoClone = document.createElement('img');
            dinoClone.src = `/img/imagen_Tablero/${dinoData.especie}.png`;
            dinoClone.className = 'dino-in-recinto';
            dinoClone.dataset.especie = dinoData.especie;
            dinoClone.style.pointerEvents = 'none';
            recinto.appendChild(dinoClone);
          });
        }
      });
    }

    console.log('✅ Estado del juego restaurado:', estado);
    return true;
  } catch (error) {
    console.error('❌ Error al cargar estado guardado:', error);
    limpiarEstadoGuardado();
    return false;
  }
}

function limpiarEstadoGuardado() {
  localStorage.removeItem(STORAGE_KEY);
  console.log('🗑️ Estado guardado eliminado');
}

function confirmarReinicioPartida() {
  if (confirm('¿Estás seguro de que quieres reiniciar la partida? Se perderá todo el progreso.')) {
    limpiarEstadoGuardado();
    location.reload();
  }
}

/* ------ ESPECIES ------ */
const especies = ['dino1', 'dino2', 'dino3', 'dino4', 'dino5', 'trex'];

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

function getDinosauriosEnRecinto(recinto) {
  return [...recinto.querySelectorAll('.dino-in-recinto')];
}

function validarBosqueSemejanza(recinto, especieDino) {
  const dinosaurios = getDinosauriosEnRecinto(recinto);
  if (dinosaurios.length === 0) return true;
  const especieExistente = dinosaurios[0].dataset.especie;
  return especieExistente === especieDino;
}

function validarPradoDiferencia(recinto, especieDino) {
  const dinosaurios = getDinosauriosEnRecinto(recinto);
  return !dinosaurios.some(d => d.dataset.especie === especieDino);
}

function validarTrioFrondoso(recinto, especieDino) {
  const dinosaurios = getDinosauriosEnRecinto(recinto);
  return dinosaurios.length < 3;
}

function validarReySelva(recinto, especieDino) {
  const dinosaurios = getDinosauriosEnRecinto(recinto);
  return dinosaurios.length === 0 && especieDino === 'trex';
}

function validarIslaSolitaria(recinto, especieDino) {
  const dinosaurios = getDinosauriosEnRecinto(recinto);
  return dinosaurios.length === 0;
}

/* ===== FUNCIÓN PRINCIPAL DE VALIDACIÓN ===== */
function puedeColocarDino(recinto, tipoRecinto, especieDino) {
  const dinosauriosEnRecinto = getDinosauriosEnRecinto(recinto);
  const cantidadDinos = dinosauriosEnRecinto.length;
  const config = configRecintos[tipoRecinto];

  // Validar capacidad máxima
  if (config && config.capacidadMax && cantidadDinos >= config.capacidadMax) {
    return false;
  }

  // NO hay restricciones de dado en modo tracking
  // Solo validar reglas específicas del recinto
  switch (tipoRecinto) {
    case 'bosque-semejanza': return validarBosqueSemejanza(recinto, especieDino);
    case 'prado-diferencia': return validarPradoDiferencia(recinto, especieDino);
    case 'trio-frondoso': return validarTrioFrondoso(recinto, especieDino);
    case 'rey-selva': return validarReySelva(recinto, especieDino);
    case 'isla-solitario': return validarIslaSolitaria(recinto, especieDino);
    case 'rio': return true;
    case 'pradera-amor': return true;
    default: return true;
  }
}

/* ===== CÁLCULO DE PUNTUACIONES (IGUAL QUE TABLERO.JS) ===== */
function calcularPuntuacionRecinto(recintoId) {
  const recinto = document.getElementById(recintoId);
  if (!recinto) return 0;

  const tipoRecinto = recinto.dataset.tipo;
  const config = configRecintos[tipoRecinto];
  const dinosaurios = getDinosauriosEnRecinto(recinto);
  const cantidadDinos = dinosaurios.length;

  if (cantidadDinos === 0) return 0;

  let puntos = 0;

  switch (tipoRecinto) {
    case 'bosque-semejanza':
      const especiesCount = {};
      dinosaurios.forEach(d => {
        const especie = d.dataset.especie;
        especiesCount[especie] = (especiesCount[especie] || 0) + 1;
      });
      const maxCantidad = Math.max(...Object.values(especiesCount));
      puntos = config.puntosPorDino[maxCantidad] || 0;
      break;

    case 'prado-diferencia':
      const especiesDiferentes = new Set(dinosaurios.map(d => d.dataset.especie)).size;
      puntos = config.puntosPorDino[especiesDiferentes] || 0;
      break;

    case 'pradera-amor':
      const especiesCountAmor = {};
      dinosaurios.forEach(d => {
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
        // En modo 1 jugador, solo cuenta si es el único T-Rex
        const especieDino = dinosaurios[0].dataset.especie;
        if (especieDino === 'trex') {
          const totalTrex = contarEspecieEnParque('trex');
          if (totalTrex === 1) {
            puntos = config.puntosGanador;
          }
        }
      }
      break;

    case 'isla-solitario':
      if (cantidadDinos === 1) {
        const especieDino = dinosaurios[0].dataset.especie;
        const totalEspecie = contarEspecieEnParque(especieDino);
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
    const tieneTrex = dinosaurios.some(d => d.dataset.especie === 'trex');
    if (tieneTrex) {
      puntos += 1;
    }
  }

  return puntos;
}

function contarEspecieEnParque(especie) {
  return [...document.querySelectorAll('.dino-in-recinto')]
    .filter(d => d.dataset.especie === especie)
    .length;
}

function actualizarPuntuaciones() {
  Object.keys(configRecintos).forEach(recintoId => {
    puntuacionesJugador[recintoId] = calcularPuntuacionRecinto(recintoId);
  });

  const total = calcularPuntuacionTotal();

  if (document.getElementById('puntos-j1')) {
    document.getElementById('puntos-j1').textContent = total;
  }

  // Actualizar scores visuales de recintos
  actualizarScoresRecintos();
}

function calcularPuntuacionTotal() {
  let total = 0;
  Object.keys(configRecintos).forEach(recintoId => {
    total += puntuacionesJugador[recintoId] || 0;
  });
  return total;
}

function actualizarScoresRecintos() {
  document.querySelectorAll('.recinto').forEach(r => {
    const puntos = calcularPuntuacionRecinto(r.id);
    const scoreElement = r.querySelector('.recinto-score');
    if (scoreElement) {
      scoreElement.textContent = puntos;
    }
  });
}

/* ===== GESTIÓN DEL MODAL DE SELECCIÓN CON CANTIDADES ===== */
let cantidadesSeleccionadas = {
  dino1: 0,
  dino2: 0,
  dino3: 0,
  dino4: 0,
  dino5: 0,
  trex: 0
};

function abrirModalSeleccion() {
  const modal = new bootstrap.Modal(document.getElementById('modalSeleccionDinos'));
  modal.show();

  // Reiniciar cantidades
  cantidadesSeleccionadas = {
    dino1: 0,
    dino2: 0,
    dino3: 0,
    dino4: 0,
    dino5: 0,
    trex: 0
  };

  // Actualizar displays
  especies.forEach(esp => {
    const display = document.querySelector(`.qty-display[data-especie="${esp}"]`);
    if (display) display.textContent = '0';
  });

  actualizarContadorSeleccion();

  // Actualizar instrucción
  const instruccion = document.getElementById('modal-instruccion');
  if (instruccion) {
    instruccion.textContent = `Ronda ${ronda} - Indica cuántos dinosaurios sacaste de la bolsa (máx 6 total)`;
  }
}

function cambiarCantidad(especie, delta) {
  const nuevaCantidad = cantidadesSeleccionadas[especie] + delta;
  const totalActual = Object.values(cantidadesSeleccionadas).reduce((a, b) => a + b, 0);

  // Validar límites
  if (nuevaCantidad < 0) return;
  if (nuevaCantidad > DINOS_POR_RONDA) {
    alert(`No puedes seleccionar más de ${DINOS_POR_RONDA} de la misma especie`);
    return;
  }

  // Validar total
  if (delta > 0 && totalActual >= DINOS_POR_RONDA) {
    alert(`Solo puedes seleccionar ${DINOS_POR_RONDA} dinosaurios en total`);
    return;
  }

  cantidadesSeleccionadas[especie] = nuevaCantidad;

  // Actualizar display
  const display = document.querySelector(`.qty-display[data-especie="${especie}"]`);
  if (display) {
    display.textContent = nuevaCantidad;
  }

  actualizarContadorSeleccion();
  actualizarBotonesQuantity();
}

function actualizarContadorSeleccion() {
  const total = Object.values(cantidadesSeleccionadas).reduce((a, b) => a + b, 0);
  const contador = document.getElementById('contador-seleccion');
  if (contador) {
    contador.textContent = total;
  }

  // Habilitar/deshabilitar botón de confirmar
  const btnConfirmar = document.getElementById('btn-confirmar-seleccion');
  if (btnConfirmar) {
    btnConfirmar.disabled = total !== DINOS_POR_RONDA;
  }
}

function actualizarBotonesQuantity() {
  const totalActual = Object.values(cantidadesSeleccionadas).reduce((a, b) => a + b, 0);

  especies.forEach(especie => {
    const btnPlus = document.querySelector(`.btn-plus[data-especie="${especie}"]`);
    const btnMinus = document.querySelector(`.btn-minus[data-especie="${especie}"]`);

    if (btnPlus) {
      btnPlus.disabled = totalActual >= DINOS_POR_RONDA || cantidadesSeleccionadas[especie] >= DINOS_POR_RONDA;
    }

    if (btnMinus) {
      btnMinus.disabled = cantidadesSeleccionadas[especie] === 0;
    }
  });
}

function confirmarSeleccion() {
  const total = Object.values(cantidadesSeleccionadas).reduce((a, b) => a + b, 0);

  if (total !== DINOS_POR_RONDA) {
    alert(`Debes seleccionar exactamente ${DINOS_POR_RONDA} dinosaurios`);
    return;
  }

  // Construir mano basada en cantidades
  mano = [];
  Object.keys(cantidadesSeleccionadas).forEach(especie => {
    const cantidad = cantidadesSeleccionadas[especie];
    for (let i = 0; i < cantidad; i++) {
      mano.push(especie);
    }
  });

  console.log('Dinosaurios seleccionados:', cantidadesSeleccionadas);
  console.log('Mano generada:', mano);

  // Cerrar modal
  const modalElement = document.getElementById('modalSeleccionDinos');
  const modal = bootstrap.Modal.getInstance(modalElement);
  if (modal) {
    modal.hide();
  }

  // Cambiar a fase de colocar
  fase = 'colocar';
  tirarDadoReferencia();

  // 💾 Guardar estado
  guardarEstadoJuego();
}

/* ===== LÓGICA DEL DADO (SOLO VISUAL/REFERENCIA) ===== */
function tirarDadoReferencia() {
  const valor = Math.floor(Math.random() * 6) + 1;
  const dadoImg = document.getElementById('tracking-dado-img');
  const restriccionText = document.getElementById('tracking-restriccion-text');

  if (dadoImg) {
    dadoImg.src = `/img/dado/dado${valor}.png`;
  }

  if (restriccionText) {
    restriccionText.textContent = '(Referencia visual)';
  }

  console.log(`🎲 Dado mostrado (referencia): ${valor}`);

  // Renderizar mano
  renderMano();
  actualizarUI();
}

/* ===== RENDERIZAR MANO ===== */
function renderMano() {
  const grid = document.getElementById('tracking-dino-grid');
  if (!grid) {
    console.error('❌ No se encontró tracking-dino-grid');
    return;
  }

  grid.innerHTML = '';

  if (!mano || mano.length === 0) {
    grid.innerHTML = '<p class="text-center text-muted">No hay dinosaurios disponibles</p>';
    console.log('⚠️ No hay dinosaurios en la mano');
    return;
  }

  console.log('🎴 Renderizando mano:', mano);
  console.log('🎮 Fase actual:', fase);

  mano.forEach((esp, idx) => {
    const img = document.createElement('img');
    img.src = `/img/imagen_Tablero/${esp}.png`;
    img.className = 'dino-img';
    img.draggable = true;
    img.dataset.especie = esp;
    img.dataset.index = idx;
    img.style.cursor = 'grab';

    grid.appendChild(img);

    img.addEventListener('dragstart', e => {
      if (fase !== 'colocar') {
        console.warn('⚠️ No es fase de colocar, bloqueando drag');
        e.preventDefault();
        return;
      }
      draggedDino = { especie: esp, index: idx };
      img.classList.add('dragging');
      img.style.cursor = 'grabbing';
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', esp);
      highlightRecintos(esp);
      console.log('🎯 Arrastrando:', esp, 'index:', idx);
    });

    img.addEventListener('dragend', () => {
      img.classList.remove('dragging');
      img.style.cursor = 'grab';
      clearHighlight();
      console.log('🏁 Terminó arrastre');
    });
  });

  console.log(`✅ ${mano.length} dinosaurios renderizados con drag and drop`);
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
  dinoClone.style.pointerEvents = 'none';
  recinto.appendChild(dinoClone);

  // Remover de la mano
  mano.splice(draggedDino.index, 1);
  dinosColocados++;

  // Actualizar contadores visuales
  actualizarContadores();
  actualizarPuntuaciones();

  console.log(`✅ Dinosaurio ${especie} colocado`);
  console.log(`📊 Dinosaurios colocados en esta ronda: ${(dinosColocados % TURNOS_POR_RONDA) || TURNOS_POR_RONDA}`);
  console.log(`🎴 Mano restante:`, mano);

  // ✅ REGLA: Después de colocar, DESCARTAR un dinosaurio
  fase = 'descartar';
  renderManoParaDescartar();
  actualizarUI();

  // 💾 Guardar estado
  guardarEstadoJuego();
}

/* ===== RENDERIZAR MANO PARA DESCARTAR ===== */
function renderManoParaDescartar() {
  const grid = document.getElementById('tracking-dino-grid');
  if (!grid) return;

  grid.innerHTML = '';

  if (!mano || mano.length === 0) {
    // Si no quedan dinosaurios, pasar al siguiente turno
    verificarFinTurno();
    return;
  }

  // Crear mensaje de instrucción
  const instruccion = document.createElement('p');
  instruccion.className = 'text-center text-warning mb-3';
  instruccion.innerHTML = '<strong>🗑️ Selecciona un dinosaurio para DESCARTAR</strong>';
  grid.appendChild(instruccion);

  // Renderizar dinosaurios disponibles con evento de click
  mano.forEach((esp, idx) => {
    const container = document.createElement('div');
    container.className = 'dino-descarte-container';
    container.style.cssText = 'display: inline-block; margin: 5px; cursor: pointer; position: relative;';

    const img = document.createElement('img');
    img.src = `/img/imagen_Tablero/${esp}.png`;
    img.className = 'dino-img dino-descartable';
    img.dataset.especie = esp;
    img.dataset.index = idx;
    img.style.cssText = 'border: 3px solid #dc3545; border-radius: 8px; transition: all 0.3s;';

    container.appendChild(img);
    grid.appendChild(container);

    // Evento hover
    img.addEventListener('mouseenter', () => {
      img.style.transform = 'scale(1.1)';
      img.style.borderColor = '#ff0000';
    });

    img.addEventListener('mouseleave', () => {
      img.style.transform = 'scale(1)';
      img.style.borderColor = '#dc3545';
    });

    // Evento click para descartar
    img.addEventListener('click', () => {
      descartarDino(idx, esp);
    });
  });
}

/* ===== DESCARTAR DINOSAURIO ===== */
let descartandoDino = false; // Flag para prevenir múltiples descartes

function descartarDino(index, especie) {
  // Prevenir múltiples descartes simultáneos
  if (descartandoDino) {
    console.warn('⚠️ Ya se está descartando un dinosaurio');
    return;
  }

  descartandoDino = true;
  console.log(`🗑️ Descartando dinosaurio: ${especie}`);

  // Obtener el elemento visual del dinosaurio
  const grid = document.getElementById('tracking-dino-grid');
  const dinoElements = grid.querySelectorAll('.dino-descartable');

  // Deshabilitar todos los clics en dinosaurios
  dinoElements.forEach(el => {
    el.style.pointerEvents = 'none';
  });

  // Añadir animación de desaparición
  if (dinoElements[index]) {
    dinoElements[index].style.transition = 'all 0.3s ease-out';
    dinoElements[index].style.opacity = '0';
    dinoElements[index].style.transform = 'scale(0.5)';

    // Esperar a que termine la animación antes de remover
    setTimeout(() => {
      // Remover de la mano
      mano.splice(index, 1);
      dinosDescartados++;

      // Actualizar contadores
      actualizarContadores();

      console.log(`📊 Descartados: ${dinosDescartados}`);
      console.log(`🎴 Mano después de descartar:`, mano);

      // 💾 Guardar estado
      guardarEstadoJuego();

      // Cambiar fase antes de verificar fin de turno
      fase = 'intercambio';

      // Verificar si terminó el turno/ronda
      verificarFinTurno();

      // Reiniciar flag
      descartandoDino = false;
    }, 300);
  } else {
    // Si no hay elemento visual, proceder directamente
    mano.splice(index, 1);
    dinosDescartados++;
    actualizarContadores();
    console.log(`📊 Descartados: ${dinosDescartados}`);
    console.log(`🎴 Mano después de descartar:`, mano);

    // 💾 Guardar estado
    guardarEstadoJuego();

    // Cambiar fase
    fase = 'intercambio';

    verificarFinTurno();

    // Reiniciar flag
    descartandoDino = false;
  }
}

/* ===== VERIFICAR FIN DE TURNO ===== */
function verificarFinTurno() {
  console.log(`📊 Verificando fin de turno: Colocados=${dinosColocados}, Turno=${turno}, Mano=${mano.length}`);

  // En cada turno se coloca 1 y se descarta 1
  // Verificar si se completaron los 3 turnos de la ronda
  const turnosCompletados = dinosColocados % TURNOS_POR_RONDA;

  if (turnosCompletados === 0 && dinosColocados > 0) {
    // Fin de la ronda (se colocaron 3 dinosaurios)
    console.log('🏁 Ronda completada');
    finalizarRonda();
  } else {
    // ✅ REGLA CORRECTA: Después de colocar y descartar, INTERCAMBIAR manos
    const dinosRestantes = mano.length;

    if (dinosRestantes > 0) {
      console.log(`🔄 Intercambiando ${dinosRestantes} dinosaurios con el rival...`);
      abrirModalIntercambio(dinosRestantes);
    } else {
      // Si no quedan dinosaurios, pasar al siguiente turno directamente
      turno++;
      fase = 'colocar';
      renderMano();
      actualizarUI();
    }
  }
}

/* ===== MODAL DE INTERCAMBIO ===== */
let cantidadesIntercambio = {
  dino1: 0,
  dino2: 0,
  dino3: 0,
  dino4: 0,
  dino5: 0,
  trex: 0
};

let dinosEsperados = 0;

function abrirModalIntercambio(cantidadEsperada) {
  dinosEsperados = cantidadEsperada;

  const modal = new bootstrap.Modal(document.getElementById('modalIntercambio'));
  modal.show();

  // Reiniciar cantidades
  cantidadesIntercambio = {
    dino1: 0,
    dino2: 0,
    dino3: 0,
    dino4: 0,
    dino5: 0,
    trex: 0
  };

  // Actualizar displays
  especies.forEach(esp => {
    const display = document.querySelector(`.qty-display-intercambio[data-especie="${esp}"]`);
    if (display) display.textContent = '0';
  });

  actualizarContadorIntercambio();

  // Actualizar instrucción
  const instruccion = document.getElementById('intercambio-instruccion');
  if (instruccion) {
    instruccion.textContent = `Pasaste ${cantidadEsperada} dinosaurios al rival. ¿Qué ${cantidadEsperada} dinosaurios recibiste?`;
  }

  const esperadosEl = document.getElementById('esperados-intercambio');
  if (esperadosEl) {
    esperadosEl.textContent = cantidadEsperada;
  }
}

function cambiarCantidadIntercambio(especie, delta) {
  const nuevaCantidad = cantidadesIntercambio[especie] + delta;
  const totalActual = Object.values(cantidadesIntercambio).reduce((a, b) => a + b, 0);

  // Validar límites
  if (nuevaCantidad < 0) return;

  // Validar total
  if (delta > 0 && totalActual >= dinosEsperados) {
    alert(`Solo puedes recibir ${dinosEsperados} dinosaurios`);
    return;
  }

  cantidadesIntercambio[especie] = nuevaCantidad;

  // Actualizar display
  const display = document.querySelector(`.qty-display-intercambio[data-especie="${especie}"]`);
  if (display) {
    display.textContent = nuevaCantidad;
  }

  actualizarContadorIntercambio();
  actualizarBotonesIntercambio();
}

function actualizarContadorIntercambio() {
  const total = Object.values(cantidadesIntercambio).reduce((a, b) => a + b, 0);
  const contador = document.getElementById('contador-intercambio');
  if (contador) {
    contador.textContent = total;
  }

  // Habilitar/deshabilitar botón de confirmar
  const btnConfirmar = document.getElementById('btn-confirmar-intercambio');
  if (btnConfirmar) {
    btnConfirmar.disabled = total !== dinosEsperados;
  }
}

function actualizarBotonesIntercambio() {
  const totalActual = Object.values(cantidadesIntercambio).reduce((a, b) => a + b, 0);

  especies.forEach(especie => {
    const btnPlus = document.querySelector(`.btn-plus-intercambio[data-especie="${especie}"]`);
    const btnMinus = document.querySelector(`.btn-minus-intercambio[data-especie="${especie}"]`);

    if (btnPlus) {
      btnPlus.disabled = totalActual >= dinosEsperados;
    }

    if (btnMinus) {
      btnMinus.disabled = cantidadesIntercambio[especie] === 0;
    }
  });
}

function confirmarIntercambio() {
  const total = Object.values(cantidadesIntercambio).reduce((a, b) => a + b, 0);

  if (total !== dinosEsperados) {
    alert(`Debes recibir exactamente ${dinosEsperados} dinosaurios`);
    return;
  }

  // Construir nueva mano basada en cantidades recibidas
  mano = [];
  Object.keys(cantidadesIntercambio).forEach(especie => {
    const cantidad = cantidadesIntercambio[especie];
    for (let i = 0; i < cantidad; i++) {
      mano.push(especie);
    }
  });

  console.log('✅ Dinosaurios recibidos del rival:', cantidadesIntercambio);
  console.log('🎴 Nueva mano:', mano);

  // Cerrar modal
  const modalElement = document.getElementById('modalIntercambio');
  const modal = bootstrap.Modal.getInstance(modalElement);
  if (modal) {
    modal.hide();
  }

  // Continuar con siguiente turno
  turno++;
  fase = 'colocar';
  console.log('🔄 Cambiando a fase COLOCAR para turno', turno);

  // 💾 Guardar estado
  guardarEstadoJuego();

  // Esperar a que el modal se cierre completamente antes de renderizar
  setTimeout(() => {
    renderMano();
    actualizarUI();
    console.log('✅ Mano renderizada y UI actualizada');
  }, 300);
}

/* ===== FINALIZAR RONDA ===== */
function finalizarRonda() {
  console.log(`🏁 Fin de la ronda ${ronda}`);

  turno = 1;
  ronda++;

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

  const puntosFinal = calcularPuntuacionTotal();

  mostrarResultadosFinales(puntosFinal);
}

function mostrarResultadosFinales(puntos) {
  const mensaje = `
    🏆 ¡JUEGO FINALIZADO! 🏆

    Puntuación Final: ${puntos} puntos

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
    let faseTexto = '';
    if (fase === 'colocar') {
      faseTexto = ' - 📍 COLOCAR dinosaurio';
    } else if (fase === 'descartar') {
      faseTexto = ' - 🗑️ DESCARTAR dinosaurio';
    } else if (fase === 'seleccion') {
      faseTexto = ' - 🎲 Seleccionar dinosaurios';
    }
    turnoText.textContent = `Ronda ${ronda} - Turno ${turno}${faseTexto}`;
  }

  const panelTitle = document.getElementById('dino-panel-title');
  if (panelTitle) {
    if (fase === 'descartar') {
      panelTitle.textContent = `🗑️ Selecciona un dinosaurio para descartar`;
    } else {
      panelTitle.textContent = `Tus Dinosaurios`;
    }
  }

  const nombreJ1 = document.getElementById('nombre-j1');
  if (nombreJ1) nombreJ1.textContent = jugadorNombre;

  const rondaActual = document.getElementById('ronda-actual');
  if (rondaActual) rondaActual.textContent = ronda;

  clearHighlight();
}

function actualizarContadores() {
  const colocadosEl = document.getElementById('dinos-colocados');
  const descartadosEl = document.getElementById('dinos-descartados');

  if (colocadosEl) {
    colocadosEl.textContent = dinosColocados;
  }

  if (descartadosEl) {
    descartadosEl.textContent = dinosDescartados;
  }
}

/* ===== INICIALIZACIÓN ===== */
document.addEventListener('DOMContentLoaded', () => {
  console.log('🎮 Inicializando modo tracking (1 jugador)...');

  // Inicializar puntuaciones
  document.querySelectorAll('.recinto').forEach(recinto => {
    puntuacionesJugador[recinto.id] = 0;
  });

  // Configurar eventos de los botones +/-
  document.querySelectorAll('.btn-plus').forEach(btn => {
    btn.addEventListener('click', () => {
      const especie = btn.dataset.especie;
      cambiarCantidad(especie, 1);
    });
  });

  document.querySelectorAll('.btn-minus').forEach(btn => {
    btn.addEventListener('click', () => {
      const especie = btn.dataset.especie;
      cambiarCantidad(especie, -1);
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
      if (!draggedDino) return;

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
      if (confirm('¿Seguro que quieres volver al perfil? El progreso se guardará automáticamente.')) {
        window.location.href = '/perfil';
      }
    });
  }

  // Botón reiniciar partida
  const btnReiniciar = document.getElementById('btn-reiniciar-partida');
  if (btnReiniciar) {
    btnReiniciar.addEventListener('click', confirmarReinicioPartida);
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

  // 💾 CARGAR ESTADO GUARDADO
  const estadoCargado = cargarEstadoJuego();

  if (estadoCargado) {
    // Si se cargó un estado previo, restaurar la UI
    console.log('📂 Restaurando juego desde estado guardado...');
    actualizarUI();
    actualizarContadores();
    actualizarPuntuaciones();

    // Renderizar la mano si existe y estamos en fase de colocar o descartar
    if (mano && mano.length > 0) {
      if (fase === 'colocar') {
        renderMano();
      } else if (fase === 'descartar') {
        renderManoParaDescartar();
      }
    }
  } else {
    // Inicializar UI normalmente
    actualizarUI();
    actualizarContadores();

    // Abrir modal de selección al inicio
    console.log('🦕 Abriendo modal de selección inicial...');
    setTimeout(() => {
      abrirModalSeleccion();
    }, 500);
  }
});
