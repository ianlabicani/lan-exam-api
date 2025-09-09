<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TakenExam;
use Illuminate\Http\Request;

class TakenExamController extends Controller
{
    public function index(int $examId)
    {
        $takenExams = TakenExam::with('user')
            ->where('exam_id', $examId)
            ->get();
        return response()->json(['takenExams' => $takenExams]);
    }
}
