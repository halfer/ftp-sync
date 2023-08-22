README
===

Introduction
---

This PHP script is a system to keep one folder in sync with a remote folder, using FTP. Files are only copied if
they have not already been copied, or if they have changed, so it can be thought of as being a bit like Rsync. It
is probably most useful for shared LAMP environments like cPanel where Rsync is not available.

A major use case is to sync log files.

Features
---

The script should work in either of two modes:

* Being called by cURL or Wget over the web
* Being called as a shell process using the console version of PHP

In general the script will be called using a scheduler such as Cron, so that ongoing changes are copied.

The script checks quite a lot of things, and if it finds an error, it will say what the problem is and exit early.

PHP requirements
---

The script presently requires:

* PHP 7.3
* The `ftp` PHP module (in shared hosts this is often enabled by default)
* Both servers may need to be running similar operating systems e.g. Linux/Unix (see below)

Installation
---

Installation is fairly straightforward:

1. Copy the `ftp-sync.php` script to your preferred remote server location (either in your web root or
outside your web root, depending on how you wish to call it)
2. Copy the `config.php.example` file locally as `config.php` and fill in the details of your remote host
3. The config value `local_directory` should point to a writeable folder on the same machine as the script -
the remote files will be copied here
4. The config value `remote_directory` will be used in FTP commands to find the remote folder to pull files from
5. Add a line in your Cron to call the script

Limitations
--

Currently, the definition of a file that is unchanged is that the file size is the same on both sides. This is
not a foolproof strategy - a file could change without changing the size, and presently this would not be
detected.

A related issue is that files are currently copied in binary mode. This is not a problem when the two systems
being synchronised are both Linux/Unix, and indeed if ASCII mode was used instead, it would still work. But if
the files being synced are text-based _and_ the sync is between a Windows server and a *nix server, ASCII mode
would result in different file sizes, and thus files would never be regarded as synchronised. This is due to the
different encodings used for line endings in these systems.

Both of these issues could be fixed by maintaining a modification time index instead. This would be stored
on the local side.

There is currently no support for recursive syncing - all objects in the source directory are expected to be
files, not directories.

There is currently no support for SFTP or FTPS.

Files that are deleted in the source folder are presently not deleted in the sync folder.

Wishlist
---

A few things that would be good to add:

* Exits cleanly if the FTP extension is not loaded
* An ability to specify a non-standard FTP port
* Configurable file filters (presently this is hardwired to `*.log`)
* Useful verbose output (it is only noisy in error conditions presently)
* A "dry run" mode
* Local-side file deletion (for where a source file is removed)
* Automated tests
* Some notes on using this tool securely

Support
---

Feel free to raise an issue if you need help or need a feature.

Patches are welcome, but it is worth discussing the change first. Not all changes may fit with the intended
scope of this work. Of course, forks are permissible.
