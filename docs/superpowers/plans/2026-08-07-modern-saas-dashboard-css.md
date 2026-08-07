# Modern SaaS Dashboard CSS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle every existing HR GO page as a consistent Modern SaaS Clean interface in light and dark modes without changing markup, scripts, backend behavior, or dependencies.

**Architecture:** Refactor the existing CSS rules in place. `style.css` owns shared design tokens and cross-application components; `admin.css` consumes those tokens for the admin shell, sidebar, statistics, responsive drawer, and action menus. Browser-computed styles and screenshots provide behavior-level regression coverage because this change is purely visual.

**Tech Stack:** Plain CSS, existing PHP templates, existing theme JavaScript, in-app browser visual inspection

## Global Constraints

- Modify production code only in `assets/css/style.css` and `assets/css/admin.css`.
- Do not modify HTML, PHP, JavaScript, API, database, class names, or interactions.
- Do not use Tailwind CSS, add dependencies, or add a separate override stylesheet.
- Preserve the existing responsive breakpoints and light/dark theme mechanism.
- Preserve existing CSS custom-property names referenced by inline styles and JavaScript.

---

### Task 1: Shared Light-Mode Design System

**Files:**
- Modify: `assets/css/style.css:1-708`

**Interfaces:**
- Consumes: Existing class names in all PHP templates and current custom properties such as `--bg-color`, `--card-bg`, `--primary-color`, `--border-color`, `--shadow-md`, and radius tokens.
- Produces: Shared Modern SaaS tokens and component styling consumed by employee pages, login, and `admin.css`.

- [ ] **Step 1: Capture the failing visual contract in the browser**

Open `http://localhost/hrgogemini/index.php` and run this assertion against the rendered login card and primary button:

```js
const card = document.querySelector('.login-card');
const button = document.querySelector('.btn-primary');
const cardStyle = getComputedStyle(card);
const buttonStyle = getComputedStyle(button);
({
  cardRadius: parseFloat(cardStyle.borderRadius),
  cardShadow: cardStyle.boxShadow,
  buttonRadius: parseFloat(buttonStyle.borderRadius),
  buttonBackground: buttonStyle.backgroundColor,
  passes:
    parseFloat(cardStyle.borderRadius) >= 18 &&
    cardStyle.boxShadow !== 'none' &&
    parseFloat(buttonStyle.borderRadius) >= 999 &&
    buttonStyle.backgroundColor === 'rgb(15, 23, 42)'
});
```

Expected before implementation: `passes` is `false` because the existing radius tokens and blue primary button do not meet the approved visual contract.

- [ ] **Step 2: Refactor shared light-mode tokens**

Update the existing `:root` rules in `assets/css/style.css` to use:

```css
--bg-color: #F8FAFC;
--card-bg: #FFFFFF;
--surface-soft: #F1F5F9;
--primary-color: #0F172A;
--primary-dark: #020617;
--primary-light: #F1F5F9;
--accent-color: #7C3AED;
--accent-light: #F3E8FF;
--border-color: rgba(148, 163, 184, 0.20);
--shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.04);
--shadow-md: 0 12px 32px rgba(15, 23, 42, 0.07), 0 2px 8px rgba(15, 23, 42, 0.04);
--shadow-lg: 0 24px 64px rgba(15, 23, 42, 0.10), 0 8px 24px rgba(15, 23, 42, 0.05);
--radius-sm: 12px;
--radius-md: 18px;
--radius-lg: 24px;
```

Keep all existing semantic variable names and add only the surface/accent tokens needed to remove repeated hard-coded neutrals.

- [ ] **Step 3: Refactor existing shared component rules**

Update existing selectors for `body`, navigation, cards, typography, forms, buttons, badges, tables, clock/check-in UI, quota cards, team cards, modals, login card, uploads, calendar, and summary boxes. Apply these concrete behaviors:

```css
.card { border-radius: var(--radius-md); box-shadow: var(--shadow-md); }
.btn { border-radius: var(--radius-full); min-height: 40px; }
.btn-primary { background-color: var(--primary-color); }
.badge { border-radius: var(--radius-full); }
.form-control { border-radius: var(--radius-sm); background-color: var(--surface-soft); }
.table-responsive { border-radius: var(--radius-sm); }
```

Use pastel semantic surfaces for status components, keep destructive/success actions semantic, and preserve the existing display, positioning, grid, modal, and scrolling behavior.

- [ ] **Step 4: Re-run the light-mode browser contract**

