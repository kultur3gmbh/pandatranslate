=== Panda Translate ===
Contributors: kultur3gmbh
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.8.1
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Panda Translate: visual WordPress translation with two languages, language paths, switchers, menus and page-builder elements.

== External services and project ownership ==

Panda Translate is developed by Kultur3 GmbH as part of the PandaCosmo product family. Manual translation and the two-language base workflow require no PandaCosmo license or translation-provider account.

Automatic translation and explicit connection tests use only the configured provider. Google Cloud Translation v2/v3 receives selected source text or protected markup, source/target language identifiers and authentication data; v3 also uses the configured project/location and Google OAuth service-account token exchange. Endpoints are translation.googleapis.com and oauth2.googleapis.com. See https://cloud.google.com/terms and https://policies.google.com/privacy .

DeepL receives selected source text or protected markup, source/target language and requested formality through api-free.deepl.com or api.deepl.com, authenticated with the configured API key. See https://www.deepl.com/pro-license and https://www.deepl.com/privacy . Provider charges may apply. Saved translations are displayed locally without requesting another external translation for every visitor. Personalized customer and order values are excluded from automatic source collection.

Collecting source pages can make requests to the current website. Browser-language recognition uses browser hints; country recognition uses available local server or WooCommerce geolocation data, without a separate external IP-lookup service initiated by Panda Translate. Explicit product-information links open only when followed. The separate GitHub distribution checks api.github.com and downloads authorized public assets from GitHub; this updater is excluded from the WordPress.org package. Standard WordPress plugin and translation update requests remain available.

== Source code and translations ==

The plugin source is included in this package. PHP, JavaScript and CSS are maintained directly; no compilation or minification step is required to run the plugin. The sources/ directory provides expanded readable copies of compact JavaScript helpers, including the shared Panda controls. sources/manifest.json maps each copy to its runtime file and records the runtime SHA-256. These copies are formatting-only equivalents, verified by comparing JavaScript syntax trees.

To reproduce an expanded copy, use Prettier 3.6.2 with parser babel, singleQuote true, useTabs true and printWidth 100 on the corresponding assets/ file. No external source download is required to inspect or modify the included plugin. Shared controls retain the same runtime implementation across Panda products.

The WordPress.org distribution is prepared for community translations through translate.wordpress.org. Existing PO/MO catalogs remain maintained for separate distributions and are omitted only from the WordPress.org package. Before a WordPress language pack is available, untranslated strings fall back to English.

== Usage ==

Configure languages on Panda Translate > Dashboard. Automatic translation has its own Translation menu, and Glossary has its own menu. The remaining settings are tabs under Settings. URL format is in Language switcher. Then use “Translate page” in the upper admin bar while viewing the desired page.

The editor can also be opened manually by appending:

?pandatranslate=edit&pt_lang=en

Click a visible text unit, edit the protected full unit in the left panel and save it as a draft or publish it. Formatting placeholders must remain complete and correctly nested.

The Translation APIs tab supports Google Cloud Translation Basic v2 and Advanced v3 simultaneously. Additional configured providers can register instances or labelled descriptors through the pandatranslate_providers filter. Each translation run selects its API; background jobs preserve that selection. Automatic translation is configured separately.

For v3, store the service-account JSON outside the public web root and add its path to wp-config.php:

define( 'PANDATRANSLATE_GOOGLE_CREDENTIALS_FILE', '/secure/path/service-account.json' );

For v2, enter the API key directly in the Translation APIs tab and test it there. Alternatively, configure it in wp-config.php:

define( 'PANDATRANSLATE_GOOGLE_API_KEY', 'your-server-side-api-key' );

Translations use the selected API. Publication follows the Automatic translation settings. API connection tests run immediately when clicked. Provider charges may apply.

The language switcher can be added to registered navigation locations, displayed as a floating menu, or inserted with `[pandatranslate_switcher]`.

Settings > System status shows the authenticated Streamable HTTP endpoint. Use a dedicated WordPress administrator application password. Write and paid tools require `confirm=true`. When ContactPanda is active, Panda Translate tools are also added to its central MCP endpoint. Royal MCP 1.5.11 or later automatically lists the same tools on its existing connection via royal_mcp_tools and royal_mcp_execute_tool_external. Royal authentication, read-only mode, drafts-only mode and connection restrictions remain in force; Panda Translate additionally requires manage_options for every call. No additional connector or Royal dependency is required.

