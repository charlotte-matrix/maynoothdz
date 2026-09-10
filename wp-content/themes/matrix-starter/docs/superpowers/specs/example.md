# What We Offer Intro Two Column Design

## Goal

Extend the existing `what_we_offer` flexible content block with a second layout variant that matches Figma node `2888:3479`. The new variant should preserve the existing block's role and authoring model while rendering a simpler two-column service grid with intro copy and no large side image.

## Scope

This is **not** a new block. It is a new `layout_style` inside the existing:

- `what_we_offer`

The current image-led layout remains available and unchanged in purpose. The new layout is an additional option for sections that use:

- heading
- underline
- short intro paragraph
- 2-column service grid
- compact service cards with a colored vertical bar accent

## Source Design

- Figma file: `0Fd6tsq9KbzxPzgmp3rokR`
- Node: `2888:3479`

The design shows:

- white section background
- heading and underline matching the established site pattern
- one intro paragraph below the heading
- four service items laid out in two columns on desktop
- each item using:
  - a vertical colored bar block on the left
  - service title with arrow
  - one paragraph of supporting text

The visual structure is clearly related to the current `what_we_offer` block, but simplified:

- no large right-side image
- no split hero-style layout
- no uploaded service icon in the visible design

## Reuse Strategy

Reuse the existing `what_we_offer` block and add:

- `layout_style`

with two values:

- `image_feature` for the current block behavior
- `intro_two_column` for the new Figma section

This is the preferred approach because:

- the section title + repeated service entry model is already present
- editors keep using one familiar block instead of choosing between near-duplicates
- the new section is visually adjacent to the current block rather than representing a new content type

## Content Model

### Shared fields kept

Keep these existing fields:

- `heading`
- `heading_tag`
- `heading_link`
- `show_heading_icon`
- `services`
- `background_gradient`
- `padding_settings`

### New block-level fields

Add:

- `layout_style`
- `intro_text`

`intro_text` is used only by `intro_two_column`.

### Existing fields with conditional visibility

Keep:

- `main_image`

but only use/show it for `image_feature`.

### Service item model changes

The new layout should keep the existing repeater, but extend it with an accent-color field for the left rail treatment:

- `accent_color`

This is used only by `intro_two_column`.

The existing service fields still remain useful:

- `service_title`
- `service_description`
- `service_link`
- `show_service_icon`

For `intro_two_column`, the visible left treatment should be driven by the accent-color rail rather than the uploaded `service_icon`.

## ACF Behavior

### Layout style selector

Add a select field near the top of the content tab:

- `image_feature`
- `intro_two_column`

### Conditional logic

- `main_image` should only appear for `image_feature`
- `intro_text` should only appear for `intro_two_column`
- `accent_color` inside each service row should only appear for `intro_two_column`

The current icon-related fields can remain visible if needed for backward compatibility, but the new layout should not require editors to provide service icons.

## Rendering

### Shared heading area

Both layouts should keep:

- section heading
- optional heading link
- optional heading chevron
- underline using the existing site pattern

### New `intro_two_column` layout

Render:

1. heading
2. underline
3. intro paragraph
4. 2-column services grid

Desktop:

- fixed two-column grid
- comfortable horizontal gap
- compact vertical rhythm between title and body

Mobile:

- collapse to one column
- preserve the left rail treatment
- keep text sizing aligned with existing site typography rules

### Service item structure

Each item should render as:

- left accent rail block
- content column containing:
  - title row
  - optional arrow icon when linked / enabled
  - description

The left rail should visually echo the Figma:

- narrow colored vertical block
- rounded corners
- dark reveal/second tone can be approximated only if it fits the current code cleanly

The implementation should aim for visual parity without overcomplicating the current block.

## Styling Notes

- use site typography and underline treatment already established elsewhere
- keep the section background simple for this variant; the Figma is visually light and uncluttered
- do not add bounce or novelty hover effects
- keep hover/focus behavior restrained and consistent with the current theme

## Interaction

- service item links should remain supported
- title arrow treatment may remain optional through `show_service_icon`
- if a service has no link, it should render as static content

## Responsive Behavior

Desktop:

- heading area spans the full section width
- intro text sits above the service grid
- service grid is 2 columns

Tablet and mobile:

- service grid becomes 1 column
- intro width relaxes naturally
- spacing tightens, but typography should remain legible and aligned with current site rules

## Demo Requirement

As with the other Figma-driven sections, this updated block must be added to `/flexi/` for demonstration after implementation.

The `/flexi/` page should show:

- the existing `what_we_offer` layout still working
- the new `intro_two_column` layout as a second example

This proves the reuse strategy rather than replacing the original block behavior.

## Testing / Verification

Implementation should verify:

- the existing `image_feature` layout still renders correctly
- the new `intro_two_column` variant renders the intro + 2-column grid correctly
- `main_image` is not rendered in the new variant
- service links still work
- desktop collapses to a single column on mobile

## Notes

- This is a layout extension, not a new block
- Prefer a light-touch evolution of `what_we_offer` over deeper refactoring unless the current file boundaries become a blocker during implementation
