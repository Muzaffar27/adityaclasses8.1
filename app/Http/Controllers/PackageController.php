<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PackageItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            //package
            'name' => 'required|string',
            'description' => 'nullable|string',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'base_price' => 'required|numeric',
            'three_month_price' => 'nullable|numeric|min:0',
            'six_month_price' => 'nullable|numeric|min:0',
            'nine_month_price' => 'nullable|numeric|min:0',

            //Package Item
            'items' => 'required|array',
            'items.*.price' => 'required|numeric',
            'items.*.type' => 'required|string',
            'items.*.subject_id' => 'nullable|exists:subjects,id',
            'items.*.grade_id' => 'nullable|exists:grades,id',
            'items.*.subjects' => 'array',
        ]);

        return DB::transaction(function () use ($data) {

            // 🧠 Create package
            $package = Package::create([
                'name' => $data['name'],
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'base_price' => $data['base_price'],
                'three_month_price' => $data['three_month_price'] ?? 0,
                'six_month_price' => $data['six_month_price'] ?? 0,
                'nine_month_price' => $data['nine_month_price'] ?? 0,
                'total_price' => 0,
            ]);

            $total = (float) $data['base_price'];
            $rows = [];

            foreach ($data['items'] as $item) {

                $subjects = $item['subjects'] ?? [];
                $subjectCount = max(count($subjects), 1);

                $pricePerSubject = $item['price'] / $subjectCount;

                foreach ($subjects as $subjectId) {

                    $rows[] = [
                        'package_id' => $package->id,
                        'price' => $pricePerSubject,
                        'type' => 'subject',
                        'subject_id' => $subjectId,
                        'grade_id' => $item['grade_id'] ?? $data['grade_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $total += $item['price'];
            }

            // ⚡ SINGLE BULK INSERT (MAJOR SPEED BOOST)
            if (!empty($rows)) {
                PackageItem::insert($rows);
            }

            $package->update(['total_price' => $total]);

            return response()->json(
                $package->load('items')
            );
        });
    }

    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);

        DB::transaction(function () use ($request, $package) {

            $package->update([
                'name' => $request->name,
                'grade_id' => $request->grade_id,
                'subject_id' => $request->subject_id,
                'base_price' => $request->base_price,
                'three_month_price' => $request->three_month_price ?? 0,
                'six_month_price' => $request->six_month_price ?? 0,
                'nine_month_price' => $request->nine_month_price ?? 0,
            ]);

            PackageItem::where('package_id', $package->id)->delete();

            $total = (float) $request->base_price; // 👈 start with base

            foreach ($request->items as $item) {
                foreach ($item['subjects'] as $subjectId) {

                    PackageItem::create([
                        'package_id' => $package->id,
                        'price' => $item['price'],
                        'grade_id' => $item['grade_id'],
                        'subject_id' => $subjectId,
                    ]);
                }

                $total += (float) $item['price']; // 👈 add item price once per item
            }

            // ✅ THIS WAS MISSING
            $package->update([
                'total_price' => $total
            ]);
        });

        return response()->json($package->load('items'));
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
