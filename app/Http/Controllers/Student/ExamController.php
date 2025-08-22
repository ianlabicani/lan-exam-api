<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->user()->year;
        $section = $request->user()->section;

        return Exam::where('year', $year)
            ->where('section', $section)
            ->get();
    }
}