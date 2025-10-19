<?php

namespace App\Console\Commands;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExamSchedule extends Command
{
    protected $signature = 'exam:check-schedule';

    protected $description = 'Automatically transition exams based on their scheduled times.';

    public function handle(): void
    {
        $now = Carbon::now();

        // PUBLISH EXAMS - Move published exams to ongoing when start time is reached
        $toStart = Exam::where('status', 'published')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->get();

        foreach ($toStart as $exam) {
            $exam->update(['status' => 'ongoing']);
            $this->info("Started exam ID {$exam->id}: {$exam->title}");
        }

        // CLOSE EXAMS - Move ongoing exams to closed when end time is reached
        $toClose = Exam::where('status', 'ongoing')
            ->where('ends_at', '<=', $now)
            ->get();

        foreach ($toClose as $exam) {
            $exam->update(['status' => 'closed']);
            $this->info("Closed exam ID {$exam->id}: {$exam->title}");
        }

        $this->info('Exam schedule check completed at '.$now);
    }
}
