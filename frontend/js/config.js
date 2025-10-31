/**
 * Configuración del Frontend
 * Define la URL base de la API Backend
 */

const API_BASE_URL = 'https://api.draftosaurus.duckdns.org';

// Helper para construir URLs de API
window.API_BASE_URL = API_BASE_URL;
window.apiUrl = (path) => {
  // Si la ruta ya tiene http, devolverla tal cual
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }
  // Asegurarse de que empieza con /
  const cleanPath = path.startsWith('/') ? path : '/' + path;
  return API_BASE_URL + cleanPath;
};
