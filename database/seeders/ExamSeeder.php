<?php

namespace Database\Seeders;

use App\Models\Exam;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exams = [
            [
                'title' => 'Math Exam',
                'description' => 'Algebra and Geometry',
                'starts_at' => now(),
                'ends_at' => now()->addHours(2),
                'year' => '2023',
                'section' => 'A',
                'status' => 'active',
                'total_points' => 100,
            ],
            [
                'title' => 'Science Exam',
                'description' => 'Physics and Chemistry',
                'starts_at' => now(),
                'ends_at' => now()->addHours(2),
                'year' => '2023',
                'section' => 'B',
                'status' => 'active',
                'total_points' => 100,
            ],
        ];

        foreach ($exams as $exam) {
            Exam::create($exam);
        }
    }
}
