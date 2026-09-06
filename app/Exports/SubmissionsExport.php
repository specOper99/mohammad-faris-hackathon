<?php

namespace App\Exports;

use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class SubmissionsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    /** @param Builder<Submission> $builder */
    public function __construct(private Builder $builder) {}

    /** @return Builder<Submission> */
    public function query(): Builder
    {
        return $this->builder->with(['team', 'track'])->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['submissionCode', 'teamCode', 'projectName', 'trackCode', 'status', 'submittedAt', 'githubUrl', 'demoUrl', 'aggregatedScore'];
    }

    /**
     * @param  Submission  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->submission_code,
            $row->team?->team_code,
            $row->project_name,
            $row->track?->code,
            $row->status->value,
            optional($row->submitted_at)?->toIso8601ZuluString(),
            $row->github_url,
            $row->demo_url,
            $row->aggregated_score,
        ];
    }
}
