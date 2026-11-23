# LetoDMS

LetoDMS is a document management application with folders, document versioning,
access controls, review and approval workflows, search, and optional hosted AI.
The modern Bootstrap interface includes a responsive sidebar, light/dark color
modes, and a searchable Help guide.

## What is available

- Upload documents and revisions, download earlier versions, manage metadata,
  assign access rights, and track reviews and approvals.
- Search names and metadata, use local database-backed full-text search with
  highlighted excerpts and phrase matching, and use
  optional semantic search from the top search bar.
- Enable AI summaries, document Q&A, textual version comparisons, reviewed
  metadata suggestions, change summaries, duplicate candidates, and local OCR.
- Configure hosted providers centrally, monitor processing jobs, retry failures,
  and set local AI request limits. Optional cost estimates are not invoices.
- Connect desktop clients through the optional SabreDAV WebDAV endpoint.
- Switch light/dark mode with the moon/sun button. The modern interface follows
  the device preference until a browser-specific choice is saved.

## Installation

Use a supported PHP runtime and a web server configured to run PHP. The modern
WebDAV adapter requires PHP 8.1 or later. MySQL/MariaDB and SQLite are supported
by the bundled database layer. Enable the PHP PDO driver for your database and
the extensions reported by the installer. Feature-specific tools are listed
below and in the component guides.

1. Upload the application to its web directory. Set up the database and a
   document storage directory, preferably outside the public web root.
2. Open `install/install.php` and review the server checks. Configure paths,
   database access, and the administrator settings. Use **Create tables** for
   a new, empty database; do not recreate tables on an existing installation.
3. Save the generated configuration in `conf/settings.xml` and grant PHP only
   the filesystem access needed for configuration, storage, staging, and cache.
4. Follow the installer's instructions for removing `ENABLE_INSTALL_TOOL`,
   sign in, and change the initial administrator password.
5. Create users, groups, roles, and folder permissions before granting access.

Use HTTPS, keep API keys and configuration out of source control, and back up
both the database and document storage. Keep the installation encryption key
with the protected backup: encrypted AI provider credentials depend on it.

## Upgrading an existing installation

Back up first, then upload the changed application files without replacing your
`conf/settings.xml` or document storage. A fresh installation is not required
for the UI, Help, or AI module updates. Theme changes include files under both
`views/bootstrap/` and `styles/bootstrap/`; upload both directories' changed
files so the PHP view and browser assets agree.

For AI Assistant upgrades, upload the complete updated module and open it as an
administrator to initialize additional module tables. Do not uninstall it to
upgrade: uninstalling removes module-owned configuration and results.

The WebDAV replacement also changes core and settings files. Install its Composer
dependencies or upload the prepared `webdav/vendor/` directory; that directory
is excluded from Git. WebDAV is disabled by default. Older MyISAM document tables
need migration to InnoDB before DAV writes can be used. Follow the
[WebDAV upgrade and setup guide](webdav/README.md) rather than recreating tables.

## Local full-text search

Enable **Full-text search** under **Admin Tools → Settings → Site → Edition**.
Open the **Full-text indexing** dashboard, queue existing documents (continue
through its batches), and schedule `utils/fulltext-worker.php` every minute.
The updated AI worker also handles these jobs when it is already scheduled;
choose one schedule. Uploads and revisions are queued automatically while
full-text search is enabled. Select **Full text** in the top search bar to search
the contents of locally indexed documents.

This replaces the legacy Zend/Lucene integration. It uses database index tables
on SQLite and MySQL/MariaDB without requiring FTS5, a native FULLTEXT index, a
separate search service, or AI credentials. Whole-word AND matching, quoted
phrases, Unicode case folding, document/version access checks, highlighted
excerpts, retries, and current-version indexing are included.

Full-text extraction and optional OCR share local tools with AI, but have their
own settings and larger limits: 50 MiB input and 2 MiB extracted text. The new
shared extractor is `inc/inc.ClassTextExtractor.php`; upload it together with
the updated AI extractor shim even when upgrading the AI module alone.
See [full-text setup, upload list, and limits](docs/FULLTEXT.md).

## Optional AI setup

1. Enable **AI Assistant** in **Admin Tools → Modules** and assign
   `module.ai-assistant.access` to the intended roles.
2. Open **AI Assistant → Provider settings → Add provider**. Choose a company,
   enter its API key, and select or enter a compatible chat model. Supported
   presets populate endpoint defaults; OpenAI model discovery is available.
