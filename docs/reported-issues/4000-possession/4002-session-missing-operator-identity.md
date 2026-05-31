# 4002 — PosSession Carries No Operator Identity (Asymmetric With Shift)

**Type:** Missing Feature
**Status:** Resolved
**Severity:** High
**Reported:** 2026-05-30
**Resolved:** 2026-06-01
**Affects:**
- Part A (priority) — `Dranzd\StorebunkPos\Application\PosSession\Command\StartSession`, `…\Command\Handler\StartSessionHandler`, `Dranzd\StorebunkPos\Domain\Model\PosSession\Event\SessionStarted`, `Dranzd\StorebunkPos\Domain\Model\PosSession\PosSession` (+ a possible new `PosSession\ValueObject\UserId`).
- Part B (secondary) — `Dranzd\StorebunkPos\Domain\Model\Shift\Shift`, `…\Shift\Event\ShiftOpened` (and/or a new assignment event), `Dranzd\StorebunkPos\Application\Shift\Command\OpenShift`.

**Source:** Library Improvement Request, host discussion doc `app/Modules/Pos/docs/discussions/20260530-pos-session-accountability.md` (lives in the host repo). Design decisions locked 2026-05-30 (§5).

---

## Issue

The **PosSession aggregate records no operator**. `StartSession` / `SessionStarted` / `PosSession::start()` reference only the shift and terminal, so the session itself never remembers *who started or operated it*.

By contrast, the **Shift aggregate already binds an operator**: `ShiftOpened` carries a `CashierId` and `OpenShift::forCashier(...)` enforces it. The model is asymmetric — a cash window has an owner, but the operator's actual use period (the session) is anonymous.

Host consequences:

- Any user with `pos.session.operate` can start a session on any open shift; the session doesn't remember who.
- "Who opened this cashier?" / "who is on terminal X?" can't be answered from operational records — only from a post-hoc, bypassable audit log (`App\Modules\Pos\Services\PosAuditLogger` writing `cashier_id` into `partner_pos_audit_logs`).
- A shift is bound to exactly one `cashier_id`, with no way to leave it **open** to a set of branch cashiers, or to **assign** it explicitly while still attributing each session to its real operator.

This blocks host work: the session-accountability UI ("who's on each terminal", session-operator column, shift assignee/open badge) and a `pos_sessions.user_id` projection all depend on Part A landing in this package.

---

## Findings

Verified against the current `main` (commit `bb04317`):

**Session side — no operator (the gap):**

- `StartSession::onTerminal(string $sessionId, string $shiftId, string $terminalId, ?string $commandId)` — no operator parameter; command payload is `session_id` / `shift_id` / `terminal_id` only. — `src/Application/PosSession/Command/StartSession.php:31-38, 22-28`
- `SessionStarted::occur(SessionId, ShiftId, TerminalId, DateTimeImmutable $startedAt)` — no operator; `getPayload()` emits only `session_id` / `shift_id` / `terminal_id` / `started_at`. — `src/Domain/Model/PosSession/Event/SessionStarted.php:22-50`
- `PosSession::start(SessionId, ShiftId, TerminalId)` records `SessionStarted` with no operator; `applyOnSessionStarted()` sets only `sessionId` / `shiftId` / `terminalId` / `state`. There is no operator field on the aggregate. — `src/Domain/Model/PosSession/PosSession.php:48-65, 329-335`
- `StartSessionHandler` passes the three ids straight through. — `src/Application/PosSession/Command/Handler/StartSessionHandler.php:20-24`

**Shift side — operator already bound (the asymmetry):**

- `ShiftOpened` carries `CashierId`; payload includes `cashier_id`. — `src/Domain/Model/Shift/Event/ShiftOpened.php:21,29,55`
- `OpenShift::forCashier(shiftId, terminalId, branchId, cashierId, …)` enforces it. — `src/Application/Shift/Command/OpenShift.php:41-51`
- `CashierId` VO exists under `Shift\ValueObject`. — `src/Domain/Model/Shift/ValueObject/CashierId.php`

**Compatibility mechanism is available:** schema evolution via upcasters is a documented property of `BaseAggregateEvent`. — `src/Domain/Event/BaseAggregateEvent.php:23`. So the non-breaking path below is feasible.

