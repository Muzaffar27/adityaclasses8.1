<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update']);
    }

    private function validatedPackage(Request $request): array
    {
        abort_unless(in_array($request->user()->role, ['tutor', 'admin'], true), 403);

        return $request->validate([
            'name' => 'required|string',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'base_price' => 'required|numeric|min:0',
            'three_month_price' => 'required|numeric|min:0',
            'six_month_price' => 'required|numeric|min:0',
            'twelve_month_price' => 'required|numeric|min:0',
            'three_month_discount_percent' => 'nullable|numeric|min:0|max:100',
            'six_month_discount_percent' => 'nullable|numeric|min:0|max:100',
            'twelve_month_discount_percent' => 'nullable|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.grade_id' => 'nullable|exists:grades,id',
            'items.*.subjects' => 'required|array|min:1',
            'items.*.subjects.*' => 'required|exists:subjects,id',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedPackage($request);
        return $this->savePackage(new Package, $data);
    }

    public function update(Request $request, $id)
    {
        $data = $this->validatedPackage($request);
        return $this->savePackage(Package::findOrFail($id), $data);
    }

    private function savePackage(Package $package, array $data)
    {
        return DB::transaction(function () use ($package, $data) {
            $items = $data['items'];
            unset($data['items']);
            foreach (['three_month', 'six_month', 'twelve_month'] as $term) {
                $data[$term.'_discount_percent'] = $data[$term.'_discount_percent'] ?? 0;
            }
            $data['total_price'] = $data['base_price'] + array_sum(array_column($items, 'price'));
            $package->fill($data)->save();
            $package->items()->delete();
            foreach ($items as $item) {
                foreach ($item['subjects'] as $subjectId) {
                    $package->items()->create([
                        'subject_id' => $subjectId,
                        'grade_id' => $item['grade_id'] ?? $data['grade_id'],
                        'price' => $item['price'] / count($item['subjects']),
                        'type' => 'subject',
                    ]);
                }
            }
            return response()->json($package->load('items'));
        });
    }

    public function index(Request $request)
    {
        $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'nullable|exists:subjects,id',
        ]);

        $packages = Package::with(['subject', 'items.subject'])
            ->where('grade_id', $request->grade_id)
            ->when($request->subject_id, function ($q) use ($request) {
                $q->where('subject_id', $request->subject_id);
            })
            ->get();

        return response()->json($packages);
    }

    public function show($id)
    {
        return Package::with('items')->findOrFail($id);
    }

    public function adminIndex()
    {
        return Package::with(['grade', 'subject', 'items.subject', 'items'])
            ->orderBy('id', 'desc')
            ->get();
    }

    public function destroy($id)
    {
        $package = Package::findOrFail($id);
        $package->delete();

        return response()->json([
            'message' => 'Package deleted successfully',
        ]);
    }
}
