# Guía de instalación

Diseñada para hostings compartidos sin acceso por SSH (Namecheap, cPanel, etc.).
Sólo necesitas un cliente FTP y el panel cPanel del hosting.

## Requisitos

- PHP **8.3** o superior con las extensiones `pdo_mysql`, `openssl`, `mbstring`, `curl` y `sodium`.
- MySQL 5.7+ / MariaDB 10.3+.
- Apache con `mod_rewrite` (Namecheap lo trae activado por defecto).

## 1. Descargar el paquete

Descarga la última `release zip` desde la sección _Releases_ del repositorio.
El zip incluye la carpeta `vendor/` con todas las dependencias, así no necesitas Composer en el servidor.

## 2. Crear la base de datos en cPanel

1. Entra a cPanel → **MySQL® Databases**.
2. Crea una base de datos (p.ej. `cpaneluser_porra`).
3. Crea un usuario con contraseña fuerte y **asígnale todos los privilegios** sobre esa base de datos.
4. Apunta los valores: nombre BD, usuario, contraseña, host (normalmente `localhost`).

## 3. Subir los archivos por FTP

Hay dos modos según lo que permita tu hosting:

### Modo recomendado: docroot apuntando a `/public`

1. Sube **todos los archivos** del zip (incluyendo `app/`, `public/`, `config/`, etc.) al servidor, p.ej. dentro de `/home/usuario/porra/`.
2. En cPanel → **Domains**, edita el dominio para que su _Document Root_ sea `/home/usuario/porra/public`.

### Modo "todo en `public_html`" (si no puedes cambiar el docroot)

1. Sube todos los archivos a `/home/usuario/public_html/`.
2. El `.htaccess` de la raíz se encarga de redirigir las peticiones a `/public` y bloquear `app/`, `config/`, `storage/`, etc.

## 4. Permisos

Desde el _File Manager_ de cPanel o tu cliente FTP, asegúrate de que las carpetas `config/` y `storage/` (y todas sus subcarpetas) tengan permisos **775** (lectura/escritura para el usuario PHP).

## 5. Lanzar el asistente

Abre el dominio en el navegador. Verás el asistente:

1. **Comprobaciones del sistema** — corrige los puntos en rojo y recarga.
2. **Base de datos** — host, puerto, nombre, usuario, contraseña, prefijo (`pm_` por defecto).
3. **Configuración del sitio** — nombre, URL pública, zona horaria, idioma.
4. **Administrador inicial** — nombre, email, contraseña fuerte. Será tu primera cuenta `admin`.
5. **Ejecutar instalación** — escribe `config/config.php`, aplica las migraciones, crea al admin y deja la marca `storage/installed.lock`.

A partir de ese momento, intentar volver a `/install` mostrará un 403 “ya instalado”.

## 6. Tras la instalación

- Habilita HTTPS desde cPanel (AutoSSL / Let's Encrypt). **Necesario** si quieres usar Yubikey o Windows Hello.
- Entra al panel → **Comunicaciones · SMTP** y configura los servidores de envío. Sin SMTP, los emails se quedarán en `storage/mail/` como archivos `.eml` (útil para depurar).
- Entra al panel → **Seguridad** y, si lo deseas, activa Captcha y la política MFA.
- Activa MFA en tu cuenta (recomendado).

## Borrar el instalador

No es estrictamente necesario (`/install` se autobloquea), pero por defensa en profundidad puedes borrar `app/Modules/Install/` por FTP cuando todo esté funcionando.

## Solución de problemas

- **"No se puede conectar a la base de datos"**: revisa que el host sea exactamente el que indica cPanel (a veces no es `localhost`), y que el usuario tenga privilegios sobre la base.
- **Página en blanco**: mira `storage/logs/app.log`. Si está vacío, comprueba los logs de error de PHP en cPanel.
- **`Class "PDO" not found`**: la extensión `pdo_mysql` no está habilitada — pídele al hosting que la active.
- **El QR de TOTP no aparece**: el QR se genera con un servicio externo (api.qrserver.com). Si tu hosting bloquea peticiones salientes, usa el secreto en texto que aparece debajo del QR.
- **IPs inestables con Cloudflare (rate limit/captcha/auditoría)**: la app prioriza `CF-Connecting-IP` y luego `X-Forwarded-For` para identificar al cliente real detrás del proxy.
