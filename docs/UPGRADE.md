# Guía de actualización

## Procedimiento

1. Descarga la nueva release zip.
2. Sube los archivos por FTP **sobre la instalación existente**, sobreescribiendo los archivos del repositorio.
   - **No** sobrescribas `config/config.php` (no está en el zip; los releases no incluyen este archivo).
   - **No** sobrescribas `storage/`. La nueva release no toca el contenido de esa carpeta.
3. Abre cualquier URL del sitio en el navegador. Si hay migraciones pendientes te redirigirá a `/install/upgrade`.
4. Inicia sesión con una cuenta de administrador.
5. Confirma la actualización. Se aplican las migraciones SQL pendientes y se actualiza `storage/installed.lock`.

## Backups antes de actualizar

- **Base de datos**: usa `phpMyAdmin` (cPanel → MySQL Databases → phpMyAdmin) para exportar un dump completo.
- **Archivos**: descarga al menos `config/config.php` y la carpeta `storage/` por FTP.

## Rollback

Si algo va mal:
1. Restaura los archivos previos por FTP (incluyendo `config/config.php` y `storage/installed.lock`).
2. Restaura la base de datos desde el dump.
