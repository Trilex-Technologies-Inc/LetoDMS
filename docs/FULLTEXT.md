# Local full-text search

Full-text search finds words inside stored document files. It replaces the old Zend/Lucene endpoint and indexer with a portable, database-backed word index. AI search remains a separate semantic feature. Full-text indexing and querying make no hosted-provider requests and do not require the AI module, credentials, or provider billing.

## Enable and index

1. Upload the files listed below. Preserve `conf/settings.xml` and document storage. No reinstall or recreation of existing document tables is needed.
2. Enable **Full-text search** under **Admin Tools → Settings → Site → Edition** and save. Configure the separate full-text OCR option/languages only if needed.
3. Open **Admin Tools → Full-text search → Indexing dashboard and rebuild**, the top administrator menu's **Full-text indexing** link, or `out/out.Fulltext.php`. Opening this page as an administrator creates four index-owned database tables.
4. Choose **Queue existing documents / rebuild**. Each submission handles up to 200 documents. If **Queue next batch** appears, continue until every batch has been queued. This does not delete an index before replacement text is ready.
5. Schedule the worker and check its activity and job status on the dashboard. After a job completes, select **Full text** in the top search bar and search for a word inside that file.

From a cron editor, use the command below in the **Command** field, with an every-minute schedule. Replace the paths for the installation:

```cron
* * * * * /usr/bin/php /path/to/LetoDMS/utils/fulltext-worker.php --config=/path/to/LetoDMS/conf/settings.xml --limit=5
```

If the updated `modules/ai-assistant/worker.php` is already scheduled, it also handles up to the specified limit of local full-text jobs before processing AI jobs. Keep that schedule rather than adding a second full-text schedule. If the AI module files are removed or that cron job is retired, schedule the standalone core worker instead. Turning off the AI module does not disable full-text indexing.

For this installation, the standalone command is:

```sh
/usr/bin/php /home/dev/public_html/letodms/utils/fulltext-worker.php --config=/home/dev/public_html/letodms/conf/settings.xml --limit=5
```

For a CLI rebuild, run once:

```sh
php utils/fulltext-worker.php --config=/absolute/path/settings.xml --rebuild --queue-only
```

Then let the scheduled worker process the queue. Do not put `--rebuild` in a recurring cron job. The compatibility entry point `utils/indexer.php` accepts the same arguments. `--help` prints usage.

## Supported extraction

| Format | Requirement |
| --- | --- |
| UTF-8 text, Markdown, CSV | PHP and mbstring |
| Text PDF | `pdftotext` |
| Word `.doc` | `antiword` with UTF-8 support |
| Word `.docx` | PHP ZIP and DOM |
| Scanned PDF | Optional OCRmyPDF, Tesseract, and installed language packs |
| PNG, JPEG, TIFF | Optional Tesseract and installed language packs |

The CLI worker must be able to read document storage, create temporary files, and execute the configured converters. Web PHP and CLI PHP may have different extensions and permissions. Unsupported, damaged, encrypted, or empty files produce job errors.

Tool paths default to commands on PATH. Override them with `LETODMS_TEXT_PDFTOTEXT`, `LETODMS_TEXT_ANTIWORD`, `LETODMS_TEXT_OCRMYPDF`, or `LETODMS_TEXT_TESSERACT` in the PHP process environment. Existing `LETODMS_AI_*` tool overrides remain fallbacks. Tool invocation passes an argument array to `proc_open`; document names are not concatenated into shell commands.

Full-text limits are 50 MiB input, 2 MiB extracted UTF-8 text, and 150,000 distinct words per document. DOCX document XML is limited to 8 MiB, images to 40 megapixels, and generated OCR PDFs to 40 MiB. Converter timeouts also apply. Over-limit extraction fails visibly instead of indexing only the beginning of a file. AI analysis retains its separate 60,000-byte text limit and 20 MiB input limit.

## Searching

