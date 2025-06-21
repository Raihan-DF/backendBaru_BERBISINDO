<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Material;
use App\Models\MaterialVideo;
use App\Models\Exercise;
use App\Models\Quiz;
use App\Models\StudentProgress;
use Illuminate\Database\Seeder;

class StudentProgressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = User::where( 'student')->get();
        $materials = Material::all();
        $exercises = Exercise::all();
        $quizzes = Quiz::all();

        foreach ($students as $student) {
            // Material Progress
            foreach ($materials as $material) {
                if (rand(1, 100) <= 70) { // 70% chance of starting material
                    $isCompleted = rand(1, 100) <= 60; // 60% chance of completion

                    StudentProgress::create([
                        'user_id' => $student->id,
                        'material_id' => $material->id,
                        'progress_type' => 'material',
                        'is_completed' => $isCompleted,
                        'started_at' => now()->subDays(rand(1, 30)),
                        'completed_at' => $isCompleted ? now()->subDays(rand(0, 15)) : null,
                    ]);

                    // Video Progress
                    $videos = MaterialVideo::where('material_id', $material->id)->get();
                    foreach ($videos as $video) {
                        if (rand(1, 100) <= 80) { // 80% chance of watching video
                            $videoCompleted = rand(1, 100) <= 70;

                            StudentProgress::create([
                                'user_id' => $student->id,
                                'material_id' => $material->id,
                                'material_video_id' => $video->id,
                                'progress_type' => 'material_video',
                                'is_completed' => $videoCompleted,
                                'started_at' => now()->subDays(rand(1, 25)),
                                'completed_at' => $videoCompleted ? now()->subDays(rand(0, 20)) : null,
                            ]);
                        }
                    }
                }
            }

            // Exercise Progress
            foreach ($exercises as $exercise) {
                if (rand(1, 100) <= 50) { // 50% chance of doing exercise
                    $isCompleted = rand(1, 100) <= 70;

                    StudentProgress::create([
                        'user_id' => $student->id,
                        'exercise_id' => $exercise->id,
                        'progress_type' => 'exercise',
                        'attempt_count' => rand(1, 3),
                        'is_completed' => $isCompleted,
                        'started_at' => now()->subDays(rand(1, 20)),
                        'completed_at' => $isCompleted ? now()->subDays(rand(0, 10)) : null,
                    ]);
                }
            }

            // Quiz Progress
            foreach ($quizzes as $quiz) {
                if (rand(1, 100) <= 40) { // 40% chance of taking quiz
                    $score = rand(60, 100);
                    $maxScore = 100;
                    $isCompleted = true;
                    $passed = $score >= 70;

                    StudentProgress::create([
                        'user_id' => $student->id,
                        'quiz_id' => $quiz->id,
                        'progress_type' => 'quiz',
                        'score' => $score,
                        'max_score' => $maxScore,
                        'attempt_count' => rand(1, 2),
                        'answers_detail' => $this->generateAnswersDetail(),
                        'is_completed' => $isCompleted,
                        'started_at' => now()->subDays(rand(1, 15)),
                        'completed_at' => now()->subDays(rand(0, 5)),
                    ]);
                }
            }
        }

        $this->command->info('Student progress seeded successfully!');
    }

    private function generateAnswersDetail()
    {
        $answers = [];
        for ($i = 1; $i <= rand(5, 10); $i++) {
            $isCorrect = rand(1, 100) <= 70; // 70% correct answers
            $answers[$i] = [
                'selected_option_id' => rand(1, 4),
                'is_correct' => $isCorrect,
                'points_earned' => $isCorrect ? 10 : 0,
                'answered_at' => now()->subMinutes(rand(1, 30))->toISOString(),
            ];
        }
        return $answers;
    }
}
