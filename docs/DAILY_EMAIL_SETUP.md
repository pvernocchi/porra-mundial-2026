# Configuración del Correo Diario

Este documento explica cómo configurar el envío automático de correos diarios con el resumen de estadísticas para todos los usuarios.

## Características

El sistema de correo diario envía a cada usuario:
- **Puntos totales** actuales
- **Posición en el ranking** (con medallas para top 3)
- **Desglose detallado por equipo**:
  - Puntos por partidos
  - Puntos por avances en el torneo
  - Puntos por premios individuales
- **Enlaces directos** al dashboard, clasificación y puntos

## Requisitos Previos

1. **SMTP configurado**: Asegúrate de tener el SMTP configurado en `/admin/communications/smtp`
2. **PHP CLI**: El servidor debe tener PHP instalado en la línea de comandos
3. **Cron**: Acceso a cron jobs en el servidor

## Configuración del Cron Job

### Paso 1: Verificar la instalación

Primero, prueba manualmente el script para asegurarte de que funciona:

```bash
cd /ruta/a/porra-mundial-2026
php bin/send-daily-stats.php
```

Deberías ver algo como:
```
[2026-04-28 08:00:00] Starting daily stats email job...
[SUCCESS] Sent 25 emails successfully.
[2026-04-28 08:00:15] Daily stats email job completed.
```

### Paso 2: Configurar el Cron Job

Edita tu crontab:

```bash
crontab -e
```

Añade una de estas líneas según tus necesidades:

#### Opción 1: Diariamente a las 8:00 AM (recomendado)
```cron
0 8 * * * cd /ruta/a/porra-mundial-2026 && php bin/send-daily-stats.php >> storage/logs/cron.log 2>&1
```

#### Opción 2: Diariamente a las 6:00 PM
```cron
0 18 * * * cd /ruta/a/porra-mundial-2026 && php bin/send-daily-stats.php >> storage/logs/cron.log 2>&1
```

#### Opción 3: Dos veces al día (8:00 AM y 8:00 PM)
```cron
0 8,20 * * * cd /ruta/a/porra-mundial-2026 && php bin/send-daily-stats.php >> storage/logs/cron.log 2>&1
```

**Nota importante:** Reemplaza `/ruta/a/porra-mundial-2026` con la ruta real donde está instalada tu aplicación.

### Paso 3: Verificar los logs

Después de que el cron se ejecute, revisa los logs:

```bash
tail -f storage/logs/cron.log
```

## Personalización

### Cambiar la hora de envío

Modifica el número de hora en la configuración del cron. La sintaxis es:
```
minuto hora día mes día_semana comando
```

Ejemplos:
- `0 7 * * *` - A las 7:00 AM todos los días
- `30 9 * * *` - A las 9:30 AM todos los días
- `0 12 * * 1-5` - A las 12:00 PM de lunes a viernes

### Enviar solo en días laborables

Si quieres enviar correos solo de lunes a viernes:

```cron
0 8 * * 1-5 cd /ruta/a/porra-mundial-2026 && php bin/send-daily-stats.php >> storage/logs/cron.log 2>&1
```

### Desactivar temporalmente

Para desactivar temporalmente el cron sin eliminarlo, añade un `#` al principio de la línea:

```cron
# 0 8 * * * cd /ruta/a/porra-mundial-2026 && php bin/send-daily-stats.php >> storage/logs/cron.log 2>&1
```

## Solución de Problemas

### Los correos no se envían

1. Verifica que el SMTP esté configurado correctamente
2. Revisa los logs en `storage/logs/cron.log`
3. Prueba el script manualmente: `php bin/send-daily-stats.php`
4. Verifica que el usuario del cron tenga permisos para ejecutar PHP y acceder a los archivos

### Errores de permisos

Si ves errores de permisos en los logs, asegúrate de que:

```bash
chmod +x bin/send-daily-stats.php
chmod -R 755 storage/logs
```

### Algunos usuarios no reciben correos

1. Verifica que los usuarios tengan el status `active` en la base de datos
2. Verifica que las direcciones de email sean válidas
3. Revisa la carpeta de spam de los usuarios
4. Consulta los logs de mail en `storage/logs/mail.log`

## Comandos Útiles

### Ver el estado del cron
```bash
crontab -l
```

### Ver logs en tiempo real
```bash
tail -f storage/logs/cron.log
```

### Probar el envío manual
```bash
php bin/send-daily-stats.php
```

### Ver logs de correo
```bash
tail -f storage/logs/mail.log
```

## Notas Adicionales

- El script envía correos a **todos los usuarios activos**
- Los correos se envían de forma secuencial (no en paralelo)
- Si hay muchos usuarios, el proceso puede tardar varios minutos
- Los usuarios con status `disabled` o `deleted` NO reciben correos
- El diseño del correo sigue el Microsoft Fluent Design System
