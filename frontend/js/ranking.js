document.addEventListener('DOMContentLoaded', function () {
  const rankingBody = document.getElementById('ranking-body');
  const medals = ['🥇', '🥈', '🥉'];

  rankingBody.innerHTML = '<tr><td colspan="4" class="text-center">Cargando ranking...</td></tr>';

  // Hacemos la petición al endpoint CORRECTO de la API
  fetch(apiUrl('/api/ranking')) // <-- CORRECCIÓN AQUÍ
    .then(response => {
      if (!response.ok) {
        throw new Error('La respuesta de la red no fue exitosa');
      }
      return response.json(); // Ahora esto debería recibir JSON
    })
    .then(data => {
      rankingBody.innerHTML = ''; 

      if (data.success && data.ranking.length > 0) {
        data.ranking.forEach((player, index) => {
          const row = document.createElement('tr');

          if (index < 3) {
            row.classList.add(`top-${index + 1}`);
          }

          const positionCell = document.createElement('td');
          positionCell.innerHTML = medals[index] || (index + 1);

          // Celda del avatar
          const avatarCell = document.createElement('td');
          const avatarImg = document.createElement('img');
          avatarImg.classList.add('ranking-avatar');
          avatarImg.src = player.foto_perfil ? apiUrl(player.foto_perfil) : 'img/isotipoOficial.png';
          avatarImg.alt = `Avatar de ${player.username}`;
          avatarImg.onerror = function() {
            this.src = 'img/isotipoOficial.png';
          };
          avatarCell.appendChild(avatarImg);

          const playerCell = document.createElement('td');
          playerCell.textContent = player.username;

          const scoreCell = document.createElement('td');
          scoreCell.textContent = player.puntuacion_total;

          row.appendChild(positionCell);
          row.appendChild(avatarCell);
          row.appendChild(playerCell);
          row.appendChild(scoreCell);

          rankingBody.appendChild(row);
        });
      } else {
        rankingBody.innerHTML = '<tr><td colspan="4" class="text-center">No hay datos de ranking disponibles.</td></tr>';
      }
    })
    .catch(error => {
      console.error('Error al obtener el ranking:', error);
      rankingBody.innerHTML = '<tr><td colspan="4" class="text-center">No se pudo cargar el ranking. Inténtelo más tarde.</td></tr>';
    });
});