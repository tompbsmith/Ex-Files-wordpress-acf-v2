# The Ex Files — WordPress theme

A WordPress version of the original Ex Files one-pager (previously a static site on Vercel), with the same design. The homepage is built from **ACF Flexible Content** sections, so each section can be edited, reordered, hidden or added from the page editor.

## Requirements

- WordPress 6.3+ (tested on 7.1.3), PHP 7.4+
- **ACF Pro** *or* the free **Secure Custom Fields** plugin. Both include Flexible Content and options pages; the free version of ACF does not.
- An SMTP plugin is recommended so the contact form's emails get delivered reliably (e.g. *WP Mail SMTP*). Resend, which the old site used, offers SMTP and works with these plugins.

## Install

1. Upload the theme to `wp-content/themes/` (or **Appearance → Themes → Add New → Upload** with a zip of this repo) and activate **The Ex Files**.
2. Install and activate ACF Pro or Secure Custom Fields.
3. Go to **Appearance → Ex Files Setup** and click **Import default content**. This:
   - creates a "Home" page and sets it as the homepage (or uses your existing static homepage)
   - fills it with the original site's sections and copy
   - adds the two hero videos and the logo to the Media Library
   - fills in **Site Settings** (footer, socials, contact recipient, meta description)
   - creates the main menu (Home / Our Services / Open Your Ex File) if none is assigned
4. Set **Settings → General → Site Title** to "The Ex Files" and the **Tagline** to "Matrimonial Finance Specialists". The browser tab title is built from these, giving "The Ex Files | Matrimonial Finance Specialists".

Running the import again won't change existing content unless you tick **Replace existing sections and settings**.

## Editing

**Pages → Home** shows the sections. Each one has:

- a **Content** tab (plus **Background** for the hero and **Form** for the contact section)
- a **Settings** tab with the **Section ID** (the anchor menu links point to, e.g. `services` for `#services`) and a **Hide this section** switch

| Section | What it is |
|---|---|
| Hero (video background) | Eyebrow, heading, lines of text, buttons, and desktop/mobile videos and posters |
| Guide (text + card) | Heading and text alongside a card with a button |
| Services (cards) | Heading, intro and any number of cards |
| How it works (numbered steps) | Steps are numbered automatically. Hidden by default, as on the original site |
| Trust (icon columns) | Heading plus items with a built-in icon (shield/report/document) or an uploaded one |
| Contact form | Heading, intro, optional email display, optional phone field, button label, success message |

Any other page can use the same sections: choose **Template → Sections** in the page sidebar.

**Site Settings** (left admin menu) holds the contact form recipient, footer tagline and email, social links, an optional footer logo, copyright and legal links, and the homepage meta description (ignored when Yoast or Rank Math is active).

The **logo** comes from **Appearance → Customize → Site Identity**. The **menu** is under **Appearance → Menus** (Primary). Use custom links such as `#services`; they still work from other pages.

### Hero posters

The poster images aren't set yet. In the Hero section's **Background** tab, upload a **Desktop poster** and a **Mobile poster**. They show while the video loads, and in place of the video for visitors with "reduce motion" switched on.

## Contact form

The form posts to `admin-ajax.php` (action `exf_contact`) and sends a plain-text email with `wp_mail()`, with Reply-To set to the sender. This replaces the old Vercel `/api/contact` function. Spam protection is a hidden honeypot field plus a limit of 5 messages per 10 minutes per IP. There's no nonce, so the form keeps working when pages are cached.

## Ex Files core (client data and the Free Divorce Guide)

Everything beyond the marketing pages lives in `inc/core/`. It's kept separate from the theme templates so it can become its own plugin later, but it ships in the theme for now because the InstaWP Git deploy only deploys this folder. The build plan is in Google Drive: *The Ex Files – Website Build Plan*.

**Admin:** an **Ex Files** menu holds Ex Files, Questionnaires, Client Documents, Guide Requests, the Conflict Register, **Settings** and **Divorce Guide Content**. Access needs the `manage_ex_files` capability. Administrators have it, and there's an **Ex Files Manager** role for the solicitor that doesn't need full admin rights.

**Pages:** the Ex Files pages below are created automatically the first time an admin opens WP admin after a deploy, and a notice lists what was added. That's the default on test sites (InstaWP, Local, or any site whose WordPress environment type isn't `production`). On the live site, a notice with a **Create missing pages** button appears instead, so nothing is added without someone choosing to. Change this under **Ex Files → Settings → Pages**. Existing pages are never changed. The pages are:

- **Free Divorce Guide** (`/free-divorce-guide/`): an ordinary Sections page, so edit it like the homepage
- **Get your free guide** (`/free-divorce-guide/start/`, template *Divorce Guide Form*)
- **Your guide is on its way** (`/free-divorce-guide/sent/`, template *Divorce Guide Sent*)
- **Open your Ex File** (`/open-your-ex-file/`, template *Open Your Ex File*): registration
- **My Ex File** (`/my-ex-file/`, template *My Ex File (account)*): the client's account page
- **Choose your service** (`/my-ex-file/buy/`, template *Buy a Service*): checkout
- **Our services** (`/services/`, a Sections page) and one page per service under it (template *Service*): Know Your Position, Check Your Deal, Make It Legal, Free 15-minute consultation

