# 🌍 Carpeta de Idiomas

Esta carpeta contiene los archivos de traducciones para el sistema de internacionalización (i18n).

## Archivos

- **es.json** - Traducciones en Español (idioma por defecto)
- **en.json** - Traducciones en Inglés

## Agregar un nuevo idioma

1. Crea un nuevo archivo JSON (ej: `fr.json` para francés)
2. Copia la estructura de `es.json`
3. Traduce todos los valores
4. Actualiza `i18n.js` para incluir el nuevo idioma en el selector

## Estructura de las traducciones

```json
{
  "seccion": {
    "clave": "Valor traducido"
  }
}
```

Ejemplo de uso en HTML:
```html
<h1 data-i18n="seccion.clave">Valor por defecto</h1>
```

Ejemplo de uso en JavaScript:
```javascript
const texto = t('seccion.clave');
```

---

Ver documentación completa en: `INTERNATIONALIZATION.md` (raíz del proyecto)
