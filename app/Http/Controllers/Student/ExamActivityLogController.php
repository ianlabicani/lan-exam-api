<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamActivityLog;
use Illuminate\Http\Request;

class ExamActivityLogController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'taken_exam_id' => 'required|integer|exists:taken_exams,id',
            'student_id' => 'required|integer|exists:users,id',
            'event_type' => 'required|string',
            'details' => 'nullable|string',
        ]);

        $log = ExamActivityLog::create($validated);

        return response()->json([
            'message' => 'Activity logged successfully',
            'data' => $log
        ], 201);
    }

    // Fetch logs for a specific student in an exam
    public function getExamLogs(Request $request, $takenExamId)
    {

        $logs = ExamActivityLog::where('taken_exam_id', $takenExamId)
            ->where('student_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $logs]);
    }

}
