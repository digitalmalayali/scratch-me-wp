=== Scratch Me | By Digital Malayali ===
Contributors: Digital Malayali
Tags: giveaway, scratch card, lead generation, phone number, sms marketing
Requires at least: 5.5
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Interactive scratch-card giveaways that capture phone number leads instead of email addresses. Self-hosted, no third-party SaaS required.

== Description ==

Scratch & Win replaces static popups with a gamified scratch-off card that visitors reveal with mouse or touch. Instead of collecting email addresses, it is optimized for mobile-first, phone-number-based marketing: SMS campaigns, WhatsApp outreach, and direct calls.

**Key Features**

* Interactive HTML5 canvas scratch-card effect (mouse + touch, works on phones)
* Indian mobile number capture: fixed +91 prefix, 10-digit numbers starting 6–9, no email required
* One entry per number per campaign — returning visitors see their original result ("You already won Smart Watch on 22 March 2027")
* Per-prize Chance (%), stock limits, coupon codes, images and descriptions
* Stock is claimed atomically, so a limited prize can never be given out more times than you set
* CSV import of prize lists (sample format included)
* Phone gate before or after the scratch reveal
* Custom questions before the card unlocks: short text, paragraph, dropdown, radio buttons, checkboxes, number, email and date — with required fields, character limits and live counters, allowed-character rules (letters / digits / letters+digits), number ranges, date ranges and min/max selections. Every rule is checked in the browser and again on the server.
* Custom titles, promo text, templates, background and scratch-layer images
* Win / lose / already-won / already-played messages with {prize}, {coupon}, {phone} and {date} placeholders
* Anti-abuse: per-IP entry limit, hidden bot trap, Cloudflare/proxy support
* Works with page caching (fresh security token fetched in the background)
* Leads list with search, pagination, Trash / Restore / Delete permanently and bulk actions
* One-click CSV export (Excel-friendly) for your SMS / WhatsApp tool
* Instant admin email notification on every new lead
* Business profile, time zone and date format settings
* 100% self-hosted — all data stays in your WordPress database
* GPLv2 licensed, fully open source

**How the odds work**

Each winning prize has its own Chance (%). A visitor wins that prize with exactly that chance, and loses the rest of the time. Example: Laptop 1%, Smart Watch 2%, 20% Off 5%, 15% Off 15%, 10% Off 27% → 50% of visitors win something, 50% see "No Prize". The "Total Chance" under the prize table is your overall win rate and must not exceed 100%. When a prize sells out, its chance is shared among the prizes still in stock (in proportion to their own Chance), so your win rate stays the same until every prize is gone. You can switch a campaign to "Turn its chance into No Prize" instead. The campaign editor shows the live odds ("Right now: visitors win …") and lists sold-out prizes.

**Custom questions**

In the campaign editor, use "+ Add Question" to ask up to 20 questions together with the mobile number. Pick the answer type, tick Required, and set the rules that apply to that type (options one per line for dropdowns, radio buttons and checkboxes). Use ↑ ↓ to change the order. Answers show on the Leads page, in the email alert, and in the CSV export (one column per question when you export a single campaign). Returning visitors are not asked again — their first answers are kept.

**Developer hooks**

* `sw_before_draw` (filter) — return a WP_Error to reject an entry, e.g. after an OTP check.
* `sw_lead_captured` (action) — runs after a new lead is saved: ($lead_id, $campaign, $phone, $draw).
* `sw_rate_limit` (filter) — change the per-IP hourly entry limit.
* `sw_client_ip` (filter) — override how the visitor IP is detected.
* `sw_uninstall_delete_data` (filter) — force data deletion on uninstall.

**Shortcode**

Use `[scratch_win id="1"]` to display a specific campaign, or `[scratch_win]` to show your most recent active campaign.

== Installation ==

1. Upload the `scratch-win` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to Scratch & Win → Campaigns to configure your first giveaway.
4. Paste the shortcode into any page or post.

== Frequently Asked Questions ==

= Does this plugin require any paid service? =
No. All leads are stored directly in your WordPress database. SMS/WhatsApp gateway integrations are on the roadmap as optional add-ons.

= Are phone numbers verified by SMS (OTP)? =
Not in this version — any valid-looking Indian mobile number is accepted. The per-IP limit and bot trap stop most abuse. An OTP add-on can plug into the `sw_before_draw` filter.

= Does it work with caching plugins? =
Yes. The card fetches a fresh security token in the background, so cached pages keep working.

