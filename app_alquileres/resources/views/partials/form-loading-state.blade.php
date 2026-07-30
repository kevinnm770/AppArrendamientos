{{--
    Bloquea el botón de envío de cualquier formulario en el momento en que se envía y
    muestra un spinner de espera, para evitar doble envío mientras se procesa la
    transacción. Se aplica automáticamente a todos los formularios de la página; un
    formulario puede optar por no usarlo agregando el atributo `data-no-loading`.
--}}
<script>
    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.noLoading !== undefined) {
            return;
        }

        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]')
            .forEach(function (button) {
                if (button.disabled) {
                    return;
                }

                button.disabled = true;

                if (button.tagName === 'BUTTON') {
                    button.dataset.originalHtml = button.innerHTML;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
                } else {
                    button.dataset.originalValue = button.value;
                    button.value = 'Procesando...';
                }
            });
    }, true);
</script>
