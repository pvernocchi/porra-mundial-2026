# Porra Mundial 2026

Aplicación web de porra para el mundial 2026, diseñada para ser **reutilizable** y desplegable en hostings compartidos (Namecheap / cPanel) con sólo subir los archivos por FTP y abrir el dominio en un navegador.

## Características

- **Sin CLI**: instalación y actualizaciones desde el navegador, estilo WordPress.
- **PHP 8.3+ / MySQL** (también soporta MariaDB y SQLite para tests).
- Núcleo propio ligero (sin Laravel/Symfony) para encajar en hostings modestos.
- Módulo de **Administración**:
  - Gestión de usuarios sólo por **invitación** (enlace válido 48 h, reenviable).
  - Roles `user` / `admin`, alta/baja/edición y reset de contraseña.
  - **MFA** con TOTP, llaves FIDO2/Yubikey y Windows Hello (WebAuthn) — múltiples credenciales por usuario.
  - Códigos de recuperación de un solo uso.
- **Comunicaciones**: configuración SMTP con cifrado, reply-to, email de prueba, plantillas.
- **Seguridad**:
  - Captcha (Google reCAPTCHA v2/v3, Cloudflare Turnstile).
  - Política MFA configurable (opcional / admins / todos).
  - Bloqueo de cuenta tras N intentos.
  - Auditoría de eventos sensibles.
- Cookies seguras, CSRF en todos los formularios, hashing con `password_hash`.

## Estado del proyecto

| PR | Funcionalidad | Estado |
| -- | ------------- | ------ |
| 1 | Esqueleto (router, autoload, config, sesiones, CSRF, vistas) | ✅ |
| 2 | Asistente de instalación (preflight, BD, sitio, admin, migraciones, lock) y modo upgrade | ✅ |
| 3 | Auth básico (login/logout, política contraseña, rate limit, audit) | ✅ |
| 4 | Invitaciones (alta vía link 48 h, reenvío, revocación) | ✅ |
| 5 | Comunicaciones SMTP (PHPMailer + SMTP propio + spool) | ✅ |
| 6 | Admin · Usuarios (CRUD completo, MFA reset, soft delete, audit) | ✅ |
| 7 | MFA TOTP (alta con QR, verificación, recovery codes) | ✅ |
| 8 | MFA WebAuthn (esquema, UI, endpoints stub — necesita `web-auth/webauthn-lib` para activarse) | ⚠️ Stub |
| 9 | Seguridad (captcha, política MFA, bloqueo, expiración) | ✅ |
| 10 | Empaquetado/release y docs (`INSTALL.md`, `UPGRADE.md`, GitHub Actions) | ✅ |

> WebAuthn está estructuralmente preparado (esquema BD, UI, rutas, política HTTPS) pero el handshake completo se delega a `web-auth/webauthn-lib`. Cuando ejecutes `composer install` antes de empaquetar el release, las rutas dejarán de devolver 501.

## Despliegue rápido

Ver [`INSTALL.md`](INSTALL.md) para una guía paso a paso (incluye Namecheap).

## Despliegue FTP manual (on-demand) con GitHub Actions

Se añadió el workflow `.github/workflows/deploy-ftp.yml` para subir el proyecto desde GitHub al hosting por FTP/FTPS cuando lo ejecutes manualmente.

### Secretos necesarios en GitHub

Configura estos secretos en **Settings → Secrets and variables → Actions**:

- `FTP_SERVER`: host FTP/FTPS (ej. `ftp.tudominio.com`)
- `FTP_USERNAME`: usuario FTP
- `FTP_PASSWORD`: contraseña FTP
- `FTP_SERVER_DIR`: ruta remota donde desplegar (ej. `/home/usuario/porra/`)
- `FTP_PROTOCOL` (opcional): `ftps` (por defecto) o `ftp`

### Cómo ejecutarlo

1. Ve a **Actions** → **Deploy FTP (On-demand)**.
2. Pulsa **Run workflow**.
3. Opcionalmente indica `ref` (branch/tag/SHA); por defecto usa `main`.

El workflow hace checkout del ref, instala dependencias de producción con Composer y sube archivos por FTPS (por defecto).

## Desarrollo

```bash
# Linter sintáctico (incluido en PHP)
find app public -name '*.php' -print0 | xargs -0 -n1 php -l

# Tests
composer install
vendor/bin/phpunit
```

## Licencia

MIT.
