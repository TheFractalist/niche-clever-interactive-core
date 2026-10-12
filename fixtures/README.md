# Disposable local fixture topology

This Compose topology is for generated NCI-only fixture data. It must not connect to an existing local WordPress installation, an AskFolio database, a provider store or a production service.

The wp65 profile targets WordPress 6.5 with PHP 8.1 and MariaDB 10.11. The current profile targets the current WordPress image, PHP 8.3 and MariaDB 11.4. Values in the Compose file are local fixture credentials only, never provider credentials. A C5 live canary may supply its server-only provider variables through a separately approved external Docker environment file; never place those values in this repository, Compose file, runtime volume snapshot or receipt.

Run one profile at a time. Capture image versions and volume/database snapshot checksums in a phase receipt. Remove or restore only the named ncli fixture volumes. Do not run a destructive Docker command against any unrelated volume.
