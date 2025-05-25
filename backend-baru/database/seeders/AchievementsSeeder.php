<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementsSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'title' => 'Pemula',
                'description' => 'Menyelesaikan 1 materi pembelajaran',
                'icon' => 'award',
                'achievement_type' => 'materials_completed',
                'required_count' => 1,
                'points' => 10,
            ],
            [
                'title' => 'Pembelajar',
                'description' => 'Menyelesaikan 5 materi pembelajaran',
                'icon' => 'book',
                'achievement_type' => 'materials_completed',
                'required_count' => 5,
                'points' => 50,
            ],
            [
                'title' => 'Mahir',
                'description' => 'Menyelesaikan 10 materi pembelajaran',
                'icon' => 'graduation-cap',
                'achievement_type' => 'materials_completed',
                'required_count' => 10,
                'points' => 100,
            ],
            [
                'title' => 'Rajin',
                'description' => 'Login 7 hari berturut-turut',
                'icon' => 'calendar',
                'achievement_type' => 'login_streak',
                'required_count' => 7,
                'points' => 30,
            ],
            [
                'title' => 'Konsisten',
                'description' => 'Login 30 hari berturut-turut',
                'icon' => 'calendar-check',
                'achievement_type' => 'login_streak',
                'required_count' => 30,
                'points' => 100,
            ],
            [
                'title' => 'Juara Kuis',
                'description' => 'Mendapatkan nilai 100 pada 3 kuis',
                'icon' => 'trophy',
                'achievement_type' => 'perfect_quizzes',
                'required_count' => 3,
                'points' => 50,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::create($achievement);
        }
    }
}
