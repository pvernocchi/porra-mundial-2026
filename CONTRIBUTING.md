<div align="center">

# 🤝 Guía de contribución

### ¡Gracias por tu interés en contribuir a **Porra Mundial 2026**! ⚽

</div>

---

Hay varias formas de participar en este proyecto. No necesitas ser programador para ayudar: reportar errores, sugerir mejoras o simplemente compartir ideas ya es una contribución valiosa.

## 📋 Tabla de contenidos

- [📌 Issues](#-issues)
- [🔀 Pull Requests](#-pull-requests)
- [💬 Discussions](#-discussions)
- [📜 Código de conducta](#-código-de-conducta)

---

## 📌 Issues

Las **Issues** son la forma principal de reportar errores, solicitar funcionalidades o señalar problemas en la documentación.

### 🐛 Reportar un error

1. Ve a la pestaña [**Issues**](../../issues) del repositorio.
2. Pulsa **New issue**.
3. Incluye en tu reporte:
   - **Título claro y descriptivo** del problema.
   - **Pasos para reproducir** el error (paso a paso).
   - **Comportamiento esperado** vs. **comportamiento actual**.
   - **Capturas de pantalla** si aplica.
   - **Entorno**: versión de PHP, MySQL/MariaDB, navegador, sistema operativo.
4. Pulsa **Submit new issue**.

### 💡 Sugerir una funcionalidad

1. Abre una nueva [issue](../../issues/new) con el prefijo `[Feature]` en el título.
2. Describe la funcionalidad que te gustaría ver.
3. Explica **por qué** sería útil y **cómo** la imaginas.
4. Si es posible, incluye ejemplos o referencias.

### 📝 Buenas prácticas para Issues

- 🔍 **Busca antes de crear**: revisa las [issues existentes](../../issues) para evitar duplicados.
- 🏷️ Usa etiquetas si están disponibles (`bug`, `enhancement`, `question`, etc.).
- 💬 Sé respetuoso y constructivo en tus comentarios.
- ✅ Si una issue se resuelve, confirma que la solución funciona.

---

## 🔀 Pull Requests

Los **Pull Requests (PRs)** son la forma de proponer cambios de código al proyecto. A continuación se detalla el proceso paso a paso.

### 🛠️ Preparación del entorno

1. **Haz un fork** del repositorio pulsando el botón **Fork** en la esquina superior derecha.
2. **Clona tu fork** en tu máquina local:
   ```bash
   git clone https://github.com/TU_USUARIO/porra-mundial-2026.git
   cd porra-mundial-2026
   ```
3. **Añade el repositorio original como remoto** (para mantener tu fork sincronizado):
   ```bash
   git remote add upstream https://github.com/pvernocchi/porra-mundial-2026.git
   ```
4. **Instala las dependencias** con Composer:
   ```bash
   composer install
   ```

### 📝 Flujo de trabajo para un PR

1. **Sincroniza tu fork** con la rama principal:
   ```bash
   git fetch upstream
   git checkout main
   git merge upstream/main
   ```

2. **Crea una rama nueva** para tu cambio:
   ```bash
   git checkout -b mi-cambio
   ```
   Usa nombres descriptivos, por ejemplo:
   - `fix/error-login-csrf`
   - `feature/exportar-resultados`
   - `docs/mejorar-instalacion`

3. **Realiza tus cambios** siguiendo las convenciones del proyecto:
   - PHP 8.1+ con tipado estricto cuando sea posible.
   - Sigue la estructura de directorios existente.
   - Añade o actualiza tests si tu cambio afecta a la lógica.

4. **Ejecuta los tests** para comprobar que todo funciona:
   ```bash
   vendor/bin/phpunit
   ```

5. **Haz commit** de tus cambios con un mensaje claro:
   ```bash
   git add .
   git commit -m "Descripción breve y clara del cambio"
   ```
   Ejemplos de buenos mensajes de commit:
   - `Fix: corregir validación CSRF en formulario de login`
   - `Feature: añadir exportación de resultados a CSV`
   - `Docs: actualizar guía de instalación para MariaDB 11`

6. **Sube tu rama** a tu fork:
   ```bash
   git push origin mi-cambio
   ```

7. **Abre un Pull Request**:
   - Ve a tu fork en GitHub.
   - Pulsa **Compare & pull request**.
   - Rellena la descripción del PR:
     - **¿Qué cambia?** — resumen de los cambios realizados.
     - **¿Por qué?** — contexto y motivación.
     - **¿Cómo probarlo?** — pasos para verificar el cambio.
     - Referencia la issue relacionada si existe (por ejemplo: `Closes #42`).
   - Pulsa **Create pull request**.

### ✅ Checklist antes de enviar tu PR

- [ ] He probado mis cambios localmente.
- [ ] Los tests existentes siguen pasando (`vendor/bin/phpunit`).
- [ ] He añadido tests para la nueva funcionalidad (si aplica).
- [ ] Mi código sigue la estructura y estilo del proyecto.
- [ ] He actualizado la documentación si es necesario.
- [ ] Mi rama está actualizada con `main`.

### 🔄 Proceso de revisión

- Un mantenedor revisará tu PR y podrá solicitar cambios.
- Responde a los comentarios y aplica las correcciones necesarias.
- Una vez aprobado, el PR se fusionará a `main`.
- Sé paciente: las revisiones pueden tomar unos días.

---

## 💬 Discussions

Las [**Discussions**](../../discussions) son el espacio ideal para conversaciones abiertas que no encajan como issues ni como PRs.

### ¿Cuándo usar Discussions?

| Usa Discussions para… | Usa Issues para… |
|------------------------|-------------------|
| 💭 Ideas y propuestas generales | 🐛 Bugs concretos y reproducibles |
| ❓ Preguntas sobre uso o configuración | 💡 Peticiones de funcionalidad específicas |
| 🗳️ Encuestas y opiniones de la comunidad | 📝 Tareas o mejoras con alcance definido |
| 📢 Anuncios y novedades | 🔧 Problemas técnicos concretos |

### 🚀 Cómo participar

1. Ve a la pestaña [**Discussions**](../../discussions) del repositorio.
2. Busca si ya existe una discusión sobre tu tema.
3. Si no existe, pulsa **New discussion** y selecciona la categoría adecuada.
4. Escribe tu mensaje con el mayor detalle posible.
5. Participa en las discusiones existentes con comentarios constructivos.

---

## 📜 Código de conducta

- Sé respetuoso y amable con todos los participantes.
- Acepta las críticas constructivas con apertura.
- Céntrate en lo que es mejor para la comunidad y el proyecto.
- No se tolerará acoso, lenguaje ofensivo ni comportamiento disruptivo.

---

<div align="center">

**⚽ ¡Toda contribución cuenta — gracias por hacer mejor este proyecto! ⚽**

</div>
