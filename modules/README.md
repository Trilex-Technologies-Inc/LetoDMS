# LetoDMS modules

Each module lives in a lowercase directory and provides a `manifest.php`. The
manifest returns an array with these keys:

- `name`: directory name and stable identifier
- `title`, `description`, `version`: admin-facing metadata
- `class`: lifecycle class name
- `bootstrap`: PHP file containing that class
- `url`: optional generic module page opened from the module manager
- `out_controller`, `op_controller`: module-relative request handlers
- `navigation`: optional boolean that adds the enabled module to the sidebar

The lifecycle class must implement:

```php
public function install($db, $driver);
public function uninstall($db, $driver);
```

Both methods return a boolean. Installation should create module-owned tables;
uninstallation should remove them. Disabling a module never calls uninstall,
so its data remains intact.

The generic output and operation dispatchers authenticate the user and verify
that the requested module is installed and enabled. State-changing module
operations must use POST and LetoDMS form keys for CSRF protection.

Core exposes `out/out.Module.php` and `op/op.Module.php` as generic dispatchers.
All module-specific controllers, classes, views, and SQL remain inside the
module directory. See `taskmanager/` for a complete example.

## Uploading modules

Administrators can install a module without shell access by uploading a ZIP
archive from the module manager page (Admin Tools → Modules → "Upload module
package"). See [UPLOAD.md](UPLOAD.md) for the full guide — package layout,
validation, requirements, and troubleshooting. A summary follows.

### Package layout

Build the ZIP so that `manifest.php` is either:

- at the archive root, or
- inside a single top-level directory (e.g. `example/manifest.php`)

The `name` in `manifest.php` must match the intended module directory and
follow the identifier rules (lowercase, starting with a letter:
`^[a-z][a-z0-9_-]*$`). The archive is extracted to `modules/<name>/`.

### What the upload validates

- The request is from an administrator and carries the `modulemanager` form key.
- The file is a genuine upload and a readable `.zip` archive.
- No entry uses an absolute path or `..` (path-traversal / zip-slip protection).
- A valid `manifest.php` with an allowed `name` is present.
- A module with that name does not already exist (uploads never overwrite).

On any failure the upload is rejected, temporary files are cleaned up, and the
module manager shows the reason.

### After uploading

Uploading only places the package on disk; it does **not** install or enable
the module. The module appears in the list as *Not installed* — click
**Install** to run its lifecycle SQL, then it can be enabled, disabled, or
uninstalled like any other module.

### Requirements and limits

- The PHP **zip** extension (`ZipArchive`) must be enabled on the server.
- The archive must fit within the PHP `upload_max_filesize` and
  `post_max_size` limits.
- The web server user needs write permission on the `modules/` directory.
