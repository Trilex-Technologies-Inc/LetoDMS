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

Every module output and operation route must authenticate the user and call
`LetoDMS_ModuleManager::isEnabled()` before reading or changing module data.
State-changing operations must use LetoDMS form keys for CSRF protection.

Core exposes `out/out.Module.php` and `op/op.Module.php` as generic dispatchers.
All module-specific controllers, classes, views, and SQL remain inside the
module directory. See `taskmanager/` for a complete example.
