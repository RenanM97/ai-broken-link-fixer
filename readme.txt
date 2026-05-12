=== Pathfinder Link Repair ===
Contributors: renanm97
Tags: broken links, link checker, AI, SEO, link fixer
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically find and fix broken links on your WordPress site using Pathfinder AI — intelligent replacement suggestions with one-click fixing.

== Description ==

**Pathfinder Link Repair** is the only WordPress plugin that doesn't just find broken links — it fixes them intelligently.

Most broken link plugins stop at detection. They hand you a list of dead URLs and leave you to figure out the rest. Pathfinder Link Repair goes further. Its built-in AI engine, **Pathfinder**, analyzes the context around every broken link — the anchor text, the surrounding paragraph, the page it lives on — and suggests the best replacement URLs from within your own site. One click and the link is fixed.

No broken link should stay broken. Pathfinder makes sure of it.

---

= Why Broken Links Hurt You =

Every broken link on your site is a problem:

* **SEO damage** — Search engines crawl broken links and penalize sites that have too many. Every 404 is a signal that your site is poorly maintained.
* **Lost visitors** — A user who clicks a broken link and hits a 404 page is a user who leaves. They rarely come back.
* **Damaged credibility** — Broken links make your site look abandoned, especially on cornerstone content that readers trust.
* **Wasted link equity** — Internal links pass SEO value between your pages. A broken internal link is wasted ranking potential.

Fixing broken links manually is tedious. Finding the right replacement for each one is even harder. Pathfinder Link Repair automates both.

---

= How It Works =

**Step 1 — Scan**
Click Scan Now in your dashboard. The plugin crawls every published post and page on your site, extracts every link, and checks each one for broken status (404, 410, 500, timeout). Results appear in your dashboard organized by status.

**Step 2 — Ask Pathfinder**
Click "Ask Pathfinder" next to any broken link. Pathfinder AI analyzes the context of the broken link — what the anchor text says, what the surrounding paragraph is about, what page it lives on — and searches your site for the best replacement. Within seconds you see up to three suggestions, each with a confidence score and a plain-English explanation of why it's a good match.

**Step 3 — Fix**
Click Fix next to your preferred suggestion. The broken URL is replaced in your post content instantly. Optionally, a 301 redirect is created so any external sites linking to the old URL are seamlessly redirected to the new one.

That's it. No technical knowledge required.

---

= Key Features =

**🔍 Intelligent Link Scanner**
* Scans all posts, pages, and custom post types
* Detects broken links returning 404, 410, 500 errors and timeouts
* Captures anchor text and surrounding context for every link
* Excludes domains and URLs you specify in settings
* Permanent Allowlist — add URLs or domains that should never be flagged
* Tracks when each broken link was first found
* Re-scans automatically on post save

**🧭 Pathfinder AI Engine**
* Analyzes anchor text, surrounding paragraph, and page context
* Searches your site for semantically relevant replacement candidates
* Ranks suggestions by confidence score (0–100%)
* Provides plain-English reasoning for every suggestion
* Falls back to recent content when context is ambiguous
* Powered by Claude Haiku — fast, accurate, cost-effective

**⚡ One-Click Fixing**
* Replaces broken URLs directly in post content
* No manual editing of posts required
* Optional automatic 301 redirect creation on fix
* Complete audit trail — every fix logged with before/after URLs

**📋 Fix Log**
* Paginated history of every fix ever applied
* Shows original URL, replacement URL, date, who fixed it
* Indicates whether a 301 redirect was created
* Exportable as CSV for reporting or client handoff

**🔀 Redirect Manager**
* Create and manage 301 redirects directly from WordPress
* Add redirects manually or automatically on link fix
* Tracks hit count for every redirect
* No need for a separate redirect plugin

**🗓️ Scheduled Scanning**
* Set scans to run daily, weekly, or monthly automatically
* Runs silently in the background via WordPress cron
* Never manually click Scan Now again
* Shows next scheduled scan time in settings

**🛡️ Privacy and Security**
* Your Anthropic API key is stored server-side only — never exposed to users or the browser
* All database queries use prepared statements
* All AJAX handlers verified with nonces and capability checks
* All output properly escaped
* Full uninstall cleanup — no orphaned data left behind

---

= Who Is This For? =

**Content-heavy sites** — Blogs, news sites, and magazines with hundreds of posts accumulate broken links over time as external URLs change and internal content gets reorganized. Pathfinder Link Repair keeps your link profile clean automatically.

**SEO-focused site owners** — Broken links are a known negative SEO signal. Cleaning them up is one of the fastest wins available in technical SEO. Pathfinder makes it fast enough to do regularly.

**WordPress developers** — Building sites for clients who need to maintain link health over time. Install once, configure, and hand off a self-maintaining plugin that doesn't require technical knowledge to operate.

