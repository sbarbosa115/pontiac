---
name: new-feature
description: Take one feature of the Symfony 8 + React/TypeScript + MySQL + Docker app from idea to ready-to-merge — plan it with the user, build it as a complete vertical slice (entity, migration, repository, Input/Output DTOs, controller, page, route, menu, strings, docs), cover it with automated tests (PHPUnit, PHPStan, ESLint, typecheck, Vitest, Playwright), test it by hand in the browser on its own Docker stack, and leave a pushed branch with a PR description that can go to production. Use it whenever the user wants to add, change or extend anything users see or call — a page, an endpoint, an entity, a field, a filter, a status, a report, an email, a role — even if they only describe the outcome ("let customers do X", "add a screen for Y"). Also use it when the app does not exist yet (it plans the app and builds the foundation first).
---

# New feature

The app is one vertical slice repeated many times: a MySQL table, an API endpoint that reads and writes it, and a
React screen that shows it. A feature that follows the shape of the ones before it is fast to build and hard to
break; one that invents its own is where data leaks between customers and screens render blank.

A feature goes through six steps, in order. Do not skip one because the feature looks small.

1. **Plan** — agree with the user what it does and how it will be built.
2. **Branch** — its own branch from fresh `origin/main`, in its own worktree.
3. **Build** — outward from the model: backend, then frontend, then docs.
4. **Automated tests** — write them with the code; all suites green.
5. **Browser test** — open it on this worktree's own stack, as each role that uses it.
6. **Ready to merge** — definition of done, rebased, pushed, PR description written.

**No app yet?** If the repo has no running app (no `docker-compose.yml`, no `backend/`), read
`references/new-app.md` first: plan the app and build the foundation, then come back here for its first feature.

**Conventions** the foundation provides (API shapes, isolation, UI kit, test helpers) are in
`references/conventions.md`. Read the part a feature touches before writing it.

Everything runs in Docker; there is no PHP or Node on the host. Run commands from the repository root:
`docker compose exec php php bin/console …`, `docker compose exec php php bin/phpunit`,
`docker compose exec node npm …`. The `node` service rebuilds the UI on save — read `docker compose logs node`.

---

## 1. Plan

Understand the feature before writing code. Read the PRD (`docs/pdr/`), `CLAUDE.md`, and the **nearest existing
feature** — the one whose shape this one should copy: where it lives, how it is scoped, how it is tested.

Ask only what the code and PRD cannot answer, two to four questions at a time, each with a **recommended answer and
why** (use the multiple-choice question tool). Talk about what people do, not about tables:
- Who is it for (which roles), and what do they do with it?
- What does it change: a new thing to manage, a field, a row action, a read-only view, a tab?
- Statuses and transitions; what may never happen (who cannot see it, what cannot be undone).
- Anything outside the screen: an email, a file, slow or third-party work (queue), a scheduled job.

Then pick the shape and list its layers:

| Shape | Layers |
|---|---|
| New thing a customer manages | entity → migration → repository → Input → Output DTO → controller → tests → types → page → lazy route → menu → strings → README → help |
| New field | entity + migration → Input → Output DTO → PATCH → test → types → the form/table → strings → README |
| New row action | route → DTO if the response changes → test → an `<Actions>` button → strings → README |
| New read-only view | query → DTO → controller → test → types → page → route → menu → strings → README |
| New tab | the slice → a page taking `embedded` → an option in the parent's `Tabs` (tab kept in the URL) |

A new screen is usually a **tab of an existing page**, not a new menu option.

Show the user a short plan and **wait for confirmation** before step 2:
- What users will be able to do, per role, in one or two sentences each.
- Endpoints (method, path, role), entities/fields, and the migration (additive or not).
- The screen: where it lives, table columns, filters, row actions, modals.
- The tests you will write (below) and the browser cases you will run (step 5).
- What is out of scope.

For a feature bigger than a day, write the plan to `docs/pdr/<feature>.md` and commit it on the feature branch.

---

## 2. Branch and stack

```bash
git fetch origin
git status --short                                      # note uncommitted work; never stash or commit someone else's
git worktree add -b <feature> ../<repo>-<feature> origin/main
cd ../<repo>-<feature>
```

