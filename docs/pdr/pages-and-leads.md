# Milestone 1 — Pages and leads

Part of [prd-pontiac.md](prd-pontiac.md) ("Delivery plan" 1). A consultant publishes landing pages built from the five
templates, and the people who answer them become prospectos.

## What people can do

**Consultant and assistants** (`/admin`)
- *Páginas*: list (search by title or address; filter by status and template), create from an enabled template,
  edit, duplicate, publish (owner and assistant), disable and reactivate (owner). The editor: *Contenido* (the
  template's sections: on/off, order, texts, images, repeatable items), *Formulario* (extra fields; a select's options
  can assign a lead category), *SEO* (title, description, social image, indexable), *Ajustes* (address, home page,
  default category, accent colour); a live preview (desktop / mobile) of the unsaved draft; *Guardar borrador* and
  *Publicar* (the live page changes only on publish).
- *Prospectos*: every contact with their category, source page and last activity; search (name, email, phone),
  filters (category, page); a contact's page with their data, consent and every form they sent; change the category;
  anonymise on request (owner only, Ley 1581).
- *Ajustes*: *Medios* (images: upload, alt text, disable), *Categorías* (name, colour, disable), *Privacidad* (the
  privacy policy the forms link to; the platform's default until changed).

**Visitors** (server-rendered, no framework JavaScript)
- `/<consultant>` is the home page (or "coming soon"), `/<consultant>/<page>` the others, `/<consultant>/privacidad`
  the privacy policy.
- The form is a plain HTML form: name, email, phone, the page's extra fields and a required consent. Honeypot,
  minimum time to fill, rate limit per IP. On success the page says so; the consultant's owner gets "Nuevo prospecto";
  a lead-magnet page emails the visitor its resource link.

**Search engines**
- Per page: title, description, canonical, Open Graph, `lang="es-CO"`, JSON-LD (`ProfessionalService`, and `FAQPage`
  when the FAQ section is on), one `h1`, responsive WebP images with sizes, inline CSS.
- `/sitemap.xml` (index), `/<consultant>/sitemap.xml`, `/robots.txt`. Draft: 404; disabled: 410; noindex pages are
  left out of the sitemap. Changing a page's or a consultant's address leaves a 301 from the old one. ETag and a short
  public cache.

## Model

- `MediaAsset` (owned): original name, type (JPEG/PNG/WebP, detected), size, dimensions, WebP variants 480/960/1600,
  alt text, active. Files under `backend/var/uploads/<account>/media/`; served at `/<consultant>/media/<id>-<width>.webp` (page
  images are public by nature). Upload checks the file-size and storage limits.
- `LeadCategory` (owned): name, colour (one of the UI tones), active.
- `LandingPage` (owned): title, slug (unique per account), template, home flag, status draft → published ⇄ disabled,
  `draft` and `published` content (JSON), published at. Content: `sections` (the template's, in order, each on/off
  with its fields), `form` (extra fields), `seo`, `settings` (default category, accent).
- `PageSlugRedirect` (owned) and `AccountSlugRedirect`: old address → page / consultant.
- `Contact` (owned): name, email (unique per account), phone, status (`lead` for now), category, source page, consent
  (at, policy hash), last activity, anonymised at. `LeadSubmission` (owned): contact, page, answers, UTM, referrer, at.
- `Account.privacyText`.

Templates are code (`App\Page\TemplateCatalog`): each is a list of sections of shared types (hero, text, features,
steps, faq, testimonials, profile, event, form) with default Spanish content, so a new page is complete before it is
edited. A draft always has exactly its template's sections; only their order, on/off and fields change.

## Out of scope

Booking and payment sections (milestones 2, 3), flows (5), board view (5), exporting leads, custom domains.
