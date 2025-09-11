/* =====  tablero.js  ===== */
/* ------ Bloqueo frontal: sin sesión → login ------ */
if (!localStorage.getItem('userId')) {
  localStorage.clear();
  location.replace('/login');
}

/* ------ Nombres reales (variables para poder actualizarlas) ------ */
let jugadorActualNombre = localStorage.getItem('jugadorActual') || 'Yo';
let rivalNombre = localStorage.getItem('rival') || 'Rival';

/* ------ VARIABLES GLOBALES (movidas FUERA del DOMContentLoaded) ------ */
let draggedDino = null;
let jugadorQueTiroDado = Math.random() < 0.5 ? 1 : 2;
let jugadorActivo = jugadorQueTiroDado;
let restriccionActual = null;
let turno = 1;
let ronda = 1;
let ID_PARTIDA = null;
const TOTAL_RONDAS = 4;

let manos = { 1: [], 2: [] };
let colocadosEnTurno = 0;
let puntuacionesJugadores = { 1: {}, 2: {} };

<<<<<<< Updated upstream
  /* 3. Pintar datos */
  if (el('user-name')) el('user-name').textContent = data.username;
  if (el('user-id')) el('user-id').textContent = `#${data.id}`;
  if (el('user-puntos')) el('user-puntos').textContent = data.puntuacion_total ?? 0;
  if (el('user-jugadas')) el('user-jugadas').textContent = data.partidas_jugadas ?? 0;
  if (el('user-ganadas')) el('user-ganadas').textContent = data.partidas_ganadas ?? 0;
  if (el('user-info')) {
    let html = `<p><strong>Email:</strong> ${data.email}</p>`;
    if (data.created_at) html += `<p><strong>Fecha:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>`;
    el('user-info').innerHTML = html;
  }
  if (el('user-puntos')) el('user-puntos').textContent = data.puntuacion_total ?? 0;
  if (el('avatar-img')) el('avatar-img').src = data.avatar || 'img/isotipoOficial.png';
=======
const especies = ['dino1', 'dino2', 'dino3', 'dino4', 'dino5', 'trex'];
const restricciones = {
  1: "Zona izquierda",
  2: "Zona derecha", 
  3: "Zona boscosa",
  4: "Recinto vacío",
  5: "Recinto sin T-REX",
  6: "Sin restricción"
};
>>>>>>> Stashed changes

/* ------ FUNCIONES GLOBALES ------ */
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
    });
    img.addEventListener('dragend', () => img.classList.remove('dragging'));
  });
}