A worktree, not `git switch`: another session may be working in the main checkout. Bring in an unmerged PRD branch
if the feature has one (`git merge origin/<prd-branch>`).

Every checkout is its own compose project (compose names containers and volumes after the folder), so the worktree's
stack has its own database, mail catcher and build — only host ports can clash. Check what is running (`docker ps`,
and whether 8080/3306/8025 are taken); if another stack of the app is up, write the next free ports to this
checkout's root `.env` (`HTTP_PORT=8081`, `DB_PORT=3307`, `MAILPIT_PORT=8026`), then:

```bash
docker compose up -d --build
docker compose exec php composer install
docker compose restart node                              # npm needs vendor/
docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console doctrine:migrations:migrate -n --env=test
docker compose exec php php bin/console app:create-super-admin
docker compose exec php php bin/console app:seed-demo
```

Tell the user which URL this stack answers on.

---

## 3. Build

**Backend**, in order: entity → migration → repository → Input → service (if there is logic) → Output DTO →
controller → test. Then **dump the schema and regenerate the types**:
`bin/console nelmio:apidoc:dump --format=json > assets/types/openapi.json` and `npm run api:types`.
**Frontend**: page (`.tsx`) → lazy route → menu entry (with `roles`) → strings → component test. Then docs.

- **Entity:** constructor takes the required fields; a docblock saying what it is in the user's world; customer-owned
  → `AccountOwnedInterface` + trait; indexes lead with `account_id`. Nothing hard-deleted.
- **Migration:** `doctrine:migrations:diff`, **read the SQL** (it will happily write a DROP), give it a real
  description, run it on dev **and** `--env=test`. It must be safe for production: additive where possible (new
  nullable column or default, backfill, then tighten in a later release); a working `down()`; no locking rewrite of
  a big table without telling the user.
- **Repository:** one `search()` returning a `Page`; **every list takes `?q=`** (escape `%` and `_`), and the fields
  it searches are the ones the search placeholder names; `addSelect()` the joins the DTO reads (no N+1); load by id
  with a DQL `findOneById()`, **never `find()`** (the identity map skips the filter); no native SQL on owned tables.
- **Input:** one class per write endpoint, public nullable properties, validation attributes, no logic; money
  validated as a decimal string; enums via `Assert\Choice(callback: …)`. New validation messages get a translation.
- **Output DTO:** `final readonly` with `#[ApiResponse]`; exact types (nullable getter → nullable field); a field a
  role must not see is **absent**, not null.
- **Controller:** in the namespace of the role that uses it (that is the authorization boundary);
  `requirements: ['id' => Requirement::UUID]`; narrower endpoints add `#[IsGranted]`; thin — map, load, call a
  service, flush, return a DTO. PATCH treats an absent key as "leave alone". New error codes get a line in `i18n.ts`.
- **Emails:** one notification mailer and template; render in the request, queue the send, call it **after** the
  flush. Only mail someone waits for on screen (codes, invitations) is sent directly.
- **Queue:** slow or third-party work is a Messenger message carrying ids, never entities; the handler enters the
  account, is idempotent (claim the work with a conditional update first).
- **Files:** stored by key under the account, type detected from content, served only through the API after a
  scoped lookup.
- **Page:** default-export a component named after the file; `useList` + `FilterBar` + `ListView`; never a raw
  `<table>`; house table style; every visible string through `t()`; money through the money input; format with the
  account's locale; every date input the app's date component; new colours are tokens in both themes.

Commit in small steps as layers land, with messages saying what changed.

---

## 4. Automated tests

Write the tests alongside the code, not after it. Each feature needs:

- **Functional (PHPUnit, `ApiTestCase`)**, per endpoint:
  - the happy path for **each role** that may call it, asserting the response shape;
  - every refusal: 422 with the expected `violations`, 409, 400 for an unknown filter, 403 for a role that may not;
  - **isolation**: another customer's ids answer **404, not 403**, for read and write;
  - side effects: the row in the database, the queued message (`runWorker` to run it), the email in the transport.
- **Shared list tests**: every new list joins the **search** (`?q=`) and **query count** tests.
- **Unit tests** for any service with real logic (state transitions, calculations, money).
- **Component test (Vitest)** for UI logic: a form's validation display, a conditional action, a calculated value.
- **Playwright e2e** when the feature touches a critical flow (sign-in, money, anything the business stops without).

