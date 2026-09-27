# Working on Pontiac

Pontiac is a product for independent consultants (finance advisors first): landing pages, leads, booking, Wompi
payments, clients, session notes and flows. The plan is `docs/pdr/prd-pontiac.md`; read its Glossary before naming
anything. Every feature is built with the `new-feature` skill (`.claude/skills/new-feature/`), whose
`references/conventions.md` describes what the foundation provides.

Everything runs in Docker; there is no PHP or Node on the host. From the repository root:
`docker compose exec php php bin/console …`, `docker compose exec php vendor/bin/phpunit`,
`docker compose exec node npm …`. The `node` service rebuilds the UI on save — read `docker compose logs node`.
Each checkout's host ports are in its root `.env` (`HTTP_PORT`, `DB_PORT`, `MAILPIT_PORT`).

## Words

The UI is Spanish, the code and API messages English. In the code the consultant's practice is an `Account`; its
people are the owner (`ROLE_OWNER`, "Asesor"), assistants (`ROLE_ASSISTANT`, "Asistente") and clients
(`ROLE_CLIENT`, "Cliente"); Pontiac's operator is the super admin. Never "tenant", "customer" or "user" for these
in the UI.

## Isolation

Every customer-owned entity implements `AccountOwnedInterface` + `AccountOwnedTrait`; its repository extends
`AccountOwnedRepository` and loads by id with `findOneById()`, never `find()`. No native SQL on owned tables.
Another consultant's ids answer 404, and every endpoint has a test proving it. `User` and `Account` are not
filtered: every query that lists them names the account itself.

## Emails and features

- Every email goes through `App\Mail\EmailTag::apply($email, '<kind>', $account)` before it is sent, so the email log
  (Plataforma › Correos) says what it was and which consultant it was for; add the kind's label in `i18n.ts`
  (`emails.kinds.<kind>`). Mail someone waits for on screen is sent through the transport at once; the rest is queued.
- A feature a consultant may not have (`AccountFeature`) guards its controllers with
  `#[RequiresFeature(AccountFeature::…)]` and its menu items with `feature`. A limit (`Account::getMax…()`) is checked
  where the thing is created or re-enabled, with a 409 `<thing>_limit_reached`.

## Pages and public routes

- A public route (PublicController) enters the account of its URL's slug itself (`AccountContext::enterAccount()`);
  nothing public is reached any other way. Public pages are Twig, never React.
- A new section type or field goes in `App\Page\TemplateCatalog::SECTION_TYPES`, its Twig partial in
  `templates/public/page/sections/`, and its labels in `i18n.ts` (`pageEditor.sectionType.*`, `pageEditor.field.*`).
- After `cache:clear` in dev, restart the worker (`docker compose restart worker`): it keeps the old compiled
  container and crashes on the first message.

## Tables

**Every table follows this.** The components that enforce it are in `assets/react/components/ui.tsx`.

1. **One actions column, last, called "Acciones".** `ListView` (a `useList()` result) and `DataTable` (fixed rows)
   add it. Never write `<table>` in a page. A table nothing can be done to passes `actions={false}`.
2. **Buttons look like buttons, coloured by the kind of action** (`ACTIONS` in `ui.tsx`): `confirm` green,
   `danger` red, `edit` blue, `open` violet, `file` teal, `setup` indigo, `revert` amber, `contact` rose. Icon-only
   buttons (`IconButton`, `label` required) for view (`eye`), edit (`pencil`) and turning a row off/on
   (`ban`/`check`) only; worded `ActionButton`s for everything else. Order: worded actions first, then view and
   edit, whatever disables or removes last.
3. **No status column: the row colour is the status.** `<Row status label>` tints the row; `label` is required
   (tooltip and hidden text); a `<RowLegend>` above the table lists every value. New statuses get a tone in
   `TONES`.
4. **A search box above every table**, and labelled dropdowns for filters (not tabs, not bare checkboxes). The
   placeholder names the fields the API searches. Every list endpoint takes `?q=` (`ListQueries::whereTerm()`
   escapes `%` and `_`) and joins `ListSearchTest` and `ListQueryCountTest`. The client portal's short lists are
   the exception.
5. **Primary action at the end of the `FilterBar`**, never in the page header. Empty states: no match says so and
   offers "Ver todos"; nothing at all says what the section is for and shows the primary button.
6. **A new screen is usually a tab** of an existing page (`<Tabs variant="page">`, tab kept in the URL with
   `useTabParam()`, the tab component takes `embedded`), not a new menu option.

## Screens

- Every visible string goes through `t()` (`lib/i18n.ts`); every API error code the UI can meet has an
  `errors.<code>` line there; every validation message has its line in `translations/validators.es.yaml`
  (`ValidatorsTranslationTest`).
- **Colours are tokens**, defined in both theme blocks at the top of `assets/styles/app.css`; never a hex in the
  CSS below them or in a `.tsx` (`styles.test.ts`). Check new screens in Claro and Oscuro.
- **Money** is a decimal string end to end: inputs are `MoneyField`/`AmountInput`, output through `formatMoney()`.
  Dates through `DateInput` and `formatDate()`, in the account's locale and timezone (`useLocaleSettings()`).
- A missing import in a page is **a blank screen, not a build error**: open the page and every modal it owns.
- Public pages (a consultant's landing pages, `/`) are Twig, not React, for search engines; they stay light.

## Checks

```bash
docker compose exec php vendor/bin/phpunit
docker compose exec php php bin/console cache:warmup --env=dev && docker compose exec php vendor/bin/phpstan
docker compose exec node npm run -s lint
docker compose exec node npm run -s typecheck
docker compose exec node npm test
docker compose --profile e2e run --rm e2e
```

PHPStan is level 6 with no baseline. When an API response changes, regenerate the schema and the types in the
same change (`OpenApiSnapshotTest` fails otherwise):

```bash
docker compose exec php php bin/console nelmio:apidoc:dump --format=json > backend/assets/types/openapi.json
docker compose exec node npm run api:types
```
