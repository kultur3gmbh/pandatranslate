=== PandaTranslate ===
Contributors: panda
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.5.0
License: Proprietary prototype

PandaTranslate Free: visual WordPress translation with two languages, language paths, switchers, menus and page-builder elements.

== Prototype usage ==

Configure languages on PandaTranslate > Dashboard. Automatic translation has its own Translation menu, and Glossary has its own menu. The remaining settings are tabs under Settings. URL format is in Language switcher. Then use “Translate page” in the upper admin bar while viewing the desired page.

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

The MCP tab shows the authenticated Streamable HTTP endpoint. Use a dedicated WordPress administrator application password. Write and paid tools require `confirm=true`. When ContactPanda is active, PandaTranslate tools are also added to its central MCP endpoint. Royal MCP 1.5.11 or later automatically lists the same tools on its existing connection via royal_mcp_tools and royal_mcp_execute_tool_external. Royal authentication, read-only mode, drafts-only mode and connection restrictions remain in force; PandaTranslate additionally requires manage_options for every call. No additional connector or Royal dependency is required.

Editorial MCP workflow: discover pandatranslate_settings_get, search pandatranslate_translations_list with language/query/limit/offset, then read pandatranslate_translation_get. Keep the complete source unit and all [[PT:...]] inline placeholders. Send translated_text, language, unit_key, reviewed/published status, expected_revision from that read, and confirm=true to pandatranslate_translation_save. Stale revisions and concurrent changes fail instead of overwriting; successful writes return verified=true and a freshly read translation. API keys and credential files are never returned. Collected units only are searchable; use the visual editor or automatic collection to capture new source pages. Pagination uses next_offset; one final empty page can occur at an exact page boundary.

Imported units have source_dictionary scope: publishing their correction can update identical source text across multiple pages. Existing page-specific manual translations take precedence. Conflicting dictionary translations are not chosen silently. Ordinary page units have unit scope. Drafts are not public. Menus are translated through collected text units, without changing their structure. pandatranslate_seo_get reads language-specific slug, SEO and social overrides and source Rank Math/Yoast values; editing those overrides remains available in the SEO metabox, not through MCP. Royal does not supply undo snapshots for external tools; retain the preceding read if a manual reversal is needed.

Existing 0.4.x data and options are retained. Free enables the source language and one target language. Additional stored languages remain available after enabling licensed PandaTranslate Multilingual. SEO and WooCommerce integration are provided by that extension. No other Panda plugin is required. Existing imports are repaired on the next administrator backend visit when the original TranslatePress tables are still present. Only unchanged imported entries previously published in TranslatePress are restored to published status.

== Distribution and migration adapters ==

Free updates use the public kultur3gmbh/pandatranslate GitHub releases and a checksum manifest. WordPress provides manual update checks and optional per-plugin automatic updates. Free requires no paid license. PandaTranslate Multilingual is included in the All-in bundle and requires only this base plugin.

The pandatranslate_migration_sources filter allows verified future import adapters to register label, detected and render callbacks. Adapters must enforce administrative capabilities, nonces, bounded batches and existing manual-translation protection. TranslatePress is the only supported built-in source in 0.5.0; no other importer is advertised as working. The Migration tab appears only for detected registered sources.

== Changelog ==

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
