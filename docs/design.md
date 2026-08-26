# UI/UX Guidelines & Design System

## 1. UI/UX

- The product is an operations CRM, so screens prioritize scanning, filtering, comparison, and repeated data entry over marketing content.
- Admin and employee pages use a fixed left navigation on desktop and an off-canvas/sidebar pattern on smaller screens.
- Primary workflows are exposed through Dashboard, Agents, Bookings, Booking Query, Query History, Employees, Accounts, Hotel Listings, and Room Manager.
- Tables support dense operational data, filters, responsive horizontal scrolling, status badges, and action controls.
- Panels/cards use clear borders, restrained shadows, rounded corners, and consistent spacing.
- Modals are used for detail views and editing rather than navigating away from an operational list.
- Loading, empty, validation, error, and success states are present in the major AJAX flows.
- Mobile layouts reduce padding, expose the menu button, allow table scrolling, and keep controls usable at touch size.
- Respect `prefers-reduced-motion`; shared CSS reduces animation and transition durations when requested.

## 2. Color & Theme

The project has several page-local token sets, unified visually by the shared modern layer.

| Purpose | Primary observed value |
|---|---|
| Primary brand | `#4f46e5` |
| Primary dark | `#4338ca` / shared `#1d4ed8` |
| Secondary/accent teal | `#0f766e` |
| Accent cyan | `#06b6d4` |
| Success | `#10b981` / shared `#15803d` |
| Warning | `#f59e0b` / shared `#b45309` |
| Danger | `#ef4444` / shared `#b91c1c` |
| Background | `#f8fafc` / shared `#f4f7fb` |
| Alternate background | `#f1f5f9` |
| Surface/light | `#ffffff` |
| Border | `#e2e8f0` / shared `#dbe3ef` |
| Main text | `#0f172a` / shared `#172033` |
| Secondary text | `#475569` |
| Muted text | `#94a3b8` / shared `#64748b` |
| Dark navigation | gradient from `#0f172a` to `#1e293b` |

There is no implemented dark-mode toggle. A few pages use teal, amber, coral, or navy local tokens, but the dominant shared application palette is light surfaces with navy navigation and blue/cyan accents.

## 3. Fonts & Typography

- **Font family:** Inter from Google Fonts, with local/system fallbacks such as `Segoe UI` and `system-ui`.
- **Body:** generally `13px` to `14px`, depending on page.
- **Page headings:** approximately `1.25rem` to `1.5rem`, typically `font-weight: 700` or `800`.
- **Section headings:** approximately `1rem` to `1.25rem`, typically `600` to `800`.
- **Table/control text:** approximately `.72rem` to `.88rem`; table headings are uppercase with modest letter spacing.
- **Weights:** 300-800 are loaded on pages; normal operational text uses 400-600 and emphasis uses 650-800.
- **Shape:** cards commonly use 12px-20px radii; controls commonly use 10px radii; badges use pill radius.

## 4. Memory (UI Preferences)

- Authentication state is stored in PHP session variables, not as the primary UI theme state.
- Sidebar open/close state is transient DOM state and is not persisted.
- There is no theme toggle or dark-mode persistence implementation.
- `listing.php` uses `localStorage` only for a one-time hotel-manager toast message/type across navigation.
- `assets/js/app.js` contains an older `localStorage` user-auth approach, but its `/router.php/api` backend is not present in the repository and it is not the verified active authentication path.
- Query history filters and search values are page-local DOM state and are not persisted.
- Any future persisted preference must use a namespaced key, validate stored values, and avoid storing credentials or sensitive customer data.
