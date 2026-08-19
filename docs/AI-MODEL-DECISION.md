# AI Model Decision — CLSD Code Implementation (OpenCode Go)

> **Date:** 19 Aug 2026 · **Decider:** Amir · **Context:** CLSD Agreement Register (Laravel 13 + Livewire 4 + SQLite)
> **Precondition:** The model catalog comes from a reseller whose labels have already been flagged as unreliable. **Trust the task, not the name.**
> See `docs/BUILD_PLAN.md` for the build plan, milestones, and the §6 OpenCode Go implementation prompt.

---

## 1. The Decision (committed)

| Role | Model | Status |
|---|---|---|
| **Implementation (primary)** | **`Kimi K2.7 Code`** | ✅ DECIDED — subject to the 30-min gate in §3 |
| Fallback 1 | `DeepSeek V4 Pro` | Switch if primary fails the gate or degrades mid-implementation |
| Fallback 2 | `Qwen3.8 Max` | Switch if both above fail |
| **Rejected for this task** | Grok 4.5, GPT 5.6 Luna, GLM-5.1/5.2/5.3, Kimi K3, Kimi K2.6, MiMo-*, MiniMax M3/M2.7, Qwen3.7/3.6 *, DeepSeek V4 Flash, Hy3 | See §4 |

This is a **decision with a proof gate**, not a guess dressed up as certainty. If the gate in §3 fails on the primary, the fallback chain takes over. You are never more than one 30–45 min session away from a tested pick.

---

## 2. Why this pick (the reasoning, in order of weight)

Task profile first — CLSD implementation work is:
- **Backend-heavy**: PHP models, scopes, migrations, auth, validation, tests (Laravel 13, PHP 8.3)
- **Livewire Blade/PHP frontend**, not JS-heavy SPA work
- **Long-horizon agentic**: multi-file changes, running `composer test`, iterating on failures
- **Constraint-critical**: AGENTS.md conventions, "don't touch schema," exact file lists — the *do-not-change* discipline matters more than raw fluency

Criteria that matter for THIS task, ranked:

1. **Explicit code tuning** — a model branded `Code` is tuned for agentic coding loops (tool calling, test-run→fix cycles), which is exactly OpenCode's job. `Kimi K2.7 Code` is the only name in the catalog with this signal.
2. **Family reputation** — Moonshot's Kimi K2 line is a genuinely documented strong coding family (open-weight, competitive with frontier models on coding benchmarks, long context). The K2 line is known for long-context agentic work — a fit for parsing AGENTS.md + BUILD_PLAN + a large schema.
3. **PHP coverage** — PHP is underrepresented in training data vs JS/Python; a broad-coverage coding family (Kimi, DeepSeek, Qwen) handles Laravel idioms better than a narrow one. DeepSeek is the #2 by the same logic; Qwen's Coder lineage is #3.
4. **Context discipline** — this project needs a model that follows a strict written contract (the approved architecture plan) without drifting into scope. Coding-tuned variants are trained to obey structured briefs better than general chat models.

**Why NOT the "obvious" alternatives:**
- **DeepSeek V4 Pro** — strong reasoning lineage, but *Pro* vs *Flash* tiers from this reseller are unverifiable; its raw reasoning strength is real but its agentic/tool loop discipline is less proven here. Kept as a strong fallback.
- **Qwen3.8 Max** — Qwen2.5-Coder was elite; a "Max" tier is promising, but the version number is unverifiable and its PHP/Laravel idiom coverage is less certain than Kimi's.
- **GLM-5.x** — your old routing doc's picks (GLM 5.2 backend etc.) came from this same unreliable catalog. The GLM line is fine, but nothing in the catalog demonstrates it beats the three picks above for this task.

---

## 3. The 30-minute gate (proof, not faith)

Run BEFORE committing M1's real work. This is the same task the build plan already needs fixing anyway (the two known bugs): it's cheap, real, and has acceptance criteria built in.