= Can I require the phone number after the reveal instead of before? =
Yes. Each campaign has a "Phone Number Gate" option to require the phone number before or after the scratch reveal.

== Changelog ==

= 1.6.1 =
* Fixed: on servers with a low PHP "max_input_vars" limit, saving a large campaign could silently drop prizes or questions while showing "Saved". The editor now refuses to save a cut-off form (nothing is changed) and explains how to fix it; the browser also warns before submitting.
* Fixed: answers such as "100%cashback" were altered by WordPress' text cleaner; answers, questions and options are now kept exactly as typed (HTML is still removed).
* Fixed: if a page cache served an old copy of the form after questions were changed, visitors now see "Please reload the page" instead of an error for a field they can't see.

= 1.6.0 =
* New: custom questions before the scratch card unlocks — 8 answer types, required fields, character limits with live counters, allowed-character rules, number/date ranges and min/max selections, reordering, and checks in both the browser and the server.
* New: answers on the Leads page (searchable), in the email alert and in the CSV export.
* Improved: the mobile number field gets its own label when questions are shown, and inputs use 16px text so iPhones don't zoom in.

= 1.5.1 =
* New: {phone} placeholder for all messages, e.g. "Congratulations! You won: {prize} on {phone}" shows "... on +91 98765 43210".

= 1.5.0 =
* Fixed: visitors got "No Prize" while gifts were still in stock, because a sold-out prize's chance became a loss. Its chance is now shared among the prizes that are still available (new per-campaign setting "When a prize runs out"; the old behaviour is still available).
* New: sold-out prizes are marked "Sold out" in red, and a "Right now" panel under the prize table shows each visitor's real odds.

= 1.4.0 =
* Fixed: unticking "Active" or "Is win" on one prize could apply it to a different prize when saving.
* Fixed: saving a campaign changed every prize's ID, so returning winners lost their coupon code, image and description. Prize IDs now stay the same, and each lead keeps its own copy of the prize details.
* Fixed: two visitors entering at the same moment could both win the last unit of a limited prize. Stock is now claimed atomically before the win is saved, with an automatic redraw if it just sold out.
* Fixed: the same number submitted twice at once could be told it won twice and use up stock; the second request now shows the first result.
* Fixed: valid mobile numbers beginning with 91 (e.g. 91234 56789) were rejected.
* Fixed: a shortcode with a paused or deleted campaign ID showed a different campaign instead.
* Fixed: the Time Zone setting is now used for all dates.
* Fixed: scratch card overflowed small phone screens; custom scratch images could briefly reveal the prize while loading.
* Changed: changing a prize's Stock no longer resets its Winner(s) counter — units already won are subtracted from the new total.
* Changed: "No Prize" rows can no longer be marked as a win; Total Chance now shows the real overall win rate and warns above 100%.
* New: import prizes from CSV.
* New: per-IP limit on new entries, hidden bot trap and Cloudflare/proxy option.
* New: works with full-page caching (fresh nonce + automatic retry).
* New: Leads search, pagination (50 per page) and Coupon column; CSV export includes coupon codes, streams large lists and is protected against spreadsheet formula injection.
* New: "Delete all data when the plugin is deleted" setting; {coupon} message placeholder; developer hooks.
* Removed the unused "win probability" option (odds come from each prize's Chance).

= 1.3.0 =
* Leads can be moved to the Trash, restored, or deleted permanently — one at a time or with Bulk actions.
* New All / Trash views on the Leads page with an "Empty Trash" button and an Undo link after trashing.
* Trashed leads are left out of the CSV export and lead counts.

= 1.2.0 =
* Returning visitors now see when they played, e.g. "You already won Smart Watch on 22 March 2027."
* New per-campaign "Already Won" and "Already Played" messages with {prize} and {date} placeholders.

= 1.1.0 =
* Phone capture locked to India format: fixed +91 prefix, 10-digit number entry only.
* Returning visitors who re-enter their phone number now see the prize they already won instead of re-rolling.
* Prizes now support an image, a description, a coupon code, and a Chance (%) instead of relative weight.
* Added per-prize Stock/Count limits (e.g. Watch = 10, Laptop = 15); prizes auto-stop being awarded once stock runs out, without affecting past winners.
* Redesigned the prize table to show image, type, description, chance, stock, winner count, and an active toggle per row.
* Added a downloadable sample prize list CSV format.

= 1.0.0 =
* Initial MVP release: campaigns, phone lead capture, CSV export, admin notifications.
