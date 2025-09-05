document.addEventListener('DOMContentLoaded', function () {
    /* ---------- CONFIG INICIAL ---------- */
    let avatar1 = 'img/isotipoOficial.png';
    let avatar2 = 'img/isotipoOficial.png';
    const userId1 = localStorage.getItem('userId');
    const userId2 = localStorage.getItem('userId2');
    let userName1 = 'Jugador 1';
    let userName2 = 'Jugador 2';

    function fetchPlayerName(userId, callback) {
        if (!userId) return callback(null);
        fetch(`/perfil/${userId}`)
            .then(res => res.json())
            .then(data => {
                if (data?.username) {
                    if (userId === userId1 && data.avatar) avatar1 = data.avatar;
                    if (userId === userId2 && data.avatar) avatar2 = data.avatar;
                    callback(data.username);
                } else callback(null);
            })
            .catch(() => callback(null));
    }

    fetchPlayerName(userId1, name => {
        if (name) userName1 = name;
        fetchPlayerName(userId2, name2 => {
            if (name2) userName2 = name2;
            iniciarRonda();
        });
    });

    /* ---------- ELEMENTOS DOM ---------- */
    const recintos = document.querySelectorAll('.recinto');
    const rulesBtn = document.querySelector('.rules-btn');
    const rulesModal = document.getElementById('rules-modal');
    const closeBtn = document.querySelector('.close-btn');
    const dadoBtn = document.getElementById('tirar-dado-btn');
    const dadoImg = document.getElementById('dado-img');
    const restriccionText = document.getElementById('restriccion-text');
    const turnoText = document.getElementById('turno-text');
    const mano1 = document.querySelector('.dino-grid');
    const dinoPanelTitle = document.getElementById('dino-panel-title');
    const playerScoreElement = document.querySelector('.player-score');

    /* ---------- VARIABLES DEL JUEGO ---------- */
    let draggedDino = null;
    let jugadorQueTiroDado = Math.random() < 0.5 ? 1 : 2;
    let jugadorActivo = jugadorQueTiroDado;
    let restriccionActual = null;
    let turno = 1;
    let ronda = 1;
    const TOTAL_RONDAS = 4;

    let manos = { 1: [], 2: [] };
    let colocadosEnTurno = 0;
    let puntuacionesJugadores = { 1: {}, 2: {} };

    const especies = ['dino1', 'dino2', 'dino3', 'dino4', 'dino5', 'trex'];
    const restricciones = {
        1: "Zona izquierda",
        2: "Zona derecha",
        3: "Zona boscosa",
        4: "Recinto vacío",
        5: "Recinto sin T-Rex",
        6: "Sin restricción"
    };

    /* ---------- FUNCIONES AUXILIARES ---------- */
    function generarMano() {
        return Array.from({ length: 6 }, () => especies[Math.floor(Math.random() * especies.length)]);
    }

    function renderMano(jugador) {
        const grid = mano1;
        grid.innerHTML = '';
        manos[jugador].forEach((esp, idx) => {
            const img = document.createElement('img');
            img.src = `img/imagen_Tablero/${esp}.png`;
            img.className = 'dino-img';
            img.draggable = restriccionActual !== null;
            img.style.cursor = restriccionActual !== null ? 'grab' : 'not-allowed';
            img.style.opacity = restriccionActual !== null ? '1' : '0.5';
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

    function iniciarRonda() {
        if (turno === 1) {
            manos[1] = generarMano();
            manos[2] = generarMano();
            recintos.forEach(r => {
                puntuacionesJugadores[1][r.id] = 0;
                puntuacionesJugadores[2][r.id] = 0;
            });
        }
        restriccionActual = null;
        colocadosEnTurno = 0;
        jugadorQueTiroDado = Math.random() < 0.5 ? 1 : 2;
        jugadorActivo = jugadorQueTiroDado;
        actualizarUI();
    }

    function actualizarUI() {
        dinoPanelTitle.textContent = `Tus Dinosaurios - ${jugadorActivo === 1 ? userName1 : userName2}`;
        const avatarImg = document.getElementById('avatar-jugador');
        avatarImg.src = jugadorActivo === 1 ? avatar1 : avatar2;
        renderMano(jugadorActivo);
        actualizarPuntuacionJugadorActivo();
        actualizarPuntuacionesVisualesRecintos();
        actualizarVisibilidadDinos();

        if (restriccionActual === null) {
            turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${jugadorActivo === 1 ? userName1 : userName2} debe tirar dado`;
        } else if (jugadorActivo === jugadorQueTiroDado) {
            turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${jugadorActivo === 1 ? userName1 : userName2} debe colocar (SIN restricciones)`;
        } else {
            turnoText.textContent = `Ronda ${ronda} - Turno ${turno} - ${jugadorActivo === 1 ? userName1 : userName2} debe colocar (CON restricción: ${restricciones[restriccionActual]})`;
        }

        dadoBtn.style.display = restriccionActual === null ? 'inline-block' : 'none';
    }

    function actualizarPuntuacionJugadorActivo() {
        let total = 0;
        recintos.forEach(r => total += puntuacionesJugadores[jugadorActivo][r.id] || 0);
        playerScoreElement.textContent = total;
    }

    function actualizarPuntuacionesVisualesRecintos() {
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

    function tirarDado() {
        if (restriccionActual !== null) return;
        const valor = Math.floor(Math.random() * 6) + 1;
        dadoImg.src = `img/dado/dado${valor}.png`;
        restriccionActual = valor;
        restriccionText.textContent = restricciones[valor];
        actualizarUI();
    }

    dadoBtn.addEventListener('click', tirarDado);

    function colocarDino(recinto, especieDino) {
        // Restricción exclusiva Rey de la Selva
        if (recinto.id === 'recinto-4') {
            if (especieDino !== 'trex') {
                alert('⚠️ Solo se permite colocar un T-Rex en el Rey de la Selva.');
                return;
            }
            const trexExistente = Array.from(
                recinto.querySelectorAll('.dino-in-recinto')
            ).some(dino => dino.dataset.especie === 'trex' && parseInt(dino.dataset.jugador) === jugadorActivo);
            if (trexExistente) {
                alert('⚠️ Solo puede haber un T-Rex en el Rey de la Selva.');
                return;
            }
        }

        const dinoClone = document.createElement('img');
        dinoClone.src = `img/imagen_Tablero/${especieDino}.png`;
        dinoClone.className = 'dino-in-recinto';
        dinoClone.dataset.especie = especieDino;
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
            dadoImg.src = "img/dado/dado.png";

            turno++;
            if (turno > 3) {
                ronda++;
                if (ronda > TOTAL_RONDAS) {
                    mostrarResultadosFinal();
                    return;
                }
                turno = 1;
            }

            jugadorQueTiroDado = ultimo === 1 ? 2 : 1;
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

                dinos.forEach(e => { especiesJugador[j][e] = (especiesJugador[j][e] || 0) + 1; });
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
                            const max = Math.max(especiesJugador[1][esp] || 0, especiesJugador[2][esp] || 0);
                            if ((especiesJugador[j][esp] || 0) === max) puntos[j] += 7;
                        }
                        break;
                    case 'trio-frondoso':
                        if (dinos.length === 3) puntos[j] += 7;
                        break;
                    case 'pradera-amor':
                        const conteo = {};
                        dinos.forEach(e => conteo[e] = (conteo[e] || 0) + 1);
                        Object.values(conteo).forEach(c => puntos[j] += Math.floor(c / 2) * 5);
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
        const nombreGanador = ganador === 'Empate' ? '' : (ganador === 1 ? userName1 : userName2);
        alert(
            `¡Fin del juego!\n\n` +
            `${userName1}: ${puntos[1]} puntos\n` +
            `${userName2}: ${puntos[2]} puntos\n\n` +
            (ganador === 'Empate' ? '¡Es un empate!' : `¡Ganador: ${nombreGanador}!`)
        );
    }

    /* ---------- DRAG & DROP ---------- */
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

            let resultado;
            if (tipoRecinto === 'rio') {
                resultado = { success: true };
            } else {
                resultado = puedeColocarDino(recinto, tipoRecinto, especieDino)
                    ? { success: true }
                    : { success: false, message: 'Colocación no permitida por restricción o regla del recinto.' };
            }

            if (resultado.success) {
                colocarDino(recinto, especieDino);
            } else {
                alert(resultado.message);
            }
        });
    });

    /* ---------- FUNCIONES DE VALIDACIÓN ---------- */
    function puedeColocarDino(recinto, tipoRecinto, especieDino) {
        const dinosauriosEnRecinto = Array.from(recinto.querySelectorAll('.dino-in-recinto'))
            .filter(d => parseInt(d.dataset.jugador) === jugadorActivo);
        const cantidadDinos = dinosauriosEnRecinto.length;

        // Siempre permitir Río (ya se valida que se haya tirado el dado)
        if (tipoRecinto === 'rio') return true;

        // Rey de la Selva: solo 1 T-Rex
        if (recinto.id === 'recinto-4') {
            return cantidadDinos === 0 && especieDino === 'trex';
        }

        // Restricciones por dado (solo para el jugador que NO tiró)
        if (jugadorActivo !== jugadorQueTiroDado) {
            const zona = recinto.dataset.zona;
            const ambiente = recinto.dataset.ambiente;
            const tieneTrex = dinosauriosEnRecinto.some(d => d.dataset.especie === 'trex');

            switch (restriccionActual) {
                case 1: if (zona !== 'izquierda') return false; break;
                case 2: if (zona !== 'derecha') return false; break;
                case 3: if (ambiente !== 'boscoso') return false; break;
                case 4: if (cantidadDinos > 0) return false; break;
                case 5: if (tieneTrex || especieDino === 'trex') return false; break;
                case 6: break;
            }
        }

        // Reglas internas de cada recinto
        switch (tipoRecinto) {
            case 'isla-solitario': return cantidadDinos === 0;
            case 'trio-frondoso': return cantidadDinos < 3;
            case 'bosque-semenjanza':
                return cantidadDinos === 0 || dinosauriosEnRecinto[0]?.dataset.especie === especieDino;
            case 'prado-diferencia':
                return !dinosauriosEnRecinto.some(d => d.dataset.especie === especieDino);
            case 'pradera-amor': return true;
            default: return true;
        }
    }

    /* ---------- MODAL ---------- */
    rulesBtn.addEventListener('click', () => rulesModal.style.display = 'flex');
    closeBtn.addEventListener('click', () => rulesModal.style.display = 'none');
    rulesModal.addEventListener('click', e => { if (e.target === rulesModal) rulesModal.style.display = 'none'; });

    /* ---------- INICIO ---------- */
    iniciarRonda();
});