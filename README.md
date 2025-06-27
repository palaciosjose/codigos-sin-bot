# Web Codigos 5.0

## Database Configuration

Database credentials are no longer stored in `instalacion/basededatos.php`.
Instead, the application loads them from environment variables or from a
non-tracked file `config/db_credentials.php`.

1. **Using environment variables**: set `DB_HOST`, `DB_USER`, `DB_PASSWORD`
   and `DB_NAME` in your server environment.
2. **Using a credentials file**: copy `config/db_credentials.sample.php` to
   `config/db_credentials.php` and fill in your database details. This file is
   ignored by Git so your credentials remain private.

During installation the system will automatically create
`config/db_credentials.php` with the data you provide.

## Installation

For a step-by-step guide to installing the system, see
[docs/INSTALACION.md](docs/INSTALACION.md).

## User Manual

Once installed and logged in, a **Manual** option appears in the navigation bar.
It links to an interactive help page (`manual.php`) with basic usage
instructions. The same content is also available in
[docs/MANUAL_USO.md](docs/MANUAL_USO.md).
