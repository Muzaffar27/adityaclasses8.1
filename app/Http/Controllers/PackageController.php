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
}
