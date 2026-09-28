# CLSD design references

## Approved V1 workflow prototype

The requirements-led V1 workflow prototype is preserved at:

- `v1-prototype/index.html`

Intan and Siti reviewed and approved its workflow on 28 Sep 2026. It contains fictional data and
simulated interactions only. It is a review artifact, not application source: never move it into
`public/`, serve it from Laravel, or import its JavaScript, CSS, fonts, or other assets into the
production application.

The review approved two amendments that take precedence over the preserved artifact:

- production screens must use substantially less explanatory text and prioritize status, next
  responsibility, and next action; and
- every newly created Agreement Register record requires a fixed expiry date. The **No fixed
  expiry** checkbox still visible in the prototype is superseded and must not be implemented.

`unikl-agreement-management-v1-spec.md` is the binding product design. Approved milestone plans,
handoffs, and security/domain rules remain authoritative for implementation details.

## Earlier visual reference

The standing visual and UX reference for the upgraded CLSD Legal Management System is:

- `reference/CLSD Legal Management System.html`

Amir supplied this Claude-generated prototype on 15 Sep 2026. Use it to guide the system's
institutional colour palette, information hierarchy, cards, tables, status treatments,
confidentiality cues, and role-aware navigation.

The prototype is a reference, not an implementation specification. It depicts features from
multiple future milestones. `AGENTS.md`, approved architecture plans, milestone handoffs, and
confirmed business and security rules take precedence. Do not implement a depicted feature until
its milestone explicitly authorises it.

Keep the artifact under `docs/`; never move it into `public/`, serve it from the application, or
import its bundled scripts, fonts, or assets into production code. Names, addresses, organisations,
and case information shown in the prototype are fictional demonstration data.
