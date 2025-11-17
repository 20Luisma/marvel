(function () {
    const audio = new Audio('./assets/sound/intromarvel.mp3');
    const introDuration = 10000;
    let audioStarted = false;
    const fadeDuration = 1800;
    let loginShown = false;

    const introShell = document.getElementById('intro');
    const loginShell = document.getElementById('login-shell');
    const form = document.getElementById('login-form');
    const errorBox = document.getElementById('login-error');
    const logoFrame = document.querySelector('.logo-frame');

    const validUser = 'marvel@gmail.com';
    const validPass = 'marvel2025';

    const showLogin = () => {
        if (loginShown) return;
        loginShown = true;
        introShell?.classList.add('hidden');
        loginShell?.classList.add('visible');
    };

    window.setTimeout(showLogin, introDuration + 200);
    if (logoFrame) {
        logoFrame.addEventListener('animationend', showLogin, {
            once: true
        });
    }

    const fadeInAudio = () => {
        let start = null;
        const step = (timestamp) => {
            if (start === null) start = timestamp;
            const progress = Math.min((timestamp - start) / fadeDuration, 1);
            audio.volume = progress;
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    };

    const startAudio = () => {
        if (audioStarted) {
            return;
        }
        audioStarted = true;
        audio.volume = 0;
        audio.muted = true;
        audio.currentTime = 0;
        const playPromise = audio.play();
        if (playPromise !== null && typeof playPromise === 'object' && typeof playPromise.then === 'function') {
            playPromise.then(() => {
                window.setTimeout(() => {
                    audio.muted = false;
                    fadeInAudio();
                }, 120);
            }).catch(() => {
                audioStarted = false;
            });
        }
    };

    // La música se activa con el primer click / interacción en pantalla
    startAudio();
    ['click', 'touchstart', 'keydown'].forEach((evt) => {
        window.addEventListener(evt, startAudio, {
            once: true
        });
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!form || !errorBox) {
            window.location.href = './home.php';
            return;
        }
        const username = (form.querySelector('#username') || {}).value?.trim() || '';
        const password = (form.querySelector('#password') || {}).value || '';

        if (username.toLowerCase() === validUser && password === validPass) {
            errorBox.textContent = '';
            window.location.href = './home.php';
        } else {
            errorBox.textContent = 'Credenciales de prueba incorrectas. Usa marvel@gmail.com / marvel2025.';
        }
    });
}());
