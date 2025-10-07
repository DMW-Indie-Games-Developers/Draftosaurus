/**
 * Helper functions para usar traducciones en JavaScript
 * Simplifica el uso de i18n en código dinámico
 */

// Función para traducir y actualizar alertas/mensajes dinámicos
window.translateAlert = function(key, fallback) {
  return window.i18n ? window.i18n.t(key) : fallback;
};

// Función para agregar selector de idioma programáticamente
window.addLanguageSelector = function(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const selectorHTML = `
    <select id="language-selector" class="form-select form-select-sm"
            style="background-color: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.3);">
      <option value="es">🇪🇸 ES</option>
      <option value="en">🇬🇧 EN</option>
    </select>
  `;

  container.innerHTML = selectorHTML;

  // Inicializar
  const selector = document.getElementById('language-selector');
  if (selector && window.i18n) {
    selector.value = window.i18n.getCurrentLanguage();
    selector.addEventListener('change', function(e) {
      window.i18n.setLanguage(e.target.value);
    });
  }
};

// Escuchar cambios de idioma y actualizar elementos dinámicos
window.addEventListener('languageChanged', function(e) {
  console.log('Idioma cambiado a:', e.detail.lang);

  // Recargar la página si es necesario (para contenido muy dinámico)
  // location.reload();
});
