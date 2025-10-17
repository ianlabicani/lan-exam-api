<?php

namespace App\Console\Commands;

use App\Models\Exam;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PublishScheduledExams extends Command
{
    protected $signature = 'exams:publish-scheduled';
    protected $description = 'Update exams from draft to published when starts_at is reached';

    public function handle()
    {
        $now = Carbon::now();

        $updated = Exam::where('status', 'draft')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->update(['status' => 'published']);

        $this->info("Updated {$updated} exams to published.");
    }

}