Pages are found by their template, so they can be renamed or moved.

### Free Divorce Guide

1. One question per screen (`assets/js/guide.js`). Without JavaScript all questions show and the server works out which apply.
2. On submit, the server re-walks the answers from Q1 (`inc/core/guide/logic.php`), drops answers that aren't on the visitor's path, and works out the tags and paragraphs from the client's mapping sheet.
3. Dompdf renders `templates/pdf/guide.php` (guide paragraphs in P-number order, then Annex A with the answers).
4. The PDF is saved to `wp-content/uploads/exfiles-private/guides/`, emailed to the visitor, and logged as a **Guide Request**. The "sent" page offers a download link that works for 48 hours.
5. Guide Requests (and their PDFs) are deleted automatically after the retention period in **Ex Files → Settings** (default 24 months). There's an **Export CSV** button on the list.

The branching is in `exf_guide_next_step()` (PHP) and `next()` in `guide.js`; keep the two the same. Everything else (paragraph wording, which paragraphs each answer adds, tooltips, jurisdiction routes, screens, PDF boxes, email) is editable in **Ex Files → Divorce Guide Content**. Empty fields fall back to the wording from the client's precedent document in `inc/core/guide/defaults.php`. Use **Load the default wording** to copy it into the fields for editing.

### Accounts and the conflict check

**Registration** (`/open-your-ex-file/`, `inc/core/accounts/register.php`) has four steps: your details, your ex, your relationship, your account. It creates a `customer` user (the WooCommerce role) and their first **Ex File** (one separation). Contact details are saved under WooCommerce's `billing_*` keys, so checkout will be pre-filled. Nothing is checked until the client clicks the link in the confirmation email (valid 48 hours). Unconfirmed registrations are deleted after 7 days (**Ex Files → Settings**).

**The conflict check** (`inc/core/accounts/conflict.php`) runs when the email is confirmed. It compares:

- the **ex** with every existing client, and with Conflict Register entries for clients and "other";
- the **client** with the exes named in other clients' Ex Files, and with register entries for opposing parties (the reverse check).

Names are normalised (case, accents, titles, punctuation, previous names, double-barrelled surnames). Each comparison scores its strongest signal:

| Signal | Points |
| --- | --- |
| Email matches | 100 |
| Full name and date of birth | 100 |
| Phone number | 80 |
| Full name and postcode | 70 |
| Surname and date of birth | 40 |
| Full name only | 30 |

Reverse matches and "other" register entries are capped at 60, so they raise a review but never block on their own. The highest score decides the outcome:

- **100+: blocked.** The client sees and is emailed a generic "unable to act" message (never the reason), can't log in, and the solicitor is alerted.
- **30–99: review.** The client can log in but can't buy anything. The Ex File appears in **Ex Files → Conflict Review**.
- **Under 30: clear.** The account is active.

The weights are constants at the top of `conflict.php`, to be tuned with the client once real data is in.

**Conflict Review:** each Ex File shows the matches side by side, with the matching details in bold, and buttons to **Clear**, **Request more information** (emails the client, who replies from My Ex File; the check runs again and it comes back to the queue), **Block**, or **Run the check again**. Every step is logged in the Ex File's **Activity** box.

