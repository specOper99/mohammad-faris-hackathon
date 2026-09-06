<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class CodeSequence
{
    public function next(string $name): int
    {
        if (DB::getDriverName() === 'pgsql') {
            $allowed = ['team_code_seq', 'submission_code_seq'];
            if (! in_array($name, $allowed, true)) {
                throw new \InvalidArgumentException('Unknown sequence.');
            }
            $row = DB::selectOne("SELECT nextval('{$name}') as n");

            return (int) $row->n;
        }

        return (int) DB::transaction(function () use ($name) {
            $row = DB::table('code_sequences')->where('name', $name)->lockForUpdate()->first();
            if ($row === null) {
                DB::table('code_sequences')->insert(['name' => $name, 'value' => 1]);

                return 1;
            }
            $next = (int) $row->value + 1;
            DB::table('code_sequences')->where('name', $name)->update(['value' => $next]);

            return $next;
        });
    }
}
