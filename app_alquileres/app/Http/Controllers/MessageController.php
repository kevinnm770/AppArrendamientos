<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Conversation;
use App\Models\FileMessage;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageAttachmentStorageService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    private const ALLOWED_ATTACHMENT_MIMES = 'pdf,png,jpg,jpeg,webp,gif,mp3,mpga,wav,m4a,ogg,webm';

    public function __construct(
        private readonly MessageAttachmentStorageService $attachmentStorage,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function store(int $agreementId, Request $request)
    {
        $user = $request->user();
        $agreement = $this->getOwnedAgreement($agreementId, $user);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:'.self::ALLOWED_ATTACHMENT_MIMES],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));

        if ($body === '' && !$request->hasFile('attachment')) {
            return response()->json(['message' => 'Escribe un mensaje o adjunta un archivo.'], 422);
        }

        $receiverId = $agreement->lessor->user_id === $user->id
            ? $agreement->roomer->user_id
            : $agreement->lessor->user_id;

        $message = DB::transaction(function () use ($agreement, $user, $receiverId, $body, $request, $validated) {
            $conversation = Conversation::firstOrCreate(
                ['agreement_id' => $agreement->id],
                ['created_by_user_id' => $user->id]
            );

            $fileId = null;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $path = $this->attachmentStorage->store($file, $conversation->id);

                $fileMessage = FileMessage::create([
                    'name_file' => $file->getClientOriginalName(),
                    'type' => strtolower($file->getClientOriginalExtension() ?: $file->extension()),
                    'weigth' => round($file->getSize() / 1024, 2),
                    'bucket' => $path,
                    'duration_seconds' => $validated['duration_seconds'] ?? null,
                ]);

                $fileId = $fileMessage->id;
            }

            return Message::create([
                'conversation_id' => $conversation->id,
                'sender_user_id' => $user->id,
                'receiver_user_id' => $receiverId,
                'type' => 'text',
                'body' => $body,
                'file_id' => $fileId,
            ]);
        });

        $message->load('file');

        $this->notifyNewMessage($agreement, $user, $receiverId, $body, $request->hasFile('attachment'));

        return response()->json(['message' => $message->toChatArray($user)], 201);
    }

    private function notifyNewMessage(Agreement $agreement, User $sender, int $receiverId, string $body, bool $hasAttachment): void
    {
        $senderIsLessor = $agreement->lessor->user_id === $sender->id;
        $senderName = $senderIsLessor ? $agreement->lessor->legal_name : $agreement->roomer->legal_name;

        $preview = $body !== '' ? $body : '📎 Adjunto';
        $title = Str::limit("{$senderName}: {$preview}", 100);

        $link = $senderIsLessor
            ? route('tenant.messages.show', $agreement->id)
            : route('admin.messages.show', $agreement->id);

        $this->notificationService->create(
            notifyUserId: $receiverId,
            title: $title,
            priority: 'medium',
            body: '',
            link: $link,
        );
    }

    public function update(int $agreementId, int $messageId, Request $request)
    {
        $user = $request->user();
        $message = $this->ownedMessage($agreementId, $messageId, $user);

        if ($message->sender_user_id !== $user->id) {
            abort(403);
        }

        if (!$message->isEditableBy($user)) {
            return response()->json(['message' => 'Este mensaje ya no se puede editar (pasaron los 5 minutos).'], 422);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'remove_attachment' => ['nullable', 'boolean'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $removeAttachment = $request->boolean('remove_attachment');

        if ($body === '' && ($removeAttachment || !$message->file_id)) {
            return response()->json(['message' => 'El mensaje no puede quedar vacío.'], 422);
        }

        if ($removeAttachment && $message->file) {
            $this->attachmentStorage->delete($message->file->bucket);
            $message->file->delete();
        }

        $message->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        $message->refresh()->load('file');

        return response()->json(['message' => $message->toChatArray($user)]);
    }

    public function destroy(int $agreementId, int $messageId, Request $request)
    {
        $user = $request->user();
        $message = $this->ownedMessage($agreementId, $messageId, $user);

        if ($message->sender_user_id !== $user->id) {
            abort(403);
        }

        if (!$message->isDeletableBy($user)) {
            return response()->json(['message' => 'Este mensaje ya no se puede eliminar (pasaron los 5 minutos).'], 422);
        }

        if ($message->file) {
            $this->attachmentStorage->delete($message->file->bucket);
            $message->file->delete();
        }

        $message->update(['deleted_at' => now()]);

        return response()->json(['message' => $message->fresh()->toChatArray($user)]);
    }

    private function ownedMessage(int $agreementId, int $messageId, User $user): Message
    {
        $agreement = $this->getOwnedAgreement($agreementId, $user);

        $conversation = Conversation::where('agreement_id', $agreement->id)->firstOrFail();

        return $conversation->messages()->with('file')->where('id', $messageId)->firstOrFail();
    }

    private function getOwnedAgreement(int $agreementId, User $user): Agreement
    {
        $query = Agreement::with(['lessor.user', 'roomer.user']);

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
