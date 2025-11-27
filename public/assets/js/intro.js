(function () {
    const audio = new Audio('./assets/sound/intromarvel.mp3');
    const introDuration = 10000;
    let audioStarted = false;
    const fadeDuration = 1800;
    const fadeOutOffset = 1500; // texto desaparece 1.5s antes de salir

    const logoFrame = document.querySelector('.logo-frame');
    const textBlocks = document.querySelectorAll('.marvel-motto, .master-note-big, .master-note-small');

    const fadeTexts = () => {
        textBlocks.forEach((el) => {
            el.classList.add('fade-out');
        });
    };

    const goHome = () => {
        window.location.href = './home.php';
    };

    if (logoFrame) {
        logoFrame.addEventListener('animationend', () => {
            fadeTexts();
            window.setTimeout(goHome, fadeOutOffset);
        }, {
            once: true
        });
    }
    window.setTimeout(fadeTexts, Math.max(0, introDuration - fadeOutOffset));
    window.setTimeout(goHome, introDuration + 200);

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

}());
