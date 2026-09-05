# LetoDMS

LetoDMS is an open-source web-based Document Management System (DMS) written in PHP. It provides document storage, versioning, access control, workflow management, full-text indexing, and multi-language support.

## Overview

LetoDMS allows organizations to manage documents efficiently through an intuitive web interface. Key features include:

- **Document Management & Versioning**: Upload, track, and maintain multiple versions of documents with checksum verification.
- **Workflow Engine**: Custom approval workflows with state transitions and multi-user triggers.
- **Access Control List (ACL)**: Granular per-user and per-group permissions for documents and folders.
- **Responsive Bootstrap UI**: Desktop and mobile-friendly interface.
- **Module System**: Dynamic module upload, validation, and management features.
- **Database Support**: Database abstraction via PDO and ADOdb, supporting MySQL/MariaDB and SQLite3.
- **Full-Text Search & Notifications**: Full-text indexing of document contents and automated email notifications.
- **WebDAV Integration**: Access and manage documents using standard WebDAV clients.

## Recent Changes (LetoDMS 5.1b / 5.0b)

- **PHP 8.3 Compatibility**: Updated the codebase and core libraries for PHP 8.3 support.
- **Module System & Management**: Added module upload process, validation, and module management tools.
- **Responsive UI & Mobile Enhancements**: Improved mobile navigation, adjusted sidebar z-index hierarchy, and added CSS/JS asset versioning.
- **Installer Enhancements**: Configured core directory (`coreDir`) defaults and fixed database connection/success feedback.
- **Codebase Clean-up**: Streamlined repository by removing legacy upgrade routines, obsolete development scripts, and IDE configuration files.

## System Requirements

- **PHP**: 8.3 or higher (with PDO, PDO_MySQL, or PDO_SQLite extensions)
- **Web Server**: Apache, Nginx, or IIS
- **Database**: MySQL, MariaDB, or SQLite3

## Installation

1. Deploy the LetoDMS directory to your web server workspace.
2. Navigate to `install/index.php` in your web browser.
3. Follow the installation wizard to set up database connections, administrator credentials, and core directory settings.

## Documentation & References

- Workflow documentation: [README.Workflow](README.Workflow)
- Notification system details: [README.Notification](README.Notification)
- Complete change log: [CHANGELOG](CHANGELOG)
- License info: GNU General Public License - see [LICENSE](LICENSE)

