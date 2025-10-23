/* frontend/js/index.js –– ENTRY POINT */

/* 0.  Idioma (i18n) */
async function initI18n() {
    // Esperar a que window.i18n esté disponible
    while (!window.i18n) {
        await new Promise(resolve => setTimeout(resolve, 100));
    }

    const languageSelector = document.getElementById('language-selector');
    if (languageSelector) {
        languageSelector.value = window.i18n.getCurrentLanguage();
        languageSelector.addEventListener('change', (e) => {
            const selectedLang = e.target.value;
            window.i18n.setLanguage(selectedLang);
        });
    }
}

/* 1.  SPLASH VIDEO AUTOMÁTICO */
function initSplash() {
    const splash = document.getElementById('video-splash');
    const video = document.getElementById('logo-video');

    if (!splash || !video) return;

    // Reproducir automáticamente al cargar
    const playVideo = () => {
        video.play().catch(e => {
            console.log('Autoplay bloqueado, intentando con muted...');
            video.muted = true;
            video.play();
        });
    };

    // Intentar reproducir inmediatamente
    playVideo();

    // También intentar cuando el usuario interactúe con la página
    document.addEventListener('click', function enableSound() {
        if (video.muted) {
            video.muted = false;
        }
        document.removeEventListener('click', enableSound);
    });

    // Ocultar splash cuando termine el video
    video.addEventListener('ended', () => {
        splash.classList.add('fade-out');
        setTimeout(() => {
            splash.style.display = 'none';
        }, 600);
    });

    // Permitir saltar el splash con click
    splash.addEventListener('click', () => {
        video.pause();
        splash.classList.add('fade-out');
        setTimeout(() => {
            splash.style.display = 'none';
        }, 600);
    });

    // Ocultar después de máximo 10 segundos (fallback)
    setTimeout(() => {
        if (splash.style.display !== 'none') {
            video.pause();
            splash.classList.add('fade-out');
            setTimeout(() => {
                splash.style.display = 'none';
            }, 600);
        }
    }, 10000);
}

/* 2.  CONTACT FORM */
function initContactForm() {
    const contactForm = document.querySelector('#contacto form');
    if (!contactForm) return;

    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const body = {
            nombre: document.getElementById('nombre').value.trim(),
            email: document.getElementById('email').value.trim(),
            asunto: document.getElementById('asunto').value.trim(),
            mensaje: document.getElementById('mensaje').value.trim()
        };

        try {
            const res = await fetch('/api/models/contacto', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });

            const data = await res.json();

            if (data.success) {
                alert('¡Mensaje enviado! Nos pondremos en contacto pronto.');
                contactForm.reset();
            } else {
                const msg = data.errors ? data.errors.join('\n') : data.message;
                alert('Error al enviar:\n' + msg);
            }
        } catch (err) {
            console.error(err);
            alert('Error de red – inténtalo más tarde.');
        }
    });
}

/* 3.  REDIRECCIÓN SI HAY SESIÓN VÁLIDA */
async function checkSession() {
    const userId = localStorage.getItem('userId');
    if (!userId) return;

    try {
        const res = await fetch('/mi-perfil');
        const json = await res.json();
        if (!json.error) location.replace('/perfil');
    } catch { }
}

/* 4.  SMOOTH SCROLL PARA LINKS INTERNOS */
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

/* 5.  INICIALIZAR TODO CUANDO EL DOM ESTÉ LISTO */
document.addEventListener('DOMContentLoaded', () => {
    initSplash();
    initContactForm();
    checkSession();
    initI18n();
    initSmoothScroll();
});