/**
 * Sistema de Internacionalización (i18n) Simplificado
 * Versión más robusta y fácil de debugear
 */

const I18nSystem = {
  currentLang: localStorage.getItem('language') || 'es',
  translations: {},
  isLoaded: false,

  // Inicializar
  async init() {
    console.log('[i18n] Inicializando sistema con idioma:', this.currentLang);
    await this.loadTranslations();
  },

  // Cargar traducciones
  async loadTranslations() {
    const url = `/lang/${this.currentLang}.json`;
    console.log('[i18n] Cargando traducciones desde:', url);

    try {
      const response = await fetch(url);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      this.translations = await response.json();
      console.log('[i18n] Traducciones cargadas:', Object.keys(this.translations));

      this.isLoaded = true;
      this.applyTranslations();

      return true;
    } catch (error) {
      console.error('[i18n] Error cargando traducciones:', error);
      return false;
    }
  },

  // Obtener traducción
  t(key) {
    const parts = key.split('.');
    let value = this.translations;

    for (const part of parts) {
      if (value && typeof value === 'object' && part in value) {
        value = value[part];
      } else {
        console.warn(`[i18n] Traducción no encontrada: ${key}`);
        return key;
      }
    }

    return value || key;
  },

  // Aplicar traducciones al DOM
  applyTranslations() {
    console.log('[i18n] Aplicando traducciones...');

    // Textos
    const textElements = document.querySelectorAll('[data-i18n]');
    console.log(`[i18n] Encontrados ${textElements.length} elementos con data-i18n`);

    textElements.forEach(el => {
      const key = el.getAttribute('data-i18n');
      const translation = this.t(key);
      el.textContent = translation;
      console.log(`[i18n] ${key} => ${translation}`);
    });

    // Placeholders
    const placeholderElements = document.querySelectorAll('[data-i18n-placeholder]');
    console.log(`[i18n] Encontrados ${placeholderElements.length} elementos con data-i18n-placeholder`);

    placeholderElements.forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      const translation = this.t(key);
      el.placeholder = translation;
      console.log(`[i18n] placeholder ${key} => ${translation}`);
    });

    // Títulos
    document.querySelectorAll('[data-i18n-title]').forEach(el => {
      const key = el.getAttribute('data-i18n-title');
      el.title = this.t(key);
    });

    // Actualizar selector
    const selector = document.getElementById('language-selector');
    if (selector) {
      selector.value = this.currentLang;
      console.log('[i18n] Selector actualizado a:', this.currentLang);
    }
  },

  // Cambiar idioma
  async setLanguage(lang) {
    console.log('[i18n] setLanguage llamado con:', lang);

    if (lang !== 'es' && lang !== 'en') {
      console.error('[i18n] Idioma no válido:', lang);
      return false;
    }

    // Si ya estamos en ese idioma, no hacer nada
    if (lang === this.currentLang && this.isLoaded) {
      console.log('[i18n] Ya estamos en el idioma:', lang);
      return true;
    }

    console.log('[i18n] Cambiando idioma de', this.currentLang, 'a', lang);
    this.currentLang = lang;
    localStorage.setItem('language', lang);

    this.isLoaded = false;
    const success = await this.loadTranslations();

    if (success) {
      console.log('[i18n] Idioma cambiado exitosamente a:', lang);
      window.dispatchEvent(new CustomEvent('languageChanged', {
        detail: { lang }
      }));
    } else {
      console.error('[i18n] Error al cambiar idioma');
    }

    return success;
  },

  // Obtener idioma actual
  getCurrentLanguage() {
    return this.currentLang;
  }
};

// Exponer globalmente
window.i18n = I18nSystem;
window.t = (key) => I18nSystem.t(key);

// Auto-inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    console.log('[i18n] DOM listo, inicializando...');
    I18nSystem.init();
  });
} else {
  console.log('[i18n] DOM ya estaba listo, inicializando ahora...');
  I18nSystem.init();
}

console.log('[i18n] Script cargado');
