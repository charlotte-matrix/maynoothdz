# Accessibility basics

Matrix Starter targets **WCAG 2.1 Level AA**. Accessibility is required from the ground up — structure, interaction, and styling — not as a late pass.

Use this document when building flexi components, templates, forms, and interactive UI. The implementation should be fully accessible and inclusive by design: screen readers, keyboard users, and visually impaired users must be able to use every feature.

---

## Essential requirements

### Semantic structure

- Use semantic HTML (`nav`, `main`, `article`, `section`, `header`, `footer`, `aside`).
- Maintain a logical heading hierarchy (`h1`–`h6`); one `h1` per page; do not skip levels.
- Implement landmark regions so users can navigate by structure (native elements and/or ARIA landmarks where needed).

### Interactive elements

- Ensure full keyboard navigation support (Tab, Shift+Tab, Enter, Space, Escape where appropriate).
- Maintain **visible** focus indicators — never remove focus styles without an equivalent.
- Implement proper focus management for modals, menus, accordions, and dynamic panels (trap focus only when appropriate; always provide escape).
- Support touch and mouse interactions; do not rely on hover-only behavior.

### ARIA implementation

- Add appropriate ARIA landmarks when native semantics are insufficient.
- Include descriptive accessible names (`aria-label`, `aria-labelledby`, visible text in `label in name`).
- Use ARIA states and properties correctly (`aria-expanded`, `aria-controls`, `aria-hidden`, `aria-required`, etc.).
- Implement `aria-live` regions for dynamic content (form errors, success messages, loading states).

### Media and content

- Provide meaningful `alt` text for informative images; use `alt=""` for decorative images.
- Meet minimum color contrast (4.5:1 normal text, 3:1 large text; 3:1 for UI components and focus indicators).
- Support text resizing and zoom up to **200%** without loss of content or function.
- Include captions and transcripts for video/audio where applicable.

### Testing requirements

Before shipping UI changes, verify:

- Screen reader compatibility (VoiceOver, NVDA, or JAWS).
- Keyboard-only navigation through all interactive flows.
- Functionality at **200%** browser zoom (and reflow at 400% where feasible).

---

## Theme accessibility classes

Apply these consistently across the theme. **Every `<button>` and button-styled control should use `.btn`** (or a documented variant) so focus and hover states stay consistent.

### `.btn` (required on buttons)

Defined in `assets/css/app.css`. Uses `:focus-visible` with a visible ring (box-shadow) — do not rely on `:focus` alone.

```css
/* Summary — see assets/css/app.css for full rules */
.btn {
  /* Removes default outline; focus ring applied on :focus-visible */
}
.btn:focus-visible {
  box-shadow: 0 0 0 3px var(--Turquoise-500);
}
```

**Usage:**

```html
<button type="button" class="btn …">Action</button>
<a href="/donate/" class="btn btn-primary …">Donate</a>
```

### `.btn-primary`

Primary CTA styling with gradient background, mint hover ring, and turquoise focus ring. Use for main calls to action.

### `.form-btn`

Form submit buttons receive consistent CTA treatment via `.form-btn` or the global form submit selectors in `app.css`. Prefer explicit `class="form-btn"` on submit buttons when markup allows.

### Tailwind utilities (`tailwind.config.js`)

| Class | Purpose |
|-------|---------|
| `a11y-focus` | Consistent `:focus-visible` ring using `--color-state-focus` |
| `tap-target` | Minimum 44×44px touch target (`THEME_TOKENS.size.touchTarget`) |
| `hocus:` | Variant applying styles on both `:hover` and `:focus-visible` |

**Examples:**

```html
<button class="btn a11y-focus tap-target">Save</button>
<a class="hocus:bg-brand-primary-hover a11y-focus" href="…">Learn more</a>
```

### Forms

- Associate every input with a `<label for="…">` or `aria-labelledby`.
- Use `autocomplete` on personal-data fields (`email`, `name`, `tel`, etc.).
- Mark required fields in text, not color alone; use `required` and `aria-required="true"` where appropriate.
- Surface errors in text near the field and/or in a summary with `role="alert"` or `aria-live`.

### Patterns already in the codebase

Reference these when building similar UI:

- **FAQ accordion** — `aria-expanded`, `aria-controls`, `aria-labelledby`, `role="region"` (see `examples/flexi/faq.php`).
- **Sections** — `aria-labelledby` tied to visible headings.
- **Skip link / landmarks** — ensure `main` and navigation landmarks exist in header/footer templates.

---

## Implementation checklist (per component)

When adding or editing a component, confirm:

