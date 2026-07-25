@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Mensajes</h3>
                <p class="text-subtitle text-muted">Contrato #{{ $agreement->id }} · {{ $agreement->property->name ?? 'Propiedad' }}</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{route('admin.messages.index')}}">Mensajes</a></li>
                        <li class="breadcrumb-item active">Contrato #{{ $agreement->id }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @include('messages.partials.styles')

    <div class="card chat-app">
        <div class="row g-0 h-100">
            <div class="col-12 col-md-4 border-end chat-thread-col d-none d-md-flex flex-column">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0">Conversaciones</h5>
                </div>
                <div class="chat-thread-list" id="chat-thread-list"
                    data-threads-url="{{ route('admin.messages.threads') }}"
                    data-show-url-base="{{ url('admin/messages') }}"
                    data-active-agreement-id="{{ $activeAgreementId ?? '' }}">
                    @forelse ($threads as $thread)
                        <a href="{{ route('admin.messages.show', $thread['agreement']->id) }}"
                            class="chat-thread-item d-flex align-items-center gap-2 text-decoration-none text-body p-3 border-bottom {{ $activeAgreementId === $thread['agreement']->id ? 'active' : '' }}"
                            data-agreement-id="{{ $thread['agreement']->id }}">
                            <img src="{{ $thread['other_party']['avatar_url'] ?? asset('storage/profiles_images/UserProfile_default.png') }}" class="chat-thread-avatar" alt="">
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-start">
                                    <strong>{{ $thread['other_party']['name'] ?? 'Usuario' }}</strong>
                                    @if ($thread['last_message_at'])
                                        <small class="text-muted">{{ $thread['last_message_at']->diffForHumans(null, true) }}</small>
                                    @endif
                                </div>
                                @if (!empty($thread['other_party']['username']))
                                    <div class="chat-username">{{ '@'.$thread['other_party']['username'] }}</div>
                                @endif
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted text-truncate d-block" style="max-width: 85%;">
                                        {{ $thread['agreement']->property->name ?? 'Propiedad' }} · {{ $thread['last_message_preview'] }}
                                    </small>
                                    @if ($thread['unread_count'] > 0)
                                        <span class="badge bg-primary rounded-pill">{{ $thread['unread_count'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <p class="text-muted p-3 mb-0">No tienes contratos disponibles para chatear.</p>
                    @endforelse
                </div>
            </div>

            <div class="col-12 col-md-8 chat-panel" id="chat-panel"
                data-poll-url="{{ route('admin.messages.poll', $agreement->id) }}"
                data-send-url="{{ route('admin.messages.store', $agreement->id) }}"
                data-history-url="{{ route('admin.messages.history', $agreement->id) }}"
                data-update-url-base="{{ url('admin/messages/'.$agreement->id) }}"
                data-has-more="{{ $hasMoreMessages ? '1' : '0' }}">
                <div class="p-3 border-bottom d-flex align-items-center gap-2">
                    <a href="{{ route('admin.messages.index') }}" class="chat-back-btn d-md-none flex-shrink-0" title="Volver a conversaciones">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <img src="{{ $otherParty['avatar_url'] ?? asset('storage/profiles_images/UserProfile_default.png') }}" class="chat-header-avatar" alt="">
                    <div>
                        <strong>{{ $otherParty['name'] ?? 'Usuario' }}</strong>
                        @if (!empty($otherParty['username']))
                            <div class="chat-username">{{ '@'.$otherParty['username'] }}</div>
                        @endif
                        <div class="text-muted" style="font-size:.8rem;">
                            {{ $agreement->property->name ?? 'Propiedad' }}
                        </div>
                    </div>
                </div>

                <div class="chat-messages" id="chat-messages"></div>

                <div class="chat-input-area">
                    <form id="chat-send-form" class="d-flex align-items-end gap-2">
                        <label class="btn btn-light-secondary mb-0" title="Adjuntar archivo">
                            <i class="bi bi-paperclip"></i>
                            <input type="file" id="chat-attachment-input" class="d-none"
                                accept=".pdf,.png,.jpg,.jpeg,.webp,.gif,.mp3,.wav,.m4a,.ogg,.webm">
                        </label>
                        <button type="button" id="chat-record-btn" class="btn btn-light-secondary mb-0" title="Grabar audio">
                            <i class="bi bi-mic-fill"></i>
                        </button>
                        <textarea id="chat-body-input" class="form-control" rows="1" placeholder="Escribe un mensaje..."></textarea>
                        <button type="submit" class="btn btn-primary mb-0"><i class="bi bi-send-fill"></i></button>
                    </form>
                    <div id="chat-attachment-preview" class="mt-2 small text-muted"></div>
                    <div id="chat-recording-indicator" class="mt-2 small text-danger d-none">
                        <i class="bi bi-record-circle-fill"></i> Grabando… <span id="chat-recording-time">00:00</span>
                        <button type="button" id="chat-record-stop" class="btn btn-sm btn-outline-danger ms-2">Detener y enviar</button>
                        <button type="button" id="chat-record-cancel" class="btn btn-sm btn-outline-secondary ms-1">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('messages.partials.viewport-fit')
    @include('messages.partials.thread-list-refresh')
    @include('messages.partials.chat-script', ['initialMessages' => $messages])
@endsection
