# Module upload

LetoDMS lets administrators add a module without shell or FTP access by
uploading a ZIP package from the web interface. This document describes how to
build a package, upload it, what is validated, and how to troubleshoot.

For details on developing a module itself (manifest fields, lifecycle class,
controllers, SQL), see the in-app **Module Development Guide** (Admin Tools →
Modules → Module Development Guide) and the complete example in
`modules/taskmanager/`.

---

## 1. Overview

The upload feature places a packaged module into the `modules/` directory so
it can be installed and enabled like any other module. Uploading only copies
files — it never installs, enables, or runs module code by itself.

**Where:** Admin Tools → Modules → *Upload module package* (top of the page).
The module manager is available in the **bootstrap** theme only.

---

## 2. Building a package

Create a normal module directory, then compress it as a **ZIP** archive.

The archive must contain `manifest.php` in one of two locations:

```
example.zip
├── manifest.php          ← at the archive root, or
└── ...

example.zip
└── example/              ← inside a single top-level directory
    ├── manifest.php
    └── ...
```

Rules:

- `manifest.php` must return an array whose `name` matches the intended
  module directory and satisfies the identifier pattern
  `^[a-z][a-z0-9_-]*$` (lowercase, starts with a letter).
- Use forward-slash relative paths inside the archive. No absolute paths and
  no `..` entries.
- The result is extracted to `modules/<name>/`.

Example (from a shell, inside the module's parent directory):

```
zip -r example.zip example/
```

---

## 3. Uploading

1. Log in as an administrator.
2. Go to Admin Tools → **Modules**.
3. Under *Upload module package*, choose the `.zip` file and click **Upload**.
4. On success the page shows a confirmation and the new module appears in the
   list as **Not installed**.
5. Click **Install** to run the module's install SQL, then enable it.

---

## 4. What is validated

Every upload is checked before anything is written. A failure aborts the
upload, removes temporary files, and reports the reason.

- The request is authenticated as an administrator and carries the
  `modulemanager` CSRF form key.
- The file is a genuine HTTP upload and a readable `.zip` archive.
- No archive entry uses an absolute path or `..` (zip-slip protection).
- A valid `manifest.php` with an allowed `name` is present.
- No module with that name already exists — uploads never overwrite.

---

## 5. Requirements and limits

- The PHP **zip** extension (`ZipArchive`) must be enabled on the server.
- The archive must fit within PHP's `upload_max_filesize` and
  `post_max_size` limits.
- The web server user needs **write permission** on the `modules/` directory.

---

## 6. Troubleshooting

| Message | Cause | Fix |
|---|---|---|
| No module package was uploaded | No file selected, or the upload was interrupted | Choose a `.zip` and retry |
| The package exceeds the server upload limit | Archive larger than `upload_max_filesize` / `post_max_size` | Shrink the archive or raise the PHP limits |
| The PHP zip extension is required to upload modules | `ZipArchive` not available | Enable the PHP zip extension |
| Module packages must be ZIP archives | File is not a `.zip` | Re-compress as ZIP |
| The archive contains unsafe paths | Zip-slip / absolute path entries | Rebuild the archive with relative paths |
| The package does not contain a valid manifest.php | Missing/invalid manifest or bad `name` | Add a valid `manifest.php` |
| A module named "…" already exists | A module with that name is present | Remove the existing one or rename |
| The module could not be moved into the modules directory | `modules/` not writable | Fix directory permissions |

---

## 7. Security notes

- Only administrators can upload.
- The upload is CSRF-protected and the archive is path-traversal checked.
- Uploading does **not** execute module code; code runs only after an admin
  explicitly installs and enables the module. Even so, only upload modules
  from sources you trust — an installed module runs with the full privileges
  of the LetoDMS application.