- [ ] Semantic HTML and heading order
- [ ] All actions reachable and operable by keyboard
- [ ] `.btn` (or `a11y-focus` + `tap-target`) on interactive controls
- [ ] Visible focus not obscured by sticky headers/overlays
- [ ] Accessible names for icon-only buttons
- [ ] Alt text / decorative image handling
- [ ] Color not the only indicator of state
- [ ] Dynamic updates announced (`aria-live` / `role="status"`)
- [ ] Tested at 200% zoom and with keyboard only

---

## Agent and review prompts

Use these phrases in briefs, PR descriptions, or AI prompts so accessibility stays explicit:

- *Ensure the code follows accessibility best practices throughout.*
- *Emphasize accessibility in all interactions and markup.*
- *Ensure full accessibility compliance (WCAG 2.1 AA), including for screen readers and keyboard users.*
- *The implementation should be fully accessible and inclusive by design.*
- *Accessibility must be considered from the ground up — structure, interaction, and styling.*
- *Write the code with a strong focus on inclusive design and usability for all users.*
- *Ensure the UI is accessible to screen readers, keyboard users, and visually impaired users.*
- *Accessibility should be baked in — use proper tab order, focus states, and ARIA labels.*
- *Add meaningful alt text, keyboard support, and ARIA where needed.*

---

## WCAG 2.1 criteria reference

Quick reference for criteria relevant to theme development. **Level AA** is the default target unless noted as A or AAA.

### Principle 1: Perceivable

#### Guideline 1.3: Adaptable

| Criterion | Level | Summary |
|-----------|-------|---------|
| 1.3.1 Info and Relationships | A | Use semantic markup (headings, lists, tables, labels). |
| 1.3.2 Meaningful Sequence | A | DOM order matches visual reading order. |
| 1.3.3 Sensory Characteristics | A | Do not rely only on shape, size, location, or sound. |
| 1.3.4 Orientation | AA | Support portrait and landscape unless essential. |
| 1.3.5 Identify Input Purpose | AA | Use `autocomplete` / programmatic input purpose. |
| 1.3.6 Identify Purpose | AAA | Programmatically identify icons, regions, buttons (ARIA/metadata). |

#### Guideline 1.4: Distinguishable

| Criterion | Level | Summary |
|-----------|-------|---------|
| 1.4.1 Use of Color | A | Do not use color as the only means of conveying information. |
| 1.4.2 Audio Control | A | Auto-playing audio >3s: provide pause/stop or volume control. |
| 1.4.3 Contrast (Minimum) | AA | 4.5:1 normal text; 3:1 large text. |
| 1.4.4 Resize Text | AA | Text resizable to 200% without loss of content/function. |
| 1.4.5 Images of Text | AA | Prefer real text over images of text (except logos). |
| 1.4.6 Contrast (Enhanced) | AAA | 7:1 normal; 4.5:1 large text. |
| 1.4.7 Low or No Background Audio | AAA | Background audio low or disableable. |
| 1.4.8 Visual Presentation | AAA | User control over text block presentation. |
| 1.4.9 Images of Text (No Exception) | AAA | No images of text except essential/decorative. |
| 1.4.10 Reflow | AA | No horizontal scroll at 400% zoom (320px width). |
| 1.4.11 Non-text Contrast | AA | UI components and graphics: 3:1 contrast. |
| 1.4.12 Text Spacing | AA | Content remains usable when users override spacing. |
| 1.4.13 Content on Hover or Focus | AA | Dismissible, hoverable, persistent until dismissed. |

### Principle 2: Operable

#### Guideline 2.1: Keyboard Accessible

| Criterion | Level | Summary |
|-----------|-------|---------|
| 2.1.1 Keyboard | A | All functionality operable via keyboard. |
| 2.1.2 No Keyboard Trap | A | Focus can always move away from components. |
| 2.1.3 Keyboard (No Exception) | AAA | No exceptions to keyboard operability. |
| 2.1.4 Character Key Shortcuts | A | Single-key shortcuts: off, remap, or focus-only. |

#### Guideline 2.2: Enough Time

| Criterion | Level | Summary |
|-----------|-------|---------|
| 2.2.1 Timing Adjustable | A | User can extend/adjust time limits. |
| 2.2.2 Pause, Stop, Hide | A | Control auto-updating/moving content >5s. |
| 2.2.3 No Timing | AAA | No time limits except essential/real-time. |
| 2.2.4 Interruptions | AAA | User controls interruptions. |
| 2.2.5 Re-authenticating | AAA | Restore data after re-auth. |
| 2.2.6 Timeouts | AAA | Warn users about inactivity timeouts. |

#### Guideline 2.3: Seizures and Physical Reactions

| Criterion | Level | Summary |
|-----------|-------|---------|
| 2.3.1 Three Flashes or Below Threshold | A | No flashing >3 times per second. |
| 2.3.2 Three Flashes | AAA | No flashing above threshold. |
| 2.3.3 Animation from Interactions | AAA | Option to disable non-essential motion. |