Editorial MCP workflow: discover pandatranslate_settings_get, search pandatranslate_translations_list with language/query/limit/offset, then read pandatranslate_translation_get. Keep the complete source unit and all [[PT:...]] inline placeholders. Send translated_text, language, unit_key, reviewed/published status, expected_revision from that read, and confirm=true to pandatranslate_translation_save. Stale revisions and concurrent changes fail instead of overwriting; successful writes return verified=true and a freshly read translation. API keys and credential files are never returned. Collected units only are searchable; use the visual editor or automatic collection to capture new source pages. Pagination uses next_offset; one final empty page can occur at an exact page boundary.

Imported units have source_dictionary scope: publishing their correction can update identical source text across multiple pages. Existing page-specific manual translations take precedence. Conflicting dictionary translations are not chosen silently. Ordinary page units have unit scope. Drafts are not public. Menus are translated through collected text units, without changing their structure. pandatranslate_seo_get reads language-specific slug, SEO and social overrides and source Rank Math/Yoast values; editing those overrides remains available in the SEO metabox, not through MCP. Royal does not supply undo snapshots for external tools; retain the preceding read if a manual reversal is needed.

Existing 0.4.x data and options are retained. Free enables the source language and one target language. Additional stored languages remain available after enabling licensed Panda Translate Multilingual. SEO and WooCommerce integration are provided by that extension. No other Panda plugin is required. Existing imports are repaired on the next administrator backend visit when the original TranslatePress tables are still present. Only unchanged imported entries previously published in TranslatePress are restored to published status.

== Distribution and migration adapters ==

The WordPress.org distribution uses the standard WordPress.org update system. Separately distributed GitHub packages include their own public-release updater and checksum manifest. WordPress provides manual update checks and optional per-plugin automatic updates. Free requires no paid license. Panda Translate Multilingual is included in the All-in bundle and requires only this base plugin.

The pandatranslate_migration_sources filter allows verified future import adapters to register label, detected and render callbacks. Adapters must enforce administrative capabilities, nonces, bounded batches and existing manual-translation protection. TranslatePress is the only supported built-in source in 0.5.0; no other importer is advertised as working. The Migration tab appears only for detected registered sources.

== Changelog ==

= 0.8.1 =
* Recover configured WooCommerce system page routes when stored rewrite rules are incomplete.
* Apply published header and footer translations on cart, checkout and account pages while excluding private transaction content.

= 0.8.0 =
* Continue automatic translation in the background and restore overall and per-language progress when the Translation page is reopened.
* Add the website overview, compact translation progress, responsive content-type options and independent Google Translate and DeepL settings.
* Improve TranslatePress migration, including formal German and imports of missing entries only.
* Update WordPress.org packaging, language pack handling and repository checks.

= 0.7.41 =
* Use WordPress language pack loading with bundled catalog fallback for separate distributions and document the translation and database operations used by the free plugin.

= 0.7.40 =
* Show automatic translation content types as checkbox tiles that reflow with the available width.

= 0.7.39 =
* List enabled translation APIs with their versions and plan on the dashboard.
* Widen the address style column so dropdown labels have room alongside the arrow.

= 0.7.38 =
* Order the Panda Translate submenu, group Panda modules around Rank Math and WooCommerce, and show published content, recorded source characters and enabled translation APIs on the dashboard.

= 0.7.37 =
* Arrange Google Translate and DeepL in equal-width responsive settings cards with independent save actions.
* Match the height of text inputs and dropdowns to the search button in Translation texts.

= 0.7.36 =
* Reflow the translation progress and actions without horizontal scrolling.

= 0.7.35 =
* Put the legend and translation actions on two rows, with a character count for failed translations.

= 0.7.34 =
* Consolidate duplicate migration report locales and refresh the completion timestamp.
* Show both native and admin-language names in the translation progress overview, and explain unmeasured content before the first scan.

= 0.7.33 =
* Link language settings to automatic translation and explain missing prerequisites.

= 0.7.32 =
* Import only missing TranslatePress translations and keep existing destination entries.

= 0.7.31 =
* Align language settings and preserve the current target when the free edition has no language slot left.

= 0.7.30 =
* Align translation list actions and avoid repeated ancestor checks while localizing page HTML.

= 0.7.29 =
* Localize translation text statuses and provide a direct publish action for individual entries.