Run the Step 1 assertion again.

Expected after implementation: `passes` is `true`, the login remains usable, and there is no horizontal document overflow at 390px width (`document.documentElement.scrollWidth === document.documentElement.clientWidth`).

- [ ] **Step 5: Commit the shared light-mode design system**

```powershell
git add -- assets/css/style.css
git commit -m "style: modernize shared light theme"
```

### Task 2: Shared Dark Mode and Employee Surfaces

**Files:**
- Modify: `assets/css/style.css:709-1220`

**Interfaces:**
- Consumes: Shared light-mode tokens and existing `[data-theme="dark"]` state set by `assets/js/theme.js`.
- Produces: Accessible dark tokens and dark variants for every shared component without changing theme behavior.

- [ ] **Step 1: Capture the failing dark-mode contract**

On `http://localhost/hrgogemini/index.php`, activate the existing dark theme and run:

```js
const rootStyle = getComputedStyle(document.documentElement);
const cardStyle = getComputedStyle(document.querySelector('.login-card'));
const buttonStyle = getComputedStyle(document.querySelector('.btn-primary'));
({
  pageBackground: getComputedStyle(document.body).backgroundColor,
  cardBackground: cardStyle.backgroundColor,
  buttonBackground: buttonStyle.backgroundColor,
  buttonColor: buttonStyle.color,
  passes:
    rootStyle.getPropertyValue('--bg-color').trim() === '#0F1117' &&
    rootStyle.getPropertyValue('--card-bg').trim() === '#181B23' &&
    buttonStyle.backgroundColor === 'rgb(248, 250, 252)' &&
    buttonStyle.color === 'rgb(15, 23, 42)'
});
```

Expected before implementation: `passes` is `false` because the current dark palette and primary button remain blue.

- [ ] **Step 2: Refactor the existing dark token block and overrides**

Set the existing `[data-theme="dark"]` variables to the approved charcoal system:

```css
--bg-color: #0F1117;
--card-bg: #181B23;
--surface-soft: #20242E;
--primary-color: #F8FAFC;
--primary-dark: #E2E8F0;
--primary-light: #272C36;
--accent-color: #C4B5FD;
--accent-light: #2E2648;
--text-main: #F8FAFC;
--text-muted: #94A3B8;
--border-color: rgba(148, 163, 184, 0.16);
```

Refactor the current dark selectors to consume variables where possible, retain readable pastel semantic badges, remove blue executive gradients from primary navigation, and give primary buttons a light surface with dark text.

- [ ] **Step 3: Verify employee surfaces in both themes**

After signing in with the existing demo account, inspect `employee_home.php`, `leave_form.php`, `profile.php`, and `employee_settings.php` at 390×844. Verify cards have at least 18px radius, active bottom navigation remains visible, check-in buttons remain enabled/disabled correctly, tables scroll within their containers, and no viewport-level horizontal overflow appears.

- [ ] **Step 4: Re-run the dark-mode browser contract**

Run the Step 1 assertion again.

Expected after implementation: `passes` is `true`; text, borders, inputs, badges, tables, modal surfaces, and navigation are distinguishable in dark mode.

- [ ] **Step 5: Commit the dark and employee styling**

```powershell
git add -- assets/css/style.css
git commit -m "style: refine dark and employee surfaces"
```

### Task 3: Admin Dashboard Shell and Components

**Files:**
- Modify: `assets/css/admin.css:1-390`

**Interfaces:**
- Consumes: Tokens from `assets/css/style.css` and existing admin class names.
- Produces: Modern SaaS sidebar, header, stat cards, action menus, and responsive drawer for every `admin/*.php` page.

- [ ] **Step 1: Capture the failing admin visual contract**

Open `http://localhost/hrgogemini/admin/dashboard.php` at 1440×900 and run:

```js
const sidebar = getComputedStyle(document.querySelector('.sidebar'));
const activeLink = getComputedStyle(document.querySelector('.sidebar-link.active'));
const statCard = getComputedStyle(document.querySelector('.stat-card'));
const statIcon = getComputedStyle(document.querySelector('.stat-icon'));
({
  passes:
    parseFloat(statCard.borderRadius) >= 18 &&
    statCard.boxShadow !== 'none' &&
    parseFloat(activeLink.borderRadius) >= 999 &&
    parseFloat(statIcon.borderRadius) >= 999 &&
    sidebar.backgroundColor === 'rgb(255, 255, 255)'
});
```

