@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Mensajes</h3>
                <p class="text-subtitle text-muted">Conversaciones con tus arrendadores, por contrato.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{route('tenant.index')}}">Tenant</a></li>
                        <li class="breadcrumb-item active">Mensajes</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @include('messages.partials.styles')

    <div class="card chat-app">
        <div class="row g-0 h-100">
            <div class="col-12 chat-thread-col d-flex flex-column">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0">Conversaciones</h5>
                </div>
                <div class="chat-thread-list" id="chat-thread-list"
                    data-threads-url="{{ route('tenant.messages.threads') }}"
                    data-show-url-base="{{ url('tenant/messages') }}"
                    data-active-agreement-id="{{ $activeAgreementId ?? '' }}">
                    @forelse ($threads as $thread)
                        <a href="{{ route('tenant.messages.show', $thread['agreement']->id) }}"
                            class="chat-thread-item d-flex align-items-center gap-2 text-decoration-none text-body p-3 border-bottom"
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
        </div>
    </div>

    @include('messages.partials.viewport-fit')
    @include('messages.partials.thread-list-refresh')
@endsection
