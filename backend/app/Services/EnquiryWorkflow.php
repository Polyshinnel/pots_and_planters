<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnquiryWorkflow
{
    private const STATUSES = ['new', 'in_progress', 'completed', 'cancelled'];

    public function changeStatus(Enquiry $enquiry, string $status, int $actorId): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid enquiry status.']);
        }

        DB::transaction(function () use ($enquiry, $status, $actorId): void {
            $locked = Enquiry::query()->lockForUpdate()->findOrFail($enquiry->getKey());
            if ($locked->status === $status) {
                return;
            }

            $oldStatus = $locked->status;
            $locked->status = $status;
            $locked->save();
            EnquiryActivity::query()->create([
                'enquiry_id' => $locked->id, 'actor_id' => $actorId, 'kind' => 'status',
                'old_status' => $oldStatus, 'new_status' => $status, 'created_at' => now(),
            ]);
        });
    }

    public function addNote(Enquiry $enquiry, string $note, int $actorId): void
    {
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 5000) {
            throw ValidationException::withMessages(['note' => 'Enter an internal note of up to 5,000 characters.']);
        }

        EnquiryActivity::query()->create([
            'enquiry_id' => $enquiry->id, 'actor_id' => $actorId, 'kind' => 'note', 'note' => $note, 'created_at' => now(),
        ]);
    }
}
