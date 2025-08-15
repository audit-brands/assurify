# Lobste.rs Story Styling Specifications

This document contains the exact styling specifications extracted from the official Lobste.rs source code for story display elements.

## Story Title (`.link a`)
- **Font size**: `11.5pt`
- **Font weight**: `normal` (not bold)
- **Text decoration**: `none`
- **Color**: `var(--color-fg-link)`
  - Light theme: `rgb(28 89 209)` (blue)
  - Dark theme: `rgb(138 177 255)` (light blue)
- **Visited color**: `var(--color-fg-link-visited)`
  - Light theme: `rgb(95 134 212)`
  - Dark theme: `rgb(79 138 255)`

## Tags (`a.tag`)
- **Font size**: `8pt`
- **Padding**: `0px 0.4em 1px 0.4em`
- **Border radius**: `5px`
- **Border**: `1px solid var(--color-tag-border)`
- **Background**: `var(--color-tag-bg)`
- **Color**: `var(--color-fg-contrast-10)`
- **White space**: `nowrap`
- **Margin left**: `0.25em`
- **Vertical align**: `middle`

## Domain Text (`.domain`)
- **Font size**: `9pt`
- **Font style**: `italic`
- **Color**: `var(--color-fg-contrast-4-5)` (grey)
- **Text decoration**: `none`
- **Vertical align**: `middle`

## Byline/Metadata (`.byline`)
- **Font size**: `9.5pt`
- **Color**: `var(--color-fg-contrast-4-5)` (grey)
- **Links in byline**: Same grey color with no decoration

## Story Container
- **Padding**: `0.25em` top/bottom (in `.story_liner`)
- **Word break**: `break-word`

## Layout Structure
Based on Lobste.rs HTML structure:
1. **Line 1**: `[score] Title tags domain`
2. **Line 2**: `by username time | X comments`

## CSS Variables Used
- `--color-fg-link`: Primary link color
- `--color-fg-link-visited`: Visited link color
- `--color-fg-contrast-4-5`: Grey text for metadata
- `--color-fg-contrast-10`: Tag text color
- `--color-tag-bg`: Tag background color
- `--color-tag-border`: Tag border color

## Source Reference
Extracted from official Lobste.rs repository:
- `/app/assets/stylesheets/application.css`
- `/app/assets/stylesheets/light-normal.css`
- `/app/assets/stylesheets/dark-normal.css`

## Implementation Notes
- Use CSS variables for theme compatibility
- Tags should have special styling for different types (media, meta, etc.)
- Maintain proper spacing and vertical alignment
- Ensure accessibility with proper contrast ratios