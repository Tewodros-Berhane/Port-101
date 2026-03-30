# AGENTS.md

## Project context
This is a Laravel + Inertia + React + TypeScript + Tailwind ERP app with local shadcn-style primitives and token-based theming.

## Frontend redesign source of truth
Use `erp_frontend_redesign_spec.md` as the primary redesign source of truth.

## Working rules
- Do not redesign everything at once.
- Preserve business logic, routes, permissions, and workflows.
- Prefer semantic tokens over one-off Tailwind class changes.
- Keep dense ERP tables usable for power users.
- Do not replace operational tables with decorative cards.
- Use modal vs drawer vs full page exactly as specified in the redesign spec.
- Keep heavy transactional workflows as full pages.
- Improve accessibility, hierarchy, contrast, and keyboard behavior.
- Avoid flashy or gimmicky UI.

## Process rules
- Before editing code, map the requested change to exact files/components.
- Propose a short implementation plan first.
- Make the smallest safe set of changes for the requested phase.
- Run build/lint/test after changes when available.
- Summarize files changed, validation results, risks, and next step.

## Validation
- Dark/light mode must continue to work.
- No random hardcoded colors.
- No broken primitives or layout regressions.
- No permission regressions.