#### Guideline 2.4: Navigable

| Criterion | Level | Summary |
|-----------|-------|---------|
| 2.4.1 Bypass Blocks | A | Skip links / landmarks to bypass repeated content. |
| 2.4.2 Page Titled | A | Descriptive `<title>` per page. |
| 2.4.3 Focus Order | A | Logical tab order. |
| 2.4.4 Link Purpose (In Context) | A | Link purpose clear from text or context. |
| 2.4.5 Multiple Ways | AA | Multiple ways to find pages (nav, search, sitemap). |
| 2.4.6 Headings and Labels | AA | Descriptive headings and labels. |
| 2.4.7 Focus Visible | AA | Keyboard focus always visible (see `.btn`, `a11y-focus`). |
| 2.4.8 Location | AAA | Indicate current location (breadcrumbs, etc.). |
| 2.4.9 Link Purpose (Link Only) | AAA | Link purpose clear from link text alone. |
| 2.4.10 Section Headings | AAA | Section headings organize content. |
| 2.4.11 Focus Not Obscured (Minimum) | AA | Focused element not fully hidden. |
| 2.4.12 Focus Not Obscured (Enhanced) | AAA | Focused element fully visible. |

#### Guideline 2.5: Input Modalities

| Criterion | Level | Summary |
|-----------|-------|---------|
| 2.5.1 Pointer Gestures | A | Simple alternative to complex gestures. |
| 2.5.2 Pointer Cancellation | A | Forgiving touch/mouse (e.g. activate on up, not down). |
| 2.5.3 Label in Name | A | Visible label text included in accessible name. |
| 2.5.4 Motion Actuation | A | Alternative to motion-triggered actions. |
| 2.5.5 Target Size (Enhanced) | AAA | Targets at least 44×44 CSS pixels. |
| 2.5.6 Concurrent Input Mechanisms | AAA | Do not restrict input methods. |
| 2.5.7 Dragging Movements | AA | Non-drag alternative for drag operations. |
| 2.5.8 Target Size (Minimum) | AA | Targets at least 24×24 CSS pixels or adequate spacing. |

### Principle 3: Understandable

#### Guideline 3.1: Readable

| Criterion | Level | Summary |
|-----------|-------|---------|
| 3.1.1 Language of Page | A | `lang` on `<html>`. |
| 3.1.2 Language of Parts | AA | `lang` on passages in other languages. |
| 3.1.3 Unusual Words | AAA | Explain jargon/idioms. |
| 3.1.4 Abbreviations | AAA | Expand abbreviations (`<abbr title="…">`). |
| 3.1.5 Reading Level | AAA | Simplified content for complex text. |
| 3.1.6 Pronunciation | AAA | Clarify ambiguous pronunciation. |

#### Guideline 3.2: Predictable

| Criterion | Level | Summary |
|-----------|-------|---------|
| 3.2.1 On Focus | A | No unexpected context change on focus alone. |
| 3.2.2 On Input | A | No unexpected change on input without warning. |
| 3.2.3 Consistent Navigation | AA | Repeated navigation in consistent order. |
| 3.2.4 Consistent Identification | AA | Same function, same label/icon across pages. |
| 3.2.5 Change on Request | AAA | Major changes only on user request. |
| 3.2.6 Consistent Help | A | Help in consistent location across pages. |

#### Guideline 3.3: Input Assistance

| Criterion | Level | Summary |
|-----------|-------|---------|
| 3.3.1 Error Identification | A | Identify errors in text. |
| 3.3.2 Labels or Instructions | A | Labels/instructions for all fields. |
| 3.3.3 Error Suggestion | AA | Suggest corrections when known. |
| 3.3.4 Error Prevention (Legal, Financial, Data) | AA | Confirm/review critical submissions. |
| 3.3.5 Help | AAA | Context-sensitive help. |
| 3.3.6 Error Prevention (All) | AAA | Review/confirm/undo for user submissions. |
| 3.3.7 Redundant Entry | A | Avoid re-asking for same data; offer autofill/copy. |
| 3.3.8 Accessible Authentication (Minimum) | AA | Auth without cognitive-only tests; allow password managers. |
| 3.3.9 Accessible Authentication (Enhanced) | AAA | No cognitive function tests in auth. |

### Principle 4: Robust

#### Guideline 4.1: Compatible

| Criterion | Level | Summary |
|-----------|-------|---------|
| 4.1.2 Name, Role, Value | A | Standard HTML or correct ARIA; expose name, role, state. |
| 4.1.3 Status Messages | AA | Announce status updates via `role="status"` / `aria-live`. |

---

## Further reading

- [WCAG 2.1 Quick Reference](https://www.w3.org/WAI/WCAG21/quickref/)
- [WAI-ARIA Authoring Practices](https://www.w3.org/WAI/ARIA/apg/)