= 0.7.28 =
* Check only the active singular or plural message as a format string. Keep the complete translation key used for plural lookup.

= 0.7.27 =
* Reject invalid PHP format strings from native and saved translations before they can interrupt page rendering.

= 0.7.26 =
* Keep the selected language when opening the editor from the toolbar. Show the file name and line of editor crashes to authorized administrators.

= 0.7.25 =
* Keep WordPress page templates on translated slugs and preserve HTML document structure when old imports contain whitespace-only entries.
* Capture plain form labels and fixed HBook captions without collecting visitor data. Skip unnecessary markup serialization and duplicate dictionary work.

= 0.7.24 =
* Publish new automatic translations by default. Keep the review preference for sites that save translations as drafts.

= 0.7.23 =
* Distinguish published translations from drafts in language progress, and link from the visual preview to the public page. Keep the editor product name unchanged.

= 0.7.22 =
* Reuse frontend language configuration when global pre-option listeners leave relevant values unchanged; keep overrides, option writes and paid entitlement live. Remove the early-locale experiment after it showed no measurable live benefit.

= 0.7.21 =
* Add content-free server timing for gettext and language configuration to identify remaining frontend overhead.

= 0.7.20 =
* Select validated public language before native WordPress catalogs load to avoid loading source and target catalogs twice. Preserve explicit recipient locale scopes and technical/admin requests.

= 0.7.19 =
* Avoid repeated URL rewrite scans when a language has no translated post slugs; retain dynamic metadata and immediate slug updates. Add standard server timing for HTML transformation.

= 0.7.18 =
* Localize remaining JavaScript flag-image controls and request-error fallbacks; verify current backend catalogs in every bundled language.

= 0.7.17 =
* Load current bundled backend translations alongside older partial language packs; render background progress in the current admin language.

= 0.7.16 =
* Resolve frontend gettext language configuration once per request while preserving immediate configuration changes and current paid entitlement checks.

= 0.7.15 =
* Preserve positional attribute placeholders in gettext HTML templates until the calling plugin or WordPress performs interpolation.

= 0.7.14 =
* Collect explicitly registered stored text and HTML templates without exposing them in public dictionaries.
* Preserve template variables and source structure; display published exact-source translations in an explicit recipient language.
* Add template collection and content-type filters to Translation texts, and collect templates before background operations.
* Include language-specific focus keywords in the SEO read tool.

= 0.7.13 =
* Register language paths in the free plugin independently of licensed SEO integration.
* Protect complete builder HTML, attributes and encoded image URLs without accepting injected provider markup.
* Retain empty imported HTML fragments as technical records, excluded from translation requests and progress totals.

= 0.7.12 =
* Preserve nested formatting as structured provider HTML and protect empty inline elements atomically.

= 0.7.11 =
* Continue language-specific operations through a durable background queue with saved overall progress, cancellation, selected API and duplicate-start protection.
* Batch Google v2 requests while preserving per-unit formatting, glossary terms and concurrent manual edits.
* Correct source-scan acknowledgement headers and validation of multiline builder tags.

= 0.7.10 =
* Load public-page translation units in bounded batches instead of one database query per text. Keep editor/job reads fresh and preserve manual/draft precedence.

= 0.7.9 =
* Avoid unnecessary paid-entitlement processing when only the two included languages are active. Additional-language permissions remain checked.

= 0.7.8 =
* Avoid repeated language-settings normalization during frontend gettext calls; source pages skip target lookup.
* Preserve Panda Translate product names in automatic translations and use Traduction for the French toolbar action.

= 0.7.7 =
* Detect TranslatePress dictionary pairs with formal source or target locales, including German (Formal).
* Preserve exact target-language mapping, existing translations and publication status.
* Distinguish missing dictionaries, unassigned language pairs and empty dictionaries in migration diagnostics.


= 0.7.6 =
* Static JavaScript translation collection with exact domains, contexts and target-language plural forms.
* WooCommerce system-page routing repairs and published public navigation labels without collecting transaction data.
* Protected manual bilingual content, safer editor and migration handling, and synchronized PandaCosmo components.


= 0.7.5 =
* Shared compact checkbox and radio controls preserve inline labels, keyboard focus and responsive layout.


= 0.7.4 =
* Shared action alignment preserves readable labels and consistent icons in lists and dialogs.

