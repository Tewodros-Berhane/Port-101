# Common Design Patterns (Port-101)

## Purpose

Define reusable UI patterns so every screen in Port-101 feels consistent. These patterns must be referenced when designing new pages.

## Global Theme

- **Theme mode**: light-only.
- **Base background**: `#f7f7f4` (soft white). Use this for page backgrounds.
- **Primary surface**: white cards (`#ffffff`) with a slate border and soft shadow.
- **Typography**: Instrument Sans throughout.

## Card Pattern

- **Background**: `#ffffff`
- **Border**: `#e2e8f0` (slate-200)
- **Radius**: rounded-3xl for primary cards, rounded-2xl for secondary cards
- **Shadow**: `shadow-sm` (subtle, consistent)
- **Padding**: 24–32px depending on density

## Primary Button Pattern

Used for all primary actions across the app.

- Background: `#0f172a` (slate-900)
- Text: `#ffffff`
- Border radius: rounded-full or rounded-md (match component context)
- Default shadow: `shadow-sm`
- Hover: **no color change**, add soft but visible shadow
- Example classes (Tailwind):
    - `bg-slate-900 text-white shadow-sm transition-shadow hover:bg-slate-900 hover:shadow-lg hover:shadow-slate-900/15`

## Secondary Button Pattern

- Background: `#ffffff`
- Border: `#cbd5f5` (slate-300)
- Text: `#0f172a`
- Hover: subtle border darken, no fill change
- Example classes:
    - `border border-slate-300 bg-white text-slate-900 hover:border-slate-400`

## Link Pattern

- Base: `text-slate-600`
- Hover: `text-slate-900`
- Emphasis: `font-semibold` for inline CTAs

## Input/Label Pattern

- Labels: `text-slate-700`, 14px
- Inputs: 44–48px height
- Focus: subtle ring (no neon)

## Layout Pattern

- Page background uses the soft white base.
- Centered content with max width containers.
- Section padding: `py-16` or `py-20` for long-form pages.

## Usage Rule

When adding or editing UI, reference these patterns first. If a new pattern is needed, document it here before reuse.
