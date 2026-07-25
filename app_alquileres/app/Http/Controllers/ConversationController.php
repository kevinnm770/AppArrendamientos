<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    private const INITIAL_MESSAGES_LIMIT = 20;
    private const HISTORY_MESSAGES_LIMIT = 10;

    public function index(Request $request)
    {
        $user = $request->user();

        $view = $user->isLessor() ? 'admin.messages.index' : 'tenant.messages.index';

        return view($view, [
            'threads' => $this->threadsFor($user),
            'activeAgreementId' => null,
        ]);
    }

    public function threads(Request $request)
    {
        $user = $request->user();

        $threads = $this->threadsFor($user)->map(function (array $thread) {
            return [
                'agreement_id' => $thread['agreement']->id,
                'other_party_name' => $thread['other_party']['name'] ?? 'Usuario',
                'other_party_username' => $thread['other_party']['username'] ?? null,
                'other_party_avatar' => $thread['other_party']['avatar_url']
                    ?? asset('storage/profiles_images/UserProfile_default.png'),
                'property_name' => $thread['agreement']->property->name ?? 'Propiedad',
                'unread_count' => $thread['unread_count'],
                'last_message_preview' => $thread['last_message_preview'],
                'last_message_at_label' => optional($thread['last_message_at'])->diffForHumans(null, true),
            ];
        })->values();

        return response()->json(['threads' => $threads]);
    }

    public function show(int $agreementId, Request $request)
    {
        $user = $request->user();
        $agreement = $this->getOwnedAgreement($agreementId, $user);

        $conversation = Conversation::firstOrCreate(
            ['agreement_id' => $agreement->id],
            ['created_by_user_id' => $user->id]
        );

        $conversation->messages()
            ->where('receiver_user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $totalMessages = Message::where('conversation_id', $conversation->id)->count();

        $messages = Message::where('conversation_id', $conversation->id)
            ->with('file')
            ->orderByDesc('id')
            ->limit(self::INITIAL_MESSAGES_LIMIT)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message) => $message->toChatArray($user));

        $view = $user->isLessor() ? 'admin.messages.show' : 'tenant.messages.show';

        return view($view, [
            'agreement' => $agreement,
            'conversation' => $conversation,
            'messages' => $messages,
            'hasMoreMessages' => $totalMessages > self::INITIAL_MESSAGES_LIMIT,
            'otherParty' => $this->otherPartyFor($agreement, $user),
            'threads' => $this->threadsFor($user),
            'activeAgreementId' => $agreement->id,
        ]);
    }

    public function history(int $agreementId, Request $request)
    {
        $user = $request->user();
        $agreement = $this->getOwnedAgreement($agreementId, $user);

        $conversation = Conversation::where('agreement_id', $agreement->id)->first();

        if (!$conversation) {
            return response()->json(['messages' => [], 'has_more' => false]);
        }

        $beforeId = (int) $request->query('before_id', 0);

        $query = Message::where('conversation_id', $conversation->id);

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->with('file')
            ->orderByDesc('id')
            ->limit(self::HISTORY_MESSAGES_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $hasMore = $messages->isNotEmpty()
            && Message::where('conversation_id', $conversation->id)
                ->where('id', '<', $messages->first()->id)
                ->exists();

        return response()->json([
            'messages' => $messages->map(fn (Message $message) => $message->toChatArray($user)),
            'has_more' => $hasMore,
        ]);
    }

    public function poll(int $agreementId, Request $request)
    {
        $user = $request->user();
        $agreement = $this->getOwnedAgreement($agreementId, $user);

        $conversation = Conversation::where('agreement_id', $agreement->id)->first();

        if (!$conversation) {
            return response()->json(['messages' => []]);
        }

        $conversation->messages()
            ->where('receiver_user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $lastId = (int) $request->query('last_id', 0);

        $messages = $conversation->messages()
            ->with('file')
            ->where('id', '>', $lastId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message) => $message->toChatArray($user));

        return response()->json(['messages' => $messages]);
    }

    private function threadsFor(User $user)
    {
        $agreements = $this->agreementsQuery($user)
            ->with(['property', 'lessor.user', 'roomer.user'])
            ->orderByDesc('start_at')
            ->get();

        $conversations = Conversation::whereIn('agreement_id', $agreements->pluck('id'))
            ->withCount(['messages as unread_count' => function ($query) use ($user) {
                $query->where('receiver_user_id', $user->id)
                    ->whereNull('read_at')
                    ->whereNull('deleted_at');
            }])
            ->with('latestMessage')
            ->get()
            ->keyBy('agreement_id');

        return $agreements->map(function (Agreement $agreement) use ($conversations, $user) {
            $conversation = $conversations->get($agreement->id);
            $lastMessage = $conversation?->latestMessage;

            return [
                'agreement' => $agreement,
                'other_party' => $this->otherPartyFor($agreement, $user),
                'unread_count' => $conversation->unread_count ?? 0,
                'last_message_preview' => $this->previewFor($lastMessage),
                'last_message_at' => $lastMessage?->created_at,
            ];
        })->sortByDesc(fn (array $thread) => $thread['last_message_at'] ?? $thread['agreement']->created_at)
          ->values();
    }

    /**
     * @return array{name: string, username: string, avatar_url: string}|null
     */
    private function otherPartyFor(Agreement $agreement, User $user): ?array
    {
        $counterpart = $agreement->lessor->user_id === $user->id ? $agreement->roomer : $agreement->lessor;

        if (!$counterpart || !$counterpart->user) {
            return null;
        }

        return [
            'name' => $counterpart->legal_name,
            'username' => $counterpart->user->name,
            'avatar_url' => $counterpart->user->avatar_url,
        ];
    }

    private function previewFor(?Message $message): string
    {
        if (!$message) {
            return 'Sin mensajes todavía';
        }

        if ($message->isDeleted()) {
            return 'Mensaje eliminado';
        }

        if ($message->file_id) {
            return $message->body !== '' ? '📎 '.Str::limit($message->body, 50) : '📎 Adjunto';
        }

        return Str::limit($message->body, 60);
    }

    private function agreementsQuery(User $user): Builder
    {
        if ($user->isLessor()) {
            return Agreement::where('lessor_id', $user->lessor->id);
        }

        if ($user->isRoomer()) {
            return Agreement::where('roomer_id', $user->roomer->id);
        }

        abort(403);
    }

    private function getOwnedAgreement(int $agreementId, User $user): Agreement
    {
        $query = Agreement::with(['property', 'lessor.user', 'roomer.user']);

        if ($user->isLessor()) {
            $query->where('lessor_id', $user->lessor->id);
        } elseif ($user->isRoomer()) {
            $query->where('roomer_id', $user->roomer->id);
        } else {
            abort(403);
        }

        return $query->findOrFail($agreementId);
    }
}