= 0.7.3 =
* Unified PandaCosmo product presentation and shared controls.
* Shared account and family integration improvements.


= 0.7.2 =
* Consistent PandaCosmo product names and cross-references; existing technical identifiers and saved data are retained.


= 0.7.1 =
* Prepare source documentation, internationalization and WordPress.org review compatibility.


= 0.7.0 =
* Separate manual, suggestion and automatic visitor language behavior from the detection source.
* Choose browser language, country of origin, or country with browser fallback; respect manual choices and explicit language URLs.
* Resolve country hints per visitor without shared-page cache leakage or external geolocation requests.

= 0.6.4 =
* Add General settings with license status, a masked four-character license suffix and browser-language suggestion controls.
* Remove the separate License tab; retain old settings links and language settings.

= 0.6.3 =
* Use PandaCosmo website addresses while retaining product names, slugs, keys and textdomains.
* Renew expired editor REST nonces once; report interrupted, cross-domain and invalid responses without replaying ambiguous paid requests.
* Prevent overlapping automatic editor requests and show accurate localized save messages.
* Add an explicit update-source check and last result in System status; refresh WordPress update metadata.

= 0.6.2 =
* Cancel per-language, all-language, current-page and background translation runs while retaining saved results.
* Show and persist concrete errors; stop after three repeated failures and retry failed entries separately.
* Protect Google formatting/glossary markers through HTML transport; protect DeepL glossary markers and locale/formality settings.
* Add a DeepL key test with encrypted key storage and persisted status/time.
* Preserve the early WordPress bootstrap fix and the flag-plus-code default.


= 0.6.1 =
* Default new language selectors to flag and language code.
* Fix a frontend fatal error when another plugin translates text before the WordPress query exists.
* Guard language resolution against recursive gettext filters.

= 0.6.0 =
* Language-specific images with the shared Panda ImagePicker, local alternative text, title and caption, responsive sources, original-image fallback and conflict detection.
* Central translation text list with search, status/language/domain filters, protected formatting variables and bulk publication.
* Static plugin/theme text collection with progress and resume support; published translations for dynamic visitor content without automatic external API requests.
* DeepL API Free and Pro with encrypted credentials and per-run API selection.
* Expanded Multilingual SEO: Yoast/RankMath variables, schema, multilingual sitemaps, taxonomy/archive metadata, translated URL bases and safe automatic drafts.
* Multilingual WooCommerce static labels, checkout-session/order language, customer email locale and reusable email templates; customer/order values excluded from external translation APIs.
* Translation editor role, language-dependent menu visibility and optional browser-language suggestion in Multilingual.
* Existing TranslatePress migration retained. WPML, Polylang and Weglot migration is planned for a later version.


= 0.5.3 =
* Parallel native WordPress Abilities (including miniOrange), Royal MCP and standalone transports with shared permissions and revision-safe writes.
* Preserve Unicode script/style contents, including currency selectors, across HTML processing.
* Support existing language-include, language-exclude and trp_language content blocks independently of migration.
* Bundle the complete canonical family UI in the license-free base; align API fields, simplify translation actions and combine MCP with System status.
* Translate recorded source units without a mandatory loopback rescan; stop failed progress indicators.

= 0.5.2 =
* Use de/en in HTML language attributes on source and target pages while retaining regional language schemas in settings.

= 0.5.1 =
* Keep the selected language in server-rendered logo, navigation and internal content links; preserve explicit language switches and technical URLs.
* Correct the HTML language and text direction; safely display encoded currency symbols and checkmarks.
* Preserve translated slugs, subdirectory installations, path/query formats and WooCommerce endpoint exceptions.


= 0.5.0 =
* Inherit the source language from WordPress website settings. Legacy source changes require an explicit warning acknowledgement; translation data is retained.
* Block migrations with incompatible source-language assignments before importing.
* Fix the target-language action and prepare source units for new targets such as Spanish.
* Visible per-language progress for interactive and background translation runs, refreshed totals and a combined untranslated-content action.
* Free includes two languages, paths, language menus, switchers and builder elements.
* Dashboard, Translation and Glossary menus; remaining settings use tabs. URL format moved to Language switcher.
* Optional licensed Multilingual extension adds languages, SEO and WooCommerce.
* Family mascots, contextual feature notices and canonical public updater.
* Additive page/unit associations for MCP post-ID search; preserve existing unit keys and translations.
* Extensible migration source registry; existing TranslatePress importer retained.


