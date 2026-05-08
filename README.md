<div align="center">

# ⚽🏆 Porra Mundial 2026 🏆⚽

### _La porra definitiva para el Mundial FIFA 2026_

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com)
[![Version](https://img.shields.io/badge/Versión-0.6.0-blue?style=for-the-badge)]()

---

🌍 Elige tus selecciones · 📊 Sigue los puntos en vivo · 🥇 Compite con tus amigos

> Aplicación web autohospedada para organizar una porra del Mundial 2026.  
> Súbela a tu hosting por FTP, abre el navegador y ¡a jugar! 🚀

</div>

---

## 📋 Índice

- [🎯 ¿Qué es Porra Mundial 2026?](#-qué-es-porra-mundial-2026)
- [🎮 Reglas del juego](#-reglas-del-juego)
- [🧮 Sistema de puntuación](#-sistema-de-puntuación)
- [✨ Características](#-características)
- [🏗️ Arquitectura](#️-arquitectura)
- [⚙️ Requisitos](#️-requisitos)
- [🚀 Instalación y despliegue](#-instalación-y-despliegue)
- [📄 Documentación](#-documentación)
- [📝 Licencia](#-licencia)

---

## 🎯 ¿Qué es Porra Mundial 2026?

**Porra Mundial 2026** es una aplicación web para crear y gestionar una **porra (quiniela) entre amigos** durante el Mundial de Fútbol FIFA 2026. Cada jugador elige 6 selecciones — una de cada bombo — y acumula puntos según el rendimiento real de sus equipos durante todo el torneo.

**En resumen:**

> 🏟️ Elige tus equipos → ⚽ Mira los partidos → 📈 Acumula puntos → 🏆 Gana la porra

---

## 🎮 Reglas del juego

### 📝 Cómo participar

1. 🔗 Recibe un **enlace de invitación** de un administrador
2. 📝 Regístrate y accede a la plataforma
3. ⚽ Elige **1 selección por cada uno de los 6 bombos** del Mundial (6 equipos de entre las 48 selecciones participantes)
4. 🔒 Una vez bloqueadas las selecciones por el admin, ¡comienza la competición!

### 🗂️ Los 6 bombos

Las 48 selecciones del Mundial están repartidas en **6 bombos** según su ranking y potencial:

| Bombo | 🏷️ Nivel | Selecciones de ejemplo |
|:-----:|-----------|----------------------|
| 🥇 **1** | Favoritas | Alemania, Argentina, Brasil, Francia, España, Inglaterra, Países Bajos, Portugal |
| 🥈 **2** | Contendientes | Bélgica, Colombia, Croacia, Marruecos, Uruguay, Suiza |
| 🥉 **3** | Competitivas | EEUU, México, Japón, Turquía, Ecuador, Noruega |
| 4️⃣ **4** | Emergentes | Australia, Canadá, Egipto, Irán, Chequia, Austria |
| 5️⃣ **5** | Sorpresa | Argelia, Costa de Marfil, Panamá, Sudáfrica, Uzbekistán |
| 6️⃣ **6** | Cenicienta | Arabia Saudí, Haití, Irak, Curazao, Nueva Zelanda |

> 💡 **Estrategia:** Elige bien en cada bombo — tus puntos dependen del rendimiento de *todas* tus selecciones.

---

## 🧮 Sistema de puntuación

Los puntos se acumulan automáticamente basándose en lo que hacen tus selecciones en la vida real.

### ⚽ Resultados de partidos

| Evento | Puntos |
|--------|:------:|
| ✅ Victoria | **+3** |
| 🤝 Empate | **+1** |
| ❌ Derrota | **0** |

### 🏅 Bonificaciones por partido

| Evento | Puntos |
|--------|:------:|
| 🔥 Marcar 3+ goles | **+2** |
| 🧤 Portería a cero | **+1** |
| 💪 Remontada | **+1** |

### 🏟️ Avance en el torneo

| Logro | Puntos |
|-------|:------:|
| ✅ Pasar la fase de grupos | **+3** |
| 🏅 Octavos de final | **+3** |
| 🥉 Cuartos de final | **+4** |
| 🥈 Semifinal | **+6** |
| 🏆 Final | **+8** |
| 👑 ¡Campeón! | **+12** |

### 🌟 Premios individuales

| Premio | Puntos |
|--------|:------:|
| ⭐ MVP del torneo | **+3** |
| 👟 Bota de Oro (máximo goleador) | **+2** |
| 🧤 Guante de Oro (mejor portero) | **+2** |
| 🌱 Mejor jugador joven | **+2** |

### ⚠️ Penalizaciones

| Evento | Puntos |
|--------|:------:|
| 🟡 Tarjeta amarilla | **−0.2** |
| 🟡🟡 Doble amarilla | **−1** |
| 🔴 Tarjeta roja | **−2** |
| 💥 Encajar 3+ goles | **−2** |
| 📉 Último del grupo | **−1** |

> 📊 **Tu puntuación total** = suma de los puntos de tus 6 selecciones. ¡Consulta el **leaderboard** en tiempo real!

---

## ✨ Características

<table>
<tr>
<td width="50%">

### 🖥️ Instalación sin CLI
Asistente desde el navegador estilo WordPress — sin terminal, sin SSH, sin Composer en el servidor. Actualización automática de migraciones al subir una nueva versión.

### 👥 Usuarios por invitación
- 🔗 Alta mediante **enlace de invitación** (válido 48 h)
- 👤 Roles `user` / `admin`
- 🗑️ Soft delete, edición y reset de contraseña

### 🔐 Autenticación multifactor
- 📱 **TOTP** — QR + códigos de recuperación
- 🔑 **WebAuthn** — Yubikeys, FIDO2, Windows Hello
- ⚙️ Política configurable por tipo de usuario

</td>
<td width="50%">

### ✉️ Comunicaciones SMTP
- Servidores SMTP con cifrado (TLS/SSL)
- Reply-to, email de prueba, plantillas
- Fallback a archivos `.eml` sin SMTP

### 🛡️ Seguridad integral
- 🤖 Captcha — reCAPTCHA v2/v3 · Turnstile
- 🔒 CSRF en todos los formularios
- 🔑 Hashing bcrypt
- 🚫 Rate limit y bloqueo de cuentas
- 🍪 Cookies seguras (Secure · HttpOnly · SameSite)
- 📋 Log de auditoría

### 📊 Panel de administración
- Gestión de partidos y resultados
- Control de avances y premios del torneo
- Bloqueo/desbloqueo de selecciones

</td>
</tr>
</table>

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
├── 📂 storage/                # Logs, caché, emails
├── 📂 tests/                  # Tests PHPUnit
└── 📂 docs/                   # Documentación
```

> 🧩 **Framework propio ligero** — sin Laravel ni Symfony — optimizado para hostings compartidos con recursos limitados.

---

## ⚙️ Requisitos

| Requisito | Versión mínima |
|-----------|:--------------:|
| 🐘 PHP | `8.1+` |
| 🗄️ MySQL / MariaDB | `5.7+` / `10.3+` |
| 🌐 Apache | `mod_rewrite` |

**Extensiones PHP necesarias:** `pdo_mysql` · `openssl` · `mbstring` · `curl` · `sodium`

---

## 🚀 Instalación y despliegue

### 📦 Despliegue rápido (FTP)

1. 📥 Descarga la última release desde [**Releases**](../../releases)
2. 🗄️ Crea la base de datos en tu panel de hosting
3. 📤 Sube los archivos por FTP
4. 🔐 Ajusta permisos `775` en `config/` y `storage/`
5. 🌐 Abre el dominio en el navegador y sigue el asistente

### ☁️ Despliegue con GitHub Actions

Usa el workflow **Deploy FTP (On-demand)** para subir automáticamente por FTP/FTPS.

Configura estos secretos en **Settings → Secrets → Actions**:

| Secreto | Descripción |
|---------|-------------|
| `FTP_SERVER` | Host FTP/FTPS |
| `FTP_USERNAME` | Usuario FTP |
| `FTP_PASSWORD` | Contraseña FTP |
| `FTP_SERVER_DIR` | Ruta remota |
| `FTP_PROTOCOL` | Protocolo _(opcional, `ftps` por defecto)_ |

### 🔄 Actualización

Sube la nueva release por FTP — las migraciones pendientes se detectan y aplican automáticamente:

- 🌐 **Navegador** → redirige a `/install/upgrade`
- 💻 **CLI** → `php bin/upgrade.php`

---

## 📄 Documentación

| 📖 Documento | Descripción |
|-------------|-------------|
| [`docs/INSTALL.md`](docs/INSTALL.md) | 🛠️ Guía de instalación paso a paso |
| [`docs/UPGRADE.md`](docs/UPGRADE.md) | 🔄 Procedimiento de actualización y rollback |

---

## 📝 Licencia

Este proyecto está bajo la licencia **MIT** — consulta el archivo [LICENSE](LICENSE) para más detalles.

---

<div align="center">

**⚽ ¡Hecho con pasión futbolera! ⚽**

🌟 _¿Preguntas o sugerencias?_ Abre un [issue](../../issues) 🌟

</div>
