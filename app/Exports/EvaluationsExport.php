<?php

namespace App\Exports;

use App\Models\Evaluation;
use App\Models\ScoringCriterion;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class EvaluationsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    /** @var list<ScoringCriterion> */
    private array $criteria;

    /** @param Builder<Evaluation> $builder */
    public function __construct(private Builder $builder)
    {
        $this->criteria = ScoringCriterion::query()->orderBy('sort_order')->get()->all();
    }

    /** @return Builder<Evaluation> */
    public function query(): Builder
    {
        return $this->builder->with(['submission.team', 'judgeProfile.user', 'scores'])->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        $heads = ['teamCode', 'submissionCode', 'judgeEmail'];
        foreach ($this->criteria as $c) {
            $heads[] = $c->code;
        }
        $heads[] = 'totalScore';
        $heads[] = 'submittedAt';

        return $heads;
    }

    /**
     * @param  Evaluation  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        $scores = $row->scores->keyBy('scoring_criterion_id');
        $out = [
            $row->submission?->team?->team_code,
            $row->submission?->submission_code,
            $row->judgeProfile?->user?->email,
        ];
        foreach ($this->criteria as $c) {
            $out[] = $scores->get($c->id)?->score;
        }
        $out[] = $row->total_score;
        $out[] = optional($row->submitted_at)?->toIso8601ZuluString();

        return $out;
    }
}