**E-commerce stores** — Product pages, blog content, and category pages all accumulate broken links as products are discontinued and URLs change. Broken links in product descriptions and buying guides hurt conversion rates.

---

= Privacy Policy =

Pathfinder Link Repair sends link context data (broken URL, anchor text, surrounding paragraph text, and candidate replacement URLs from your site) to the Anthropic API for processing by the Pathfinder AI engine. No personally identifiable information is included in these requests. No data is stored by Anthropic beyond the duration of the API request.

The plugin does not collect, store, or transmit any visitor data. All data stored by the plugin (broken links, suggestions, fix log, redirects) lives entirely in your WordPress database.

See the **External services** section below for full details on what is sent to the Anthropic API and when.

---

== External services ==

This plugin connects to the **Anthropic API** to generate AI-powered replacement-URL suggestions for broken links. It is the only external service the plugin uses.

= What the service is =

The Anthropic API is a third-party AI service operated by Anthropic, PBC. Pathfinder Link Repair uses it to run the Claude Haiku language model, which ranks candidate replacement URLs from your own site and explains why each is a good match.

= What data is sent and when =

Data is **only** sent to the Anthropic API when a logged-in administrator clicks the **Ask Pathfinder** button (or runs the equivalent bulk action) for a specific broken link. No data is sent during normal scanning, on the front end, or in the background.

For each request, the plugin sends:

* The broken URL (e.g. `https://example.com/old-page`)
* The anchor text of the broken link
* A short snippet of surrounding paragraph text from the post where the link appears
* The title of the post the broken link was found on
* A list of up to ~8 candidate replacement URLs from your own site, with their titles and short excerpts
* The Anthropic API key you configured in **Broken Links → Settings**

The plugin does **not** send visitor data, post content beyond the snippet around the broken link, user account information, login credentials, or any other personal data.

Requests are sent to: `https://api.anthropic.com/v1/messages`

= Anthropic's terms and policies =

By configuring an Anthropic API key and using the Ask Pathfinder feature, you are bound by Anthropic's terms of use and privacy policy:

