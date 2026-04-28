<div align="center">

# ⚽ Porra Mundial 2026

### 🏆 Tu porra futbolera, lista para desplegar

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com)
[![License](https://img.shields.io/badge/Licencia-MIT-green?style=for-the-badge)](LICENSE)
[![Version](https://img.shields.io/badge/Versión-0.1.0-blue?style=for-the-badge)]()

> Aplicación web de porra para el Mundial 2026 🌍  
> Diseñada para hostings compartidos — sube por FTP, abre el navegador y listo.

</div>

---

## 📋 Tabla de contenidos

- [✨ Características](#-características)
- [🏗️ Arquitectura](#️-arquitectura)
- [⚙️ Requisitos](#️-requisitos)
- [🚀 Despliegue rápido](#-despliegue-rápido)
- [☁️ Despliegue con GitHub Actions](#️-despliegue-con-github-actions)
- [📦 Estado del proyecto](#-estado-del-proyecto)
- [🛠️ Desarrollo](#️-desarrollo)
- [📄 Documentación](#-documentación)
- [📝 Licencia](#-licencia)

---

## ✨ Características

### 🖥️ Instalación sin CLI
Asistente desde el navegador estilo WordPress — sin terminal, sin SSH, sin Composer en el servidor.  
Actualización automática de migraciones al subir una nueva versión.

### 👥 Gestión de usuarios por invitación
- 🔗 Alta exclusivamente mediante **enlace de invitación** (válido 48 h, reenviable)
- 👤 Roles `user` / `admin` con CRUD completo
- 🗑️ Soft delete, edición y reset de contraseña

### 🔐 Autenticación multifactor (MFA)
- 📱 **TOTP** — QR + secreto manual, códigos de recuperación de un solo uso
- 🔑 **WebAuthn** — Yubikeys, FIDO2, Windows Hello (múltiples credenciales por usuario)
- ⚙️ Política configurable: _opcional_ · _solo admins_ · _todos los usuarios_

### ✉️ Comunicaciones SMTP
- Configuración de servidores SMTP con cifrado (TLS/SSL)
- Reply-to, email de prueba, plantillas personalizables
- Fallback a archivos `.eml` en disco cuando no hay SMTP

### 🛡️ Seguridad integral
| Medida | Detalle |
|--------|---------|
| 🤖 Captcha | Google reCAPTCHA v2/v3 · Cloudflare Turnstile |
| 🔒 CSRF | Token en todos los formularios |
| 🔑 Hashing | `password_hash` (bcrypt) |
| 🚫 Rate limit | Bloqueo de cuenta tras N intentos fallidos |
| 🍪 Cookies | Secure · HttpOnly · SameSite |
| 📋 Auditoría | Log de eventos sensibles |

---

## 🏗️ Arquitectura

```
porra-mundial-2026/
├── 📂 app/                    # Código fuente PHP
│   ├── Core/                  # Núcleo (Router, Session, CSRF, DB…)
│   ├── Models/                # Modelos de datos
│   ├── Modules/
│   │   ├── Admin/             # Panel de administración
│   │   ├── Auth/              # Login, logout, MFA
│   │   ├── Game/              # Lógica de la porra
│   │   └── Install/           # Asistente de instalación
│   ├── bootstrap.php
│   └── routes.php
├── 📂 bin/                    # Scripts CLI (build, upgrade)
├── 📂 config/                 # Configuración (generada por el instalador)
├── 📂 database/               # Migraciones SQL
├── 📂 public/                 # Document root del servidor web
│   ├── assets/                # CSS, JS, imágenes
│   └── index.php              # Front controller
├── 📂 storage/                # Logs, caché, emails, lock
├── 📂 tests/                  # Tests PHPUnit
├── 📂 docs/                   # Documentación (INSTALL, UPGRADE)
└── 📄 composer.json
```

> **Framework propio ligero** — sin Laravel ni Symfony — diseñado para funcionar en hostings modestos con recursos limitados.

---

## ⚙️ Requisitos

| Requisito | Versión mínima |
|-----------|----------------|
| 🐘 PHP | 8.1+ |
| 🗄️ MySQL / MariaDB | 5.7+ / 10.3+ |
| 🌐 Apache | `mod_rewrite` habilitado |

**Extensiones PHP necesarias:**

```
pdo_mysql · openssl · mbstring · curl · sodium
```

---

## 🚀 Despliegue rápido

<details>
<summary><strong>📖 Pasos de instalación (clic para expandir)</strong></summary>

1. **Descarga** la última release zip desde [Releases](../../releases)
2. **Crea la base de datos** en cPanel → MySQL® Databases
3. **Sube los archivos** por FTP al hosting
4. **Ajusta permisos** `775` en `config/` y `storage/`
5. **Abre el dominio** en el navegador → el asistente te guía:
   - ✅ Comprobaciones del sistema
   - 🗄️ Conexión a base de datos
   - 🌐 Configuración del sitio
   - 👤 Creación del administrador
   - 🚀 Ejecución de migraciones

</details>

> 📚 Guía completa paso a paso en [`docs/INSTALL.md`](docs/INSTALL.md)

### 🔄 Actualización

Sube la nueva release por FTP sobre la instalación existente.  
Las migraciones pendientes se detectan y aplican automáticamente:

- **🌐 Desde el navegador** — al acceder al sitio, redirige a `/install/upgrade` donde un admin confirma la actualización.
- **💻 Desde la línea de comandos** — ejecuta `php bin/upgrade.php` (o `php bin/upgrade.php --force` para modo no interactivo).

> 📚 Detalles en [`docs/UPGRADE.md`](docs/UPGRADE.md)

---

## ☁️ Despliegue con GitHub Actions

El workflow **Deploy FTP (On-demand)** sube el proyecto por FTP/FTPS directamente desde GitHub.

### 🔑 Secretos necesarios

Configura en **Settings → Secrets and variables → Actions**:

| Secreto | Descripción | Ejemplo |
|---------|-------------|---------|
| `FTP_SERVER` | Host FTP/FTPS | `ftp.tudominio.com` |
| `FTP_USERNAME` | Usuario FTP | `deploy@tudominio.com` |
| `FTP_PASSWORD` | Contraseña FTP | — |
| `FTP_SERVER_DIR` | Ruta remota | `/home/usuario/porra/` |
| `FTP_PROTOCOL` | Protocolo _(opcional)_ | `ftps` (por defecto) |

### ▶️ Ejecución

1. Ve a **Actions** → **Deploy FTP (On-demand)**
2. Pulsa **Run workflow**
3. Indica opcionalmente un `ref` (branch / tag / SHA) — por defecto usa `main`

---

## 📦 Estado del proyecto

| # | Funcionalidad | Estado |
|---|---------------|--------|
| 1 | 🧱 Esqueleto (router, autoload, config, sesiones, CSRF, vistas) | ✅ Completado |
| 2 | 🧙 Asistente de instalación + modo upgrade | ✅ Completado |
| 3 | 🔐 Auth básico (login/logout, rate limit, auditoría) | ✅ Completado |
| 4 | 🔗 Invitaciones (link 48 h, reenvío, revocación) | ✅ Completado |
| 5 | ✉️ Comunicaciones SMTP (PHPMailer + spool) | ✅ Completado |
| 6 | 👥 Admin · Usuarios (CRUD, MFA reset, soft delete) | ✅ Completado |
| 7 | 📱 MFA TOTP (QR, verificación, recovery codes) | ✅ Completado |
| 8 | 🔑 MFA WebAuthn (FIDO2, Yubikey, Windows Hello) | ✅ Completado |
| 9 | 🛡️ Seguridad (captcha, política MFA, bloqueo, expiración) | ✅ Completado |
| 10 | 📦 Empaquetado, release y documentación | ✅ Completado |


---

## 📄 Documentación

| Documento | Descripción |
|-----------|-------------|
| 📖 [`docs/INSTALL.md`](docs/INSTALL.md) | Guía de instalación paso a paso (incluye Namecheap/cPanel) |
| 🔄 [`docs/UPGRADE.md`](docs/UPGRADE.md) | Procedimiento de actualización y rollback |

---

## 📝 Licencia

Este proyecto está bajo la licencia **MIT** — consulta el archivo [LICENSE](LICENSE) para más detalles.

---

<div align="center">

**⚽ ¡Hecho con pasión futbolera! ⚽**

_¿Preguntas o sugerencias? Abre un [issue](../../issues)._

</div>
