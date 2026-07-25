<script>
    window.addEventListener('load', () => {
        const list = document.getElementById('chat-thread-list');
        if (!list) {
            return;
        }

        const threadsUrl = list.dataset.threadsUrl;
        const showUrlBase = list.dataset.showUrlBase;
        const activeAgreementId = list.dataset.activeAgreementId;

        const escapeHtml = (str) => {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        };

        const threadItemHtml = (thread) => {
            const isActive = activeAgreementId && String(thread.agreement_id) === String(activeAgreementId);
            const timeLabel = thread.last_message_at_label
                ? `<small class="text-muted">${escapeHtml(thread.last_message_at_label)}</small>`
                : '';
            const badge = thread.unread_count > 0
                ? `<span class="badge bg-primary rounded-pill">${thread.unread_count}</span>`
                : '';
            const usernameLine = thread.other_party_username
                ? `<div class="chat-username">@${escapeHtml(thread.other_party_username)}</div>`
                : '';

            return `
                <a href="${showUrlBase}/${thread.agreement_id}"
                    class="chat-thread-item d-flex align-items-center gap-2 text-decoration-none text-body p-3 border-bottom ${isActive ? 'active' : ''}"
                    data-agreement-id="${thread.agreement_id}">
                    <img src="${thread.other_party_avatar}" class="chat-thread-avatar" alt="">
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-start">
                            <strong>${escapeHtml(thread.other_party_name)}</strong>
                            ${timeLabel}
                        </div>
                        ${usernameLine}
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted text-truncate d-block" style="max-width: 85%;">${escapeHtml(thread.property_name)} · ${escapeHtml(thread.last_message_preview)}</small>
                            ${badge}
                        </div>
                    </div>
                </a>`;
        };

        const refreshThreads = async () => {
            try {
                const response = await fetch(threadsUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const threads = payload.threads || [];
                const previousScrollTop = list.scrollTop;

                if (threads.length === 0) {
                    list.innerHTML = '<p class="text-muted p-3 mb-0">No tienes contratos disponibles para chatear.</p>';
                    return;
                }

                list.innerHTML = threads.map((thread) => threadItemHtml(thread)).join('');
                list.scrollTop = previousScrollTop;
            } catch (error) {
                // noop
            }
        };

        setInterval(refreshThreads, 30000);
    });
</script>
