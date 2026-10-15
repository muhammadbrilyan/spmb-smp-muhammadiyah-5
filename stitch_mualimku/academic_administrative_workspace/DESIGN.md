---
name: Academic Administrative Workspace
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#424752'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#727784'
  outline-variant: '#c2c6d4'
  surface-tint: '#115cb9'
  primary: '#003f87'
  on-primary: '#ffffff'
  primary-container: '#0056b3'
  on-primary-container: '#bbd0ff'
  inverse-primary: '#acc7ff'
  secondary: '#006c49'
  on-secondary: '#ffffff'
  secondary-container: '#6cf8bb'
  on-secondary-container: '#00714d'
  tertiary: '#003f8b'
  on-tertiary: '#ffffff'
  tertiary-container: '#0055b8'
  on-tertiary-container: '#bcd0ff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d7e2ff'
  primary-fixed-dim: '#acc7ff'
  on-primary-fixed: '#001a40'
  on-primary-fixed-variant: '#004491'
  secondary-fixed: '#6ffbbe'
  secondary-fixed-dim: '#4edea3'
  on-secondary-fixed: '#002113'
  on-secondary-fixed-variant: '#005236'
  tertiary-fixed: '#d8e2ff'
  tertiary-fixed-dim: '#adc6ff'
  on-tertiary-fixed: '#001a42'
  on-tertiary-fixed-variant: '#004395'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-xl:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-xl-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 26px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
    letterSpacing: 0em
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
    letterSpacing: 0em
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
    letterSpacing: 0.01em
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.005em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
  caption:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '500'
    lineHeight: 14px
    letterSpacing: 0.03em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-mobile: 1rem
  margin: 2rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system delivers a disciplined, modern academic management environment built for administrative clarity, institutional credibility, and daily operational speed. It addresses educators, administrative staff, students, and guardians through a structured, low-friction interface that prioritizes data legibility and systematic workflows over decorative ornament.

The design movement combines **Modern Institutional Utility** with contemporary **Shadcn-inspired clarity**:
- **Neutral, High-Trust Surfaces**: Crisp white cards layered across a calibrated cool slate canvas reduce cognitive fatigue during heavy data entry and record management.
- **Purposeful Color Anchoring**: Institutional deep blue commands trust and authority for primary workflows, while Muhammadiyah emerald green signifies success, validation, and academic integrity.
- **Information Architecture First**: High visual order through clear data density, precise tabular grids, distinct alert tokens, and minimal, structural separation lines.

## Colors

The palette balances formal civic authority with functional, high-visibility semantic signaling.

### Core Hierarchy
- **Primary (`#0056B3`)**: Deep Institutional Blue. Reserved for core navigation landmarks, primary action buttons, active tab indicators, and critical system states.
- **Secondary (`#10B981`)**: Emerald Accent. Represents institutional identity, approved verifications, graduation status, and positive milestones.
- **Tertiary (`#3B82F6`)**: Sky Blue. Applied to scheduled events, calendar allocations, active timelines, and informational states.
- **Neutral Canvas (`#F8FAFC`)**: Light Gray canvas background. Surfaces are layered with Pure White (`#FFFFFF`) containers and fine border rules (`#E2E8F0`).

### Semantic Status Tokens
- **Verified / Lulus**: Surface `#ECFDF5`, Border `#A7F3D0`, Text `#065F46` (Accent `#10B981`).
- **Pending / In Review**: Surface `#FFFBEB`, Border `#FDE68A`, Text `#92400E` (Accent `#F59E0B`).
- **Rejected / Terminated**: Surface `#FEF2F2`, Border `#FECACA`, Text `#991B1B` (Accent `#EF4444`).
- **Scheduled / Info**: Surface `#EFF6FF`, Border `#BFDBFE`, Text `#1E40AF` (Accent `#3B82F6`).

### Content Text Contrast
- **Text Heading**: `#0F172A` (Slate 900)
- **Text Body**: `#334155` (Slate 700)
- **Text Muted**: `#64748B` (Slate 500)
- **Text Subtle**: `#94A3B8` (Slate 400)

## Typography

The typographic hierarchy utilizes **Inter** across all roles to maintain optical rhythm, dense legibility in high-volume tables, and immediate visual hierarchy in analytical metric dashboards.

- **Headlines (`headline-xl`, `headline-lg`, `headline-md`)**: Crisp tracking with negative letter spacing (`-0.01em` to `-0.02em`) creates a refined, professional posture for page headers, modal titles, and section dividers.
- **Body (`body-lg`, `body-md`, `body-sm`)**: Set with balanced line heights to support uninterrupted reading of academic evaluations, student notes, and curriculum guidelines.
- **Labels & Microcopy (`label-md`, `label-sm`, `caption`)**: Elevated weights (500 to 600) ensure status badges, form labels, table column headers, and data indicators remain punchy and immediately distinguishable even at small scale.

## Layout & Spacing

The layout is grounded in a structured 12-column fluid grid system engineered for enterprise productivity and dense data presentation.

### Grid Rhythm & Viewports
- **Desktop (1024px and up)**: 12-column grid, `margin` of `2rem` (32px), `gutter` of `1.5rem` (24px). Sidebar navigation occupies a fixed 260px container, with workspace content flowing dynamically within a max-width of 1440px.
- **Tablet (768px – 1023px)**: 8-column grid, `margin` of `1.5rem` (24px), `gutter` of `1.25rem` (20px). Collapses the persistent sidebar into an expandable navigation drawer.
- **Mobile (< 768px)**: 4-column grid, `margin-mobile` of `1rem` (16px), `gutter-mobile` of `1rem` (16px). All multidirectional tabular data converts to stacked metric cards or horizontally scrollable micro-tables.

