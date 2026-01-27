# Auth Page Design Edits (Port-101)

## Purpose

Define a consistent, light-theme design system for all authentication pages so they match the Port-101 landing page and feel cohesive, modern, and production-ready.

## Visual Direction

- **Theme**: light-only (no dark theme styles).
- **Background**: soft white/neutral base (`#f7f7f4`) used on the landing page.
- **Card surface**: white cards with subtle border and soft shadow, rounded corners.
- **Typography**: Instrument Sans (already loaded on landing page).
- **Branding**: Port-101 logo mark + name on every auth page.

## Shared Layout

- Centered auth card in a calm, minimal page.
- Maximum width: 420–480px for forms.
- Padding: generous spacing (24–32px) inside cards.
- Use consistent spacing scale and button sizes.
- Add a top branding block (logo + “Port-101” + short tagline).

## Core Visual Tokens

- Background: `#f7f7f4`
- Card: `#ffffff`
- Border: `#e2e8f0` (slate-200)
- Primary button: `#0f172a` (slate-900)
- Primary button hover: `#1f2937` (slate-800)
- Muted text: `#64748b` (slate-500/600)

## Component Guidelines

### Header Block (all auth pages)

- Logo icon + Port-101 name
- Subtitle (ex: “Secure access to your workspace”)
- Keep consistent height and spacing on every page

### Form Elements

- Input fields full width, 44–48px height
- Focus ring: subtle slate border, no neon glow
- Labels above inputs, light muted text
- Inline errors below fields, muted red tone

### Buttons

- Primary: solid slate-900, white text
- Secondary: outline or link-style with subtle border
- Full-width primary button for main action

### Links and Helper Text

- Use muted body color
- Links bolded or slightly darker slate
- “Forgot password” aligned to the right of password

## Page-by-Page Notes

### Login

- Title: “Sign in to Port-101”
- Subtitle: “Access your operational workspace.”
- Primary action: “Sign in”
- Secondary: “Create an account”

### Register

- Title: “Create your Port-101 workspace”
- Subtitle: “Start with a company name and invite your team later.”
- Primary: “Create account”
- Secondary: “Already have an account?”

### Forgot Password

- Title: “Reset your password”
- Subtitle: “We will email you a reset link.”

### Reset Password

- Title: “Set a new password”
- Subtitle: “Choose a strong password for your workspace.”

### Verify Email

- Title: “Verify your email”
- Subtitle: “Check your inbox to activate your account.”

### Confirm Password

- Title: “Confirm your password”
- Subtitle: “Required for sensitive actions.”

### Two-Factor Challenge

- Title: “Two-factor verification”
- Subtitle: “Enter the code from your authenticator app.”

## Layout Structure (Implementation)

- Use a single layout for all auth pages (e.g., `AuthLayout` or `AuthCardLayout`).
- Replace any existing dark-mode classes with light-only classes.
- Make sure the logo and branding are reusable (componentized if possible).
- Ensure consistent card padding, border, and drop shadow.

## Accessibility and Usability

- Visible focus states on inputs/buttons.
- Button labels and form labels are clear and descriptive.
- All forms keyboard navigable.

## Next Steps

1. Update `resources/js/layouts/auth/*` to reflect the new light theme.
2. Update `resources/js/pages/auth/login.tsx` to the new layout.
3. Apply the same layout to remaining auth pages in sequence.
