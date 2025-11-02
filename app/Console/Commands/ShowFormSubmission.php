<?php

namespace App\Console\Commands;

use App\Models\FormSubmission;
use Illuminate\Console\Command;

class ShowFormSubmission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:show-submission {id : The form_submissions row ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display a saved form submission (pretty JSON) by database row ID';

    public function handle(): int
    {
        $id = (int) $this->argument('id');

        if ($id <= 0) {
            $this->error('Invalid id. Must be a positive integer.');
            return self::FAILURE;
        }

        $submission = FormSubmission::find($id);
        if (! $submission) {
            $this->error('Form submission not found: id=' . $id);
            return self::FAILURE;
        }

        $payload = [
            'id' => $submission->id,
            'created_at' => (string) $submission->created_at,
            'updated_at' => (string) $submission->updated_at,
            'data' => $submission->data, // Model accessor already decompresses/decodes
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