### Internal Spacing Rhythm
- **Component Padding**: `space-sm` (8px) for compact table cells and action badges; `space-md` (16px) for form inputs and card bodies; `space-lg` (24px) for metric card interiors and modal bodies.
- **Section Offsets**: `space-xl` (32px) standard vertical gap separating metric panels, tab navigators, and main data tables.

## Elevation & Depth

This system avoids heavy drop shadows and exaggerated blurs. Instead, it creates depth via **structured low-contrast borders combined with faint ambient diffusions**.

### Depth Tiers
- **Tier 0 (Base / Canvas)**: `#F8FAFC`. Flat background on which all operational components rest.
- **Tier 1 (Surface Cards & Panels)**: `#FFFFFF` background bound by a crisp 1px solid `#E2E8F0` border. Ambient shadow: `0 1px 2px 0 rgba(15, 23, 42, 0.05)`.
- **Tier 2 (Dropdowns, Popovers & Context Menus)**: `#FFFFFF` background, `#E2E8F0` border, with ambient diffusion: `0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -2px rgba(15, 23, 42, 0.04)`.
- **Tier 3 (Modals & Overlays)**: `#FFFFFF` elevation with backdrop dimming (`rgba(15, 23, 42, 0.4)`), bordered by `#CBD5E1`. Shadow: `0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -4px rgba(15, 23, 42, 0.05)`.

Interactive states (hovering over clickable cards or table rows) step up subtly through border transitions (`#CBD5E1`) rather than heavy shadow shifts.

## Shapes

The design system maintains a consistent **Rounded (Level 2)** geometry:
- **Base Elements (`0.5rem` / 8px)**: Standard buttons, text inputs, select dropdowns, search triggers, and table containers.
- **Large Containers (`1rem` / 16px)**: Metric overview cards, dialog wrappers, and primary content sections.
- **Badges & Avatars (`9999px` / Full Pill)**: Status pills (Verified, Pending, Rejected, Scheduled) and student avatar representations use fully rounded caps to quickly distinguish meta-tags from operational inputs.

## Components

### Buttons
- **Primary**: Background `#0056B3`, text `#FFFFFF`, border none, 8px radius. Hover: `#004494`. Active: `#00387A`.
- **Secondary / Outline**: Background `#FFFFFF`, text `#0F172A`, 1px solid `#E2E8F0`, 8px radius. Hover: `#F1F5F9`.
- **Destructive**: Background `#EF4444`, text `#FFFFFF`, hover `#DC2626`.
- **Ghost**: Background transparent, text `#334155`, hover `#F1F5F9`.

### Status Badges & Chips
- **Geometry**: Height 24px, pill-shaped (`9999px`), horizontal padding `8px`, typography `caption` or `label-sm`.
- **Verified / Lulus**: Background `#ECFDF5`, text `#065F46`, border 1px solid `#A7F3D0`. Includes leading green dot indicator.
- **Pending**: Background `#FFFBEB`, text `#92400E`, border 1px solid `#FDE68A`.
- **Rejected**: Background `#FEF2F2`, text `#991B1B`, border 1px solid `#FECACA`.
- **Scheduled**: Background `#EFF6FF`, text `#1E40AF`, border 1px solid `#BFDBFE`.

### Data Tables
- **Container**: White surface with 1px solid `#E2E8F0` border and 8px border-radius.
- **Header Row**: Background `#F8FAFC`, height 40px, bottom border 1px solid `#E2E8F0`. Typography `label-sm`, text `#64748B`, uppercase tracking (`0.05em`).
- **Data Rows**: Height 52px (compact 44px for high density), text `body-md` (`#334155`), alternating or clean white rows with 1px `#F1F5F9` row dividers. Hover row background: `#F8FAFC`.
- **Inline Actions**: Right-aligned ghost action icons or status badges.

### Form Inputs
- **Base Input**: Height 40px, background `#FFFFFF`, border 1px solid `#CBD5E1`, 8px radius, padding 0 12px.
- **Focus State**: Border `#0056B3`, box-shadow `0 0 0 2px rgba(0, 86, 179, 0.15)`, outline none.
- **Placeholder**: Color `#94A3B8`.

### Checkboxes & Radios
- **Checkbox**: 18px × 18px square, border 1px solid `#CBD5E1`, 4px radius. Checked state fills `#0056B3` with a crisp white check icon.
- **Radio**: 18px diameter circle with an inset dot on selection.

### Metric Cards
- **Structure**: Surface white, 16px radius, 1px solid `#E2E8F0`, padding 20px.
- **Visual Pattern**: Top row shows metric title (`label-md`, `#64748B`) paired with a contextual soft-tinted icon container (36px × 36px, 8px radius). Middle displays numeric value (`headline-xl`, `#0F172A`). Bottom contains micro-trend indicators or progress bars.

### Tab Navigation
- **Pill Segmented**: Inset container `#F1F5F9` with 8px radius. Active tab displays as a white elevated card (`#FFFFFF`) with subtle shadow and `#0F172A` text.
- **Underline Tabs**: Text `#64748B`, with active state receiving `#0056B3` text color and an anchoring 2px bottom rule.