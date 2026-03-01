<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::query()
            ->where('notify_id', $user->id)
            ->where('status', 'sent')
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->latest('created_at')
            ->get();

        if ($user->isLessor()) {
            return view('admin.notifications.index', compact('notifications'));
        }
        if ($user->isLessor()) {
            return view('tenant.notifications.index', compact('notifications'));
        }

        return response()->json($notifications);
    }

    public function view(int $notificationId, Request $request)
    {
        $user = $request->user();

        $notification = Notification::query()
            ->where('id', $notificationId)
            ->where('notify_id', $user->id)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->firstOrFail();

        if ($user->isLessor()) {
            return view('admin.notifications.view', compact('notification'));
        }

        if ($user->isRoomer()) {
            return view('tenant.notifications.view', compact('notification'));
        }

        abort(403);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Notification $notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notification $notification)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notification $notification)
    {
        //
    }
}
