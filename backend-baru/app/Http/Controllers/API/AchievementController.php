<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\StudentAchievement;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index()
    {
        $achievements = Achievement::all();
        return response()->json($achievements);
    }

    public function userAchievements(Request $request)
    {
        $userId = $request->user()->id;

        // Get all achievements
        $allAchievements = Achievement::all();

        // Get user's achievements
        $userAchievements = StudentAchievement::where('user_id', $userId)
            ->with('achievement')
            ->get();

        // Format the response
        $formattedAchievements = $allAchievements->map(function ($achievement) use ($userAchievements) {
            $userAchievement = $userAchievements->firstWhere('achievement_id', $achievement->id);

            return [
                'id' => $achievement->id,
                'title' => $achievement->title,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'achievement_type' => $achievement->achievement_type,
                'required_count' => $achievement->required_count,
                'points' => $achievement->points,
                'is_achieved' => $userAchievement ? true : false,
                'achieved_at' => $userAchievement ? $userAchievement->achieved_at : null,
            ];
        });

        return response()->json($formattedAchievements);
    }
}
