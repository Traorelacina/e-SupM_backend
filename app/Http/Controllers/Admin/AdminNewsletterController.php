<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class AdminNewsletterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(\App\Models\NewsletterSubscription::latest()->paginate(20));
    }
    public function send(Request $request): JsonResponse
    {
        $request->validate(['subject'=>['required','string'],'body'=>['required','string'],'segment'=>['nullable','in:all,clients,active']]);
        $users = User::when($request->segment === 'clients', fn($q) => $q->where('role','client'))
            ->when($request->segment === 'active', fn($q) => $q->where('status','active')->where('role','client'))
            ->whereNotNull('email')->get();
        foreach ($users->chunk(100) as $chunk) {
            \Illuminate\Support\Facades\Notification::send($chunk, new \App\Notifications\NewsletterNotification($request->subject, $request->body));
        }
        return response()->json(['message' => "Newsletter envoyée à {$users->count()} personnes"]);
    }
}
