<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);
        return response()->json($notifications);
    }
    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();
        return response()->json(['message' => 'Marquée comme lue']);
    }
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Toutes les notifications lues']);
    }
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();
        return response()->json(['message' => 'Supprimée']);
    }
    public function subscribePush(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required','string'], 'public_key' => ['nullable','string'], 'auth_token' => ['nullable','string']]);
        \App\Models\PushSubscription::updateOrCreate(['endpoint' => $request->endpoint], ['user_id' => $request->user()->id, ...$request->only(['public_key','auth_token'])]);
        return response()->json(['message' => 'Notifications push activées']);
    }
    public function adminSend(Request $request): JsonResponse
    {
        $request->validate(['user_id' => ['required','exists:users,id'], 'title' => ['required','string'], 'body' => ['required','string']]);
        $user = \App\Models\User::findOrFail($request->user_id);
        $user->notify(new \App\Notifications\AdminMessageNotification($request->title, $request->body));
        return response()->json(['message' => 'Notification envoyée']);
    }
    public function broadcast(Request $request): JsonResponse
    {
        $request->validate(['title' => ['required','string'], 'body' => ['required','string'], 'role' => ['nullable','in:client,all']]);
        $users = \App\Models\User::when($request->role && $request->role !== 'all', fn($q) => $q->where('role', $request->role))->get();
        foreach ($users->chunk(100) as $chunk) {
            \Illuminate\Support\Facades\Notification::send($chunk, new \App\Notifications\AdminMessageNotification($request->title, $request->body));
        }
        return response()->json(['message' => "Notification envoyée à {$users->count()} utilisateurs"]);
    }
}
