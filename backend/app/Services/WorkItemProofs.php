<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorkItemProofs
{
    public function replace(HasMany $items, int $itemId, UploadedFile $proof, ?string $comment): Model
    {
        $newPath = $proof->store('work-item-proofs', 'local');
        if (! is_string($newPath)) {
            throw new RuntimeException('Could not store work item proof.');
        }

        $oldPath = null;

        try {
            $item = DB::transaction(function () use ($items, $itemId, $newPath, $comment, &$oldPath): Model {
                $item = $items->whereKey($itemId)->lockForUpdate()->firstOrFail();
                abort_unless(
                    in_array($item->submission_status, ['not_submitted', 'changes_requested'], true),
                    409,
                );

                $oldPath = $item->proof_path;
                $data = [
                    'submission_status' => 'pending_review',
                    'proof_path' => $newPath,
                    'submission_comment' => $comment,
                    'review_comment' => null,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                ];
                if ($item instanceof Task && $item->status === 'to_do') {
                    $data['status'] = 'in_progress';
                }

                if (! $item->update($data)) {
                    throw new RuntimeException('Could not save work item proof.');
                }

                return $item;
            });
        } catch (Throwable $exception) {
            $this->delete($newPath, $itemId);

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            $this->delete($oldPath, $itemId);
        }

        return $item->refresh();
    }

    public function download(Task|Requirement $item, string $filename): StreamedResponse
    {
        $path = $item->proof_path;
        $disk = Storage::disk('local');
        abort_unless($path !== null && $disk->exists($path), 404);

        $mimeType = $disk->mimeType($path);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => null,
        };
        abort_unless($extension !== null, 404);

        return $disk->download($path, "{$filename}.{$extension}", [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function delete(string $path, int $itemId): void
    {
        try {
            if (! Storage::disk('local')->delete($path)) {
                Log::warning('Could not remove a work item proof file.', ['work_item_id' => $itemId]);
            }
        } catch (Throwable) {
            Log::warning('Could not remove a work item proof file.', ['work_item_id' => $itemId]);
        }
    }
}