= 0.4.5 =
* Native optional Royal MCP integration with paginated search, complete-unit read, revision-protected manual save, read-after-write verification, permission checks and SEO override reads.
* Published manual dictionary corrections reach the frontend while preserving page-specific manual translations.
* Simultaneous API configurations with API selection in the site runner and page editor, including the selected API in action labels.
* New target languages can collect source pages, prepare missing entries and translate without requiring prior target-language records.
* Preserve the selected API through page scans, background jobs and SEO translation.
* Show only relevant v2/v3 settings; direct API tests with separate persisted results and no confirmation popup.
* ContactPanda glossary list with view, edit and create popups, immediate persistence and shared row action buttons.
* Compact language table with hidden Standard/second-name headers, aligned flags, address style labels and gray 0-percent bars.
* Accurate interactive dropdown preview; reduced floating selector font size.
* Hide languages without published translations from frontend switchers, menus and builder elements; preserve the source language and complete admin preview.
* System status tab label and canonical family button styles loaded after admin integration styles.
* Complete updated English, German, formal German, French and Italian catalogs.

= 0.4.3 =
* General language list shows translated content in dark gray, untranslated content in light gray and the translated percentage.
* Detailed status charts use Klar colors at medium saturation, between the original and muted variants.

= 0.4.2 =
* Softer Klar status colors: muted blue, orange and green; untranslated content remains light gray.

= 0.4.1 =
* Use canonical ContactPanda action styles and icons across settings, migration, glossary, menu integration and visual editor.
* Preserve labelled actions and synchronize disabled states after dynamic changes.
* Larger menu flags with consistent spacing between flags and language names or codes.
* Translation status directly after the native and localized language-name columns in General settings.
* Compact status bars show recorded translated units and percentages; no misleading 0% for unrecorded languages.
* Selected Klar palette: migrated blue, automatic orange, manual green, untranslated light gray.
* No visible Delete column heading; accessible deletion labels remain available.

= 0.4.0 =
* Approved ten-column language table with native and website-language names, source indicator, reorder handles, flag controls and Panda delete buttons.
* Compact searchable language selection; safely persist ordering after additions, removals and reordering.
* Resolve published TranslatePress dictionary translations by source content, including inline segments, entities and text attributes.
* Restore publication status for untouched legacy imports and preserve it for new imports.
* Preserve manual page translations and exclude administrative controls, scripts and language selectors from dictionary replacement.
* Existing navigation menus inherit their theme styling; standalone builder menus have a separate container background.
* Updated translation templates and English, German, formal German, French and Italian catalogs.

= 0.3.7 =
* Explicitly resolve language home URLs to the configured static front page.
* Refresh language routes when the front page assignment changes.
* Three paired rows of color controls for text, background and border colors.
* Independent hover border colors for menu and floating selectors.
* Five display modes and independent native-language name switches.
* Hide the selected language from dropdown options and preserve full flag glyphs.
* Recover legacy language menu items without href and persist safe menu markers.
* Language menu elements for Avada, Elementor, Gutenberg, WPBakery, Divi, Bricks and Beaver Builder.
* Compact paired settings, menu shortcode at the bottom and neutral German interface wording.

= 0.3.6 =
* Switch between language paths (/en/...) and query parameter URLs.
* Generate language routes for the home page, nested pages and archive rules.
* Resolve language menu URLs before theme walkers render them.
* Independent menu and floating switcher design settings in two columns.
* Four padding fields in one row and corrected German Right label.
* Stable translation-unit context across language URL modes.

= 0.3.5 =
* Independent menu display modes including flags only and custom flag images.
* Hide the active language in menu items and menu switchers.
* Separate dropdown buttons from navigable language links.
* Bundle the shared ContactPanda controls and supply WordPress, Avada, Elementor and Divi theme palettes.
* Resolve source-language links against the original page permalink.

= 0.3.4 =
* Correct TranslatePress source-target locale pairs to the configured target language.
* Repair previously imported language assignments and stored migration reports.
* Show migration language pairs with an arrow, for example DE-DE → EN-US.

= 0.3.3 =
* Stacked translation progress by language: migrated, automatic, manual and untranslated.
* Counts, percentages and source character totals for every category.
* Per-language batch actions for untranslated units or all recorded translations.
* Preserve translation origin when publishing without text changes.

