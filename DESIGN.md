# FTalentHub Design System

## Visual Theme & Atmosphere

FTalentHub is a warm, trustworthy career-development workspace: clear, calm, and optimistic. Marketing surfaces can be expressive, while learner, teacher, school, enterprise, and admin workspaces stay utility-first and scan-friendly.

## Color Palette & Roles

| Name | Hex | Role |
| --- | --- | --- |
| Brand orange | `#F97316` | Primary actions, active navigation, `Hub` wordmark |
| Brand hover | `#EA580C` | Hover and pressed brand controls |
| Brand soft | `#FFF7ED` | Subtle highlights and selected backgrounds |
| Ink | `#0F172A` | Headings and primary text |
| Muted | `#64748B` | Supporting text and metadata |
| Surface | `#FFFFFF` | Cards, panels, navigation surfaces |
| Canvas | `#F8FAFC` | Page backgrounds |
| Border | `#E2E8F0` | Dividers and control boundaries |

## Typography Rules

Use **Be Vietnam Pro**, falling back to the system sans stack. Body text uses `1rem` with a `1.55` line height. Headings use tight `1.2` line height and a clear weight contrast. The FTalentHub wordmark is `800`; supporting labels are `500` or `600`.

## Component Stylings

- **BrandHeader:** 36px orange gradient mark, 8px radius, 12px gap, `FTalent` in ink/white and `Hub` in brand orange, with a 12px role subtitle.
- **Buttons:** minimum 44px height for frequent actions; use orange only for the primary action, white/outlined controls for secondary actions.
- **Cards and panels:** white surfaces, 12-16px radius, subtle border, and restrained shadow. Use containment only when it helps grouping or scanning.
- **Controls:** shared buttons, text fields, native selects, and textareas use a 44px interaction height, 8px radius, quiet borders, and a visible orange focus ring. Compact controls may use 40px where density requires it.
- **Tables and modals:** tables scroll horizontally rather than clipping columns; modals keep a bounded viewport height, move toward the bottom edge on mobile, and retain full-width actions at narrow widths.
- **Focus:** visible orange outline with a 3px offset; never rely on hover or color alone.

## Layout Principles

Use a 4px spacing base, consistent content rhythm, and a maximum content width near `1200px`. Shared responsive breakpoints are 480px, 768px, and 1024px, but components should adapt to available space first. Keep navigation, page title, filters, and primary action visually distinct. Avoid layout shifts by reserving space for icons, badges, images, and async states.

## Depth & Elevation

Prefer borders and surface contrast over heavy shadows. Use `shadow-sm` for controls and `shadow-md` only for floating menus or important elevated panels.

## Do's and Don'ts

- Do reuse `BrandHeader`, tokens, semantic HTML, and existing portal behavior.
- Do preserve keyboard access, readable contrast, reduced motion, and long-content reflow.
- Don't introduce a second logo path, wordmark spelling, or portal-specific color language.
- Don't add animation, blur, or scroll hijacking unless it supports the task and remains performant.

## Responsive Behavior

Design from available space. At narrow widths, collapse portal navigation using the existing mobile toggles, allow titles and account labels to wrap or truncate safely, keep controls at least 44px where practical, and preserve every primary action.
