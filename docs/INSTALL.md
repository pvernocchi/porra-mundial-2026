<div align="center">

# 🏗️ Guía de Instalación

### ⚽ Porra Mundial 2026

---

**Diseñada para hostings compartidos sin acceso SSH**
Sólo necesitas un cliente FTP y el panel cPanel de tu hosting.

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com)
[![Apache](https://img.shields.io/badge/Apache-mod__rewrite-D22128?style=flat-square&logo=apache&logoColor=white)](https://httpd.apache.org)

</div>

---

## 📋 Índice

- [⚙️ Requisitos previos](#️-requisitos-previos)
- [📥 Paso 1 — Descargar el paquete](#-paso-1--descargar-el-paquete)
- [🗄️ Paso 2 — Crear la base de datos](#️-paso-2--crear-la-base-de-datos)
- [📤 Paso 3 — Subir los archivos por FTP](#-paso-3--subir-los-archivos-por-ftp)
- [🔐 Paso 4 — Ajustar permisos](#-paso-4--ajustar-permisos)
- [🧙 Paso 5 — Ejecutar el asistente de instalación](#-paso-5--ejecutar-el-asistente-de-instalación)
- [✅ Paso 6 — Configuración post-instalación](#-paso-6--configuración-post-instalación)
- [🧹 Limpieza opcional](#-limpieza-opcional)
- [🐛 Solución de problemas](#-solución-de-problemas)

---

## ⚙️ Requisitos previos

Antes de empezar, comprueba que tu hosting cumpla con estos requisitos mínimos:

| Componente | Versión mínima | Notas |
|:-----------|:---------------|:------|
| 🐘 **PHP** | 8.1+ | La mayoría de hostings lo ofrecen en cPanel → "Select PHP Version" |
| 🗄️ **MySQL / MariaDB** | 5.7+ / 10.3+ | cPanel → "MySQL® Databases" |
| 🌐 **Apache** | con `mod_rewrite` | Namecheap y la mayoría de hostings lo traen activado por defecto |

### 📦 Extensiones PHP necesarias

Asegúrate de que estas extensiones estén habilitadas en tu hosting:

| Extensión | Para qué se usa |
|:----------|:----------------|
| `pdo_mysql` | Conexión a la base de datos |
| `openssl` | Cifrado y generación de tokens seguros |
| `mbstring` | Soporte de caracteres multibyte (UTF-8) |
| `curl` | Verificación de captchas y servicios externos |
| `sodium` | Cifrado simétrico (secretos TOTP, contraseñas SMTP) |

> [!TIP]
> En cPanel → **Select PHP Version** puedes activar/desactivar extensiones con un clic.

---

## 📥 Paso 1 — Descargar el paquete

1. Ve a la sección [**Releases**](../../releases) del repositorio.
2. Descarga el archivo **`porra-mundial-2026-vX.X.X.zip`** de la última versión.

> [!NOTE]
> El zip ya incluye la carpeta `vendor/` con todas las dependencias PHP.
> **No necesitas instalar Composer** en el servidor.

---

## 🗄️ Paso 2 — Crear la base de datos

Desde tu panel de control cPanel:

| # | Acción | Dónde |
|:-:|:-------|:------|
| **1** | Crear una nueva base de datos | cPanel → **MySQL® Databases** → _Create New Database_ |
| **2** | Crear un usuario MySQL | Misma página → _MySQL Users_ → _Add New User_ |
| **3** | Asignar usuario a la base de datos | _Add User to Database_ → selecciona ambos → **ALL PRIVILEGES** |

📝 **Apunta estos 4 valores** — los necesitarás en el asistente:

```
📌 Host de la BD      → normalmente localhost (cPanel lo muestra en la misma página)
📌 Nombre de la BD    → p.ej. cpaneluser_porra
📌 Usuario de la BD   → p.ej. cpaneluser_porrauser
📌 Contraseña de la BD → la que hayas elegido
```

> [!WARNING]
> Usa una **contraseña fuerte** para el usuario MySQL. El generador de cPanel es buena opción.

---

## 📤 Paso 3 — Subir los archivos por FTP

Conecta a tu hosting con un cliente FTP (FileZilla, WinSCP, Cyberduck, etc.) y elige una de estas dos modalidades:

### 🌟 Opción A — Document Root apuntando a `/public` (recomendada)

Esta es la opción más segura porque los archivos de la aplicación quedan fuera del directorio público.

```
/home/usuario/porra/          ← sube TODO aquí
├── 📂 app/
├── 📂 config/
├── 📂 database/
├── 📂 public/                ← este será el Document Root
├── 📂 storage/
├── 📂 vendor/
├── 📄 .htaccess
└── 📄 composer.json
```

Después, ve a cPanel → **Domains** → edita tu dominio y cambia el _Document Root_ a:

```
/home/usuario/porra/public
```

### 📂 Opción B — Todo en `public_html` (si no puedes cambiar el docroot)

Si tu hosting no permite modificar el Document Root:

```
/home/usuario/public_html/    ← sube TODO aquí
├── 📂 app/
├── 📂 config/
├── 📂 public/
├── 📂 storage/
├── ...
```

> [!NOTE]
> El archivo `.htaccess` de la raíz se encarga automáticamente de redirigir las peticiones a `/public`
> y de bloquear el acceso directo a `app/`, `config/`, `storage/`, etc.

---

## 🔐 Paso 4 — Ajustar permisos

Desde el **File Manager** de cPanel o tu cliente FTP, ajusta los permisos de estas carpetas:

| Carpeta | Permisos | Motivo |
|:--------|:--------:|:-------|
| 📁 `config/` | **775** | El asistente necesita escribir `config.php` aquí |
| 📁 `storage/` | **775** | Logs, caché, correos, archivo de lock |
| 📁 `storage/logs/` | **775** | Archivos de log de la aplicación |
| 📁 `storage/cache/` | **775** | Archivos de caché |
| 📁 `storage/mail/` | **775** | Correos `.eml` cuando no hay SMTP |

> [!TIP]
> En FileZilla: clic derecho sobre la carpeta → _File permissions…_ → escribe `775` → marca _Recurse into subdirectories_.

---

## 🧙 Paso 5 — Ejecutar el asistente de instalación

Abre tu dominio en el navegador — el asistente se lanzará automáticamente.

El proceso tiene **5 pantallas**:

<table>
<tr>
<td width="60" align="center">

### 1️⃣

</td>
<td>

**🔍 Comprobaciones del sistema**

El asistente verifica versión de PHP, extensiones, permisos de carpetas y conectividad.
Corrige los puntos marcados en 🔴 rojo y **recarga la página** para volver a comprobar.

</td>
</tr>
<tr>
<td align="center">

### 2️⃣

</td>
<td>

**🗄️ Conexión a base de datos**

Introduce los datos que anotaste en el Paso 2:
- Host (normalmente `localhost`)
- Puerto (`3306` por defecto)
- Nombre de la base de datos
- Usuario y contraseña
- Prefijo de tablas (`pm_` por defecto)

</td>
</tr>
<tr>
<td align="center">

### 3️⃣

</td>
<td>

**🌐 Configuración del sitio**

- Nombre del sitio (p.ej. _"Porra del grupo"_)
- URL pública (p.ej. `https://porra.tudominio.com`)
- Zona horaria (p.ej. `Europe/Madrid`)
- Idioma (`es` por defecto)

</td>
</tr>
<tr>
<td align="center">

### 4️⃣

</td>
<td>

**👤 Administrador inicial**

Crea la primera cuenta de administrador:
- Nombre de usuario
- Email
- Contraseña fuerte (mín. 8 caracteres)

</td>
</tr>
<tr>
<td align="center">

### 5️⃣

</td>
<td>

**🚀 Ejecutar instalación**

Un solo clic. El asistente:
1. Escribe `config/config.php` con todos los ajustes
2. Genera una `app_key` criptográfica única
3. Ejecuta las migraciones SQL
4. Crea la cuenta de administrador
5. Escribe `storage/installed.lock`

</td>
</tr>
</table>

> [!IMPORTANT]
> Una vez completada la instalación, la ruta `/install` devuelve **403 — Ya instalado**.
> El asistente se autobloquea para evitar reinstalaciones accidentales.

---

## ✅ Paso 6 — Configuración post-instalación

Una vez dentro del panel de administración, configura estos ajustes recomendados:

### 🔒 Habilitar HTTPS

Ve a cPanel → **SSL/TLS** o **Let's Encrypt™ SSL** y activa un certificado para tu dominio.

> [!WARNING]
> HTTPS es **obligatorio** si quieres usar autenticación WebAuthn (Yubikey, Windows Hello, FIDO2).

### ✉️ Configurar SMTP

Panel de admin → **Comunicaciones · SMTP**

| Campo | Ejemplo |
|:------|:--------|
| Servidor | `mail.tudominio.com` |
| Puerto | `587` (TLS) o `465` (SSL) |
| Usuario | `noreply@tudominio.com` |
| Contraseña | Tu contraseña SMTP |
| Cifrado | TLS (recomendado) |
| Reply-to | `admin@tudominio.com` |

> [!TIP]
> Sin configurar SMTP, los emails se guardan como archivos `.eml` en `storage/mail/`. Es útil para depurar, pero los usuarios no recibirán correos reales.

### 🛡️ Activar seguridad adicional

| Ajuste | Dónde | Descripción |
|:-------|:------|:------------|
| 🤖 Captcha | Panel → Seguridad | Google reCAPTCHA v2/v3 o Cloudflare Turnstile |
| 🔑 Política MFA | Panel → Seguridad | _Opcional_ · _Solo admins_ · _Todos los usuarios_ |
| 📱 Tu propio MFA | Tu perfil | Activa TOTP o WebAuthn en tu cuenta de admin |

---

## 🧹 Limpieza opcional

> No es estrictamente necesario, el asistente se autobloquea tras la instalación.

Para una **defensa en profundidad** adicional, puedes eliminar los archivos del instalador:

```
🗑️ Borrar:  app/Modules/Install/
```

Hazlo por FTP o desde el File Manager de cPanel una vez que todo funcione correctamente.

---

## 🐛 Solución de problemas

<details>
<summary>🔴 <strong>"No se puede conectar a la base de datos"</strong></summary>

- Verifica que el **host** sea exactamente el que muestra cPanel (a veces no es `localhost`, puede ser algo como `localhost:3306` o un hostname específico del servidor).
- Comprueba que el **usuario MySQL tiene todos los privilegios** sobre la base de datos.
- Asegúrate de que el **nombre de la BD** incluye el prefijo de tu usuario cPanel (p.ej. `cpaneluser_porra`).

</details>

<details>
<summary>⚪ <strong>Página en blanco (error 500)</strong></summary>

1. Revisa `storage/logs/app.log` por FTP.
2. Si está vacío, busca los errores de PHP en cPanel → **Errors** o **Error Log**.
3. Comprueba que los permisos de `storage/` y sus subcarpetas sean **775**.

</details>

<details>
<summary>🔴 <strong><code>Class "PDO" not found</code></strong></summary>

La extensión `pdo_mysql` no está habilitada.

**Solución:** cPanel → **Select PHP Version** → marca la casilla `pdo_mysql` → guardar.

</details>

<details>
<summary>🟡 <strong>El QR de TOTP no aparece</strong></summary>

El código QR se genera usando un servicio externo (`api.qrserver.com`).

- Si tu hosting bloquea peticiones HTTP salientes, el QR no se mostrará.
- **Alternativa:** usa el **secreto en texto** que aparece debajo del área del QR para configurar manualmente tu app de autenticación (Google Authenticator, Authy, etc.).

</details>

<details>
<summary>🟡 <strong>IPs inestables con Cloudflare (rate limit / captcha / auditoría)</strong></summary>

La aplicación prioriza estas cabeceras para identificar al cliente real:

1. `CF-Connecting-IP` (Cloudflare)
2. `X-Forwarded-For` (otros proxies)
3. `REMOTE_ADDR` (conexión directa)

Si usas Cloudflare u otro proxy inverso, la detección es automática.

</details>

<details>
<summary>🟡 <strong>Los emails no llegan a los usuarios</strong></summary>

1. Verifica que el **SMTP está configurado** en Panel → Comunicaciones.
2. Envía un **email de prueba** desde el panel.
3. Revisa `storage/mail/` para ver si los emails se están guardando como `.eml` (indica que no hay SMTP activo).
4. Comprueba que el hosting no bloquea el puerto SMTP (25, 465, 587).

</details>

<details>
<summary>🔴 <strong>Error de permisos al instalar</strong></summary>

```
❌ "Cannot write to config/"
❌ "Cannot write to storage/"
```

**Solución:**
```bash
# Desde File Manager de cPanel o tu cliente FTP:
config/     → permisos 775
storage/    → permisos 775 (recursivo)
```

</details>

---

<div align="center">

📖 **¿Necesitas actualizar una instalación existente?** → [`UPGRADE.md`](UPGRADE.md)

🏠 **Volver al README** → [`README.md`](../README.md)

---

_⚽ Porra Mundial 2026 — Hecho con pasión futbolera_

</div>