= 0.3.2 =
* Language switcher display modes for native names, language codes or flags only.
* Expanded language and flag catalog for regions outside Europe.
* Batch-based TranslatePress migration with a live progress indicator.
* Persistent migration results and completion progress for each language.
* Separate imported, skipped and failed migration counters.

= 0.3.1 =
* Language settings now use the full page width; system diagnostics moved to Advanced.
* Critical system problems are shown only when action is required.
* Expanded checks for Google configuration, Cron, language URLs and SEO integrations.
* The default language is fixed in first position and no longer shows a drag handle.
* Compact Schema and URL code fields improve the language-row layout.

= 0.3.0 =
* ContactPanda family color controls and per-language custom flag images.
* Reliable classic-menu items for current language, switcher and individual languages.
* High-contrast current-page progress and progress totals per target language.
* WordPress, Avada, Elementor and builder toolbars are excluded from translation extraction.
* Translated slugs, hreflang, canonical URLs and SEO metadata integrations for Yoast and RankMath.

= 0.2.6 =
* Complete backend translations for English, German, formal German, French and Italian.
* Compact searchable two-column language dropdown.
* Stored Google key display with masked value and visible final four characters.
* Translation APIs and Automatic translation are now separate settings areas.
* Automatic translation for selected content types and a scheduled full-site run.

= 0.2.5 =
* Searchable two-column language selector with German regional and formal variants.
* Green active switches, four-field padding controls and shared/fallback color controls.
* Google v2 API-key test with encrypted storage, masked last four characters and persisted status.
* One confirmation per editor session and whole-page automatic translation.
* Character and local word statistics; Google billing remains character-based.
* TranslatePress dictionary migration and initial translation template.
* Live editor respects the WordPress admin bar.

= 0.2.0 =
* Searchable row-based website languages and migration from 0.1.0.
* Encrypted Google v2 key entry with persistent connection status.
* Glossary management and protected automatic translation terms.
* Visual switcher settings, WordPress menu integration and builder adapters.

= 0.1.0 =
* First installable local prototype.

== MCP transports and content compatibility (0.5.3) ==

WordPress 6.9 Abilities API: pandatranslate/settings-get, translations-list, translation-get, translation-save, google-translate and (Multilingual) seo-get. The existing Royal and ContactPanda registries and standalone endpoint remain available simultaneously. Additional authenticated transports can consume the native abilities or PandaTranslate_MCP::tools()/call(). Hosts retain responsibility for discovery policy, allowed tools and authentication; no host allow-list is modified. All calls additionally require manage_options; writes require confirmation and the last-read revision. Native abilities are not exposed as unauthenticated REST actions. On older WordPress versions Royal, ContactPanda and the standalone endpoint still work.

Language blocks register only if another provider has not already registered the shortcode. lang accepts base or regional codes and comma-separated values. language-include and trp_language preserve visible localized editorial text from automatic retranslation by default; enable_translation="yes" explicitly opts in. language-exclude remains translatable unless enable_translation="no" is supplied. Hidden blocks are not executed. No stored content is stripped or rewritten.

== Stored templates and integration contract ==

Translation texts > Collect stored templates collects saved ContactPanda registration messages and system-email text blocks. Stored text/HTML template filters show these separately from public text. The full-site and missing-content operations collect these templates before queuing work. Existing manual translations remain unchanged. Published template translations are never included in the public JavaScript catalog and do not by themselves make a target language visible in the frontend language switcher.

Integrations register only static source definitions through pandatranslate_stored_templates (source, id, domain, html). PandaTranslate_Stored_Templates::translate reads an exact-source published translation without sending anything to an API. Variables must be expanded afterwards. A mail integration uses PandaTranslate_Stored_Templates::with_language($recipient_language, $callback) to scope template lookup; the previous scope is restored even when the callback throws. Unknown/inactive target languages use the current source. Never pass a rendered personalized email or order/customer details to source collection.

WooCommerce uses its existing stored order/customer language context. The ContactPanda option/frame adapters cover raw configured templates and translated frontend reads. Asynchronous ContactPanda sends require the explicit recipient-language scope before template expansion; the adapter never guesses a recipient from the administrator locale. HBook stored-mail integration still requires validation against its installed template API and is not claimed as automatic email translation by this package. No real test emails are sent by the fixture checks.
