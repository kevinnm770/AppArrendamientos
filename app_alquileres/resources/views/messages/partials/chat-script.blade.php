<script>
    window.addEventListener('load', () => {
        const panel = document.getElementById('chat-panel');
        if (!panel) {
            return;
        }

        const pollUrl = panel.dataset.pollUrl;
        const sendUrl = panel.dataset.sendUrl;
        const historyUrl = panel.dataset.historyUrl;
        const updateUrlBase = panel.dataset.updateUrlBase;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const messagesEl = document.getElementById('chat-messages');
        const bodyInput = document.getElementById('chat-body-input');
        const attachmentInput = document.getElementById('chat-attachment-input');
        const attachmentPreview = document.getElementById('chat-attachment-preview');
        const sendForm = document.getElementById('chat-send-form');
        const sendBtn = sendForm.querySelector('button[type="submit"]');
        const sendBtnDefaultHtml = sendBtn.innerHTML;

        const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
        const AUDIO_EXT = ['mp3', 'mpga', 'wav', 'm4a', 'ogg', 'webm'];

        let lastId = 0;
        let pendingAttachment = null;
        let pendingDuration = null;
        let isSending = false;

        const escapeHtml = (str) => {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        };

        const scrollToBottom = () => {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        };

        const showError = (message) => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Ups', text: message || 'Ocurrió un error.' });
            } else {
                alert(message || 'Ocurrió un error.');
            }
        };

        const formatDuration = (seconds) => {
            const value = Number(seconds) || 0;
            const minutes = Math.floor(value / 60);
            const secs = String(value % 60).padStart(2, '0');
            return `${minutes}:${secs}`;
        };

        const bubbleHtml = (message) => {
            const ext = message.file ? (message.file.extension || '').toLowerCase() : null;
            let attachmentHtml = '';

            if (message.file) {
                if (IMAGE_EXT.includes(ext)) {
                    attachmentHtml = `<a href="${message.file.url}" target="_blank" rel="noopener"><img src="${message.file.url}" alt="${escapeHtml(message.file.name)}"></a>`;
                } else if (AUDIO_EXT.includes(ext)) {
                    const durationLabel = message.file.duration_seconds ? `<div><small>Nota de voz · ${formatDuration(message.file.duration_seconds)}</small></div>` : '';
                    attachmentHtml = `<div><audio controls src="${message.file.url}"></audio></div>${durationLabel}`;
                } else {
                    attachmentHtml = `<a href="${message.file.url}" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-1"><i class="bi bi-file-earmark-arrow-down"></i> ${escapeHtml(message.file.name)}</a>`;
                }
                attachmentHtml = `<div class="chat-attachment mb-1">${attachmentHtml}</div>`;
            }

            let bodyHtml = '';
            if (message.is_deleted) {
                bodyHtml = `<div class="chat-bubble-body chat-bubble-deleted"><i class="bi bi-slash-circle"></i> Mensaje eliminado</div>`;
            } else if (message.body) {
                bodyHtml = `<div class="chat-bubble-body">${escapeHtml(message.body)}</div>`;
            }

            const actions = [];
            if (message.editable && !message.file) {
                actions.push('<button type="button" class="btn btn-sm btn-link p-0 chat-edit-btn" title="Editar"><i class="bi bi-pencil-fill"></i></button>');
            }
            if (message.deletable) {
                actions.push('<button type="button" class="btn btn-sm btn-link p-0 text-danger chat-delete-btn" title="Eliminar"><i class="bi bi-trash-fill"></i></button>');
            }
            const actionsHtml = actions.length ? `<div class="chat-bubble-actions">${actions.join('')}</div>` : '';

            const metaBits = [];
            if (message.is_edited) {
                metaBits.push('editado');
            }
            metaBits.push(message.created_at_label);
            const readIcon = message.is_own
                ? `<i class="bi ${message.read_at ? 'bi-check2-all text-primary' : 'bi-check2'}"></i>`
                : '';

            return `
                <div class="chat-bubble-row ${message.is_own ? 'own' : ''}" data-message-id="${message.id}">
                    <div class="chat-bubble">
                        ${actionsHtml}
                        ${attachmentHtml}
                        ${bodyHtml}
                        <div class="chat-bubble-meta">${metaBits.map(escapeHtml).join(' · ')} ${readIcon}</div>
                    </div>
                </div>`;
        };

        const renderMessage = (message) => {
            if (message.id > lastId) {
                lastId = message.id;
            }

            const html = bubbleHtml(message);
            const existing = messagesEl.querySelector(`[data-message-id="${message.id}"]`);

            if (existing) {
                existing.outerHTML = html;
                return;
            }

            messagesEl.insertAdjacentHTML('beforeend', html);
            scrollToBottom();
        };

        const historyLoadingEl = document.createElement('div');
        historyLoadingEl.className = 'chat-history-loading d-none';
        historyLoadingEl.textContent = 'Cargando mensajes anteriores…';
        messagesEl.appendChild(historyLoadingEl);

        const initialMessages = @json($initialMessages);
        initialMessages.forEach((message) => renderMessage(message));
        scrollToBottom();

        let oldestId = initialMessages.length ? initialMessages[0].id : null;
        let hasMoreHistory = panel.dataset.hasMore === '1';
        let loadingHistory = false;

        const maybeLoadHistory = async () => {
            if (!hasMoreHistory || loadingHistory || oldestId === null || messagesEl.scrollTop > 80) {
                return;
            }

            loadingHistory = true;
            historyLoadingEl.classList.remove('d-none');

            const previousScrollTop = messagesEl.scrollTop;
            const previousScrollHeight = messagesEl.scrollHeight;

            try {
                const response = await fetch(`${historyUrl}?before_id=${oldestId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const olderMessages = payload.messages || [];
                hasMoreHistory = !!payload.has_more;

                if (olderMessages.length) {
                    const fragment = document.createDocumentFragment();
                    const temp = document.createElement('div');
                    temp.innerHTML = olderMessages.map((message) => bubbleHtml(message)).join('');
                    while (temp.firstChild) {
                        fragment.appendChild(temp.firstChild);
                    }
                    messagesEl.insertBefore(fragment, historyLoadingEl.nextSibling);
                    oldestId = olderMessages[0].id;
                    messagesEl.scrollTop = previousScrollTop + (messagesEl.scrollHeight - previousScrollHeight);
                }
            } finally {
                loadingHistory = false;
                historyLoadingEl.classList.add('d-none');
            }
        };

        messagesEl.addEventListener('scroll', () => {
            maybeLoadHistory();
        });
        maybeLoadHistory();

        messagesEl.addEventListener('click', async (event) => {
            const editBtn = event.target.closest('.chat-edit-btn');
            const deleteBtn = event.target.closest('.chat-delete-btn');

            if (editBtn) {
                const row = editBtn.closest('.chat-bubble-row');
                const id = row.dataset.messageId;
                const bodyEl = row.querySelector('.chat-bubble-body');
                const currentText = bodyEl ? bodyEl.textContent.trim() : '';

                let newText;
                if (typeof Swal !== 'undefined') {
                    const result = await Swal.fire({
                        title: 'Editar mensaje',
                        input: 'textarea',
                        inputValue: currentText,
                        showCancelButton: true,
                        confirmButtonText: 'Guardar',
                        cancelButtonText: 'Cancelar',
                    });
                    if (!result.isConfirmed) {
                        return;
                    }
                    newText = result.value;
                } else {
                    newText = prompt('Editar mensaje:', currentText);
                    if (newText === null) {
                        return;
                    }
                }

                const response = await fetch(`${updateUrlBase}/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ body: newText }),
                });

                const payload = await response.json();
                if (!response.ok) {
                    showError(payload.message);
                    return;
                }
                renderMessage(payload.message);
            }

            if (deleteBtn) {
                const row = deleteBtn.closest('.chat-bubble-row');
                const id = row.dataset.messageId;

                let confirmed;
                if (typeof Swal !== 'undefined') {
                    const result = await Swal.fire({
                        title: '¿Eliminar mensaje?',
                        text: 'Esta acción no se puede deshacer.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#d9534f',
                    });
                    confirmed = result.isConfirmed;
                } else {
                    confirmed = confirm('¿Eliminar este mensaje?');
                }
                if (!confirmed) {
                    return;
                }

                const response = await fetch(`${updateUrlBase}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                const payload = await response.json();
                if (!response.ok) {
                    showError(payload.message);
                    return;
                }
                renderMessage(payload.message);
            }
        });

        attachmentInput.addEventListener('change', () => {
            const file = attachmentInput.files[0];
            pendingAttachment = file || null;
            pendingDuration = null;
            attachmentPreview.textContent = file ? `Adjunto: ${file.name}` : '';
        });

        sendForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (isSending) {
                return;
            }

            const body = bodyInput.value.trim();
            if (!body && !pendingAttachment) {
                return;
            }

            const formData = new FormData();
            formData.append('body', body);
            if (pendingAttachment) {
                formData.append('attachment', pendingAttachment);
            }
            if (pendingDuration) {
                formData.append('duration_seconds', pendingDuration);
            }

            isSending = true;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            try {
                const response = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const payload = await response.json();
                if (!response.ok) {
                    showError(payload.message);
                    return;
                }

                renderMessage(payload.message);
                bodyInput.value = '';
                pendingAttachment = null;
                pendingDuration = null;
                attachmentInput.value = '';
                attachmentPreview.textContent = '';
            } catch (error) {
                showError('No se pudo enviar el mensaje. Revisa tu conexión.');
            } finally {
                isSending = false;
                sendBtn.disabled = false;
                sendBtn.innerHTML = sendBtnDefaultHtml;
            }
        });

        bodyInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendForm.requestSubmit();
            }
        });

        const recordBtn = document.getElementById('chat-record-btn');
        const recordingIndicator = document.getElementById('chat-recording-indicator');
        const recordingTimeEl = document.getElementById('chat-recording-time');
        const recordStopBtn = document.getElementById('chat-record-stop');
        const recordCancelBtn = document.getElementById('chat-record-cancel');

        let mediaRecorder = null;
        let recordedChunks = [];
        let recordingStream = null;
        let recordingStartedAt = null;
        let recordingTimer = null;

        const stopStream = () => {
            if (recordingStream) {
                recordingStream.getTracks().forEach((track) => track.stop());
                recordingStream = null;
            }
        };

        const updateRecordingTime = () => {
            const elapsed = Math.floor((Date.now() - recordingStartedAt) / 1000);
            recordingTimeEl.textContent = formatDuration(elapsed).padStart(5, '0');
        };

        recordBtn?.addEventListener('click', async () => {
            if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
                showError('Tu navegador no soporta grabación de audio.');
                return;
            }

            try {
                recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (error) {
                showError('No se pudo acceder al micrófono.');
                return;
            }

            recordedChunks = [];
            mediaRecorder = new MediaRecorder(recordingStream);
            mediaRecorder.addEventListener('dataavailable', (event) => {
                if (event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            });

            mediaRecorder.start();
            recordingStartedAt = Date.now();
            recordingTimer = setInterval(updateRecordingTime, 500);
            recordingIndicator.classList.remove('d-none');
            recordBtn.classList.add('d-none');
        });

        const finishRecording = (send) => {
            if (!mediaRecorder) {
                return;
            }

            mediaRecorder.addEventListener('stop', () => {
                clearInterval(recordingTimer);
                recordingIndicator.classList.add('d-none');
                recordBtn.classList.remove('d-none');
                stopStream();

                if (!send || recordedChunks.length === 0) {
                    mediaRecorder = null;
                    return;
                }

                const duration = Math.floor((Date.now() - recordingStartedAt) / 1000);
                const mimeType = mediaRecorder.mimeType || 'audio/webm';
                const extension = mimeType.includes('ogg') ? 'ogg' : 'webm';
                const blob = new Blob(recordedChunks, { type: mimeType });
                pendingAttachment = new File([blob], `nota-de-voz.${extension}`, { type: mimeType });
                pendingDuration = duration;

                sendForm.requestSubmit();
                mediaRecorder = null;
            }, { once: true });

            mediaRecorder.stop();
        };

        recordStopBtn?.addEventListener('click', () => finishRecording(true));
        recordCancelBtn?.addEventListener('click', () => finishRecording(false));

        setInterval(async () => {
            try {
                const response = await fetch(`${pollUrl}?last_id=${lastId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    return;
                }
                const payload = await response.json();
                (payload.messages || []).forEach((message) => renderMessage(message));
            } catch (error) {
                // noop
            }
        }, 4000);
    });
</script>
