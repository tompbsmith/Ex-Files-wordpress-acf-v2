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
template-parts/sections/  One template per section layout
front-page.php            Homepage (renders sections)
page-sections.php         "Sections" page template
```