function actualizarUI() {
  const nombreTurno = jugadorActivo === 1 ? jugadorActualNombre : rivalNombre;
  const dinoPanelTitle = document.getElementById('dino-panel-title');
  const turnoText = document.getElementById('turno-text');
  const dadoBtn = document.getElementById('tirar-dado-btn');
  
  dinoPanelTitle.textContent = `Tus Dinosaurios - ${nombreTurno}`;
  renderMano(jugadorActivo);
  actualizarPuntuacionJugadorActivo();
  actualizarPuntuacionesVisualesRecintos();
  actualizarVisibilidadDinos();

  if (restriccionActual === null) {
    turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${nombreTurno} debe tirar dado`;
  } else if (jugadorActivo === jugadorQueTiroDado) {
    turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${nombreTurno} debe colocar (SIN restricciones)`;
  } else {
    turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${nombreTurno} debe colocar (CON restricción: ${restricciones[restriccionActual]})`;
  }

  dadoBtn.style.display = restriccionActual === null ? 'inline-block' : 'none';
}

function actualizarPuntuacionJugadorActivo() {
  const playerScoreElement = document.querySelector('.player-score');
  const recintos = document.querySelectorAll('.recinto');
  let total = 0;
  recintos.forEach(r => {
    total += puntuacionesJugadores[jugadorActivo][r.id] || 0;
  });
  playerScoreElement.textContent = total;
}

function actualizarPuntuacionesVisualesRecintos() {
  const recintos = document.querySelectorAll('.recinto');
  recintos.forEach(r => {
    const p = puntuacionesJugadores[jugadorActivo][r.id] || 0;
    r.querySelector('.recinto-score').textContent = p;
  });
}

function actualizarVisibilidadDinos() {
  document.querySelectorAll('.dino-in-recinto').forEach(dino => {
    dino.style.display = parseInt(dino.dataset.jugador) === jugadorActivo ? 'block' : 'none';
  });
}

/* ---------- Función que restaura el estado ---------- */
async function cargarPartidaYRestaurar(idPartida){
  try {
    const r = await fetch(`/api/tablero/cargarPartida?id=${idPartida}`, { credentials: 'include' });
    const { success, data: p, message } = await r.json();
    if (!success) { alert(message); return; }

    console.log('Datos recibidos:', p); // Para debug

    // Actualizar nombres con los datos reales de la partida
    jugadorActualNombre = p.jugador1;
    rivalNombre = p.jugador2;

    // variables globales - AHORA SÍ PUEDEN ACCEDERSE
    ID_PARTIDA = p.id;
    ronda = p.ronda;
    turno = p.turno;
    jugadorActivo = p.jugadorActivo;
    jugadorQueTiroDado = p.jugadorQueTiroDado;
    restriccionActual = p.restriccion;
    
    // AHORA SÍ FUNCIONA porque manos está en scope global
    manos[1] = p.mano1.dinosaurios || p.mano1;
    manos[2] = p.mano2.dinosaurios || p.mano2;

    // limpiar tablero
    document.querySelectorAll('.dino-in-recinto').forEach(d => d.remove());

    // volcar fichas
    p.colocaciones.forEach(({ recinto, jugador, especie }) => {
      const r = document.getElementById(recinto);
      if (!r) return;
      const img = document.createElement('img');
      img.src = `/img/imagen_Tablero/${especie}.png`;
      img.className = 'dino-in-recinto';
      img.dataset.especie = especie;
      img.dataset.jugador = jugador;
      img.style.pointerEvents = 'none';
      r.appendChild(img);
    });

    actualizarUI();
    alert(`Partida ${p.id} cargada. ¡A continuar!\n\nJugadores: ${jugadorActualNombre} vs ${rivalNombre}`);
  } catch (e) {
    console.error('Error completo:', e);
    alert('Error al cargar partida: ' + e.message);
  }
}

/* ---------- Al cargar: si hay partidaId en localStorage → restaurar ---------- */
(function(){
  const partidaId = localStorage.getItem('partidaId');
  if (partidaId) {
    cargarPartidaYRestaurar(+partidaId);
    localStorage.removeItem('partidaId');
  }
})();

/* ------ RESTO DEL CÓDIGO VA DENTRO DEL DOMContentLoaded ------ */
document.addEventListener('DOMContentLoaded', function () {
  const recintos = document.querySelectorAll('.recinto');
  const rulesBtn = document.querySelector('.rules-btn');
  const rulesModal = document.getElementById('rules-modal');
  const closeBtn = document.querySelector('.close-btn');

  const dadoBtn = document.getElementById('tirar-dado-btn');
  const dadoImg = document.getElementById('dado-img');
  const restriccionText = document.getElementById('restriccion-text');

  function iniciarRonda() {
    if (turno === 1) {
      manos[1] = generarMano();
      manos[2] = generarMano();
      recintos.forEach(recinto => {
        puntuacionesJugadores[1][recinto.id] = 0;
        puntuacionesJugadores[2][recinto.id] = 0;
      });
    }
    restriccionActual = null;
    colocadosEnTurno = 0;
    jugadorQueTiroDado = Math.random() < 0.5 ? 1 : 2;
    jugadorActivo = jugadorQueTiroDado;
    actualizarUI();
  }

  function tirarDado() {
    if (restriccionActual !== null) return;
    const valor = Math.floor(Math.random() * 6) + 1;
    dadoImg.src = `/img/dado/dado${valor}.png`;
    restriccionActual = valor;
    restriccionText.textContent = restricciones[valor];
    actualizarUI();
  }

  function colocarDino(recinto, especie) {
    const dinoClone = document.createElement('img');
    dinoClone.src = `/img/imagen_Tablero/${especie}.png`;
    dinoClone.className = 'dino-in-recinto';
    dinoClone.dataset.especie = especie;
    dinoClone.dataset.jugador = jugadorActivo;
    dinoClone.style.pointerEvents = 'none';
    recinto.appendChild(dinoClone);

    manos[jugadorActivo].splice(draggedDino.index, 1);
    colocadosEnTurno++;

    if (colocadosEnTurno === 2) {
      const ultimo = jugadorActivo;
      colocadosEnTurno = 0;
      restriccionActual = null;
      restriccionText.textContent = "Esperando...";
      dadoImg.src = "/img/dado/dado.png";

      turno++;
      if (turno > 3) {
        ronda++;
        if (ronda > TOTAL_RONDAS) {
          mostrarResultadosFinal();
          return;
        }
        turno = 1;
      }

      jugadorQueTiroDado = ultimo;
      jugadorActivo = jugadorQueTiroDado;
      iniciarRonda();
    } else {
      jugadorActivo = jugadorActivo === 1 ? 2 : 1;
      actualizarUI();
    }
  }

  function mostrarResultadosFinal() {
    const especiesJugador = { 1: {}, 2: {} };
    const recintosConTrex = { 1: new Set(), 2: new Set() };

    [1, 2].forEach(j => {
      recintos.forEach(r => {
        const dinos = Array.from(r.querySelectorAll('.dino-in-recinto'))
          .filter(d => parseInt(d.dataset.jugador) === j)
          .map(d => d.dataset.especie);

        dinos.forEach(e => {
          especiesJugador[j][e] = (especiesJugador[j][e] || 0) + 1;
        });

        if (dinos.includes('trex')) recintosConTrex[j].add(r.id);
      });
    });

    let puntos = { 1: 0, 2: 0 };

    [1, 2].forEach(j => {
      recintos.forEach(r => {
        const tipo = r.dataset.tipo;
        const dinos = Array.from(r.querySelectorAll('.dino-in-recinto'))
          .filter(d => parseInt(d.dataset.jugador) === j)
          .map(d => d.dataset.especie);

        if (tipo === 'rio') {
          puntos[j] += dinos.length;
          return;
        }

        if (recintosConTrex[j].has(r.id)) puntos[j] += 1;

        switch (tipo) {
          case 'isla-solitario':
            if (dinos.length === 1 && (especiesJugador[j][dinos[0]] || 0) === 1) puntos[j] += 7;
            break;
          case 'rey-selva':
            if (dinos.length === 1) {
              const esp = dinos[0];
              const max = Math.max(
                especiesJugador[1][esp] || 0,
                especiesJugador[2][esp] || 0
              );
              if ((especiesJugador[j][esp] || 0) === max) puntos[j] += 7;
            }
            break;
          case 'trio-frondoso':
            if (dinos.length === 3) puntos[j] += 7;
            break;
          case 'pradera-amor':
            const conteo = {};
            dinos.forEach(e => {
              conteo[e] = (conteo[e] || 0) + 1;
            });
            Object.values(conteo).forEach(c => {
              puntos[j] += Math.floor(c / 2) * 5;
            });
            break;
          case 'prado-diferencia':
          case 'bosque-semenjanza':
            const tabla = [0, 1, 3, 6, 10, 15, 21];
            puntos[j] += tabla[dinos.length] || 0;
            break;
        }
      });
    });

    const ganador = puntos[1] === puntos[2] ? 'Empate' : (puntos[1] > puntos[2] ? 1 : 2);
    alert(
      `¡Fin del juego!\n\n` +
      `${jugadorActualNombre}: ${puntos[1]} puntos\n` +
      `${rivalNombre}: ${puntos[2]} puntos\n\n` +
      (ganador === 'Empate' ? '¡Es un empate!' : `¡Ganador: ${ganador === 1 ? jugadorActualNombre : rivalNombre}!`)
    );
  }

  function puedeColocarDino(recinto, tipoRecinto, especieDino) {
    const dinosauriosEnRecinto = Array.from(recinto.querySelectorAll('.dino-in-recinto'))
      .filter(d => parseInt(d.dataset.jugador) === jugadorActivo);
    const cantidadDinos = dinosauriosEnRecinto.length;

    if (tipoRecinto === 'rio') return true;

    if (tipoRecinto === 'rey-selva') {
      return cantidadDinos === 0 && especieDino === 'trex';
    }
    if (recinto.id === 'recinto-4') {
      return cantidadDinos === 0 && especieDino === 'trex';
    }

    if (jugadorActivo !== jugadorQueTiroDado) {
      const zona = recinto.dataset.zona;
      const ambiente = recinto.dataset.ambiente;
      const tieneTrex = Array.from(dinosauriosEnRecinto).some(d => d.dataset.especie === "trex");

      switch (restriccionActual) {
        case 1: if (zona !== 'izquierda') return false; break;
        case 2: if (zona !== 'derecha') return false; break;
        case 3: if (ambiente !== 'boscoso') return false; break;
        case 4: if (cantidadDinos > 0) return false; break;
        case 5: if (tieneTrex || especieDino === 'trex') return false; break;
        case 6: break;
      }
    }

    switch (tipoRecinto) {
      case 'isla-solitario': return cantidadDinos === 0;
      case 'trio-frondoso': return cantidadDinos < 3;
      case 'bosque-semenjanza':
        return cantidadDinos === 0 || dinosauriosEnRecinto[0]?.dataset.especie === especieDino;
      case 'prado-diferencia':
        return !Array.from(dinosauriosEnRecinto).some(d => d.dataset.especie === especieDino);
      case 'pradera-amor':
      case 'rio': return true;
      default: return true;
    }
  }

  // Event listeners
  dadoBtn.addEventListener('click', tirarDado);

  recintos.forEach(recinto => {
    recinto.addEventListener('dragover', e => {
      e.preventDefault();
      recinto.classList.add('highlight');
    });
    recinto.addEventListener('dragleave', () => recinto.classList.remove('highlight'));
    recinto.addEventListener('drop', e => {
      e.preventDefault();
      recinto.classList.remove('highlight');

      if (!draggedDino || draggedDino.jugador !== jugadorActivo) return;
      const tipoRecinto = recinto.dataset.tipo;
      const especieDino = draggedDino.especie;

      if (restriccionActual === null) {
        alert("Primero debe tirar el dado.");
        return;
      }

      if (puedeColocarDino(recinto, tipoRecinto, especieDino)) {
        colocarDino(recinto, especieDino);
      } else {
        const recintosValidos = Array.from(recintos).filter(r =>
          puedeColocarDino(r, r.dataset.tipo, especieDino)
        );
        const nombres = recintosValidos.map(r => r.dataset.nombre).join(', ');
        const mensaje = jugadorActivo === jugadorQueTiroDado
          ? `No puedes colocar aquí por las reglas del recinto.\n\nRecintos válidos: ${nombres || 'Ninguno disponible'}`
          : `No puedes colocar aquí por la restricción del dado: ${restricciones[restriccionActual]}\n\nRecintos válidos: ${nombres || 'Ninguno disponible'}`;
        alert(mensaje);
      }
    });
  });

  /* ---------- Botón Guardar partida ---------- */
  document.getElementById('btn-guardar')?.addEventListener('click', async () => {
    const colocaciones = [];
    document.querySelectorAll('.recinto').forEach(r => {
      r.querySelectorAll('.dino-in-recinto').forEach(d => {
        colocaciones.push({
          recinto: r.id,
          jugador: parseInt(d.dataset.jugador),
          especie: d.dataset.especie
        });
      });
    });

    const payload = {
      id: ID_PARTIDA || 0,
      ronda: ronda,
      turno: turno,
      jugadorActivo: jugadorActivo,
      jugadorQueTiroDado: jugadorQueTiroDado,
      restriccion: restriccionActual,
      mano1: manos[1],
      mano2: manos[2],
      colocaciones: colocaciones
    };

    try {
      const res = await fetch('/api/tablero/guardarEstadoPartida', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      if (data.success && data.id) {
        ID_PARTIDA = data.id; // Guardar el ID para futuras actualizaciones
      }
      alert(data.message || 'Partida guardada');
    } catch (e) {
      alert('Error al guardar: ' + e.message);
    }
  });

  iniciarRonda();
});