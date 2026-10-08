# SkillSpring — HR Training Program UI Starter

## Run locally (XAMPP)
1. Extract this folder into `C:\xampp\htdocs\company-training-ui`.
2. Start Apache in XAMPP.
3. Open `http://localhost/company-training-ui/public/`.

Or use PHP CLI from the extracted project root:
`php -S localhost:8080 -t public`

## What's included
- PHP partials for **sidebar**, **header**, **layout**, and **training content**.
- Dedicated layout and training CSS, responsive light green/white interface.
- Frontend date-range + weekday + count recurring schedule generator.
- Review modal and demo-only publish action saved in **browser localStorage**.
- Validation for reversed date ranges, unavailable recurring days, incorrect times, and count.
- Lucide icons and Google Fonts use CDNs and require connectivity; the layout still works offline with fallback fonts.

## Important
This is a **UI prototype**, not a production application. No login, roles, PHP POST persistence, email delivery, database storage, conflict checks, or employee workflows have been implemented. Demo publish stores only in the current browser. Sidebar links besides Training Programs are placeholders.

Recommended next stage: database schema for `training_programs`, `training_sessions`, instructors and room allocations; server-side validation, CSRF, authentication and role-based authorization.

## GitHub + Railway deployment

Upload the **contents of this folder** to the repository root, so `Dockerfile`, `public/`, and `app/` all appear in the root. Do not nest the entire folder inside a second directory.

Railway: New Project -> Deploy from GitHub repo -> select repository -> leave Root Directory blank -> deploy. Railway detects the root Dockerfile. In service Settings -> Networking, generate a public domain. The Docker container serves `public/` on Railway's assigned `PORT`.

This is a front-end demonstration. Training data is currently browser-only; do not use it for real employee data until authentication, access control, and a persistent database are implemented.