- All query words must occur in the current indexed document. Use quotes for ordered phrases, for example `"renewal date" contract`.
- Words consist of contiguous Unicode letters and numbers and are case folded. Short words and numbers are indexed. There is no stopword list, prefix matching, stemming, accent folding, or language-specific segmentation. Punctuation separates words.
- Results show the current document name, version, escaped/highlighted excerpt, and a PDF page number when extraction supplies one. Links open the document information page. Other formats do not have reliable page numbers.
- Results are ordered by document ID, 20 accessible matches per page. Pagination does not count inaccessible documents as results. Extremely broad searches are bounded to 2,000 candidate checks per request; refine the words or folder if prompted.
- Existing advanced-search folder, owner, and category filters are honored. The dedicated full-text form preserves those filters between searches and result pages.
- Access is checked against the current document and current version before stored text is loaded. A new version immediately makes the old index ineligible, even while its replacement is pending or has failed. Deleted documents do not produce results; the worker also removes their cached text and jobs.
- Only current document files are indexed. Historical versions and attachments are not included. Metadata/name search and semantic AI search retain their separate behavior.

## Queue and storage behavior

Successful browser uploads, multi-uploads, revisions, WebDAV uploads, and the existing add-document CLI utility queue the latest version while the feature is enabled. Direct database or filesystem imports and third-party integrations should explicitly queue a document or run a rebuild.

The worker uses conditional claims, random tokens, a 15-minute lease, and version/checksum checks. A worker for an old version cannot publish over a newer queued upload. Failed jobs retry after at least one minute, up to three attempts. Use **Reindex** after resolving an extraction error or changing OCR settings. Running jobs are not reset by the dashboard.

The tables are `tblFulltextDocuments`, `tblFulltextTerms`, `tblFulltextJobs`, and `tblFulltextState`. Extracted text is stored in the database and must be protected and backed up with it. SQLite uses its normal transactions; newly created MySQL/MariaDB index tables use InnoDB. No FTS5 extension, native FULLTEXT feature, or Lucene directory is required. Older Lucene indexes are neither read nor automatically deleted.

The hash-based term index provides consistent word matching across supported databases. It is intended for bounded document collections; benchmark query/indexing load on large installations. Database privileges must allow creation of the new tables and indexes, and MySQL's packet/storage limits must accommodate the extracted text.

## Files for this update

Upload the following, preserving their paths:

```text
inc/inc.ClassFulltext.php
inc/inc.ClassTextExtractor.php
inc/inc.FulltextUpload.php
inc/inc.ClassSettings.php
op/op.AddDocument.php
op/op.AddMultiDocument.php
op/op.UpdateDocument.php
op/op.UpdateDocument2.php
op/op.QuickSearch.php
op/op.Search.php
op/op.SearchFulltext.php
op/op.Settings.php
out/out.Fulltext.php
out/out.Indexer.php
out/out.CreateIndex.php
out/out.IndexInfo.php
views/bootstrap/class.Fulltext.php
views/bootstrap/class.SearchFulltext.php
views/bootstrap/class.Bootstrap.php
views/bootstrap/class.AdminTools.php
views/bootstrap/class.Settings.php
styles/bootstrap/application.js
styles/bootstrap/dark-mode.css
utils/fulltext-worker.php
utils/indexer.php
utils/adddoc.php
webdav/src/Context.php
modules/ai-assistant/inc/Extractor.php
modules/ai-assistant/worker.php
languages/English/help.htm
docs/FULLTEXT.md
README.md
TODO
modules/ai-assistant/README.md
conf/settings.xml.template
```

The template is a reference; do not replace the live configuration. The new AI extractor shim needs the shared core extractor even when uploading only module files. The older index administration URLs redirect to the new authenticated dashboard. No third-party dependency install is introduced by this full-text update.

## Validation

```sh
php -d extension=pdo_sqlite utils/tests/fulltext.php
php utils/tests/fulltext-views.php
php -d extension=pdo_sqlite utils/tests/fulltext-cli.php
php -d extension=pdo_sqlite -d extension=fileinfo webdav/tests/integration.php
php modules/ai-assistant/tests/extraction.php
php modules/ai-assistant/tests/ocr.php
```

Tests exercise actual core objects and SQLite, access changes, workflow restrictions, long text, phrases, Unicode, pagination, scoped search, stale-worker claims, rollback, retries, version deletion, disabled indexing, escaped HTML, CSRF fields, and WebDAV indexing with AI disabled. Converter orchestration tests simulate external tools. Validate MySQL/MariaDB operation and real PDF/DOC/OCR tools on the target server before calling those environments verified.
