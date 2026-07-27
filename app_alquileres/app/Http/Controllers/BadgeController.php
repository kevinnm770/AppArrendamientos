<?php

namespace App\Http\Controllers;

use App\Models\Ademdum;
use App\Models\Agreement;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function counts(Request $request)
    {
        $user = $request->user();

        $unreadMessages = Message::where('receiver_user_id', $user->id)
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->count();

        $unreadNotifications = Notification::where('notify_id', $user->id)
            ->where('status', 'sent')
            ->count();

        $pendingAgreements = 0;

        if ($user->isRoomer() && $user->roomer) {
            $roomerId = $user->roomer->id;

            $pendingAgreements = Agreement::where('roomer_id', $roomerId)
                ->where('status', 'sent')
                ->count();

            $pendingAgreements += Ademdum::whereHas(
                'agreement',
                fn ($query) => $query->where('roomer_id', $roomerId)
            )->where('status', 'sent')->count();
        }

        return response()->json([
            'unread_messages' => $unreadMessages,
            'unread_notifications' => $unreadNotifications,
            'pending_agreements' => $pendingAgreements,
        ]);
    }
}
