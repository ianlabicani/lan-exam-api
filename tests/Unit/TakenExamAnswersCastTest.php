<?php

use App\Models\Exam;
use App\Models\ExamItem;
use App\Models\TakenExam;
use App\Models\TakenExamAnswers;
use App\Models\User;

it('casts matching answer array to JSON and back', function () {
    // Create minimal related records
    $user = User::factory()->create();
    $exam = Exam::create([
        'title' => 'Test Exam',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'year' => '2025',
        'sections' => ['A'],
        'status' => 'draft',
        'total_points' => 0,
        'tos' => [],
    ]);

    $item = $exam->items()->create([
        'type' => 'matching',
        'question' => 'Match the pairs',
        'points' => 5,
    ]);

    $taken = TakenExam::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now(),
        'total_points' => 0,
    ]);

    $payload = [1, null, 2];

    $answer = TakenExamAnswers::create([
        'taken_exam_id' => $taken->id,
        'exam_item_id' => $item->id,
        'type' => 'matching',
        'answer' => $payload,
    ]);

    expect($answer->answer)->toBe($payload);

    // Fetch fresh from DB
    $fresh = TakenExamAnswers::find($answer->id);
    expect($fresh->answer)->toBe($payload);
});
