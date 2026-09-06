<?php

namespace App\Exports;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class TeamsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    /** @param Builder<Team> $builder */
    public function __construct(private Builder $builder) {}

    /** @return Builder<Team> */
    public function query(): Builder
    {
        return $this->builder->with(['leader', 'track', 'members'])->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['teamCode', 'name', 'leaderFirst', 'leaderLast', 'leaderEmail', 'leaderPhone', 'university', 'city', 'trackCode', 'trackNameEn', 'memberCount', 'status', 'createdAt'];
    }

    /**
     * @param  Team  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->team_code,
            $row->name,
            $row->leader?->first_name,
            $row->leader?->last_name,
            $row->leader?->email,
            $row->leader?->phone,
            $row->university,
            $row->city,
            $row->track?->code,
            $row->track?->name_en,
            $row->members->count(),
            $row->status->value,
            optional($row->created_at)?->toIso8601ZuluString(),
        ];
    }
}
