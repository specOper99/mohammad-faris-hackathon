<?php

namespace App\Http\Resources;

use App\Models\ChallengeSettings;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Submission */
final class SubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['currentFiles', 'track', 'team']);
        $publish = ChallengeSettings::current()->publish_results;
        $user = $request->user();
        $showScore = $publish || ($user && $user->hasRole(['admin', 'judge']));

        return [
            'id' => $this->id,
            'submissionCode' => $this->submission_code,
            'teamId' => $this->team_id,
            'trackId' => $this->track_id,
            'projectName' => $this->project_name,
            'abstract' => $this->abstract,
            'problemDescription' => $this->problem_description,
            'solutionDescription' => $this->solution_description,
            'githubUrl' => $this->github_url,
            'demoUrl' => $this->demo_url,
            'limitations' => $this->limitations,
            'aiUsage' => $this->ai_usage,
            'readmeInline' => $this->readme_inline,
            'status' => $this->status->value,
            'submittedAt' => optional($this->submitted_at)?->toIso8601ZuluString(),
            'lockedAt' => optional($this->locked_at)?->toIso8601ZuluString(),
            'lockReason' => $this->lock_reason?->value,
            'publicationStatus' => $this->publication_status->value,
            'aggregatedScore' => $showScore ? $this->aggregated_score : null,
            'version' => $this->version,
            'files' => $this->currentFiles->map(fn ($f) => [
                'id' => $f->id,
                'fileType' => $f->file_type->value,
                'originalFileName' => $f->original_file_name,
                'mimeType' => $f->mime_type,
                'fileSize' => $f->file_size,
                'scanStatus' => $f->scan_status->value,
                'uploadedAt' => optional($f->uploaded_at)?->toIso8601ZuluString(),
            ])->values()->all(),
        ];
    }
}