* **Terms of Service:** [https://www.anthropic.com/legal/consumer-terms](https://www.anthropic.com/legal/consumer-terms)
* **Privacy Policy:** [https://www.anthropic.com/legal/privacy](https://www.anthropic.com/legal/privacy)

If you do not configure an Anthropic API key, no data is ever sent to the Anthropic API and the plugin's scanning, allowlist, redirect, and fix-log features all continue to work normally.

---

== Installation ==

= Automatic Installation (Recommended) =

1. Log in to your WordPress admin dashboard
2. Go to **Plugins → Add New**
3. Search for **Pathfinder Link Repair**
4. Click **Install Now** next to the plugin
5. Click **Activate**
6. Go to **Broken Links** in your admin sidebar to get started

= Manual Installation =

1. Download the plugin zip file from wordpress.org
2. Log in to your WordPress admin dashboard
3. Go to **Plugins → Add New → Upload Plugin**
4. Choose the downloaded zip file and click **Install Now**
5. Click **Activate Plugin**
6. Go to **Broken Links** in your admin sidebar

= First-Time Setup =

The plugin works immediately after activation with no configuration required. To use Pathfinder AI suggestions you will need an Anthropic API key:

1. Go to [console.anthropic.com](https://console.anthropic.com) and create an account
2. Navigate to **API Keys** and click **Create Key**
3. Copy your API key (it begins with `sk-ant-`)
4. In your WordPress admin go to **Broken Links → Settings**
5. Paste your API key in the **Pathfinder AI** section
6. Click **Save Settings**

= First Scan =

1. Go to **Broken Links → Dashboard**
2. Click **Scan Now**
3. The plugin extracts all links from your posts and pages and checks each one
4. Broken links appear in the dashboard as the scan completes
5. Click **Ask Pathfinder** next to any broken link to get AI suggestions
6. Click **Fix** next to your preferred suggestion to apply it

---

== Frequently Asked Questions ==

= Do I need technical knowledge to use this plugin? =

No. Pathfinder Link Repair is designed for anyone who manages a WordPress site. The dashboard is straightforward — scan, review suggestions, click Fix. No coding required at any point.

= How does Pathfinder find replacement URLs? =

Pathfinder uses a two-step process. First it searches your own site's content for pages that are semantically related to the broken link — using the anchor text, the broken URL's slug, and the surrounding paragraph as search signals. Then it sends those candidates to Claude Haiku (Anthropic's AI model) which ranks them by relevance and explains its reasoning. The result is a ranked list of replacement suggestions pulled entirely from your own site.

= Will Pathfinder ever suggest a URL that doesn't exist on my site? =

No. Pathfinder only suggests URLs from your own site's published content. It never invents URLs or suggests external replacements.

= What happens if Pathfinder can't find a good match? =

Pathfinder returns "No confident matches found on this site." This happens when the broken link's context is too generic or your site doesn't have relevant content to replace it with. In this case you can ignore the link, add it to your Allowlist if it's intentionally broken, or create new content that would be a good replacement.

= Does the plugin slow down my site? =

No. All scanning and AI processing happens in the WordPress admin only and runs via background cron jobs. Nothing runs on your public-facing site pages. Your visitors will never experience any slowdown.

= How often should I run a scan? =

For most sites, weekly is sufficient. Active blogs and news sites benefit from daily scanning. You can configure automatic scheduled scans under **Settings → Scan Settings** so you never have to think about it.

= What HTTP status codes does the scanner flag as broken? =

The scanner flags these as broken:
* **404** — Page not found (most common)
* **410** — Page permanently gone
* **500** — Server error
* **503** — Service unavailable
* **0** — Connection timeout or DNS failure (completely unreachable URL)

Links returning 200 (OK), 301 (redirect followed successfully), or 302 (temporary redirect followed successfully) are considered healthy and not flagged.

= Can I exclude specific URLs or domains from scanning? =

Yes. Go to **Broken Links → Settings → Scan Settings** and add URLs or domains to the exclusion lists. You can also use the **Allowlist** tab in the dashboard to permanently whitelist specific URLs or entire domains that should never be flagged.

= What is the Allowlist? =

The Allowlist is a permanent safe list of URLs and domains that the scanner will always skip. Use it for:
* URLs that intentionally return 404 (like API endpoints)
* External domains you know are temporarily down
* Partner sites you trust but that occasionally have server issues
* Any URL you never want the scanner to flag

= Does fixing a link also create a redirect? =

Optionally, yes. In **Settings → On Fix** you can enable automatic 301 redirect creation. When enabled, every time you fix a broken link the plugin also creates a 301 redirect from the old broken URL to the new replacement URL. This is useful when other external sites might be linking to your broken URL.

= Can I undo a fix? =

The plugin does not automatically undo fixes, but every fix is recorded in the Fix Log with the original and replacement URLs. You can manually restore the original URL by editing the post directly in WordPress.

= How is my data handled? =

When you use Pathfinder, the broken link's context (anchor text, surrounding paragraph, and candidate replacement URLs from your site) is sent to the Anthropic API for processing. No visitor data, no personal information, and no sensitive content is transmitted. See the **External services** section above for full details.

= Is this plugin compatible with page builders? =

The scanner extracts links from WordPress post content (the `post_content` field in the database). Links added via page builders that store content in the standard post content field (like Gutenberg, Classic Editor, and most block-based builders) are fully supported. Links stored in custom meta fields by some page builders (like Elementor's proprietary storage) may not be detected. Support for additional storage formats is planned for future versions.

= Is this compatible with multisite? =

For WordPress multisite networks, install the plugin on each subsite individually. Network-wide multisite activation is not currently supported but is planned for a future release.

= Where can I get support? =

For general questions and community support visit the [WordPress.org support forum](https://wordpress.org/support/plugin/pathfinder-link-repair/).

---

== Screenshots ==

1. **Dashboard** — The main broken links dashboard showing scan controls, summary cards, and the broken links table with Pathfinder suggestion cards expanded.
2. **Pathfinder Suggestions** — The AI suggestion card showing ranked replacement URLs with confidence scores and plain-English reasoning.
3. **Fix Log** — The audit trail showing every fix applied with original URLs, replacement URLs, timestamps, and redirect status.
4. **Redirect Manager** — The 301 redirect manager showing active redirects with hit counts.
5. **Settings** — The settings page showing scan configuration, exclusion lists, and notification options.
6. **Allowlist** — The allowlist tab showing permanently excluded URLs and domains with options to add and remove entries.

---

== Changelog ==

= 1.0.0 =
* Initial release
* Broken link scanner with HTTP status detection (404, 410, 500, timeout)
* Pathfinder AI engine with Claude Haiku integration
* One-click link fixing with post content replacement
* Fix log with CSV export
* Allowlist manager for permanent URL exclusions
* Excluded domains and URLs settings
* Redirect manager with 301 redirect creation and hit tracking
* Scheduled automatic scanning — daily, weekly, monthly
* Ignore and restore broken links
* Bulk actions — ignore selected, restore selected, add to allowlist
* Full i18n support with .pot translation template
* Complete uninstall cleanup

---

== Upgrade Notice ==

= 1.0.0 =
Initial release of Pathfinder Link Repair. Install and activate to start finding and fixing broken links with Pathfinder AI.
