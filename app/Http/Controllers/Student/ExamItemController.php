<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamItem;
use Illuminate\Http\Request;

class ExamItemController extends Controller
{
    public function index(Exam $exam)
    {
        $items = $exam->items;
        return response()->json($items);
    }
}
