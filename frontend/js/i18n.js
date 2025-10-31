/**
 * Sistema de Internacionalización (i18n) para Draftosaurus
 * Soporta Español e Inglés
 */

class I18n {
  constructor() {
    this.currentLang = localStorage.getItem('language') || 'es'; // Idioma por defecto: español
    this.translations = {};
    this.isLoaded = false;
    this.init();
  }

  // Inicializar y cargar traducciones
  async init() {
    // Establecer el idioma en el HTML desde el inicio
    document.documentElement.lang = this.currentLang;

    await this.loadTranslations();
    this.isLoaded = true;
  }

  // Cargar traducciones desde archivos JSON
  async loadTranslations() {
    try {
      // Agregar timestamp para evitar caché
      const cacheBuster = new Date().getTime();
      const response = await fetch(`/lang/${this.currentLang}.json?v=${cacheBuster}`);
      this.translations = await response.json();

      // Aplicar traducciones después de cargar
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => this.applyTranslations());
      } else {
        this.applyTranslations();
      }
    } catch (error) {
      console.error('Error cargando traducciones:', error);
    }
  }

  // Obtener traducción por clave
  t(key) {
    const keys = key.split('.');
    let value = this.translations;

    for (const k of keys) {
      if (value && typeof value === 'object') {
        value = value[k];
      } else {
        return key; // Si no encuentra la clave, devuelve la clave misma
      }
    }

    return value || key;
  }

  // Cambiar idioma
  async setLanguage(lang) {
    if (lang !== 'es' && lang !== 'en') {
      console.error('Idioma no soportado:', lang);
      return;
    }

    this.currentLang = lang;
    localStorage.setItem('language', lang);

    // Actualizar el atributo lang del HTML para los navegadores
    document.documentElement.lang = lang;

    await this.loadTranslations();

    // Disparar evento personalizado para que otros componentes se enteren
    window.dispatchEvent(new CustomEvent('languageChanged', { detail: { lang } }));
  }

  // Aplicar traducciones a elementos con data-i18n
  applyTranslations() {
    console.log('Aplicando traducciones para idioma:', this.currentLang);
    console.log('Traducciones cargadas:', this.translations);

    // Traducir elementos con data-i18n (textos)
    document.querySelectorAll('[data-i18n]').forEach(element => {
      const key = element.getAttribute('data-i18n');
      const translation = this.t(key);
      element.textContent = translation;
      console.log(`Traduciendo ${key}: ${translation}`);
    });

    // Traducir placeholders
    document.querySelectorAll('[data-i18n-placeholder]').forEach(element => {
      const key = element.getAttribute('data-i18n-placeholder');
      const translation = this.t(key);
      element.placeholder = translation;
      console.log(`Traduciendo placeholder ${key}: ${translation}`);
    });

    // Traducir títulos (atributo title)
    document.querySelectorAll('[data-i18n-title]').forEach(element => {
      const key = element.getAttribute('data-i18n-title');
      element.title = this.t(key);
    });

    // Actualizar el selector de idioma si existe
    const langSelector = document.getElementById('language-selector');
    if (langSelector) {
      langSelector.value = this.currentLang;
      console.log('Selector actualizado a:', this.currentLang);
    }
  }

  // Obtener idioma actual
  getCurrentLanguage() {
    return this.currentLang;
  }
}

// Crear instancia global
window.i18n = new I18n();

// Función global de conveniencia para traducciones
window.t = (key) => window.i18n.t(key);
