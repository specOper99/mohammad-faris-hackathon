<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';
        $false = $pgsql ? 'false' : '0';
        $true = $pgsql ? 'true' : '1';

        Schema::create('tracks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('code', 1)->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('difficulty', 40);
            $table->string('focus_en')->nullable();
            $table->string('focus_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('team_code', 32)->unique();
            $table->string('name', 200);
            $table->foreignUuid('leader_user_id')->constrained('users');
            $table->string('university')->nullable();
            $table->string('organization')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('technical_level', 40);
            $table->foreignUuid('track_id')->constrained('tracks');
            $table->string('github_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('status', 40);
            $table->string('rejected_reason', 1000)->nullable();
            $table->unsignedInteger('max_members_snapshot');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_deleted')->default(false);
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            $table->index('leader_user_id');
            $table->index('track_id');
            $table->index('status');
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('role', 20);
            $table->string('skill', 200)->nullable();
            $table->string('status', 20);
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('removed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("CREATE UNIQUE INDEX team_members_one_active_user ON team_members (user_id) WHERE status IN ('invited','active')");
        DB::statement("CREATE UNIQUE INDEX team_members_team_user_not_removed ON team_members (team_id, user_id) WHERE status <> 'removed'");
        DB::statement("CREATE UNIQUE INDEX team_members_one_leader ON team_members (team_id) WHERE role = 'leader' AND status = 'active'");

        Schema::create('one_time_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->string('purpose', 40);
            $table->binary('token_hash');
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX one_time_tokens_hash_unique ON one_time_tokens (token_hash)');

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->string('email');
            $table->foreignUuid('invited_by_user_id')->constrained('users');
            $table->foreignUuid('one_time_token_id')->constrained('one_time_tokens');
            $table->string('status', 20);
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
        });

        Schema::create('team_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('rules_policy_version');
            $table->string('data_usage_policy_version');
            $table->timestampTz('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('submission_code', 32)->nullable()->unique();
            $table->foreignUuid('team_id')->unique()->constrained('teams');
            $table->foreignUuid('track_id')->constrained('tracks');
            $table->string('project_name', 300)->nullable();
            $table->text('abstract')->nullable();
            $table->text('problem_description')->nullable();
            $table->text('solution_description')->nullable();
            $table->string('github_url')->nullable();
            $table->string('demo_url')->nullable();
            $table->text('limitations')->nullable();
            $table->text('ai_usage')->nullable();
            $table->text('readme_inline')->nullable();
            $table->string('status', 40);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('first_submitted_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->string('lock_reason', 20)->nullable();
            $table->unsignedInteger('reopen_count')->default(0);
            $table->timestampTz('reopened_at')->nullable();
            $table->foreignUuid('reopened_by_user_id')->nullable()->constrained('users');
            $table->string('publication_status', 20)->default('private');
            $table->decimal('aggregated_score', 6, 2)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('created_by_user_id')->constrained('users');
            $table->foreignUuid('updated_by_user_id')->nullable()->constrained('users');
            $table->timestampsTz();
        });

        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('file_type', 40);
            $table->string('storage_key', 500);
            $table->string('s3_upload_id')->nullable();
            $table->unsignedBigInteger('declared_size');
            $table->string('declared_mime');
            $table->string('original_file_name', 260);
            $table->string('status', 20);
            $table->unsignedInteger('part_size')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
        });

        Schema::create('submission_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->string('file_type', 40);
            $table->string('original_file_name', 260);
            $table->string('storage_key', 500)->unique();
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->char('checksum_sha256', 64)->nullable();
            $table->foreignUuid('uploaded_by_user_id')->constrained('users');
            $table->timestampTz('uploaded_at');
            $table->boolean('is_current')->default(true);
            $table->string('scan_status', 20);
            $table->foreignUuid('upload_session_id')->nullable()->constrained('upload_sessions');
        });
        DB::statement("CREATE UNIQUE INDEX submission_files_current_type ON submission_files (submission_id, file_type) WHERE is_current = {$true}");

        Schema::create('judge_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users');
            $table->string('specialization')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users');
            $table->timestampsTz();
        });

        Schema::create('judge_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('judge_profile_id')->constrained('judge_profiles')->cascadeOnDelete();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->timestampTz('assigned_at');
            $table->foreignUuid('assigned_by_user_id')->constrained('users');
            $table->timestampTz('unassigned_at')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX judge_assignments_active_unique ON judge_assignments (judge_profile_id, submission_id) WHERE unassigned_at IS NULL');

        Schema::create('scoring_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('weight', 5, 4);
            $table->integer('min_score')->default(0);
            $table->integer('max_score')->default(100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->foreignUuid('judge_profile_id')->constrained('judge_profiles');
            $table->string('status', 20);
            $table->decimal('total_score', 6, 2)->nullable();
            $table->text('comments')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reopened_at')->nullable();
            $table->foreignUuid('reopened_by_user_id')->nullable()->constrained('users');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->unique(['submission_id', 'judge_profile_id']);
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignUuid('scoring_criterion_id')->constrained('scoring_criteria');
            $table->integer('score');
            $table->unique(['evaluation_id', 'scoring_criterion_id']);
        });

        Schema::create('challenge_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('challenge_year');
            $table->boolean('registration_enabled')->default(true);
            $table->timestampTz('registration_start');
            $table->timestampTz('registration_end');
            $table->boolean('submission_enabled')->default(true);
            $table->timestampTz('submission_start');
            $table->timestampTz('submission_end');
            $table->boolean('scoring_enabled')->default(false);
            $table->boolean('publish_results')->default(false);
            $table->unsignedInteger('max_team_members')->default(6);
            $table->boolean('allow_multiple_teams')->default(false);
            $table->boolean('require_admin_team_confirmation')->default(false);
            $table->boolean('one_active_track_per_team')->default(true);
            $table->boolean('lock_on_submit')->default(true);
            $table->boolean('lock_on_deadline')->default(true);
            $table->boolean('allow_participant_edits_after_submit')->default(false);
            $table->boolean('all_members_can_edit')->default(false);
            $table->boolean('email_all_members_on_submit')->default(true);
            $table->string('score_aggregation', 20)->default('average');
            $table->string('current_rules_version');
            $table->string('current_data_usage_version');
            $table->json('allowed_track_ids')->nullable();
            $table->json('file_policy');
            $table->json('required_file_types_on_submit');
            $table->json('required_fields_on_submit');
            $table->json('required_file_types_by_track_code');
            $table->unsignedBigInteger('allow_direct_upload_below_bytes')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('updated_by_user_id')->nullable()->constrained('users');
            $table->timestampTz('updated_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->string('action');
            $table->string('entity_type');
            $table->uuid('entity_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('email_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('template_key');
            $table->string('to_email');
            $table->json('payload')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('notification_id')->nullable();
            $table->timestampsTz();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key');
            $table->uuid('user_id')->nullable();
            $table->string('path');
            $table->string('body_hash');
            $table->unsignedSmallInteger('status');
            $table->json('response_json');
            $table->timestampTz('created_at')->nullable();
            $table->unique(['key', 'user_id', 'path']);
        });

        Schema::create('code_sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        if ($pgsql) {
            DB::statement('CREATE SEQUENCE team_code_seq START 1');
            DB::statement('CREATE SEQUENCE submission_code_seq START 1');
        } else {
            DB::table('code_sequences')->insert([
                ['name' => 'team_code_seq', 'value' => 0],
                ['name' => 'submission_code_seq', 'value' => 0],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('email_outbox');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('challenge_settings');
        Schema::dropIfExists('evaluation_scores');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('scoring_criteria');
        Schema::dropIfExists('judge_assignments');
        Schema::dropIfExists('judge_profiles');
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('upload_sessions');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('team_agreements');
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('one_time_tokens');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('tracks');
        Schema::dropIfExists('code_sequences');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS team_code_seq');
            DB::statement('DROP SEQUENCE IF EXISTS submission_code_seq');
        }
    }
};
