# AI Assistant 0.4.0

The newer core full-text search is independent of this module. Deploy the shared
`inc/inc.ClassTextExtractor.php` together with the updated module extractor shim.
The updated AI worker also processes local full-text jobs when that core feature
is enabled. See [full-text setup](../../docs/FULLTEXT.md) for its settings,
indexing dashboard, separate limits, and standalone worker. Do not configure two
cron schedules solely to run the same full-text queue.

## Upgrade from 0.3

Upload the updated module directory; **do not uninstall/reinstall**. Open AI
Assistant once as an administrator to create additional module-owned tables.
Existing documents, provider credentials, summaries, indexes, and jobs are preserved.
Keep the existing cron command. OCR, automatic metadata suggestions, and automatic
change summaries start disabled; enable the options you want in Processing settings.
Existing jobs can be retried from Jobs & usage to populate new information.

New features:

- Local OCR for scanned PDFs and PNG/JPEG/TIFF images.
- Jobs & usage dashboard with filtering, retries, cancellation, and worker activity.
- Automatic change summaries for queued versions with a readable predecessor.
- Metadata suggestions with explicit Apply/Dismiss review and an audit trail.
- Exact-file, normalized-text, and embedding-based duplicate candidates.
- Monthly/per-user request limits, token totals, and optional cost estimates.

Install and enable through Admin Tools → Modules. Grant the appropriate roles
`module.ai-assistant.access`. Users choose among administrator-configured hosted
providers. Access to both the document and the selected version is checked before
reading text or displaying saved suggestions.

## Administrator settings

Open **AI Assistant → Provider settings** as an administrator. Add a provider ID,
display name, API format, full HTTPS endpoint, model ID, and API key. Multiple
providers can be configured; users choose among enabled providers for analysis.
Saving settings does not contact the provider or verify the account/model.

Choose OpenAI or Anthropic in **AI company** to populate the API endpoint and
format (new entries also get a provider ID and display name). For OpenAI, enter
the key and click **Load OpenAI models**, then select a candidate chat model.
Existing OpenAI entries can load models using the stored key with a blank key
field. Model discovery is an explicit server-side GET to OpenAI's fixed
`/v1/models` endpoint; it never sends document content or returns API keys.
Restricted keys need permission to list models. Listing models does not verify
generation billing or compatibility with Chat Completions. Candidate chat models
are filtered by name because the API does not return endpoint capability flags;
manual model entry remains available for custom and future models.

In processing settings, select a saved OpenAI embeddings provider and click
**Load OpenAI embedding models**. Choose the returned model and save. An empty
embeddings endpoint is filled automatically for OpenAI. Other companies retain
manual endpoints/model IDs; discovery does not send their saved keys to OpenAI.

API keys are encrypted with AES-256-GCM using the installation's existing
`encryptionKey` setting. PHP OpenSSL and a nonempty server encryption key are
required. Keep that key private and backed up: changing it requires re-entering
saved provider keys. Keys are never populated into browser fields. Leave the
password field blank to retain a stored key. Changing an endpoint or API format
requires re-entering it. Use HTTPS on the LetoDMS site when entering credentials.

Disable providers to hide them without deleting their configuration. Removing a
provider deletes its stored key and preserves document suggestions. The settings
table is created automatically when opening the module, including upgrades from
0.1.0; no uninstall/reinstall is needed.

## Optional environment configuration

Existing environment configuration remains supported. Database entries override
environment entries with the same ID, including disabled entries. Environment-only
providers are managed on the server and are not listed in the editor. Removing a
database override reveals any matching environment entry again; disable it instead
to keep that provider unavailable.

Set `LETODMS_AI_PROVIDERS` in the PHP web process environment to a JSON object.
Each entry needs a stable ID, label, protocol, full API endpoint, model ID, and
the name of the environment variable holding its API key. For example:

```json
{
  "openai": {
    "label": "OpenAI",
    "protocol": "openai",
    "endpoint": "https://api.openai.com/v1/chat/completions",
    "model": "YOUR_CHAT_MODEL_ID",
    "key_env": "LETODMS_OPENAI_KEY"
  },
  "anthropic": {
    "label": "Anthropic",
    "protocol": "anthropic",
    "endpoint": "https://api.anthropic.com/v1/messages",
    "model": "YOUR_CLAUDE_MODEL_ID",
    "key_env": "LETODMS_ANTHROPIC_KEY"
  }
}
```

