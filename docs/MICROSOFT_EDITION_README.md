# Porra Mundial 2026 - Microsoft Edition

Sistema de porra/quiniela para el Mundial 2026, diseñado específicamente para empleados de Microsoft con el look and feel de Microsoft Fluent Design System.

## 🎨 Características de Diseño

### Microsoft Fluent Design System
- **Paleta de colores oficial de Microsoft**: Azul primario (#0078D4), colores complementarios
- **Efectos modernos**: Hover effects, transiciones suaves, sombras elevadas
- **Responsive Design**: Adaptado para dispositivos móviles y desktop
- **Iconos SVG**: Íconos inline en todo el sistema

### Página de Inicio Rediseñada
La nueva home page incluye:

1. **Tarjetas de estadísticas**
   - Equipos elegidos (X/6)
   - Puntos totales
   - Posición en el ranking

2. **Equipos seleccionados**
   - Visualización de los 6 equipos elegidos con banderas
   - Organización por bombo

3. **Tarjetas de navegación**
   - Selecciones: Elige tus equipos
   - Mis Puntos: Desglose detallado
   - Clasificación: Ranking general
   - Mi Cuenta: Configuración y seguridad
   - **Admin** (solo para administradores): Gestión del torneo

### Clasificación Mejorada
- **Podio visual** para los 3 primeros lugares (🥇🥈🥉)
- **Avatares con iniciales** de los jugadores
- **Barras de altura** representando posiciones
- **Tabla completa** con todos los jugadores y sus equipos
- **Diseño responsive** que se adapta a móviles

## 📧 Sistema de Correo Diario

### Funcionalidad
Envío automático de un resumen diario a todos los usuarios activos que incluye:

- **Estadísticas personales**:
  - Puntos totales actuales
  - Posición en el ranking (con medallas para top 3)
  - Total de jugadores

- **Desglose por equipo**:
  - Puntos por partidos
  - Puntos por avances
  - Puntos por premios individuales

- **Diseño del email**:
  - Microsoft Fluent Design System
  - Responsive para todos los dispositivos
  - Enlaces directos al dashboard

### Configuración

#### 1. Configurar SMTP
Primero, configura el SMTP desde el panel de administración:
- Ve a `/admin/communications/smtp`
- Completa los datos del servidor SMTP
- Prueba el envío con el botón de test

#### 2. Configurar el Cron Job
```bash
# Editar crontab
crontab -e

# Añadir esta línea para envío diario a las 8:00 AM
0 8 * * * cd /ruta/a/porra-mundial-2026 && php bin/send-daily-stats.php >> storage/logs/cron.log 2>&1
```

#### 3. Prueba Manual
Antes de configurar el cron, prueba el envío manualmente:
```bash
cd /ruta/a/porra-mundial-2026
php bin/send-daily-stats.php
```

Para más detalles, consulta [docs/DAILY_EMAIL_SETUP.md](docs/DAILY_EMAIL_SETUP.md)

## 🎯 Sistema de Puntuación

### Resultados de Partidos
- ✅ Victoria: **+3 puntos**
- ➖ Empate: **+1 punto**

### Avances en el Torneo
- Pasar fase de grupos: **+3 puntos**
- Octavos de final: **+3 puntos**
- Cuartos de final: **+4 puntos**
- Semifinales: **+6 puntos**
- Llegar a la final: **+8 puntos**
- 🏆 Campeón: **+12 puntos**

### Bonus
- 3+ goles en un partido: **+2 puntos**
- Portería a cero: **+1 punto**
- Remontada: **+1 punto**

### Penalizaciones
- 🟨 Tarjeta amarilla: **−0.2 puntos**
- 🟨🟨 Doble amarilla: **−1 punto**
- 🟥 Tarjeta roja: **−2 puntos**
- Recibir 3+ goles: **−2 puntos**
- Último del grupo: **−1 punto**

### Premios Individuales
- MVP del torneo: **+3 puntos**
- Bota de Oro (goleador): **+2 puntos**
- Guante de Oro (mejor portero): **+2 puntos**
- Mejor jugador joven: **+2 puntos**

## 🚀 Instalación y Actualización

### Requisitos
- PHP 8.1+
- MySQL/MariaDB
- Servidor web (Apache/Nginx)
- Acceso a cron jobs (para emails automáticos)

### Primera Instalación
1. Clona el repositorio
2. Ejecuta `composer install` (si aplica)
3. Configura la base de datos en el instalador web
4. Accede a `/install` y sigue los pasos

### Actualización desde Versión Anterior
Si ya tienes una versión anterior instalada:

1. Haz backup de tu base de datos
2. Actualiza los archivos del proyecto
3. Visita `/install/upgrade`
4. Configura el SMTP si aún no lo has hecho
5. Opcionalmente, configura el cron job para emails diarios

## 📁 Estructura del Proyecto

```
porra-mundial-2026/
├── app/
│   ├── Core/
│   │   ├── DailyStatsEmail.php    # Servicio de emails diarios
│   │   └── ...
│   ├── Models/
│   │   ├── Score.php               # Motor de puntuación
│   │   ├── Team.php                # Equipos
│   │   ├── User.php                # Usuarios
│   │   └── ...
│   └── Modules/
│       ├── Game/                   # Vistas del juego
│       │   ├── GameController.php
│       │   └── views/
│       │       ├── game/home.php           # Home rediseñada
│       │       ├── game/leaderboard.php    # Clasificación con podio
│       │       └── ...
│       └── Admin/                  # Panel de administración
├── bin/
│   └── send-daily-stats.php       # Script cron para emails
├── docs/
│   └── DAILY_EMAIL_SETUP.md       # Documentación de emails
├── public/
│   └── assets/
│       └── css/
│           └── app.css             # Estilos Microsoft Fluent Design
└── storage/
    └── logs/
        ├── cron.log                # Logs del cron job
        └── mail.log                # Logs de emails
```

## 🔧 Administración

### Panel de Admin
Los usuarios con rol `admin` o `account_manager` tienen acceso a:

- **Usuarios**: Invitar, editar, gestionar usuarios
- **Partidos**: Registrar resultados y estadísticas
- **Avances**: Marcar progreso de equipos en el torneo
- **Premios**: Asignar premios individuales
- **Comunicaciones**: Configurar SMTP
- **Seguridad**: Configurar opciones de seguridad

### Navegación Rápida para Admins
En la home page, los administradores ven una tarjeta adicional de "Administración" para acceso rápido.

## 📱 Características Responsive

El diseño se adapta perfectamente a:
- 📱 **Móviles**: Diseño vertical, menú hamburguesa
- 💻 **Tablets**: Grid adaptativo de 2 columnas
- 🖥️ **Desktop**: Grid completo de 3-4 columnas

## 🎨 Paleta de Colores Microsoft

- **Primary**: `#0078D4` (Microsoft Blue)
- **Success**: `#107C10` (Microsoft Green)
- **Warning**: `#FFB900` (Microsoft Yellow)
- **Danger**: `#D13438` (Microsoft Red)
- **Purple**: `#5C2D91` (Microsoft Purple)
- **Orange**: `#CA5010` (Microsoft Orange)

## 📞 Soporte

Para soporte técnico o dudas sobre la aplicación:
1. Revisa la documentación en `/docs`
2. Consulta los logs en `/storage/logs`
3. Contacta al administrador del sistema

---

**Desarrollado con ❤️ usando Microsoft Fluent Design System**
