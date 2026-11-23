# LetoDMS WebDAV

This endpoint replaces the obsolete PEAR HTTP_WebDAV_Server implementation with SabreDAV 4.7. The PEAR WebDAV package is no longer required. WebDAV is disabled by default and is independent of the optional AI Assistant module.

## Install or upgrade

Upload the updated application files, including `webdav/`, the settings controller/view, `inc/inc.ClassSettings.php`, the English language file, and both copies of `inc.ClassDocument.php` and `inc.ClassFolder.php` in `inc/` and `LetoDMS_Core/Core/`. These core fixes are required for approver assignment, initial workflow assignment, and recursive folder deletion. Existing installations do not need reinstalling or their tables recreated.

PHP 8.1 or later is required. Install the PHP Fileinfo extension and the extensions required by Composer/SabreDAV, including DOM, XMLWriter, and mbstring. The web server and command-line PHP may have different extensions enabled.

From the application's `webdav` directory, run:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
```

Alternatively, upload the complete prepared `webdav/vendor/` directory from this workspace. It is intentionally excluded from Git, so pulling the repository alone will not install the dependencies. The dependency versions are recorded in `composer.lock`.

In **Admin Tools → Settings → System → Authentication**, enable WebDAV, select read-only mode if desired, set the upload limit, and save. The default upload limit is 50 MiB. The application creates its two WebDAV tables on first connection; it does not replace existing document tables.

Administrators can connect immediately. Other users need the `webdav.access` permission assigned through a role. Uploading, updating, and deleting also require `document.create`, `document.update`, and `document.delete`, respectively, as well as the appropriate folder/document ACLs. Saving settings registers the new WebDAV permission for existing installations.

## Storage readiness

SQLite supports the transactions used by the adapter. On MySQL/MariaDB, the document, workflow, metadata, ACL, and DAV tables modified by the adapter must use InnoDB. Older installations may use MyISAM. Read access remains available, but writes are rejected on nontransactional tables rather than risking partially committed document updates.

Check the database from the application root:

```sh
php webdav/setup.php --config=/path/to/letodms/conf/settings.xml --check
```

The check prints the tables that need conversion. To prepare an existing MyISAM installation, back up the database and document storage and stop document writes during maintenance, then explicitly run:

```sh
php webdav/setup.php --config=/path/to/letodms/conf/settings.xml --migrate
```

This converts only the named document-related tables needed by DAV and creates the DAV tables/permission. Each ALTER TABLE commits independently; a failure can leave a partially completed conversion. Rerunning continues with the remaining tables. The check exits with code 2 if nontransactional tables remain, 1 for setup errors, and 0 when the storage-engine check passes. `--initialize` creates only the DAV tables and permission. `--help` lists the options.

## Connect

Use HTTPS and native LetoDMS credentials. For this installation, the explicit endpoint is:

```text
https://dev.trilex.net/letodms/webdav/index.php/
```

For other installations, substitute the host and application path. Keep the trailing slash. The shorter `/letodms/webdav/` endpoint is also supported when Apache rewrite rules are active. The explicit `index.php/` endpoint requires PATH_INFO support.

Apache must allow the supplied `.htaccess` rules and forward the Authorization header to PHP. Disable Apache's separate DAV handler for this location. If TLS terminates at a trusted reverse proxy, configure the web server to set PHP's HTTPS state correctly; the application deliberately does not trust arbitrary forwarded-protocol headers.

For Nginx or servers that ignore `.htaccess`, deny HTTP access to `webdav/vendor/`, `webdav/src/`, `webdav/tests/`, Composer metadata, and this README, and route endpoint paths to `webdav/index.php` while preserving the original request URI and Authorization header. Apply your normal FastCGI configuration, including PATH_INFO and HTTPS parameters. Do not allow arbitrary files below vendor/ to execute as PHP entry points.

A protocol check, which prompts for the password:

```sh
curl --user admin --request PROPFIND --header 'Depth: 1' 'https://dev.trilex.net/letodms/webdav/index.php/'
```

A successful listing returns HTTP 207. No browser session cookie is needed. LDAP/AD authentication, OAuth, and application passwords are not implemented in this adapter.

## Behavior

- Listings and downloads enforce document and current-version workflow access. Inaccessible documents are omitted. PROPFIND supports Depth 0 and 1; recursive listings are rejected.
- PUT creates a document or a new version of an existing document. Updates preserve the existing document ID/history and version attributes. Mandatory and existing review/approval assignments and workflows are retained and checked before commit. Missing required metadata must be completed through the web interface.
- MOVE to an unused path preserves the document ID and history. COPY copies the current file into a new document, retaining source document/version attributes and review policy. It does not copy the entire version history. Folder copying refuses a partial result caused by inaccessible descendants.
- MOVE or COPY over an existing document saves the incoming file as a new target version, preserving the target ID and history. An overwriting MOVE then removes the source document and its history, requiring full source access and delete permission. Folder overwrites are rejected.
- DELETE requires full access, and deleting/moving a folder checks every descendant, including items hidden from a normal listing. The DMS root cannot be removed, renamed, or moved.
- Duplicate visible titles receive deterministic ID suffixes such as `Report [D12].pdf`. This does not rename the stored documents.
- Exclusive file locks persist in the database for 30 minutes and can be refreshed. Shared locks and collection locks are not supported. Lock refresh/unlock is restricted to the user who created the lock. Expired locks stop blocking writes automatically.
- Existing browser document locks block DAV writes, including writes by the browser lock's owner. A DAV lock does not prevent browser editing; if the document changes in the browser while DAV holds the lock, the next DAV save is rejected with HTTP 412. Reopen the current document before saving again. ETags also support conditional saves.
- Uploads enforce the configured size limit and document owner's quota. Large XML requests are bounded independently. Read-only mode rejects mutations.
- Successful nonempty uploads and versions enter the normal AI processing queue when the module is enabled, automatic analysis is configured, and the uploading user has AI access. The existing scheduled AI worker processes them. WebDAV does not call the AI provider during an upload. Empty LOCK placeholder files are not queued for analysis.

## Validation

From the application root:

```sh
php -d extension=pdo_sqlite -d extension=fileinfo webdav/tests/integration.php
```

The integration suite uses the actual SabreDAV server, actual LetoDMS core, SQLite, and temporary files. It exercises authentication, roles/ACLs, uploads and versions, ETags, moves/copies, overwrite history, locks and expiry, stale browser edits, recursive deletion, quotas, Unicode names, duplicate titles, metadata, reviews/approvals, workflow visibility, and the real AI queue hook. It sends no provider requests.

MySQL/MariaDB migration, Apache/Nginx deployment, and native Windows/macOS/Office client behavior still need validation on the target server. Test with disposable documents before enabling staff write access.