3. Configure an embeddings provider/model for semantic search. Model discovery
   does not verify generation compatibility, billing, or account access.
4. Under **Processing settings**, enable the automatic features you want and
   schedule the worker. Automatic queuing alone does not process documents.

Run the worker every minute, replacing the paths for your server:

```cron
* * * * * /usr/bin/php /path/to/LetoDMS/modules/ai-assistant/worker.php --config=/path/to/LetoDMS/conf/settings.xml --limit=5
```

Document extraction uses `pdftotext` for PDFs and `antiword` for older Word
documents. DOCX requires PHP ZIP and DOM; text processing requires mbstring.
Optional scanned-PDF OCR uses OCRmyPDF, and supported image OCR uses Tesseract
with installed language packs. Hosted API requests require PHP cURL and trusted
CA certificates. Stored provider keys require OpenSSL and a configured LetoDMS
encryption key. Configure both web and worker PHP environments.

Hosted features can send required document text and queries to the configured
company and incur charges. Review your provider's billing and privacy terms.
AI output remains subject to human verification and normal document permissions.

See the [AI Assistant guide](modules/ai-assistant/README.md) for supported formats,
provider setup, OCR limits, processing, request controls, and troubleshooting.

## Help and appearance

Open **Help** in the sidebar for instructions covering uploads, versions,
permissions, reviews, AI search, processing, provider setup, and WebDAV. The
updated English guide includes instant, browser-side topic search, a topic
navigation list, a clear-search action, and troubleshooting instructions.
Other existing translations remain available with a link to the updated guide.

The modern Bootstrap interface's moon/sun button switches light and dark mode
on both signed-in pages and the sign-in screen. The preference is stored in the
browser, follows across tabs, and does not need a database migration. Legacy
visual themes do not gain the modern theme switch.

## Checking for application updates

Administrators can open **Admin Tools → Version information** and use **Check
for updates** to compare the installed version with published releases from
`Trilex-Technologies-Inc/LetoDMS` on GitHub. The current development version is
**5.2b**. Beta installations include prereleases by default; the checkbox can
limit the comparison to stable releases. Legacy tags such as `5.1b-php8.3` are
recognized as application beta versions, with the PHP suffix excluded from the
application-version comparison.

Checks use a fixed public GitHub API endpoint, verified HTTPS, an eight-second
timeout, and a bounded response. No documents, provider keys, database changes,
or automatic installation are involved. Successful checks are cached for one
hour in the configured cache directory, with a private temporary-directory
fallback when no cache directory is configured. PHP cURL, trusted CA certificates,
and a writable cache location are required. Opening the information page alone
does not contact GitHub.

To make a version discoverable, publish a **GitHub Release** with a comparable
version tag, for example `v5.2b` or `v5.2.0`. A branch commit or a Git tag by itself
is not a published release. Older published releases do not cause downgrade
recommendations; a development build can legitimately be newer than the newest
published release. The panel links to release notes/downloads for manual review.

## Developer validation

Run checks from the application root using a PHP runtime with the required
extensions. SQLite tests use temporary data and do not need live AI credentials.

```sh
php -d extension=pdo_sqlite modules/ai-assistant/tests/integration.php
php -d extension=pdo_sqlite modules/ai-assistant/tests/advanced.php
php -d extension=pdo_sqlite -d extension=fileinfo webdav/tests/integration.php
php -d extension=pdo_sqlite install/tests/database-check.php
php -d extension=pdo_sqlite utils/tests/fulltext.php
php utils/tests/fulltext-views.php
php utils/tests/update-checker.php
php utils/tests/update-views.php
```

The module README lists its additional checks. Test new UI assets in desktop
and mobile browsers. MySQL/MariaDB migrations, production web-server routing,
live providers, and native Office/WebDAV clients require target-environment
validation; passing local tests does not establish those integrations.

## More documentation

- [Module installation and development](modules/README.md)
- [AI Assistant](modules/ai-assistant/README.md)
- [WebDAV setup and maintenance](webdav/README.md)
- [Full-text indexing and search](docs/FULLTEXT.md)
- [Workflow reference](README.Workflow)
- [Notification reference](README.Notification)
- [TODO and validation backlog](TODO)

LetoDMS is distributed under the GNU GPL; see [LICENSE](LICENSE).
