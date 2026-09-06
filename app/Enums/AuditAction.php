<?php

namespace App\Enums;

final class AuditAction
{
    public const AUTH_LOGIN = 'Auth.Login';

    public const AUTH_LOGOUT = 'Auth.Logout';

    public const AUTH_ACTIVATE = 'Auth.Activate';

    public const TEAM_CREATE = 'Team.Create';

    public const TEAM_UPDATE = 'Team.Update';

    public const TEAM_STATUS_CHANGE = 'Team.StatusChange';

    public const MEMBER_INVITE = 'Member.Invite';

    public const MEMBER_ACCEPT = 'Member.Accept';

    public const MEMBER_REMOVE = 'Member.Remove';

    public const SUBMISSION_CREATE = 'Submission.Create';

    public const SUBMISSION_UPDATE = 'Submission.Update';

    public const SUBMISSION_SUBMIT = 'Submission.Submit';

    public const SUBMISSION_REOPEN = 'Submission.Reopen';

    public const FILE_COMPLETE = 'File.Complete';

    public const FILE_DELETE = 'File.Delete';

    public const FILE_DOWNLOAD = 'File.Download';

    public const JUDGE_CREATE = 'Judge.Create';

    public const JUDGE_ASSIGN = 'Judge.Assign';

    public const JUDGE_UNASSIGN = 'Judge.Unassign';

    public const EVALUATION_SUBMIT = 'Evaluation.Submit';

    public const EVALUATION_REOPEN = 'Evaluation.Reopen';

    public const SETTINGS_UPDATE = 'Settings.Update';

    public const EXPORT_TEAMS = 'Export.Teams';

    public const EXPORT_SUBMISSIONS = 'Export.Submissions';

    public const EXPORT_EVALUATIONS = 'Export.Evaluations';
}