**Conflict Register:** add people by hand, or import a CSV under **Ex Files → Import Register** (there's a template to download). Until the solicitor's existing clients are imported, the check only knows about people who registered on the website.

**Emails:** all account emails can be edited in **Ex Files → Email Copy**. Empty fields use the defaults in `inc/core/accounts/emails.php`.

**Login:** clients log in on the branded Log in page (see Client portal below) and go to My Ex File afterwards. They're kept out of wp-admin.

### Services, payments and booking

**Services** are pages using the *Service* template. Each has **Service settings**: price (or free), whether it needs an appointment and how long, whether it needs the financial questionnaire, what the client receives, a short description, and the button label. Below that, the page uses the same Page Builder sections as the homepage. New service pages are created **without a price**, and their Buy button stays hidden until one is set. A notice in the admin lists any services without a price.

**Buying** (`inc/core/commerce/buy.php`, `page-buy.php`): a logged-out visitor clicking Book/Get started goes to registration first, and the service is remembered. A logged-in client chooses **which Ex File** it's for (or adds **a new separation**, which runs the conflict check on its own; only that Ex File is affected), chooses a time if the service has an appointment, and pays on **Stripe Checkout**. Free services are confirmed without payment. Clients under review can't buy.

**Payments** (`inc/core/commerce/stripe.php`): Stripe Checkout Sessions created with the API directly (no plugin). Keys and the webhook secret go in **Ex Files → Settings → Payments**, or better, as `EXF_STRIPE_SECRET_KEY` and `EXF_STRIPE_WEBHOOK_SECRET` in `wp-config.php` on the live site. The webhook URL is shown on that settings tab. A payment is confirmed by the signed webhook or by the return page checking the session, whichever comes first, and it's only processed once. Refunds are done in the Stripe dashboard, and the order is marked refunded through the webhook.

**Orders** (Ex Files → Orders) record the client, Ex File, service, amount, appointment and a history. Paying creates the client's questionnaire as a to-do (the form itself comes in phase 6), and sends confirmation emails to the client and to you, with a calendar invite when there's an appointment.

**Booking** (`inc/core/commerce/booking.php`): set your weekly availability, how far ahead clients can book, the gap between appointments, dates you're away, and the minimum notice (default 24 hours) in **Ex Files → Settings → Bookings**. Paste your Google Calendar's **secret iCal address** there too, and busy times in your calendar (including recurring events) are never offered. Events marked "Free" are ignored. A chosen time is held for 30 minutes while the client pays. Bookings are checked again under a lock just before the order is created, so two people can't book the same time. Clients get a reminder email about 24 hours before.

### Client portal (My Ex File)

`page-account.php` with sections in `template-parts/portal/`, logic in `inc/core/portal/`. The sections are rewrite endpoints on the one My Ex File page: `/my-ex-file/` (overview: what to do next, and each Ex File), `ex-files/` and `ex-files/{id}/` (status, the reply form when you've asked for more information, services, questionnaires, documents), `documents/`, `appointments/`, `payments/` and `account-details/`. Wording is editable in **Ex Files → Portal Copy**. The header shows **My Ex File** to logged-in visitors and **Log in** to everyone else.

**Documents** (Ex Files → Client Documents, or *Upload a document for this client* on an Ex File): files are stored privately, never in the media library. Tick *Email the client* to send a "document ready" email, which links to the portal rather than attaching the file. The portal marks new documents until the client opens them, and the admin shows when they did. Clients can send documents too (PDF, Word, Excel, CSV, text or photos, up to 15 MB, 20 an hour); you get an email, and they can remove their own uploads. Deleting a document deletes its file.

**Appointments:** clients can download a calendar file, but can't move or cancel online. They send a request, which emails you and shows on the order, the Ex File and the dashboard. If you cancel, use *Cancel appointment* on the order to free the slot; it isn't refunded (refunds, if ever, are done in Stripe). **Payments** lists what they've paid, with a reference; Stripe emails the receipts, and there's no VAT.

**Account details:** clients can change their phone, address and marketing choice. A new email address only takes effect after they click a link sent to it, and the old address is told. Name and date of birth can't be changed online, because they're part of the conflict check.

**Log in** (`page-login.php`): branded log in, forgotten password and reset password. Every login and reset link on the site points here, and the reset email uses the wording in Email Copy. `wp-login.php` still works for staff. Ten failed logins from one address lock it out for 15 minutes.

The **dashboard widget** shows conflict checks waiting, appointment requests, the next 7 days' appointments and recent client uploads.

### Private files

Client files go in `wp-content/uploads/exfiles-private/`, which has an `.htaccess` that blocks direct access on Apache. **On Nginx hosts, also add a rule denying web access to that folder.** Files are only served through PHP after a permission or token check (`inc/core/storage.php`).

### PDF library

`vendor/` holds Dompdf and its dependencies. It's committed because the deploy doesn't run Composer. It also holds sabre/vobject, for reading calendars. To update them, run `composer update` (`composer.json` points at the packages' GitHub repositories), then commit `vendor/`.

## Developing

- Field groups are stored as JSON in `acf-json/` and loaded automatically. Edit them on a local copy of the site (e.g. Local, with the theme folder cloned from this repo). Saving a field group re-writes its JSON in the theme, so commit and push it.
- **Auto-sync:** when a deploy brings in field group JSON that's newer than a site's database copy, the theme imports it the next time an admin page loads. A notice says "Field groups updated from the theme". The repo is the source of truth, so don't edit field groups directly on the deployed sites.
- Section templates are in `template-parts/sections/<layout>.php`. To add a new section, add a layout to the "Sections" flexible content field and create a matching template file.
- Styles are in `assets/css/main.css`, carried over from the original site. Scripts are in `assets/js/main.js` (menu, hero video loader, contact form).

```
acf-json/                 Field groups (Page Sections, Site Settings)
assets/css/main.css       Site styles
assets/js/main.js         Menu, hero video, contact form
assets/img/logo.png       Fallback logo
assets/defaults/          Hero videos used by the importer
inc/                      Setup, helpers, ACF config, contact handler, importer
inc/core/                 Ex Files core: post types, private files, settings, Free Divorce Guide
templates/pdf/            PDF templates (Dompdf)
vendor/                   Dompdf (committed)
template-parts/sections/  One template per section layout
front-page.php            Homepage (renders sections)
page-sections.php         "Sections" page template
page-guide-form.php       Free Divorce Guide questionnaire
page-guide-sent.php       Guide sent / download page
```
