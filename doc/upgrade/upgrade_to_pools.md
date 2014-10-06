Upgrading to version "pools":
================================

We need to alter few tables and add a completely new, "Pool" table.
Upgrade script will read your database settings from `config.php`, no changes required.

1. Check `upgrade.sql` and `upgrade2.sql` for intended SQL changes.
2. Check `upgrade.php` for migration steps that will be run.
3. While in this upgrade directory, run `php -f upgrade.php`

It should print no output on success.
