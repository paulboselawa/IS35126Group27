# CAS Learning Resources Portal

A portal for storing and sharing **syllabi, textbooks and student worksheets** for the 5 Key Learning Areas (KLAs).

## Pages (`/cas/`)

| Page | Who | Purpose |
|---|---|---|
| `index.php` | Everyone (students need no account) | The 5 KLAs with resource counts, search, recently added |
| `resources.php` | Everyone | Browse/search; filter by KLA, type (syllabus / textbook / worksheet), grade |
| `download.php` | Everyone | Download (or view PDFs/images inline) |
| `manage.php` | Teachers, admins | Stats per KLA, list/edit/delete resources |
| `upload.php` | Teachers, admins | Upload a new resource or edit / replace an existing one |
| `klas.php` | Admins | Rename the 5 KLAs to match your curriculum |

## Setup

1. Import `database.sql`, then `cas/schema.sql`. This adds the `teacher` role, the KLA and resource tables, the 5 default KLAs and a test `teacher` account (password `Password1!`).
2. Staff log in through the normal `login.php` (with email 2FA). Teachers go to `cas/manage.php`; admins get a link on their dashboard.

Default KLAs: English / Language, Mathematics, Science, Social Science, Personal Development. An admin can rename them in `klas.php`.

## Security notes

- Uploads: max 50 MB, checked against an extension allow-list **and** the file's real MIME type (finfo). Files are stored in `uploads/cas/` under random names with no extension, so they can't be run or guessed, and are only served through `download.php`.
- CSRF tokens on all forms, prepared statements, output escaping, role checks, and audit log entries for upload/edit/delete.
- Upload limits are raised in `nixpacks.toml` (`upload_max_filesize=50M`, `post_max_size=55M`).