The reported asymmetry is **valid and accurate**: the session aggregate is genuinely operator-less today, and the package is the correct place to add operator identity (a domain concern, not a host workaround — the host audit log is post-hoc and bypassable).

---

## Root Cause

The PosSession aggregate was modeled as a thin operational wrapper around a shift+terminal pairing, with operator accountability assumed to live on the Shift (`CashierId`) or in the host's audit log. That leaves the *actual operator of a session* unrepresented in the domain: the shift's single `cashier_id` is the cash-window owner, not necessarily the person operating each session, and the audit log is a soft, after-the-fact record rather than part of the event-sourced aggregate. The system's identity scheme also diverged — the shift opener is a `Cashier` UUID (`partner_cashiers.id`) while auth, the actor metadata, and the audit log are all keyed by the Laravel `User` id — so there was no single operator identity to attribute a session to.

---

## Recommended Action

> **Owner override (2026-05-31, supersedes discussion §5's `user_id` decision):** this package
> is a **bounded context** and must not depend on the host's `User` identity — doing so would
> break its independence. The operator is expressed in **this module's own ubiquitous language:
> `CashierId`**. The host maps its user → cashier at the boundary (and resolves the cashier's
> display name on its read model). Payload/getter use the module's `cashier_id`, not a host
> `user_id`.
>
> **Two distinct identities, kept separate:** the domain operator (`CashierId`, on the command
> payload + `SessionStarted`) is *not* the command **meta actor**. The actor is the host `User`
> performing the action and already travels as `ActorCapable` metadata (`_actor_id`/`_actor_name`)
> on every `AbstractCommand` — a generic framework concern this package never reads into its
> domain. Example: host user `0005` operating as cashier `1` records `cashier_id = 1` in the
> domain while the actor metadata independently carries user `0005`.
>
> **Operator is REQUIRED, not optional (decided 2026-05-31):** a session is always operated by
> someone, so a new session cannot be started without a `CashierId`. Because this package has
> **never been released**, there are no stored `SessionStarted` events to migrate — so there is
> **no backward-compat path**: no deprecated overload, no nullable operator, no upcaster. The
> correct shape is implemented directly.

### Part A — operator on the session (priority) — **DELIVERED**

1. Add a single factory `StartSession::onTerminalForCashier(sessionId, shiftId, terminalId, cashierId)` with a **required** cashier. The operator-less `onTerminal(...)` is **removed** (no compat path needed — package unreleased).
2. `SessionStarted::occur(...)` carries a **required** operator `CashierId`; the `storebunk.pos.session.started` payload gains a non-null **`cashier_id`** key.
3. `PosSession` aggregate stores the operator and exposes `cashierId(): CashierId` so the host projector can persist the operator on its read model (host denormalizes the name for display).

Operator VO: a module-owned `PosSession\ValueObject\CashierId extends Uuid` was added (not the `Shift\ValueObject\CashierId`), keeping PosSession decoupled from the Shift namespace.

This unblocks the host read model, presence/ownership queries ("who's on terminal X"), and host-side enforcement of the shift-membership rule at session start.

### Part B — shift assignment / open / fallback set (secondary)

Represent shift membership on the Shift aggregate, distinct from the per-session operator of Part A. Modeled via a **new `ShiftAssigned` event** (Q2 — separate from `ShiftOpened`, so membership can change without re-opening and `ShiftOpened`'s upcaster stays trivial):

- An **optional assignee** (`CashierId`). Unassigned ⇒ shift is **open** (host policy: any branch cashier may start a session).
- A **fallback set of up to 3** `CashierId`s — designated backups when the assignee is out.
- Assigned shifts restricted to assignee + fallbacks, with a host-side supervisor/manager override.

Vendor surface: store assignee + fallback list (≤3) on the Shift aggregate, emit a `ShiftAssigned` event, expose getters so the host can project + enforce. **Keyed by the module's `CashierId`** to match the session operator. Existing shifts upcast to **no assignee / no fallbacks** (open), preserving today's behaviour. The shift's existing `cashier_id` opener is unchanged.

### Compatibility

- **Part A:** none required. The package is unreleased, so there are no persisted `SessionStarted`
  events — the required operator is introduced directly, with no nullable param, deprecated
  overload, or upcaster.
- **Part B:** since shift membership is a *new* `ShiftAssigned` event (not a change to `ShiftOpened`),
  existing shifts simply have no membership recorded ⇒ they read as **open**, preserving today's
  single-cashier-opener behaviour. No upcaster needed for the unreleased package.
- No projection rebuild required beyond the host adding the new columns + replaying.

---

## Open Questions for Owner

**Q1. Typed `UserId` value object, or plain string operator?**
- **(a)** Introduce a typed `PosSession\ValueObject\UserId` VO (and use it on the command / event / aggregate). — Matches the package's everything-is-a-VO convention (`SessionId`, `ShiftId`, `TerminalId`, `CashierId`). **Recommended.**
- **(b)** Carry the operator as a plain `string $userId` / nullable string. — Lighter, but inconsistent with the rest of the domain model.

**Q2. How to model Part B's shift membership on the event stream?**
- **(a)** Add a dedicated `ShiftAssigned` (assignee + fallbacks) event, separate from `ShiftOpened`. — Assignment is a distinct lifecycle action; keeps `ShiftOpened`'s upcaster trivial and lets membership change without re-opening. **Recommended.**
- **(b)** Extend `ShiftOpened`'s payload with assignee + fallback fields. — Fewer events, but couples membership to open-time and complicates the `ShiftOpened` upcaster.

**Q3. Scope — keep Part A + Part B in this one issue, or split Part B out?**
- **(a)** Keep both in 4002, implement Part A first (higher value), then Part B. — One broader accountability feature; single tracking surface. **Recommended.**
- **(b)** Implement Part A under 4002 and split Part B into its own 3000-series Shift issue. — Cleaner per-aggregate scoping; Part B can be deferred independently.

---

## Owner Response

**Decision:** Accept (one override)
**Date answered:** 2026-05-31
**Question Answers:**

- **Q1 — Operator identity: OVERRIDE.** Despite discussion §5 locking the operator to the host's `User` id, this package is a **bounded context** and must not depend on an outside system's identity — that would break its independence. The operator is `CashierId`, this module's own ubiquitous language. The host maps user → cashier at the boundary. (Neither a host `user_id` nor a new `UserId` VO.)
- **Q2 — (a) New `ShiftAssigned` event.** Assignment is a distinct lifecycle action; keeps `ShiftOpened`'s upcaster trivial and lets membership change without re-opening.
- **Q3 — (a) Keep Part A + Part B in 4002.** One broader accountability feature; implement Part A first (higher value), then Part B.

**Notes:**
The Q1 override propagates through the whole design: command factory is `onTerminalForCashier(...)`, the `storebunk.pos.session.started` payload key is `cashier_id` (not `user_id`), and Part B's assignee + fallback set are `CashierId`-keyed.

Two refinements were locked during implementation (2026-05-31):
- **Actor vs. operator.** The command **meta actor** (host `User`, via `ActorCapable`'s `_actor_id`) and the domain **operator** (`CashierId`, on the command payload + event) are distinct. The package only knows the operator; the host user travels as actor metadata and is never read into the domain. cashier `1` ↔ host user `0005` are mapped at the boundary.
- **Operator required + no backward-compat.** A new session must carry a `CashierId` (no operator-less path). Because the package is unreleased there are no stored events to upcast, so the optional/nullable/deprecated-overload/upcaster machinery from the original filing was dropped — the correct shape is implemented directly. `StartSession::onTerminal(...)` is removed.

VO sourcing resolved to a module-owned `PosSession\ValueObject\CashierId` (not reusing `Shift\ValueObject\CashierId`), avoiding cross-aggregate coupling.

---

## Resolution

### Part A — delivered (2026-05-31)

**As-delivered API:**
- New VO `Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject\CashierId` (`extends Uuid`) — the module-owned session operator. — `src/Domain/Model/PosSession/ValueObject/CashierId.php`
- `StartSession::onTerminalForCashier(string $sessionId, string $shiftId, string $terminalId, string $cashierId, ?string $commandId = null)` — required cashier; `cashierId(): CashierId` accessor; command payload key `cashier_id`. `onTerminal(...)` removed. — `src/Application/PosSession/Command/StartSession.php`
- `SessionStarted::occur(SessionId, ShiftId, TerminalId, CashierId, DateTimeImmutable)` — required operator; payload key `cashier_id` (non-null); `getCashierId(): CashierId`. — `src/Domain/Model/PosSession/Event/SessionStarted.php`
- `PosSession::start(SessionId, ShiftId, TerminalId, CashierId)` records the operator; `applyOnSessionStarted()` rehydrates it; `cashierId(): CashierId` getter. — `src/Domain/Model/PosSession/PosSession.php`
- `StartSessionHandler` passes `$command->cashierId()` through. — `src/Application/PosSession/Command/Handler/StartSessionHandler.php`

**Operator vs actor:** the host `User` performing the action is unchanged — it travels as `ActorCapable` metadata (`_actor_id`) on the command, separate from this domain `cashier_id`.

**Tests:** new `StartSessionHandlerTest` (operator recorded + survives reconstitution); `PosSessionTest::test_it_can_be_started` asserts the operator; all session/shift call sites updated to pass a cashier. Full suite green (166 unit + 23 integration), PHPStan clean, PHPCS clean.

**Commit/PR:** branch `feature/4002-session-operator-identity` (this commit).

### Part B — delivered (2026-06-01)

**As-delivered API:**
- New event `Dranzd\StorebunkPos\Domain\Model\Shift\Event\ShiftAssigned` (`storebunk.pos.shift.assigned`) — payload `shift_id`, `assignee_cashier_id`, `fallback_cashier_ids` (array), `assigned_at`; getters `getAssignee(): CashierId`, `getFallbackCashiers(): CashierId[]`. Separate from `ShiftOpened` per Q2(a), so membership can change without re-opening. — `src/Domain/Model/Shift/Event/ShiftAssigned.php`
- New command `AssignShift::toCashier(string $shiftId, string $assigneeCashierId, string[] $fallbackCashierIds = [], ?string $commandId = null)` (`storebunk.pos.shift.assign`); `assignee(): CashierId`, `fallbackCashiers(): CashierId[]`. — `src/Application/Shift/Command/AssignShift.php`
- `AssignShiftHandler` loads the shift, calls `assign(...)`, stores. — `src/Application/Shift/Command/Handler/AssignShiftHandler.php`
- Inverse transition (assigned → open): new event `ShiftUnassigned` (`storebunk.pos.shift.unassigned`), command `UnassignShift::shift(string $shiftId, ?string $commandId)` (`storebunk.pos.shift.unassign`), and `UnassignShiftHandler`. Modeled as its own event (not a nullable `ShiftAssigned`) so the open ⇄ assigned transitions are explicit in the stream. — `src/Domain/Model/Shift/Event/ShiftUnassigned.php`, `src/Application/Shift/Command/UnassignShift.php`, `…/Handler/UnassignShiftHandler.php`
- `Shift::assign(CashierId $assignee, CashierId[] $fallbackCashiers)` records `ShiftAssigned`; `Shift::unassign()` records `ShiftUnassigned`; `applyOnShiftAssigned()` / `applyOnShiftUnassigned()` rehydrate; getters `assignee(): ?CashierId`, `fallbackCashiers(): CashierId[]`, `isAssigned(): bool`. — `src/Domain/Model/Shift/Shift.php`

**Membership keyed by `Shift\ValueObject\CashierId`** (the same cashier identity the Shift aggregate already uses for its opener; no host `User` id).

**Invariants enforced on `assign()`:** shift must be open; ≤3 fallbacks (`MAX_FALLBACK_CASHIERS`); assignee may not also be a fallback; fallbacks must be distinct. Re-assigning replaces membership. **`unassign()`** requires the shift to be open (status) and currently assigned, and clears assignee + fallbacks. A shift that was never assigned — or has been unassigned — is **open** ⇒ host policy decides who may start a session.

**Demo:** `./demo/demo shift assign [--assignee-id] [--fallback-ids=a,b,c]` added (assignee defaults to the last shift's cashier). A pre-existing `bootstrap.php` mis-wiring of `CloseShiftHandler` (1 arg vs the required 3) that fatally broke the whole demo CLI was fixed alongside this — `ShiftClosePolicy` + `InMemoryPosSessionReadModel` are now wired.

**Tests:** `ShiftTest` (assign/unassign + all invariants + assigned→unassigned→open reconstitution + reassign-after-unassign), `AssignShiftHandlerTest`, `UnassignShiftHandlerTest`, `PayloadContractTest` (assigned + unassigned round-trips). Full suite green (208 tests), PHPStan clean, PHPCS clean.

**Commit/PR:** branch `feature/4002-session-operator-identity`.
