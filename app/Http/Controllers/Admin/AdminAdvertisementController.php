<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminAdvertisementController extends Controller
{
    public function index(): JsonResponse { return response()->json(Advertisement::latest()->paginate(20)); }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title'=>['required','string'],'client_name'=>['nullable','string'],'link'=>['nullable','url'],'position'=>['required','string'],'page'=>['nullable','string'],'is_flashing'=>['nullable','boolean'],'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date'],'slide_count'=>['nullable','integer']]);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('ads','public');
        return response()->json(Advertisement::create($data), 201);
    }
    public function show(int $id): JsonResponse { return response()->json(Advertisement::findOrFail($id)); }
    public function update(Request $request, int $id): JsonResponse { Advertisement::findOrFail($id)->update($request->except('views_count','clicks_count')); return response()->json(['message'=>'Pub mise à jour']); }
    public function destroy(int $id): JsonResponse { Advertisement::findOrFail($id)->delete(); return response()->json(['message'=>'Pub supprimée']); }
    public function toggle(int $id): JsonResponse { $a = Advertisement::findOrFail($id); $a->update(['is_active'=>!$a->is_active]); return response()->json(['is_active'=>$a->is_active]); }
    public function stats(): JsonResponse { return response()->json(Advertisement::select('id','title','views_count','clicks_count','is_active')->get()); }
}