Run all of it — not only the new tests:

```bash
docker compose exec php php bin/phpunit            # all of it
docker compose exec php vendor/bin/phpstan         # no new baseline entries
docker compose exec node npm run -s lint
docker compose exec node npm run -s typecheck
docker compose exec node npm test
docker compose logs --tail=30 node | grep -iE "error|compiled"
docker compose --profile e2e run --rm e2e          # when the feature touches a critical flow
```

A red suite is fixed, not skipped: no `markTestSkipped`, no new PHPStan baseline entries, no `eslint-disable` or
`@ts-expect-error` to get green. If a failure is unrelated to the feature, say so and show it.

---

## 5. Browser test

Passing suites do not prove a screen renders. A missing import in a page component is **a blank screen, not a build
error** — webpack compiles it and React throws at render. So open it.

Use the browser tools (Claude in Chrome: load them in one ToolSearch call, start with `tabs_context_mcp`, work in a
new tab) on **this worktree's** stack URL. Sign in with the demo account of each role that uses the feature
(`app:seed-demo`; watch for browser autofill on the login form). For every screen and **every modal it owns**, in
light **and** dark theme:

- the list with rows; each row action; the row colours match the legend;
- a filter that matches nothing — the empty state says so and offers "Ver todos";
- nothing at all — the empty state says what the section is for and shows its primary button;
- each modal: fields typed into, a submit that fails validation (errors under the fields, in Spanish), a submit
  that succeeds (the list updates);
- a role that must not see it: no menu entry, and the URL typed directly does not show the data;
- emails sent: open Mailpit on this stack's port and read them;
- next to a sibling screen: same header, filter bar, table and button style;
- the console: no errors or warnings from the app (`read_console_messages`).

Reload after the bundle rebuilds (a stale `/build/` file looks like a bug that is not there). Record a GIF of the main
flow when it helps the reviewer. Add the cases to `docs/tests/ui-regression.md`.

If you cannot open the browser, say so plainly and list the cases the user should run — never claim a screen renders
without having seen it.

---

## 6. Ready to merge

### Definition of done

- [ ] Plan confirmed by the user; own branch from fresh `origin/main`, own worktree, verified on own stack.
- [ ] Customer-owned entities carry the account; migration read, production-safe, run on dev and test.
- [ ] Every list takes `?q=` and joined the shared search and query-count tests; the placeholder names the searched fields.
- [ ] Functional tests: happy path per role, every refusal (422 with violations, 409, 403), and **another customer
      cannot see or touch it** (their ids answer 404).
- [ ] PHPUnit, PHPStan, ESLint, typecheck, Vitest (and e2e if touched) green; no new baseline or suppressions.
- [ ] Output DTOs with `#[ApiResponse]`; schema dumped and types regenerated in the same change.
- [ ] No N+1; indexes lead with `account_id`; slow work on the queue.
- [ ] Page is `.tsx`, lazy, in the menu with the right roles; UI logic has a component test.
- [ ] House table style, strings through `t()`, colours as tokens.
- [ ] Opened in the browser as each role, with every modal, in light and dark; console clean.
- [ ] README: API reference row, "Data model decisions" bullet for a real decision, "Known gaps" bullet for what was
      left out; help guide if the app has one; cases in `docs/tests/ui-regression.md`.
- [ ] A lesson every later feature needs is written into `CLAUDE.md` (or `references/conventions.md`) in this change.

### Hand-off

1. `git fetch origin && git rebase origin/main`, then run step 4 again if `main` moved.
2. Commit with a message saying what users can now do; push the branch (`git push -u origin <feature>`).
3. Write the PR description and give it to the user:
   - **What users can do now**, per role.
   - **How it was tested**: the suites run and their result, the browser cases checked.
   - **Deploy notes**: migrations (and whether they lock or backfill), new env vars, new cron or worker needs,
     anything to run after deploy.
   - **Not done / known gaps**.
4. Open the pull request only if the user asks. Leave the stack running until they have looked; `docker compose
   down` (and `git worktree remove`) once the branch is merged.

Report what you did **not** do as clearly as what you did.
