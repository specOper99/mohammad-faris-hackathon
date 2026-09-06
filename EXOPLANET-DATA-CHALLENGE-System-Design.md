# EXOPLANET DATA CHALLENGE — System Design

A bilingual competition-management platform for an astronomy and data-science challenge associated with **Hack4Dev**, operated by the **Andromeda Iraqi Team**.

This document explains the full system design: what the platform is, how it is structured, how data flows, and which technical decisions hold the architecture together.

---

## Table of Contents

1. [What This System Is](#1-what-this-system-is)
2. [Architecture at a Glance](#2-architecture-at-a-glance)
3. [Technology Stack and Why](#3-technology-stack-and-why)
4. [Backend Architecture](#4-backend-architecture)
5. [Frontend Architecture](#5-frontend-architecture)
6. [Data Model](#6-data-model)
7. [Object Storage and Files](#7-object-storage-and-files)
8. [Users, Roles, and Access Control](#8-users-roles-and-access-control)
9. [Authentication and Account Activation](#9-authentication-and-account-activation)
10. [Team Registration](#10-team-registration)
11. [Challenge Tracks](#11-challenge-tracks)
12. [Public Website](#12-public-website)
13. [Project Submission Lifecycle](#13-project-submission-lifecycle)
14. [Judging and Scoring](#14-judging-and-scoring)
15. [Administration](#15-administration)
16. [Notifications, Localization, and UX](#16-notifications-localization-and-ux)
17. [API Surface](#17-api-surface)
18. [Business Rules](#18-business-rules)
19. [Security](#19-security)
20. [Deployment, Operations, and Scale](#20-deployment-operations-and-scale)
21. [Testing Strategy](#21-testing-strategy)
22. [Development Phases](#22-development-phases)
23. [End-to-End Platform Workflow](#23-end-to-end-platform-workflow)
24. [Architecture Decisions (Baseline)](#24-architecture-decisions-baseline)

---

## 1. What This System Is

EXOPLANET DATA CHALLENGE is **not a brochure website**. It is a full challenge-management platform covering the entire competition lifecycle:

| Area | What it covers |
| --- | --- |
| Public site | Challenge story, tracks, judging criteria, requirements, FAQ |
| Registration | Team signup, leader account, members, track selection |
| Participant workspace | Dashboard, team editing, draft and final submission, file upload |
| Judging | Assignment, review, scorecard, comments |
| Administration | Users, teams, submissions, settings, exports, audit logs |
| Operations | Deadlines, locking, emails, bilingual UI, responsive layout |

The frontend is a **React** application. The backend is an **ASP.NET Core Web API**. The frontend never talks to PostgreSQL. All data access goes through the API.

Two lifecycles drive the product:

**Participant**

```text
Registration → Email Activation → Login → Team Management → Track Selection
→ Submission Draft → File Upload → Final Submission → Submission Lock
```

**Organizer**

```text
Manage Challenge → Manage Teams → Manage Submissions → Assign Judges
→ Review Evaluations → Calculate Scores → Select Finalists → Announce Results
```

---

## 2. Architecture at a Glance

The system is a **client–server modular monolith**. One backend application, split into logical modules, not microservices.

```text
                         INTERNET
                            │
                            ▼
                  ┌───────────────────┐
                  │   React Frontend  │
                  │   TypeScript      │
                  └─────────┬─────────┘
                            │
                       HTTPS / REST
                            │
                            ▼
                  ┌───────────────────┐
                  │   ASP.NET Core    │
                  │     Web API       │
                  ├───────────────────┤
                  │ Auth, Users, Teams│
                  │ Members, Tracks   │
                  │ Registration      │
                  │ Submissions, Files│
                  │ Judging, Admin    │
                  │ Notifications     │
                  │ Audit Logs        │
                  └──────┬─────┬──────┘
                         │     │
             ┌───────────┘     └────────────┐
             ▼                              ▼
      ┌──────────────┐              ┌────────────────┐
      │ PostgreSQL   │              │ Object Storage │
      │ (metadata)   │              │ (actual files) │
      └──────────────┘              └────────────────┘
                         │
                         ▼
                    ┌─────────┐
                    │  SMTP   │
                    └─────────┘
```

**Three storage concerns are kept separate:**

- **PostgreSQL** — users, teams, submissions, scores, settings, audit logs, **file metadata**
- **S3-compatible object storage** — PDFs, notebooks, ZIPs, presentations, images
- **SMTP** — activation and operational emails

The backend is the **source of truth** for permissions, deadlines, statuses, and scores. The frontend can hide buttons and show progress, but it is never trusted for enforcement.

---

## 3. Technology Stack and Why

### Frontend

| Technology | Role |
| --- | --- |
| React.js | Interactive UIs: registration wizard, dashboards, uploads, judging |
| TypeScript | Type safety across a large form-heavy app |
| Vite | Dev server and production build |
| React Router | Public, participant, judge, and admin route trees |
| TanStack Query | Server-state caching and refetching |
| React Hook Form | Multi-step and complex forms |
| Zod | Schema validation aligned with API contracts |
| Tailwind CSS | Space/astronomy visual system without a generic SaaS look |
| Axios | HTTP client |
| i18next / react-i18next | One app, English LTR and Arabic RTL |

React is chosen because the product is several interactive surfaces sharing components: public site, participant dashboard, judge dashboard, admin dashboard.

### Backend

| Technology | Role |
| --- | --- |
| ASP.NET Core Web API | REST API, middleware, auth |
| C# / .NET 10 | Strong typing, performance, mature ecosystem |
| Entity Framework Core | ORM against PostgreSQL |
| ASP.NET Core Identity | Users, passwords, confirmation |
| JWT | Stateless API authentication |
| Swagger / OpenAPI | Contract documentation |

ASP.NET Core is a fit because this system is **security-heavy**: roles, resource ownership, file access, deadlines, and audit trails.

### Data and infrastructure

| Piece | Choice |
| --- | --- |
| Database | PostgreSQL |
| Files | S3-compatible object storage (AWS S3, Cloudflare R2, MinIO, etc.) |
| Email | SMTP |
| Packaging | Docker |
| Edge | Nginx reverse proxy |
| Hosting | Linux VPS / cloud |
| Source + CI | Git, GitHub, GitHub Actions |

PostgreSQL is preferred because the domain is highly relational (users → teams → members → tracks → submissions → files → judges → evaluations). Foreign keys, transactions, and constraints matter more here than a document store.

---

## 4. Backend Architecture

### Style: modular monolith / Clean Architecture

Microservices are rejected. They would add extra deployments, service-to-service auth, distributed transactions, and heavier local development for a traffic level that does not need them.

Logical modules live **inside one application**:

```text
Authentication · Users · Teams · Team Members · Tracks
Registration · Submissions · Files · Judging
Administration · Notifications · Challenge Settings · Audit Logs
```

### Layering

```text
ExoplanetChallenge.API
        │
        ▼
ExoplanetChallenge.Application
        │
        ▼
ExoplanetChallenge.Domain
        ▲
        │
ExoplanetChallenge.Infrastructure
```

| Layer | Contains | Must not contain |
| --- | --- | --- |
| **Domain** | Entities and concepts: Team, TeamMember, Track, Submission, SubmissionFile, Judge, Evaluation | EF, S3, SMTP, JWT |
| **Application** | Use cases: RegisterTeam, ActivateAccount, CreateSubmission, SubmitProject, AssignJudge, EvaluateSubmission, ChangeSubmissionStatus, ExportTeams | Controllers, storage SDKs |
| **Infrastructure** | PostgreSQL, EF Core, Identity, object storage, SMTP, JWT, external services | Business rules |
| **API** | Controllers, middleware, filters, request/response models, DI, `Program.cs` | Domain logic |

### Suggested project layout

```text
ExoplanetChallenge/
├── src/
│   ├── ExoplanetChallenge.API/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   ├── Filters/
│   │   ├── Extensions/
│   │   └── Program.cs
│   ├── ExoplanetChallenge.Application/
│   │   ├── Authentication/
│   │   ├── Teams/
│   │   ├── Members/
│   │   ├── Tracks/
│   │   ├── Registrations/
│   │   ├── Submissions/
│   │   ├── Judging/
│   │   ├── Administration/
│   │   └── Common/
│   ├── ExoplanetChallenge.Domain/
│   │   ├── Entities/
│   │   ├── Enums/
│   │   ├── Interfaces/
│   │   └── Exceptions/
│   └── ExoplanetChallenge.Infrastructure/
│       ├── Persistence/
│       ├── Identity/
│       ├── Storage/
│       ├── Email/
│       └── Services/
└── tests/
    ├── UnitTests/
    └── IntegrationTests/
```

### Middleware

Every request can pass through:

- Global exception handling (no raw DB/stack traces to clients)
- Request logging
- Authentication
- Authorization
- Rate limiting
- CORS

Errors use a stable envelope so the frontend can handle them predictably:

```json
{
  "success": false,
  "message": "The submission deadline has passed.",
  "code": "SUBMISSION_DEADLINE_PASSED"
}
```

Validation failures group field errors:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "projectName": ["Project name is required."]
  }
}
```

---

## 5. Frontend Architecture

### Feature-based React layout

```text
src/
├── app/
│   ├── router/
│   ├── providers/
│   └── store/
├── components/
│   ├── ui/
│   ├── forms/
│   ├── layout/
│   └── common/
├── features/
│   ├── auth/
│   ├── teams/
│   ├── members/
│   ├── tracks/
│   ├── submissions/
│   ├── judging/
│   └── admin/
├── pages/
│   ├── public/
│   ├── participant/
│   ├── judge/
│   └── admin/
├── services/
│   ├── api.ts
│   ├── auth.ts
│   ├── teams.ts
│   ├── tracks.ts
│   ├── submissions.ts
│   └── judging.ts
├── hooks/
├── schemas/
├── types/
├── i18n/
└── utils/
```

### Route tree

```text
/
├── /challenge
├── /citizen-science
├── /tracks
├── /judging
├── /requirements
├── /timeline
├── /faq
├── /register
├── /activate
├── /login
├── /dashboard
│   ├── /team
│   ├── /members
│   ├── /submission
│   └── /settings
├── /judge
│   ├── /dashboard
│   ├── /submissions
│   └── /submissions/:id
└── /admin
    ├── /dashboard
    ├── /teams
    ├── /teams/:id
    ├── /submissions
    ├── /submissions/:id
    ├── /judges
    ├── /evaluations
    ├── /settings
    ├── /exports
    └── /audit-logs
```

Frontend route guards improve UX (hide admin from participants). **They do not replace API authorization.** A participant must never read another team by changing `/api/v1/teams/{teamId}`.

### Visual system

The UI should feel **astronomy / scientific**, not a generic SaaS admin:

- Dark space background, stars, blue/violet/cyan glow
- Glassmorphism cards, soft borders, subtle motion
- High readability; effects never block forms or tables
- Responsive from mobile through large desktop, especially registration, login, dashboard, submission, uploads, admin tables, and judge scoring

---

## 6. Data Model

### Core relationships

```text
User 1 ──── * TeamMembers
Team 1 ──── * TeamMembers
Team * ──── 1 Track
Team 1 ──── * Submissions
Submission 1 ──── * SubmissionFiles
Judge 1 ──── * JudgeAssignments
Submission 1 ──── * JudgeAssignments
Submission 1 ──── * Evaluations
Judge 1 ──── * Evaluations
```

Simplified ERD:

```text
                         ┌──────────────┐
                         │    Users     │
                         └──────┬───────┘
                                │
                     ┌──────────┴──────────┐
                     ▼                     ▼
                TeamMembers              Judges
                     │
                     ▼
                  ┌─────────┐
                  │  Teams  │
                  └────┬────┘
             ┌─────────┼─────────┐
             ▼         ▼         ▼
           Track   Registration Submission
                                  │
                                  ▼
                           SubmissionFiles
                                  │
                                  ▼
                             Evaluations ◄── Judge
```

### Main tables

**Users** (Identity may add extra columns and tables)

| Field | Notes |
| --- | --- |
| Id, Email, PasswordHash | Auth |
| FirstName, LastName, Phone | Profile |
| IsActive, EmailConfirmed | Activation gate |
| CreatedAt, UpdatedAt | Audit |

**Teams**

| Field | Notes |
| --- | --- |
| Id | Internal PK |
| TeamCode | Public unique ID, e.g. `EXP-2026-00482`, generated server-side |
| Name, LeaderId | Identity |
| University, Organization, City, TechnicalLevel | Registration profile |
| TrackId, Status | Track + registration/lifecycle status |
| CreatedAt, UpdatedAt | |

**TeamMembers** — `TeamId`, `UserId`, `Role`, `Skill`, `JoinedAt`

**Tracks** — bilingual names/descriptions (`NameEn`/`NameAr`, `DescriptionEn`/`DescriptionAr`), `Code`, `Difficulty`, `IsActive`. Tracks are **database-driven**, not hardcoded in the UI, so admins can edit copy or deactivate a track without a frontend deploy.

**Registrations** — optional if `Teams.Status` already covers it. Avoid duplicating the same state.

**Submissions**

| Field | Notes |
| --- | --- |
| SubmissionCode | Public ID, e.g. `SUB-2026-00182` |
| TeamId, TrackId, ProjectName | Identity |
| Abstract, ProblemDescription, SolutionDescription | Narrative |
| GithubUrl, DemoUrl | Links |
| Limitations, AIUsage | Required disclosures |
| Status, SubmittedAt | Lifecycle; timestamp is **server time** |

**SubmissionFiles** — metadata only: `FileType`, `OriginalFileName`, `StorageKey`, `MimeType`, `FileSize`, `UploadedAt`

**Judges** — can be a User with the Judge role; extra table if specialization/active flag is needed.

**JudgeAssignments** — `JudgeId`, `SubmissionId`, `AssignedAt`, `AssignedBy`

**Evaluations** — per-criterion scores, `TotalScore` (computed on the backend), `Comments`, timestamps

**ChallengeSettings** — registration/submission windows, `MaxTeamMembers`, feature flags, `UpdatedBy`/`UpdatedAt`. Organizers change competition rules without a code change.

**AuditLogs** — `UserId`, `Action`, `EntityType`, `EntityId`, `OldValue`, `NewValue`, `IpAddress`, `CreatedAt`

### Indexing

Index hot lookup fields, including uniqueness where required:

`Users.Email`, `Teams.TeamCode`, `Teams.LeaderId`, `Teams.TrackId`, `Submissions.SubmissionCode`, `Submissions.TeamId`, `Submissions.TrackId`, `Submissions.Status`, `Submissions.SubmittedAt`, `Evaluations.SubmissionId`, `Evaluations.JudgeId`, `JudgeAssignments.JudgeId`, `JudgeAssignments.SubmissionId`

---

## 7. Object Storage and Files

PostgreSQL must **not** store binary project files. Large blobs inflate DB size, backups, and cost. Object storage holds the bytes; PostgreSQL holds the pointer.

```text
PostgreSQL          Object Storage
    │                     │
    └── File metadata     ├── Reports
                          ├── Notebooks
                          ├── ZIP files
                          ├── Presentations
                          └── Other files
```

The bucket is **private**. Downloads go through the API: authenticate, authorize, then issue a short-lived signed URL (or stream through the backend).

### Upload path

```text
Participant → React → ASP.NET Core API
                         ├── Authenticate
                         ├── Authorize
                         ├── Validate type / size
                         └── Upload
                                ▼
                          Object Storage
```

Backend validation covers extension, MIME type, size, filename, auth, authorization, submission status, and deadline.

**Initial size limits** (final numbers with organizers):

| Type | Limit |
| --- | --- |
| PDF | 20 MB |
| Notebook | 20 MB |
| ZIP | 500 MB |
| PPTX | 50 MB |
| Images | 10 MB |

Allowed types (configurable): PDF, ZIP, IPYNB, MD, PPTX, PPT, CSV, PNG, JPG, JPEG.

Malware scanning is recommended if infrastructure allows it.

---

## 8. Users, Roles, and Access Control

Three primary roles. Authorization is **role-based and resource-based**.

### Participant

Register a team, activate, log in, view/edit own team, manage members, select a track, create/save/submit a project, upload files, view submission status.

**Cannot** see another team’s private data by ID guessing.

### Judge

Log in, see **assigned** submissions only, view project info, download permitted files, score, comment, submit evaluations.

**Cannot** manage users, change registrations or deadlines, touch unrelated submissions, or use admin settings.

### Admin

Full management: users, teams, members, tracks, judges, assignments, registrations, submissions, files, statuses, challenge settings (open/close registration and submissions), evaluations, CSV/Excel export, audit logs.

Frontend:

| Surface | Who |
| --- | --- |
| Public pages | Anyone |
| `/dashboard/*` | Participant |
| `/judge/*` | Judge |
| `/admin/*` | Admin |

---

## 9. Authentication and Account Activation

**Stack:** ASP.NET Core Identity + JWT.

A team is not fully usable until the leader confirms email.

```text
Register Team → Create Team + Leader Account (Pending)
→ Send Activation Email → Leader clicks link
→ Account Activated → Login → Team Dashboard
```

The activation URL carries a **server-generated, hard-to-guess, time-limited, single-use token**, invalidated after success.

Login:

```text
React (email + password) → API validates → issues access token → React
```

Protected calls send `Authorization: Bearer <access-token>`. Refresh tokens are part of the security baseline; logout and account-status checks apply on every sensitive operation.

---

## 10. Team Registration

A **six-step wizard**:

1. **Team** — name, university/org, city, technical level, track
2. **Leader** — name, email, phone, password + confirm
3. **Members** — name, email, role/skill
4. **Links** — GitHub, portfolio
5. **Agreements** — challenge rules and data-usage policy
6. **Confirmation** — Team ID (e.g. `EXP-2026-00482`) and “check your email”

Default business rule: **one person, one team**. A team has exactly one active track.

Registration statuses: `Registered`, `Confirmed`. Optional extras: `Pending`, `Rejected`, `Cancelled`.

After login, the dashboard shows Team ID, name, leader, members, track, registration and submission status, deadlines, progress, and actions (edit team, manage members, submit project).

Registration is allowed only when:

```text
CurrentTime >= RegistrationStart
AND CurrentTime <= RegistrationEnd
AND RegistrationEnabled = true
```

---

## 11. Challenge Tracks

Six tracks, stored in the database so copy and availability can change without a frontend release.

| Code | Name | Difficulty | Focus |
| --- | --- | --- | --- |
| A | Transit Hunter | Beginner | Transit signals, light curves, interpretation |
| B | Analyst | Intermediate | Cleaning, analysis, parameter extraction |
| C | ML Classifier | Intermediate / Advanced | ML, candidate classification, features |
| D | Advanced / FITS | Advanced | FITS, images, metadata, photometry |
| E | Discovery Tool | Advanced | Dashboards, analysis, visualization, reporting |
| F | AI Challenge | Advanced | Signal detection, AI-assisted analysis, false-positive reduction |

---

## 12. Public Website

Public routes: `/`, `/challenge`, `/citizen-science`, `/tracks`, `/judging`, `/requirements`, plus `/faq`, `/contact`, `/timeline`. A later `/projects` gallery is allowed only when an admin marks a project publishable (`IsPublic` or `PublicationStatus`: Private → Approved → Published).

**Landing page** — cinematic astronomy design: Andromeda Iraqi Team, bilingual title, CTAs (Register, Challenge Brief, Submit Project), tracks, timeline, judging criteria, required outputs, FAQ, Hack4Dev qualification.

**Challenge page** — scientific problem: exoplanets, transits, light curves. When a planet crosses its star, brightness can dip by less than 1%; that dip is the measurable signal.

**Citizen science** — Observation → Data → Analysis → Signal → Scientific Result, kept accessible for beginners.

**Judging page** — public criteria and weights. **Actual scores stay hidden until official results.**

**Required outputs** — organized repo, notebook where applicable, README, visualization, reproducible steps, short pitch, limitations/uncertainty, error analysis, AI usage disclosure.

---

## 13. Project Submission Lifecycle

Authenticated form: project name, track, abstract, problem, solution, GitHub (required), demo URL, README, notebook, final report PDF, presentation, visualization, limitations, AI disclosure. The UI must distinguish required vs optional fields/files.

Teams can **save drafts** and return later.

### Status machine

```text
Draft → Submit Project → Submitted → Under Review
                                         │
                          ┌──────────────┴──────────────┐
                          ▼                             ▼
                       Judged                       Finalist
```

Status is owned by the backend, not the client.

### Final submit

```text
Auth → Team status → Submission period → Deadline
→ Required fields → Required files → File validation
→ Generate Submission ID → Status = Submitted
→ Server timestamp → Lock → Confirmation email
```

Example confirmation: `SUB-2026-00182` at a **server** timestamp.

### Deadlines and locking

The frontend may hide Submit after the deadline. The **API still rejects** using server time.

After deadline (or after final submit, per lock policy), participants cannot edit info, replace/delete files, or submit a new version unless an admin reopens the submission.

Only an authorized team member can mutate that team’s submission.

---

## 14. Judging and Scoring

Official scorecard (backend is authoritative):

| Criterion | Weight |
| --- | --- |
| Scientific problem understanding | 10% |
| Data processing quality | 15% |
| Analysis / model | 20% |
| Accuracy / validation | 15% |
| Scientific interpretation | 15% |
| Innovation | 10% |
| Usability | 5% |
| Presentation / communication | 10% |
| **Total** | **100%** |

```text
TotalScore =
    ScientificUnderstanding * 0.10
  + DataProcessing * 0.15
  + AnalysisModel * 0.20
  + AccuracyValidation * 0.15
  + ScientificInterpretation * 0.15
  + Innovation * 0.10
  + Usability * 0.05
  + Presentation * 0.10
```

The frontend scorecard is a convenience. The API computes `TotalScore`.

**Judge workflow:** Admin creates judge → assigns submissions → judge logs in → reviews assigned work → downloads permitted files → scores + comments → submits evaluation.

A judge cannot edit a submitted evaluation unless an admin reopens it.

**Multiple judges:** one submission can have several evaluations. Default aggregation is the **average**, but the formula is configurable for organizers.

---

## 15. Administration

### Dashboard

Counts for teams, members, submissions; track distribution; status breakdowns; recent registrations and submissions.

### Team management

Search and filter by track, status, university, city. Open a team, view members, edit, confirm or reject registration. Detail view includes Team ID, leader contact, track, city, dates, registration and submission status.

### Submission management

Search/filter, open project, download files, assign judges, view evaluations, change status (`Draft`, `Submitted`, `Under Review`, `Judged`, `Finalist`).

### Challenge settings (runtime, not code)

Registration and submission on/off, open/close dates, max team size, allowed tracks, allowed file types and sizes, judge scoring enabled.

### Export (CSV and Excel)

- **Teams** — ID, name, leader, email, phone, university, city, track, member count, status, date
- **Submissions** — ID, team, project, track, status, submitted at, GitHub, demo
- **Evaluations** — team, submission, judge, individual scores, total, date

### Audit log

Competition integrity: who changed what. Example: admin changed submission status from Submitted to Under Review, with timestamp and IP.

---

## 16. Notifications, Localization, and UX

### Email (SMTP from the backend)

| Stage | Messages |
| --- | --- |
| Registration | Received, account activation, confirmed |
| Submission | Received, confirmation |
| Review | Under review, finalist |

### Internationalization

One React app, not two sites. Toggle `EN | العربية`. English `dir="ltr"`, Arabic `dir="rtl"`. Localization covers nav, buttons, forms, validation, notifications, dashboards, tables, challenge copy, tracks, judging criteria, and system messages.

---

## 17. API Surface

Versioned base path: `/api/v1/`

### Auth

```http
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/activate
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
```

### Teams (current user)

```http
POST   /api/v1/teams
GET    /api/v1/teams/me
PUT    /api/v1/teams/me
GET    /api/v1/teams/me/members
POST   /api/v1/teams/me/members
PUT    /api/v1/teams/me/members/{id}
DELETE /api/v1/teams/me/members/{id}
```

### Tracks

```http
GET /api/v1/tracks
GET /api/v1/tracks/{id}
```

### Submissions

```http
POST   /api/v1/submissions
GET    /api/v1/submissions/me
GET    /api/v1/submissions/{id}
PUT    /api/v1/submissions/{id}
POST   /api/v1/submissions/{id}/submit
POST   /api/v1/submissions/{id}/files
DELETE /api/v1/submissions/{id}/files/{fileId}
```

### Judge

```http
GET  /api/v1/judge/submissions
GET  /api/v1/judge/submissions/{id}
POST /api/v1/judge/submissions/{id}/evaluation
PUT  /api/v1/judge/submissions/{id}/evaluation
```

### Admin

```http
GET  /api/v1/admin/dashboard
GET  /api/v1/admin/teams
GET  /api/v1/admin/teams/{id}
GET  /api/v1/admin/submissions
GET  /api/v1/admin/submissions/{id}
GET  /api/v1/admin/users
POST /api/v1/admin/judges
POST /api/v1/admin/assignments
PUT  /api/v1/admin/teams/{id}/status
PUT  /api/v1/admin/submissions/{id}/status
GET  /api/v1/admin/evaluations
GET  /api/v1/admin/export/teams
GET  /api/v1/admin/export/submissions
GET  /api/v1/admin/export/evaluations
GET  /api/v1/admin/settings
PUT  /api/v1/admin/settings
GET  /api/v1/admin/audit-logs
```

---

## 18. Business Rules

These live in the **application layer**, not in React.

| Rule | Enforcement |
| --- | --- |
| Registration window | Server time vs settings flags and dates |
| One team per participant | Default; changeable if organizers allow |
| One active track per team | Required |
| Submission ownership | Only authorized team members |
| Deadline | Reject late submits; do not rely on hidden UI |
| Lock | Locked after deadline; only admin reopens |
| Judge scope | Assigned submissions only |
| Evaluation immutability | No edit after submit unless admin reopens |
| Scores | Backend weighted total; frontend is not truth |
| IDs | TeamCode and SubmissionCode generated server-side |

---

## 19. Security

### Authentication

Secure password hashing via Identity, JWT access tokens, refresh tokens, email activation, account status checks.

### Authorization

Roles plus resource checks (team ownership, judge assignment). Never “if the URL has an ID, return it.”

### API

HTTPS, tight CORS (dev `http://localhost:5173`, prod via env, e.g. `https://exoplanet.example.com`), rate limiting on login, registration, activation, password reset, uploads, and public APIs, request validation, sanitization, global exception handling.

### Files

MIME + extension + size, private bucket, authorized download, optional malware scan.

### Secrets and data

Connection strings, JWT, SMTP, and storage keys in **environment variables**, never Git. Parameterized queries via EF Core. Regular backups. Logs must not contain passwords, JWT secrets, tokens, or file contents.

### Privacy

Emails, phones, member lists, accounts, private files, and judge evaluations stay off public pages unless administration explicitly publishes them.

---

## 20. Deployment, Operations, and Scale

### Production shape

```text
Internet → HTTPS → Nginx
                    ├── /     → React
                    └── /api  → ASP.NET Core
                                    ├── PostgreSQL (prefer managed)
                                    ├── Object Storage
                                    └── SMTP
```

Same-origin API (`https://exoplanet.example.com/api`) simplifies CORS vs a separate `api.` host.

Docker Compose can run frontend, backend, and Nginx. PostgreSQL may be Docker, a separate server, or managed.

### CI/CD

Branches: `main`, `develop`, `feature/*`.

```text
Push → GitHub Actions → Frontend build + tests
                      → API build + tests
                      → Docker images → Deploy
```

### Backups and monitoring

Daily DB backups plus retention; manual backup before major changes; object-storage versioning where possible. Log logins, registration, activation, submissions, uploads, judge assignment, evaluations, admin status changes. Add production monitoring when feasible.

### Scale path (no rewrite)

Expected load does not justify microservices. If traffic grows, put a load balancer in front of multiple API instances sharing one PostgreSQL and object storage. Files already sit off the API process.

---

## 21. Testing Strategy

| Layer | What to prove |
| --- | --- |
| Backend unit | Score formula, registration/deadline/membership rules, submission and file validation, authorization |
| Integration | Auth, DB, registration, teams, submission, file metadata, judging, admin permissions |
| Frontend | Forms, wizard, login, route guards, dashboards, upload UI, scorecard, admin tables, EN/AR |
| E2E | Full path from register → activate → submit → admin assign → judge score → admin sees total |

Example deadline unit test: deadline 20:00, submit at 20:01 → API rejects, submission unchanged.

---

## 22. Development Phases

Build incrementally:

1. **Foundation** — repos, React, API, PostgreSQL, EF, Identity, JWT, Swagger, layering
2. **Public website** — landing through FAQ, responsive, bilingual
3. **Auth and registration** — wizard, activation, Team ID, members, track
4. **Participant dashboard** — team, members, track, statuses, deadlines
5. **Submission** — draft, files, validation, ID, timestamp, lock, email
6. **Admin** — dashboard, teams, tracks, submissions, settings, export, audit
7. **Judging** — accounts, assignment, review, scorecard, comments
8. **Security and testing** — authz, rate limits, file security, unit/integration/E2E
9. **Deployment** — Docker, Nginx, HTTPS, domain, storage, SMTP, CI/CD, backups, monitoring

---

## 23. End-to-End Platform Workflow

```text
PUBLIC WEBSITE
      │
      ├── Challenge / Tracks / Judging
      ▼
Register Team → Leader Account → Activation Email → Activate → Login
      ▼
Team Dashboard → Manage Team + Select Track
      ▼
Create Submission → Save Draft → Upload Files → Complete → Submit
      ▼
Submission ID → LOCKED AFTER DEADLINE
      ▼
ADMIN reviews → assigns judges
      ▼
JUDGE reviews → scores → submits evaluation
      ▼
ADMIN reviews finals → Finalists → Official Results
```

---

## 24. Architecture Decisions (Baseline)

These are the implementation defaults:

1. React.js + TypeScript frontend, Vite build.
2. ASP.NET Core Web API, C#, .NET 10.
3. PostgreSQL + Entity Framework Core.
4. ASP.NET Core Identity + JWT.
5. Role-based and resource-based authorization.
6. S3-compatible object storage for files; PostgreSQL for metadata only.
7. SMTP for activation and system mail.
8. REST over HTTPS; frontend never accesses the database.
9. Modular monolith / Clean Architecture — not microservices.
10. React Router, TanStack Query, React Hook Form + Zod, Tailwind, i18next.
11. Docker + Nginx; GitHub + GitHub Actions.
12. Audit logs for administrative actions.
13. Server-side deadline and status enforcement; backend owns scores.
14. Private object storage and secure downloads.
15. Arabic RTL and English LTR from day one; responsive across devices.
16. Scale later with extra API instances, not a platform rewrite.

---

## Summary

EXOPLANET DATA CHALLENGE is a **bilingual competition platform**: public science site, team registration, participant workspace, judging, and administration.

```text
React + TypeScript
        │ REST
        ▼
ASP.NET Core Web API  ──► PostgreSQL (relational state + file metadata)
                      ──► Object Storage (project files)
                      ──► SMTP (email)
```

The architecture trades **simplicity and security** for microservice overhead, while still allowing horizontal API scale, runtime challenge settings, and a clear path from first registration to official results.
