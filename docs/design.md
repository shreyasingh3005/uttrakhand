# UI/UX Guidelines & Design System

## 1. UI/UX

- The product is an internal operations CRM, so screens prioritize scanning, search, tables, filters, status badges, and direct actions over marketing content.
- Admin navigation is sidebar-led with dashboard, agents, booking query, query history, hotel listings, employees, accounts, and bookings.
- Employee navigation is a dashboard workspace with sections for dashboard, agents, bookings, booking query, add agent, create booking, and query history.
- Forms use grouped fields, labels, validation feedback, inline agent lookup, date inputs, occupancy controls, room selection, and clear success/error toasts or alerts.
- Hotel query results are room-option rows/cards with property, category, availability, nightly rate, stay estimate, meal plans, and selection controls.
- Common operations include search, reset, filter, view, copy, WhatsApp share, lock/unlock, CRUD actions, and CSV download.
- Responsive CSS collapses sidebars and adjusts tables/cards at approximately 992px and 576px breakpoints. Tables should remain horizontally usable on small screens.
- Keep one clear primary action per panel, preserve stable controls, and avoid hiding authorization decisions in client-side code.
- Customer quotation text is a separate plain-text experience and must use the shared formatter rather than page-specific templates.

## 2. Color & Theme

The codebase has several compatible token layers. The common operational palette is:

| Token | Hex / value | Use |
|---|---|---|
| Primary / Brand | `#4f46e5` | Main actions, active controls, focus states |
| Primary dark | `#4338ca` | Hover and pressed brand states |
| Secondary / Navy | `#0f172a` | Sidebar, headings, dark navigation |
| Secondary light | `#1e293b` | Sidebar gradients and dark surfaces |
| Accent cyan | `#06b6d4` | Secondary highlights and query/history accents |
| Accent teal | `#0d9488` / `#0f766e` | Profile and operational accents |
| Success | `#10b981` | Positive status, paid/active/success indicators |
| Warning | `#f59e0b` | Pending, attention, lock or caution states |
| Danger | `#ef4444` | Error, cancellation, destructive actions |
| Background | `#f8fafc` | Main page background |
| Alternate background | `#f1f5f9` | Inputs, muted surfaces, table states |
| Surface | `#ffffff` | Panels, cards, forms |
| Border | `#e2e8f0` | Field, panel, and table borders |
| Text | `#0f172a` | Primary text |
| Secondary text | `#475569` | Supporting text |
| Muted text | `#64748b` / `#94a3b8` | Metadata and placeholders |

The login page uses a dark navy background with indigo/cyan gradients and a light authentication pane. There is no implemented dark-mode toggle or separate persisted dark palette.

## 3. Fonts & Typography

- Primary family: `Inter`, loaded from Google Fonts, with local system fallbacks.
- Body text: generally 13px in admin operational pages; compact controls commonly use `.82rem` to `.95rem`.
- Page headings: typically 1.25rem to 1.6rem and bold/extra-bold.
- Login/promotional heading: responsive `clamp()` sizing from approximately 1.8rem to 2.8rem.
- Table labels: uppercase, compact, approximately `.68rem` to `.75rem`, with letter spacing.
- Common weights: 400 body, 500/600 labels and navigation, 700/800 headings and KPI values.
- Existing CSS uses rounded panels/cards from roughly 8px to 20px and shadows for hierarchy; preserve local page conventions when extending a screen.

## 4. Memory (UI Preferences)

- Sidebar open/close state is runtime DOM state controlled by `assets/js/admin-sidebar.js` and shared page listeners; it is not persisted to localStorage.
- Employee section visibility is runtime state controlled by `showSection()` and `.view-section.active`.
- Search/filter values are page-local DOM state and generally reset on reload; no general preference store is implemented.
- Query results and selected rows are held in JavaScript variables such as result stores and are not persisted until the history-save action.
- Theme preferences are not persisted because there is no implemented theme toggle or localStorage theme contract.
- Authentication identity and role are persisted in the PHP session, not browser storage.
- When adding UI state persistence, use a documented, namespaced key and ensure stale/unauthorized data cannot affect server-side behavior.
