# Learner sidebar and banner consistency

## Goal

Make the learner portal navigation and page introductions consistent with the
other TalentHub role areas.

## Scope

- Move the learner "Đổi vai trò" control from the header to the bottom of the
  sidebar.
- Add a visible "Đăng xuất" control beside it, using the existing
  `/logout.php` endpoint.
- Create one reusable learner page-banner pattern and apply it to the primary
  navigation pages which currently only have a plain page heading:
  profile, aptitude discovery, activities, QR check-in, evaluation, AI
  recommendations, badges, statistics, and "Hoạt động của tôi".
- Keep the dashboard welcome panel and existing specialized detail heroes
  unchanged.
- Align the sidebar subtitle with the wordmark, rather than the logo icon.

## Design

### Sidebar actions

The sidebar remains a vertical flex layout. A footer is placed after the
current level card and is pushed to the lower left by the navigation area.
It contains, in order, the neutral "Đổi vai trò" link and a visually distinct
but non-destructive "Đăng xuất" link. Both controls use the learner icon
system and preserve keyboard focus styles.

The header removes its duplicate role-switch link. Its mobile menu button,
search, notifications, and account trigger are otherwise unchanged.

### Shared page banner

The shared banner is a semantic `<section>` with a page-specific eyebrow,
heading, and short description. It uses the existing orange/blue learner
palette, a subtle gradient, and the existing card radius. It precedes page
content, so it introduces the page without replacing functional cards.

Each route supplies its own copy and an appropriate learner icon. Responsive
styles stack the icon and text without obscuring page content.

### Existing heroes

The dashboard welcome card, ecosystem hero, activity detail, opportunity,
partner, assessment result, and assessment flows already have a purpose-built
intro or task-focused header. They are excluded to avoid duplicated headers.
The profile identity card remains beneath its new page banner because it shows
user data rather than providing a page-level introduction.

### Logo and slogan

The shared SVG remains the source of truth. The logo gets an explicit display
size, and the subtitle left inset matches the wordmark's text start (48px from
the logo's left edge). This removes the present five-pixel visual mismatch.

## Testing

- Add a static UI regression test that confirms the learner header has no
  role-switch, the sidebar contains both actions, and the new shared banner
  appears on all in-scope primary routes.
- Run the focused regression test before and after the implementation.
- Run PHP syntax checks on each modified PHP file and a whitespace diff check.