Expected before implementation: `passes` is `false` because active items and statistic icons are not pill/circular enough and the cards use the old radius/elevation.

- [ ] **Step 2: Refactor the existing admin rules**

Update `admin.css` in place so the sidebar has a clean elevated surface, navigation items use pill geometry, the active item is near-black in light mode and light in dark mode, statistic cards use shared card elevation, statistic icons are circular pastel surfaces, and action menus use the shared surface/radius system.

Preserve these layout contracts exactly:

```css
.sidebar { position: fixed; height: 100dvh; }
.admin-main { margin-left: 240px; }
@media (max-width: 1024px) { .admin-main { margin-left: 0 !important; } }
@media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; } }
```

- [ ] **Step 3: Re-run the desktop admin contract**

Run the Step 1 assertion again.

Expected after implementation: `passes` is `true`, four stat cards fit responsively where space permits, and no sidebar or table overlaps the main content.

- [ ] **Step 4: Verify the existing mobile drawer behavior**

At 390×844, click the existing mobile menu control and assert:

```js
const sidebar = document.querySelector('.sidebar');
const backdrop = document.querySelector('.sidebar-backdrop');
({
  sidebarOpen: sidebar.classList.contains('active'),
  sidebarVisible: getComputedStyle(sidebar).transform !== 'matrix(1, 0, 0, 1, -260, 0)',
  backdropVisible: getComputedStyle(backdrop).visibility === 'visible'
});
```

Expected: all values are `true`; closing the drawer restores hidden state.

- [ ] **Step 5: Commit the admin styling**

```powershell
git add -- assets/css/admin.css
git commit -m "style: modernize admin dashboard surfaces"
```

### Task 4: Cross-Page Verification and Polish

**Files:**
- Modify if verification exposes a defect: `assets/css/style.css`
- Modify if verification exposes a defect: `assets/css/admin.css`

**Interfaces:**
- Consumes: Completed shared and admin styling.
- Produces: Verified CSS-only release with consistent responsive light/dark presentation.

- [ ] **Step 1: Verify CSS parsing in a real browser**

On one employee page and one admin page, run:

```js
const localSheets = [...document.styleSheets].filter(sheet =>
  sheet.href?.includes('/assets/css/style.css') || sheet.href?.includes('/assets/css/admin.css')
);
({
  sheets: localSheets.map(sheet => ({ href: sheet.href, ruleCount: sheet.cssRules.length })),
  allParsed: localSheets.length >= 1 && localSheets.every(sheet => sheet.cssRules.length > 0)
});
```

Expected: `allParsed` is `true`; the admin page reports both local stylesheets.

- [ ] **Step 2: Run PHP syntax checks without modifying PHP**

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Expected: every file reports `No syntax errors detected` and the command exits successfully.

- [ ] **Step 3: Check repository scope and whitespace**

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors; implementation changes are limited to `assets/css/style.css` and `assets/css/admin.css` beyond the approved spec/plan documents.

- [ ] **Step 4: Perform the visual matrix**

Capture and inspect these representative states:

| Page | Viewport | Themes | Required checks |
|---|---:|---|---|
| `index.php` | 1440×900, 390×844 | Light, Dark | centered login card, black/light primary action, no overflow |
| `employee_home.php` | 390×844 | Light, Dark | clock card, check-in actions, summaries, bottom navigation |
| `leave_form.php` | 390×844 | Light, Dark | quota cards, form controls, table scrolling |
| `profile.php` | 390×844 | Light, Dark | profile card, upload controls, forms |
| `admin/dashboard.php` | 1440×900, 390×844 | Light, Dark | sidebar/drawer, stats, table, header controls |
| `admin/reports.php` | 1440×900 | Light, Dark | filters, report tabs, tables/calendar |

Expected: cards, shadows, radii, pastel icons/badges, typography, buttons, focus/disabled states, and navigation match the approved design; no overlap or viewport-level horizontal overflow is visible.

- [ ] **Step 5: Apply minimal CSS-only polish for observed defects**

For each observed defect, first reproduce it at its viewport/theme, record the failing computed style or screenshot, then change only the responsible existing selector in one of the two CSS files and repeat the relevant check.

- [ ] **Step 6: Run final verification and commit any polish**

Repeat Steps 1–4, then:

```powershell
git add -- assets/css/style.css assets/css/admin.css
git commit -m "style: polish responsive SaaS theme"
```

Expected: all verification checks pass and the implementation diff contains no non-CSS production changes.
