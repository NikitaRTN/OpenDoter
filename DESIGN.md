# OpenDoter UI reference

The visual reference is `/matches/{id}/overview`. New screens must extend this style, not introduce a separate theme.

- Palette, surfaces, borders, spacing, radii and table geometry are defined in `css/root.css`.
- Fonts and controls inherit `css/base.css`; do not set a page-specific font family.
- Tables follow `css/overview.css`: flat dark panel, container-colour header, uppercase muted column labels, compact rows, blue player links, 48 x 27 hero portraits.
- Reuse semantic variables for Radiant, Dire, gold, links and hover. Do not add alternative pastel versions of those colours.
- Panels are flat, subtly bordered, radius 8px. Controls use radius 4px; no decorative gradients or accent stripes.
- Preserve data hierarchy, disclosures, filters, accessible focus and touch targets. On narrow screens, wide tables scroll inside their region and summary tables may become labelled rows.
- `match-insights.css` owns only damage/teamfight layouts; `profile-workspace.css` owns profile layouts. Both consume the shared overview tokens.
- Check overview (reference), damage, teamfights and profile at 1440px and 390px after any shared-style change. Test open/closed disclosures, empty/error states, filters and pagination without reload.
