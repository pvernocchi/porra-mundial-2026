<div align="center">

# 🔄 Guía de Actualización

### ⚽ Porra Mundial 2026

---

**Actualizar tu instalación es rápido y seguro**
Sube la nueva versión por FTP y las migraciones se aplican desde el navegador.

[![Version](https://img.shields.io/badge/Guía-Actualización-blue?style=flat-square)](UPGRADE.md)
[![Install](https://img.shields.io/badge/Guía-Instalación-green?style=flat-square)](INSTALL.md)

</div>

---

## 📋 Índice

- [⚠️ Antes de actualizar — Backup](#️-antes-de-actualizar--backup)
- [📥 Paso 1 — Descargar la nueva versión](#-paso-1--descargar-la-nueva-versión)
- [📤 Paso 2 — Subir los archivos por FTP](#-paso-2--subir-los-archivos-por-ftp)
- [🚀 Paso 3 — Aplicar las migraciones](#-paso-3--aplicar-las-migraciones)
- [✅ Verificar la actualización](#-verificar-la-actualización)
- [⏪ Rollback — Revertir a la versión anterior](#-rollback--revertir-a-la-versión-anterior)
- [❓ Preguntas frecuentes](#-preguntas-frecuentes)

---

## ⚠️ Antes de actualizar — Backup

> [!CAUTION]
> **Haz siempre una copia de seguridad** antes de actualizar. Si algo sale mal, podrás restaurar tu instalación.

### 🗄️ Backup de la base de datos

| Método | Pasos |
|:-------|:------|
| **phpMyAdmin** _(recomendado)_ | cPanel → **phpMyAdmin** → selecciona tu base de datos → pestaña **Exportar** → formato SQL → **Continuar** |
| **cPanel Backup** | cPanel → **Backup** → _Download a MySQL Database Backup_ → selecciona tu base de datos |

### 📁 Backup de archivos críticos

Descarga por FTP al menos estos archivos y carpetas:

```
📦 Archivos a respaldar:
├── 📄 config/config.php         ← tu configuración personalizada
├── 📂 storage/                  ← logs, correos, caché, lock
│   ├── 📄 installed.lock
│   ├── 📂 logs/
│   └── 📂 mail/
└── 📄 VERSION                   ← para referencia de la versión actual
```

> [!TIP]
> Guarda el backup en una carpeta con la fecha y versión actual, p.ej. `backup-v0.1.0-2026-04-28/`.

---

## 📥 Paso 1 — Descargar la nueva versión

1. Ve a la sección [**Releases**](../../releases) del repositorio.
2. Descarga el archivo **`porra-mundial-2026-vX.X.X.zip`** de la nueva versión.
3. Lee las **notas de la release** — pueden incluir instrucciones especiales para esa versión.

---

## 📤 Paso 2 — Subir los archivos por FTP

Conecta con tu cliente FTP y sube los archivos de la nueva versión **sobre la instalación existente**, sobreescribiendo los archivos del repositorio.

### ✅ Qué sobreescribir

| Carpeta / Archivo | Acción | Motivo |
|:-------------------|:------:|:-------|
| 📂 `app/` | ✅ Sobreescribir | Código actualizado |
| 📂 `database/` | ✅ Sobreescribir | Nuevas migraciones SQL |
| 📂 `public/` | ✅ Sobreescribir | Assets y front controller actualizados |
| 📂 `vendor/` | ✅ Sobreescribir | Dependencias actualizadas |
| 📂 `bin/` | ✅ Sobreescribir | Scripts auxiliares |
| 📄 `.htaccess` | ✅ Sobreescribir | Reglas de rewrite actualizadas |
| 📄 `composer.json` | ✅ Sobreescribir | Definición de dependencias |
| 📄 `VERSION` | ✅ Sobreescribir | Número de versión actualizado |

### 🚫 Qué NO sobreescribir

| Carpeta / Archivo | Acción | Motivo |
|:-------------------|:------:|:-------|
| 📄 `config/config.php` | 🚫 **NO tocar** | Tu configuración personalizada — la release no incluye este archivo |
| 📂 `storage/` | 🚫 **NO tocar** | Tus logs, correos, caché y `installed.lock` |

> [!IMPORTANT]
> Las releases **nunca incluyen** `config/config.php` ni el contenido de `storage/`.
> Si subes la release tal cual, estos archivos no se sobreescribirán.

---

## 🚀 Paso 3 — Aplicar las migraciones

Tras subir los archivos, abre cualquier URL de tu sitio en el navegador:

<table>
<tr>
<td width="60" align="center">

### 1️⃣

</td>
<td>

**🔍 Detección automática**

Si hay migraciones pendientes, la aplicación te redirigirá automáticamente a `/install/upgrade`.

</td>
</tr>
<tr>
<td align="center">

### 2️⃣

</td>
<td>

**🔐 Inicio de sesión**

Inicia sesión con tu cuenta de **administrador**. Solo los administradores pueden ejecutar actualizaciones.

</td>
</tr>
<tr>
<td align="center">

### 3️⃣

</td>
<td>

**✅ Confirmar la actualización**

Revisa el resumen de migraciones pendientes y pulsa **Confirmar actualización**.

El sistema:
1. Ejecuta las migraciones SQL pendientes en orden
2. Actualiza el archivo `storage/installed.lock`
3. Te redirige al panel principal

</td>
</tr>
</table>

> [!NOTE]
> Si **no hay migraciones pendientes** (p.ej. una release que sólo corrige bugs en PHP/CSS/JS), el sitio funcionará con normalidad sin necesidad de pasar por la pantalla de upgrade.

---

## ✅ Verificar la actualización

Tras completar el proceso, verifica que todo funciona:

| Comprobación | Cómo |
|:-------------|:-----|
| 🌐 Página principal carga | Abre tu dominio en el navegador |
| 🔐 Login funciona | Inicia sesión con tu cuenta |
| 📋 Panel de admin accesible | Ve al panel de administración |
| 📄 Versión actualizada | Comprueba que el archivo `VERSION` muestra el nuevo número |

> [!TIP]
> Si usas caché del navegador, haz un **hard refresh** (`Ctrl + Shift + R` o `Cmd + Shift + R`) para cargar los assets actualizados.

---

## ⏪ Rollback — Revertir a la versión anterior

Si algo sale mal durante o después de la actualización:

<table>
<tr>
<td width="60" align="center">

### 1️⃣

</td>
<td>

**📁 Restaurar archivos**

Sube por FTP los archivos de la versión anterior (del backup que hiciste), asegurándote de incluir:
- `config/config.php`
- `storage/installed.lock`
- Todo el código de la versión anterior

</td>
</tr>
<tr>
<td align="center">

### 2️⃣

</td>
<td>

**🗄️ Restaurar la base de datos**

Desde phpMyAdmin:
1. Selecciona tu base de datos
2. Ve a la pestaña **Importar**
3. Sube el archivo `.sql` del backup
4. Marca la opción para eliminar tablas existentes si es necesario

</td>
</tr>
<tr>
<td align="center">

### 3️⃣

</td>
<td>

**✅ Verificar**

Abre tu dominio y comprueba que todo funciona con la versión anterior.

</td>
</tr>
</table>

> [!WARNING]
> El rollback de base de datos **reemplaza todos los datos** al estado del backup.
> Cualquier dato introducido después del backup se perderá (usuarios nuevos, apuestas, etc.).

---

## ❓ Preguntas frecuentes

<details>
<summary>🤔 <strong>¿Puedo saltar versiones? (p.ej. de v0.1.0 a v0.3.0)</strong></summary>

**Sí.** Las migraciones SQL son incrementales y se aplican en orden. El sistema detecta cuáles faltan y las ejecuta todas en secuencia, sin importar cuántas versiones hayas saltado.

</details>

<details>
<summary>🤔 <strong>¿Qué pasa si la actualización falla a mitad?</strong></summary>

Las migraciones se ejecutan en orden. Si una falla:
1. Las migraciones anteriores ya estarán aplicadas.
2. La aplicación mostrará un error con la migración que falló.
3. Puedes corregir el problema y volver a intentarlo — el sistema retomará desde donde falló.
4. Si no puedes corregirlo, haz rollback al backup.

</details>

<details>
<summary>🤔 <strong>¿Necesito volver a configurar SMTP, captcha o MFA tras actualizar?</strong></summary>

**No.** Toda la configuración se guarda en `config/config.php` y en la base de datos. Al no tocar estos archivos durante la actualización, tu configuración se conserva intacta.

</details>

<details>
<summary>🤔 <strong>¿Se pierden los datos de los usuarios al actualizar?</strong></summary>

**No.** Las migraciones sólo añaden o modifican la estructura de las tablas. Los datos existentes (usuarios, apuestas, configuración) se conservan.

</details>

<details>
<summary>🤔 <strong>¿Cómo sé qué versión tengo instalada?</strong></summary>

Abre el archivo `VERSION` en la raíz de la instalación. Contiene el número de versión actual (p.ej. `0.1.0`).

</details>

---

<div align="center">

📖 **¿Primera instalación?** → [`INSTALL.md`](INSTALL.md)

🏠 **Volver al README** → [`README.md`](../README.md)

---

_⚽ Porra Mundial 2026 — Hecho con pasión futbolera_

</div>
