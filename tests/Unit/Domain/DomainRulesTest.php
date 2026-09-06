<?php

use App\Domain\Codes\PublicCodeFormatter;
use App\Domain\Files\FilenameSanitizer;
use App\Domain\Files\FilePolicy;
use App\Domain\Registration\RegistrationWindow;
use App\Domain\Scoring\ScoreCalculator;
use App\Domain\Scoring\WeightSumValidator;
use App\Domain\Submissions\SubmissionStateMachine;
use App\Enums\FileType;
use App\Enums\SubmissionStatus;
use App\Support\AppException;
use Carbon\CarbonImmutable;

it('computes weighted total 73.75', function () {
    $calc = new ScoreCalculator;
    $total = $calc->total([
        ['code' => 'scientific_understanding', 'weight' => 0.10, 'score' => 80],
        ['code' => 'data_processing', 'weight' => 0.15, 'score' => 70],
        ['code' => 'analysis_model', 'weight' => 0.20, 'score' => 90],
        ['code' => 'accuracy_validation', 'weight' => 0.15, 'score' => 60],
        ['code' => 'scientific_interpretation', 'weight' => 0.15, 'score' => 75],
        ['code' => 'innovation', 'weight' => 0.10, 'score' => 50],
        ['code' => 'usability', 'weight' => 0.05, 'score' => 40],
        ['code' => 'presentation', 'weight' => 0.10, 'score' => 100],
    ]);
    expect($total)->toBe(73.75);
});

it('scores all 100 and all 0', function () {
    $calc = new ScoreCalculator;
    $weights = [0.10, 0.15, 0.20, 0.15, 0.15, 0.10, 0.05, 0.10];
    $all100 = [];
    $all0 = [];
    foreach ($weights as $i => $w) {
        $all100[] = ['code' => (string) $i, 'weight' => $w, 'score' => 100];
        $all0[] = ['code' => (string) $i, 'weight' => $w, 'score' => 0];
    }
    expect($calc->total($all100))->toBe(100.0);
    expect($calc->total($all0))->toBe(0.0);
});

it('rejects missing criterion list', function () {
    expect(fn () => (new ScoreCalculator)->total([]))->toThrow(AppException::class);
});

it('rejects out of range score', function () {
    expect(fn () => (new ScoreCalculator)->total([
        ['code' => 'a', 'weight' => 1, 'score' => 101],
    ]))->toThrow(AppException::class);
});

it('validates weight sum', function () {
    $v = new WeightSumValidator;
    $v->assert([0.10, 0.15, 0.20, 0.15, 0.15, 0.10, 0.05, 0.10]);
    expect(fn () => $v->assert([0.5, 0.4]))->toThrow(AppException::class);
});

it('registration window is inclusive', function () {
    $w = new RegistrationWindow;
    $start = CarbonImmutable::parse('2026-09-01T00:00:00Z');
    $end = CarbonImmutable::parse('2026-09-06T20:00:00Z');
    expect($w->canRegister($start, true, $start, $end))->toBeTrue();
    expect($w->canRegister($end, true, $start, $end))->toBeTrue();
    expect($w->canRegister($end->addSecond(), true, $start, $end))->toBeFalse();
    expect($w->canRegister($start, false, $start, $end))->toBeFalse();
    expect($w->notStarted($start->subSecond(), $start))->toBeTrue();
});

it('blocks illegal submission transitions', function () {
    $sm = new SubmissionStateMachine;
    expect(fn () => $sm->assertCanTransition(SubmissionStatus::Draft, SubmissionStatus::Judged))
        ->toThrow(AppException::class);
    $sm->assertCanTransition(SubmissionStatus::Draft, SubmissionStatus::Submitted);
    $sm->assertCanTransition(SubmissionStatus::Submitted, SubmissionStatus::Draft);
});

it('sanitizes filenames', function () {
    $s = new FilenameSanitizer;
    expect($s->sanitize('ok.pdf'))->toBe('ok.pdf');
    expect($s->sanitize('bad:name?.pdf'))->toBe('bad_name_.pdf');
    expect(fn () => $s->assertValid('a/b.pdf'))->toThrow(AppException::class);
});

it('enforces file policy matrix', function () {
    $p = new FilePolicy;
    $policy = config('exoplanet.file_policy');
    $p->assert(FileType::ReportPdf, 'a.pdf', 'application/pdf', 1000, $policy);
    expect(fn () => $p->assert(FileType::ReportPdf, 'a.txt', 'application/pdf', 1000, $policy))->toThrow(AppException::class);
    expect(fn () => $p->assert(FileType::ReportPdf, 'a.pdf', 'application/octet-stream', 1000, $policy))->toThrow(AppException::class);
    expect(fn () => $p->assert(FileType::ReportPdf, 'a.pdf', 'application/pdf', 21 * 1024 * 1024, $policy))->toThrow(AppException::class);
    expect($p->matchesMagicBytes(FileType::ReportPdf, '%PDF-1.4'))->toBeTrue();
    expect($p->matchesMagicBytes(FileType::ArchiveZip, 'PK..'))->toBeTrue();
    expect($p->matchesMagicBytes(FileType::ReportPdf, 'PK'))->toBeFalse();
});

it('formats public codes', function () {
    $f = new PublicCodeFormatter;
    expect($f->team(2026, 1))->toBe('EXP-2026-00001');
    expect($f->submission(2026, 182))->toBe('SUB-2026-00182');
});
