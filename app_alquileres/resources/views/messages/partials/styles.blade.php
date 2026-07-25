<style>
    /* Actual height is set from JS (see viewport-fit.blade.php), which measures the card's
       real offset and fills the rest of the viewport - this also sidesteps Bootstrap's own
       dark-theme rule for .card (html[data-bs-theme=dark] .card), which is more specific than
       a single class selector and would otherwise silently win and collapse the card back to
       height:auto in dark mode. This min-height is just the pre-JS / no-JS fallback. */
    .chat-app { min-height: 420px; }
    .chat-thread-col { height: 100%; }
    .chat-thread-list { overflow-y: auto; height: calc(100% - 57px); }
    .chat-thread-item.active { background-color: rgba(var(--bs-primary-rgb), .12); }
    .chat-thread-item:hover { background-color: rgba(var(--bs-body-color-rgb, 0, 0, 0), .05); }
    .chat-panel { display: flex; flex-direction: column; height: 100%; }
    .chat-messages { flex: 1 1 auto; overflow-y: auto; padding: 1rem; background: #f5f6fa; position: relative; }
    html[data-bs-theme=dark] .chat-messages { background: #0f0f1a; }

    .chat-history-loading { text-align: center; padding: .5rem 0 .75rem; font-size: .75rem; color: #6c757d; }

    .chat-bubble-row { display: flex; margin-bottom: .75rem; }
    .chat-bubble-row.own { justify-content: flex-end; }
    .chat-bubble { max-width: 70%; padding: .5rem .75rem; border-radius: .9rem; position: relative; }

    .chat-bubble-row.own .chat-bubble { background: #d9fdd3; color: #111b21; border-bottom-right-radius: .2rem; }
    .chat-bubble-row:not(.own) .chat-bubble { background: #fff; color: #111b21; border-bottom-left-radius: .2rem; box-shadow: 0 1px 1px rgba(0, 0, 0, .08); }
    html[data-bs-theme=dark] .chat-bubble-row.own .chat-bubble { background: #025c4b; color: #e9edef; }
    html[data-bs-theme=dark] .chat-bubble-row:not(.own) .chat-bubble { background: #202c33; color: #e9edef; }

    .chat-bubble a { color: inherit; text-decoration: underline; }
    .chat-bubble-body { white-space: pre-wrap; word-break: break-word; margin-bottom: .15rem; }
    .chat-bubble-meta { font-size: .7rem; color: rgba(17, 27, 33, .6); display: flex; gap: .35rem; align-items: center; justify-content: flex-end; }
    html[data-bs-theme=dark] .chat-bubble-meta { color: rgba(233, 237, 239, .65); }

    /* Action buttons live fully inside .chat-bubble's own box (not floated above it in the
       row's margin gap) so the pointer never leaves the hoverable area while moving from the
       message text up to the buttons - that gap was causing the buttons to vanish on hover. */
    .chat-bubble-actions {
        display: none;
        gap: .25rem;
        position: absolute;
        top: .2rem;
        right: .4rem;
        background: rgba(255, 255, 255, .85);
        border-radius: .5rem;
        padding: 0 .2rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
    }
    html[data-bs-theme=dark] .chat-bubble-actions { background: rgba(30, 30, 45, .9); }
    .chat-bubble-row:hover .chat-bubble-actions { display: flex; }
    .chat-bubble-actions .btn { color: #111b21; }
    html[data-bs-theme=dark] .chat-bubble-actions .btn { color: #e9edef; }
    .chat-bubble-actions .btn.text-danger { color: #dc3545 !important; }

    .chat-bubble-deleted { font-style: italic; }
    .chat-attachment img { max-width: 220px; border-radius: .5rem; display: block; margin-bottom: .25rem; }
    .chat-attachment audio { max-width: 240px; }
    .chat-input-area { border-top: 1px solid #e5e7eb; padding: .75rem; }
    html[data-bs-theme=dark] .chat-input-area { border-top-color: rgba(255, 255, 255, .08); }

    .chat-send-btn { min-width: 2.6rem; }

    .chat-back-btn {
        width: 2.25rem;
        height: 2.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(var(--bs-body-color-rgb, 0, 0, 0), .08);
        color: inherit;
        font-size: 1.1rem;
    }
    .chat-back-btn:hover { background: rgba(var(--bs-body-color-rgb, 0, 0, 0), .16); color: inherit; }

    .chat-thread-avatar {
        width: 2.6rem;
        height: 2.6rem;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .chat-header-avatar {
        width: 2.4rem;
        height: 2.4rem;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .chat-username { font-size: .72rem; color: rgba(var(--bs-body-color-rgb, 0, 0, 0), .65); line-height: 1.3; }
</style>
