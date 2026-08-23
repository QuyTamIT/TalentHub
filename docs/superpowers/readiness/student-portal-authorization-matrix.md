# Student Portal Four-Role Authorization Matrix

This matrix is executable evidence from `tests/student_portal_four_role_e2e_mysql_test.php`. Every actor is de-identified and exists only in the allow-listed Phase 11 disposable schema.

## Role permission checks

| Actor | Expected allowed permission | Result | Expected forbidden permission | Denial |
|---|---|---|---|---|
| Student A | `student_profile.read_own` | allowed | `activity.create_managed` | `403 PERMISSION_DENIED` |
| Student B | `checkin.create_own` | allowed | `school_dashboard.read_own` | `403 PERMISSION_DENIED` |
| Teacher A | `activity.create_managed` | allowed | `internship_post.create_own_business` | `403 PERMISSION_DENIED` |
| Teacher B | `assessment.update_managed` | allowed | `student_profile.update_own` | `403 PERMISSION_DENIED` |
| School A | `school_dashboard.read_own` | allowed | `checkin.create_own` | `403 PERMISSION_DENIED` |
| School B | `student_profile.read_own_school` | allowed | `internship_application.review_own_business` | `403 PERMISSION_DENIED` |
| Enterprise A | `internship_post.create_own_business` | allowed | `assessment.update_managed` | `403 PERMISSION_DENIED` |
| Enterprise B | `internship_application.review_own_business` | allowed | `school_dashboard.read_own` | `403 PERMISSION_DENIED` |

## Organization ownership checks

| Resource | Positive owner | Negative actor | Expected result |
|---|---|---|---|
| School A write scope | School A admin | School A admin targeting School B | `403 FORBIDDEN` |

## Evidence contract

- Eight actor user IDs are distinct.
- Student, Teacher, School, and Enterprise each have exactly two actors.
- School A and School B have separate school/class ownership.
- Enterprise A and Enterprise B have separate enterprise membership.
- Current executable totals: 9 positive checks and 9 denial checks.
- Resource-specific activity, QR, assessment, application, notification, and learner-owner checks are added to this matrix only when their corresponding E2E assertions pass.

