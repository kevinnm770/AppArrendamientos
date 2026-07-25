<script>
    window.addEventListener('load', () => {
        const card = document.querySelector('.chat-app');
        if (!card) {
            return;
        }

        const footer = document.querySelector('#main > footer');
        if (footer) {
            footer.style.display = 'none';
        }

        const fit = () => {
            const top = card.getBoundingClientRect().top + window.scrollY;
            const available = window.innerHeight - top - 16;
            card.style.height = Math.max(available, 420) + 'px';
        };

        fit();
        window.addEventListener('resize', fit);
        window.addEventListener('orientationchange', fit);
    });
</script>
