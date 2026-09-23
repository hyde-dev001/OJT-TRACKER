# Phase 4 Overview UI/UX Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Overview an interpretation and decision-support page instead of a duplicate of Work Hours, Tasks, and Requirements.

**Architecture:** Keep the existing one-request `GET /api/student/overview` contract and backend-authoritative calculations. Add one defensive backend status for genuinely incomplete schedule data, then refactor the existing Overview view and shared status labels without introducing a dashboard framework or new dependency.

**Tech Stack:** Laravel 12, PHP 8.2, Vue 3, Tailwind CSS, Vue Router, Vitest, Vue Test Utils, Playwright smoke script.

**Spec:** `docs/superpowers/specs/2026-09-23-phase-4-smart-assistant-design.md` plus the user-provided Phase 4 Overview UI/UX refactor brief.

## Global Constraints

- Preserve authenticated student scoping, one-request loading, and all valid Phase 4 calculations.
- Work Hours remains the detailed work-record page; Tasks and Requirements remain their detailed pages.
- Vue may format backend values but must not recalculate progress, pace, readiness, or attention priority.
- Hide numeric priority metadata from the student-facing Overview.
- Treat missing/invalid schedule inputs as `pace_unavailable`, not `at_risk`; valid zero remaining days keep the existing status rules.
- Keep the existing light/dark theme, responsive layout, accessible labels/focus, and no new dependencies.
- Do not modify unrelated pages or start Phase 5.

## Review Focus

- A legacy internship with missing work days or expected minutes must not be mislabeled `At risk`; add a backend regression test.
- A valid schedule with zero remaining scheduled days must remain `At risk` or `Deadline passed` according to the existing rules; add a boundary test.
- The Overview must not expose internal priority numbers or raw minutes; add view assertions.
- Readiness must list exact hour/required-requirement blockers and exclude tasks/optional requirements; add view assertions.
- Loading, 404 no-internship, API error, mobile, and dark-mode structures must remain safe and readable; cover the view state and browser smoke.

### Task 1: Guard the unavailable pace state

**Files:**
- Modify: `backend/app/Services/OjtProgressAssistant.php`
- Modify: `backend/tests/Feature/OjtProgressAssistantTest.php`
- Modify: `frontend/src/components/StatusBadge.vue`
- Modify: `frontend/src/components/StatusBadge.test.js`

- [x] **Step 1: Write failing service tests** for missing/invalid schedule inputs returning `pace_unavailable`, while a valid schedule with no remaining days still returns the existing deadline/at-risk status.
- [x] **Step 2: Run the focused backend test and confirm the new case fails.**
- [x] **Step 3: Implement the smallest schedule-validity guard** before normal pace status evaluation and add the readable `Pace unavailable` status label/tone.
- [x] **Step 4: Run the focused backend and status component tests.**

### Task 2: Refactor Overview presentation

**Files:**
- Modify: `frontend/src/views/student/OverviewView.vue`
- Modify: `frontend/src/views/student/OverviewView.test.js`

- [x] **Step 1: Add failing view assertions** for compact progress, header context, current pace emphasis, unavailable pace copy, hidden priorities, actionable attention labels, exact readiness blockers, and ready checklist copy.
- [x] **Step 2: Implement the new hierarchy:** header/context → compact progress → current pace → Needs Attention → Completion Readiness.
- [x] **Step 3: Keep all values sourced from the response**; only format minutes/dates/day labels and map reason/status text for display.
- [x] **Step 4: Run the focused Overview tests and refactor only after green.**

### Task 3: Update durable design guidance

**Files:**
- Modify: `docs/superpowers/specs/2026-09-23-phase-4-smart-assistant-design.md`
- Modify: `DESIGN.md`

- [x] **Step 1: Document Overview as interpretation/decision support** with compact progress, current pace, Needs Attention, and readiness.
- [x] **Step 2: Document hidden priority numbers, exact blockers, no nested period card, and `pace_unavailable` behavior.**

### Task 4: Verify the complete workflow

**Files:**
- Modify: `scripts/phase0_smoke.py` only if assertions need to reflect the refactor.

- [x] **Step 1: Run the full backend suite, full frontend suite, production build, and `git diff --check`.**
- [x] **Step 2: Run browser smoke at desktop/mobile and manually verify light/dark Overview states without changing detail-page ownership.**
- [x] **Step 3: Perform a final read-only review against this plan and the user brief; do not commit.**
