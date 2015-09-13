# modx-revo-update-script
A very rough MODX Revo upgrade script that I use for my own sites.

## What it Does
- It logs any signed in manager user out.
- It clears the cache
- It backs up the site database
- It attempts (memory permitting) to backup the site files.
- It zips the backup of the site files (again memory permitting)
-It grabs the version of MODX specified from the MODX server, downloads, unzips and overwrites the existing install with the new file versions
- Finally it provides a link to the setup screen

### Licensing
This is licensed under the GPl V3 for anyone foolish enough to use it.
