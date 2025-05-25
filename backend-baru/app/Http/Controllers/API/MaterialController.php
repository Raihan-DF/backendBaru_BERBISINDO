<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = Material::with('creator');

        // Filter by published status for students
        if ($request->user()->isStudent()) {
            $query->where('is_published', true);
        }

        // Filter by creator for teachers
        if ($request->user()->isTeacher() && $request->has('my_materials')) {
            $query->where('created_by', $request->user()->id);
        }

        // Filter by difficulty level
        if ($request->has('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $materials = $query->latest()->paginate(10);

        return response()->json($materials);
    }

    public function store(Request $request)
    {
        // Check if user is a teacher
        if (!$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048',
            'difficulty_level' => 'required|integer|min:1|max:5',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $material = Material::create([
            'title' => $request->title,
            'description' => $request->description,
            'thumbnail' => $thumbnailPath,
            'created_by' => $request->user()->id,
            'difficulty_level' => $request->difficulty_level,
            'is_published' => $request->is_published ?? false,
        ]);

        return response()->json($material, 201);
    }

    public function show(Request $request, $id)
    {
        $material = Material::with(['creator', 'videos'])->findOrFail($id);

        // Check if material is published or user is the creator
        if (!$material->is_published && $request->user()->id !== $material->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($material);
    }

    public function update(Request $request, $id)
    {
        $material = Material::findOrFail($id);

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $material->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048',
            'difficulty_level' => 'required|integer|min:1|max:5',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if exists
            if ($material->thumbnail) {
                Storage::disk('public')->delete($material->thumbnail);
            }
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
            $material->thumbnail = $thumbnailPath;
        }

        $material->title = $request->title;
        $material->description = $request->description;
        $material->difficulty_level = $request->difficulty_level;
        $material->is_published = $request->is_published ?? $material->is_published;
        $material->save();

        return response()->json($material);
    }

    public function destroy(Request $request, $id)
    {
        $material = Material::findOrFail($id);

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $material->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete thumbnail if exists
        if ($material->thumbnail) {
            Storage::disk('public')->delete($material->thumbnail);
        }

        $material->delete();

        return response()->json(['message' => 'Material deleted successfully']);
    }
}
