<?php

namespace App\Console\Commands;

use App\Models\Exam;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateExamStatuses extends Command
{
    protected $signature = 'exams:update-statuses';
    protected $description = 'Update exam statuses based on start and end times';

    public function handle()
    {
        $now = Carbon::now();

        // Publish exams that should start
        $published = Exam::where('status', 'draft')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->update(['status' => 'published']);

        // Archive exams that already ended
        $archived = Exam::whereIn('status', ['active', 'published'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->update(['status' => 'archived']);

        $this->info("Exams published: {$published}, Exams archived: {$archived}");
    }
}
