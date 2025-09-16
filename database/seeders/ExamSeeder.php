<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\User;
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
                'year' => '1',
                'sections' => json_encode(['A', 'B']),
                'status' => 'active',
                'total_points' => 100,
                'tos' => json_encode([
                    [
                        'topic' => 'Algebra',
                        'subtopics' => [
                            [
                                'outcome' => 'Solve linear equations',
                                'time_allotment' => 3,
                                'no_of_items' => 10,
                                'distribution' => [
                                    'easy' => [
                                        'knowledge_comprehension' => [
                                            'allocation' => 3,
                                            'placement' => 'Test I: 1-3'
                                        ],
                                    ],
                                    'average' => [
                                        'application_analysis' => [
                                            'allocation' => 5,
                                            'placement' => 'Test I: 4-8'
                                        ],
                                    ],
                                    'difficult' => [
                                        'synthesis_evaluation' => [
                                            'allocation' => 2,
                                            'placement' => 'Test I: 9-10'
                                        ],
                                    ],
                                ]
                            ],
                        ],
                    ],
                    [
                        'topic' => 'Geometry',
                        'subtopics' => [
                            [
                                'outcome' => 'Identify angles and triangles',
                                'time_allotment' => 2,
                                'no_of_items' => 5,
                                'distribution' => [
                                    'easy' => [
                                        'knowledge_comprehension' => [
                                            'allocation' => 2,
                                            'placement' => 'Test II: 1-2'
                                        ],
                                    ],
                                    'average' => [
                                        'application_analysis' => [
                                            'allocation' => 2,
                                            'placement' => 'Test II: 3-4'
                                        ],
                                    ],
                                    'difficult' => [
                                        'synthesis_evaluation' => [
                                            'allocation' => 1,
                                            'placement' => 'Test II: 5'
                                        ],
                                    ],
                                ]
                            ],
                        ],
                    ]
                ]),
            ],
        ];


        foreach ($exams as $exam) {
            $createdExam = Exam::create($exam);

            // Attach all teachers (users having the 'teacher' role) to the exam
            $teachers = User::whereHas('roles', function ($q) {
                $q->where('name', 'teacher');
            })->pluck('id');

            if ($teachers->isNotEmpty()) {
                $createdExam->teachers()->syncWithoutDetaching($teachers);
            }
        }
    }
}