Set the named key variables separately. Never put keys in source control, URLs,
or browser forms. Ensure the environment is available to PHP-FPM/Apache, not
just an interactive shell. PHP cURL and trusted CA certificates are required.
An administrator can configure other hosted Chat Completions-compatible APIs
with protocol `openai`; compatibility depends on that company's supported models
and request format. Models must support system/user messages and text output.
Native Anthropic uses Messages with API version `2023-06-01`.

Endpoints are trusted administrator configuration, never user input. Requests use
HTTPS certificate verification, no redirects, a 45-second timeout, and a bounded
response. Do not expose PHP environment diagnostics publicly.

## PDF and Word extraction

Supported: PDF, Word DOC/DOCX, UTF-8 text, Markdown, CSV, and PNG/JPEG/TIFF with
OCR enabled. Files must be
at most 20 MB and extracted text at most 60,000 bytes; oversized content is rejected
without silently truncating it.
PDF extraction preserves page numbers; Word main-body paragraphs and tables are
read as text without reliable page numbering. Headers, footers, images, tracked
changes, and formatting are not part of the DOCX extraction contract.

On a Debian server install the converters:

```sh
sudo apt install poppler-utils antiword
```

[poppler-utils](https://packages.debian.org/stable/poppler-utils) supplies
`pdftotext`; [antiword](https://packages.debian.org/stable/antiword) reads legacy
binary Word DOC files. DOCX is read directly using PHP ZIP and DOM. PHP mbstring,
cURL, and OpenSSL are also required. Process execution must be available to PHP
for PDF/DOC extraction. The extractor runs without a shell, with a 30-second
timeout and bounded output. If executables are outside the service PATH, set
`LETODMS_AI_PDFTOTEXT` and `LETODMS_AI_ANTIWORD` to their full executable paths
in both the web and worker environments (no command arguments).

For local OCR on Debian:

~~~sh
sudo apt install ocrmypdf tesseract-ocr tesseract-ocr-eng
~~~

Enable OCR and choose languages in Processing settings (`eng` or `eng+fra`).
Install each requested Tesseract language pack. PDF OCR uses
[OCRmyPDF](https://ocrmypdf.readthedocs.io/en/latest/cookbook.html) with
`--skip-text` on a private temporary copy, then extracts text/page numbers with
pdftotext. Original documents are never replaced. Image OCR uses
[Tesseract](https://tesseract-ocr.github.io/tessdoc/Command-Line-Usage.html).
Limits: 180 seconds per PDF OCR process, 90 seconds for image OCR, 40 megapixels
per input image, 40 MB temporary PDF output, and the same 60,000-byte text limit.

Set `LETODMS_AI_OCRMYPDF` / `LETODMS_AI_TESSERACT` to executable paths if needed.
Use queued processing for large scans. Once indexed, subsequent Q&A/source reads
reuse published text after checking the original SHA-256 and OCR configuration.
Change OCR settings and reindex to refresh older results. Web requests without a
published index may need a larger PHP/proxy timeout.

## Automatic upload analysis and background worker

In Provider settings choose an automatic analysis provider, optionally configure
embeddings, then enable automatic analysis. This explicitly authorizes future
uploads to be sent to the selected services; automatic analysis starts disabled.
It covers standard single/multiple uploads and both version-upload handlers.
External imports or API integrations that bypass those handlers must explicitly
call `letoDMSQueueAIUpload` after a successful commit.

Only uploads by users with `module.ai-assistant.access` are queued. Workers recheck
the uploader's current account, module permission, document/version access, and
provider availability. Queueing never contacts an AI service and does not block
an upload on extraction or provider failures. Queue insertion failures are logged
as a generic server error without failing the upload.

Schedule a CLI worker as an account that can read the content/config directories:

```cron
* * * * * /usr/bin/php /path/to/LetoDMS/modules/ai-assistant/worker.php --config=/path/to/settings.xml --limit=5
```

Use absolute paths for config and content directories. The worker processes up to
five jobs per invocation by default, uses exclusive per-job leases, and recovers
interrupted work after thirty minutes. Failed work retries after five minutes,
up to three attempts. The version's AI page shows job status/errors. Use **Queue
analysis and indexing** to retry a failed/completed job; **Analyze now** generates
a fresh summary synchronously. Background retries reuse a saved analysis for the
same version/provider/model and retry indexing without adding duplicate summaries.
Embedding batches are checkpointed while incomplete; an index is published only
when every batch succeeds. Quota-paused jobs resume missing batches and reuse
completed indexes. Local request limits defer work until the UTC period resets
without consuming failure retries. After increasing a limit, use Retry to resume
deferred work immediately.
Disabling the module stops processing; disabling automatic analysis stops new
automatic jobs while existing queued/manual work remains eligible.

## Semantic search

When the module is enabled, embeddings are configured with an enabled provider,
and the current user has AI module access, the top search bar offers **Documents**
and **AI search**. Documents uses the existing metadata search (including its
folder context); AI search searches all accessible latest indexed versions and
opens a focused results page. A standard-search link remains available when AI
search has no matches or a provider fails. Queries are submitted explicitly using
POST and the existing session form token; no hosted requests run while typing.
The hosted usage/data-sharing notice appears in administrator provider settings,
not as a repeated checkbox on analysis, search, Q&A, or comparison forms.
Automatic analysis still uses the upload queue and scheduled worker; searching
does not trigger document ingestion.

Deploying top-bar search requires the updated `inc/inc.ClassUI.php`,
`views/bootstrap/class.Bootstrap.php`, and new `op/op.QuickSearch.php` in addition
to the module directory. Existing non-AI search remains available when AI is
disabled, not configured, or the user lacks module access.

Configure an enabled OpenAI-compatible embeddings provider, full embeddings
endpoint (for example `https://api.openai.com/v1/embeddings`), and an embeddings
model ID in Provider settings. That provider's saved API key is used. The analysis
provider may be different, including Anthropic. Enabling embeddings alone does not
authorize automatic uploading; enable automatic analysis separately if desired.

Use **Index for semantic search** for existing versions or **Queue analysis and
indexing** for background work. Automatic jobs index after analysis when embeddings
are configured. Indexing splits text into UTF-8-safe sections, embeds it, and
replaces a version's index atomically only after all embedding calls succeed.

Semantic search matches your query against the latest indexed versions you may
currently read. If the newest version is not indexed, that document is excluded
until it is indexed. Older versions remain usable through explicit version Q&A.
Changing the embeddings provider, endpoint, or model requires reindexing; stale
vectors are excluded automatically. Search uses cosine similarity in PHP rather
than requiring an external vector database. It scans accessible indexed versions
and is intended for modest libraries; large deployments need a vector index.

## Document Q&A and version comparison

Document tools now live on the document page in **Document AI**, with **Ask AI**,
**Summaries**, **Compare versions**, **Metadata**, and **Duplicates** tabs. The document ID is filled internally;
version selectors show only readable versions. Answers and comparison results
return to the same document page and are loaded only for their requesting user
after current document/version permissions are checked. Saved summaries and job
status are available in the Summaries tab. Semantic search lives in the top bar.

The AI Assistant module landing page is now administrator-only configuration and
processing status. Its sidebar link is hidden from non-admin users; users with
module access continue to use the top bar and document tools. Old document/version
module links redirect to the corresponding document. Old tools links no longer
display duplicate search/Q&A/comparison forms.

Deploy these changes with `inc/inc.ClassUI.php`,
`views/bootstrap/class.ViewDocument.php`, and the updated module directory. No
reinstall is needed and existing summaries, settings, and interaction data remain.

For document Q&A, choose a readable version and enter a question. It uses the
complete extracted version text and requires no indexing. The server API also
supports retrieval-based Q&A from up to six semantic passages; the document UI
uses explicit document versions.

## Metadata review and automatic changes

The Metadata tab suggests titles, keywords, categories, entities, expiry dates,
and missing information. Enable automatic metadata suggestions to add this step
to queued analysis. Suggestions are tied to version, provider, and model;
background retries reuse an existing suggestion.

Apply supports selected title and/or keywords only. Categories, dates, entities,
and missing-information notes remain advisory. Review requires module access,
write permission, an unlocked document, and its latest version. Applying requires
the original metadata snapshot to still match. Concurrent edits and newer versions
prevent application; empty suggestions cannot erase existing metadata. Apply and
Dismiss retain the reviewer, timestamp, and before/after audit. Reviewed
suggestions stay available as collapsed history.

Enable automatic change summaries to compare queued versions with their immediate
predecessor. Unreadable predecessors are skipped, never silently substituted by
another older version. Cached cited changes appear in Compare versions and are
hidden if either version's access is revoked. These steps add hosted requests and
follow local limits.

## Duplicate candidates

Use Duplicates → Find duplicates. Identical files are checked by SHA-256 against
accessible latest versions, including older files not yet indexed. Normalized-text
matching requires fingerprints from analysis/indexing. Approximate matching uses
mean chunk embeddings with the current configuration and a cosine threshold of
0.95. Only readable versions are inspected or shown. Similarity is not proof of
duplication. No file is automatically deleted, merged, moved, or blocked.

## Jobs, usage, and local controls

Jobs & usage shows counts, the latest 30 jobs (optionally filtered), worker
activity, progress/errors, and Retry/Cancel. Administrators retry non-running jobs
under their own current permissions, and can cancel pending/failed jobs, including
orphan jobs for deleted documents. Active leased jobs cannot be modified.

Limits are local controls, not provider billing limits: monthly across this
installation and daily per user, both in UTC. 0 means unlimited. Every generation
or embedding HTTP attempt reserves one request before contacting the provider;
failures also count. Transactional quota claims prevent concurrent requests from
exceeding configured caps. Provider model-list GETs are excluded. One document can
require several batches plus summary/metadata/change requests.

Usage starts recording after upgrade; older calls cannot be reconstructed. The
report groups requests by provider, model, operation, and state. Token totals use
provider usage when returned, otherwise a rough byte/4 estimate. Optional default
prices are entered in USD per million tokens, shared across selected models.
These are estimates, not invoices or a monetary hard cap. Unconfigured/failed
costs are marked unpriced. Cached-token rates, taxes, unknown charges, and calls
outside this module are not accounted for. Configure actual provider billing
restrictions separately.

For comparison, select an earlier version, later version, and provider.
Both versions are extracted and sent with a request to explain substantive text
changes. This is an AI-assisted text comparison, not a visual or tracked-changes
diff. Both versions must be readable by the requesting user.

Answers require structured citation references; invalid or missing references are
rejected, and responses with no cited sources become a fixed insufficient-evidence
message. Citation links open an access-checked extracted source page (or full Word
text); a separate link opens the document. Models may still misinterpret evidence,
so review the sources. Text and model output are escaped before rendering.

Q&A/comparison are synchronous and may require a longer web/PHP timeout for large
documents or slower providers. Comparison may send up to 120,000 bytes of source
text, so choose a model with a suitable context window.

## Data and permissions

Results are suggestions, never automatic metadata edits or review/approval
decisions. Suggestions store version, user, provider, model, and timestamp.
Indexes additionally store extracted passages, PDF page numbers, and embeddings
in the database. Q&A/comparison audit records store the requesting user, question,
answer, provider, and referenced document/version/page identifiers. These copies
must be included in the installation's backup and retention procedures. Provider
retention and billing follow the configured provider account.

Access is checked on both document and version before extraction, before sources
reach an answer model, and before results are returned. Deleted/inaccessible
documents are excluded even if cached index rows remain. Disabling preserves
module data; uninstalling deletes suggestions, provider settings, indexes, jobs,
options, interaction records, metadata review/change records, fingerprints,
embedding checkpoints, usage/quota counters, and the module access permission.

## Developer checks and manual acceptance

```sh
php modules/ai-assistant/tests/settings.php
php modules/ai-assistant/tests/extraction.php
php modules/ai-assistant/tests/integration.php
php modules/ai-assistant/tests/views.php
php modules/ai-assistant/tests/ocr.php
php modules/ai-assistant/tests/advanced.php
php modules/ai-assistant/tests/worker-bootstrap.php
php modules/ai-assistant/tests/quota-race.php
```

The integration test requires PDO SQLite, uses an in-memory database and fake
provider responses, and never contacts a hosted service. Extraction tests use real
DOCX fixtures and bounded child processes; PDF/DOC converter output is simulated
when the actual converters are unavailable.

Before relying on the integration, verify the following against your server:

1. Configure valid analysis and embeddings accounts; install converter tools.
2. Analyze text-based PDF, DOC, and DOCX samples; check extracted facts/page links.
3. Enable automatic analysis, upload a document and a new version, run the worker,
   and verify completed status plus saved summaries and semantic search results.
4. Test Q&A with supported and unsupported questions; open the linked evidence.
5. Compare two versions with known additions, removals, and changed dates.
6. Repeat as a user denied document/version access; no snippets or answers from
   those versions should be accessible. Revoke uploader access before a queued
   job runs and verify that the worker refuses to process it.
7. Stop the worker, break provider configuration, and use a scanned/encrypted or
   oversized file to verify clear pending/failure states without losing uploads.
8. Install OCR tools/languages, enable OCR, upload a scanned PDF/image, and verify
   summary/index/Q&A output while confirming the original is unchanged.
9. Enable metadata/change automation and upload a new version. Review suggestions,
   Apply/Dismiss, locking, stale metadata, and the cached cited comparison.
10. Check duplicates as an administrator and a restricted user.
11. Set a small request cap, verify calls stop before contacting the provider and
    queued work pauses; increase it and Retry to verify checkpointed resumption.

API references: [Chat Completions](https://developers.openai.com/api/reference/resources/chat)
and [Anthropic Messages](https://platform.claude.com/docs/en/api/messages).
