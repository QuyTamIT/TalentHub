# Learner centered sidebar brand

## Goal

Make the learner sidebar brand visually match the approved school reference
while using the learner subtitle.

## Design

- Replace the learner's wide SVG wordmark with an inline, accessible brand
  lockup: orange rounded-square star icon and the `TalentHub` wordmark.
- Reuse the existing learner-specific `learner-brand__mark` and
  `learner-brand__name` classes rather than coupling the learner UI to school
  classes.
- Center the complete lockup and the subtitle as one visual group in the
  sidebar. The subtitle text is `Khu vực Học sinh`.
- Use a 36px rounded-square icon, the existing orange primary color, and a
  text-aligned subtitle beneath the wordmark; content can wrap rather than be
  clipped on narrow screens.
- Do not change the shared `assets/images/logo.svg` asset or any other role's
  sidebar.

## Verification

- Add a static learner UI assertion for the inline mark, wordmark, learner
  subtitle, and centered CSS alignment.
- Run the focused Node.js regression test and PHP lint for the learner sidebar.