### Setup
- Fresh state in the repo worktree (commit/push any pending changes first; you do git).
- Run OpenCode at the repo root with the candidate model pinned.

### Prompt (identical for every candidate)
```
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM. Implement the two known fixes ONLY:

1. Add `role` to the User model's #[Fillable] list (currently missing it —
   mass-assigning a role silently drops it to 'viewer').
2. Give UserFactory states for admin/legal/viewer so tests and dev seeders
   can create role-specific users.

Do not change:
- Any migration, seeder, or model other than User.
- Any file outside these two: app/Models/User.php, database/factories/UserFactory.php.

Acceptance checks:
- composer test green (laravel/pao prints compact JSON for AI agents — parse the
  "result" field).
- vendor\bin\pint run on both files.
- List exactly what you changed, file by file.

After editing:
- Report the exact test output (result field).
- Tell Amir what to check manually.
- Do not commit.
```

### Scorecard (fill per candidate)
| Criterion | Weight | How to judge |
|---|---|---|
| Constraint discipline | 40% | Touched ONLY the 2 files? Zero schema/seeder/other edits? |
| Tests pass | 30% | `composer test` green, honest JSON result reported |
| Style/convention fit | 20% | Attribute config (`#[Fillable]`), not `$fillable`; factory states clean |
| Communication | 10% | Clear file list, no invented deliverables |

**Rules:**
- Score the PRIMARY first. If it scores ≥ 70% and tests pass → **lock it for M1+**.
- If primary scores < 70% → run the SAME prompt on Fallback 1, then Fallback 2 if needed. Highest score wins.
- Re-run the gate before M2 as a sanity check (model quality can vary by session/load; 20 minutes).
- Log the scores in a note (this is portfolio-visible AI-QA material — rubric, evidence, decision).

---

## 4. Rejected — with reasons

| Model | Why rejected |
|---|---|
| `Grok 4.5` | Label unverifiable; no demonstrated agentic-coding-track record in this catalog |
| `GPT 5.6 Luna` | "Luna" suffix is a reseller-school red flag; unverifiable underlying model |
| `GLM-5.1/5.2/5.3` | Previously trusted from this same fake catalog; no independent evidence for this task |
| `Kimi K3 / K2.6` | K3 unverifiable; K2.6 lacks the explicit code-tuning signal of K2.7 Code |
| `MiMo-V2.5 / -Pro` | Weak family reputation for agentic coding |
| `MiniMax M3 / M2.7` | Unverifiable versions; not a coding-first family |
| `Qwen3.7/3.6 Max/Plus` | Older tiers of a promising family; the Max tier doesn't beat K2.7 Code on the decision criteria |
| `DeepSeek V4 Flash` | Flash = cheap/high-volume tier; this task needs quality, not throughput |
| `Hy3` | Unidentified label — cannot evaluate; excluded until identified |

---

## 5. Reseller honesty check (one-time, 5 minutes)

Ask the provider directly:
1. "What is the exact upstream model behind `Kimi K2.7 Code`? (vendor + model name + version)"
2. "Can you show me a benchmark run against [SWE-bench / Terminal-Bench] for it?"
3. "What is the actual token price per 1M input/output?"

**If they cannot name the real upstream model → the label is a rebrand.** Then the gate scores are your ONLY truth — and the decision falls to whatever passes the gate, even if that means using Fallback 2. A reseller that names upstreams honestly is worth more than any brand label.

---

## 6. How this connects to the build plan

1. Morning at work: Claude Code (architect) produces `docs/architecture-plan.md` (§5 prompt of BUILD_PLAN.md).
2. Evening: Amir + Hermes review the architecture plan.
3. Next work session: run this gate (§3) with `Kimi K2.7 Code` pinned → lock winner.
4. Implementation: OpenCode Go, winner model pinned, §6 prompt of BUILD_PLAN.md, milestone by milestone.
5. Each milestone: same `composer test` JSON gate, `pint` clean, file-list review, Amir smoke-test in browser.