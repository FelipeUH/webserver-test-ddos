# Servidor web seguro: login + CRUD, firewall anti-DDoS y auditoría con OWASP ZAP

Montaje práctico (Parte I) del curso de comunicaciones 2. El objetivo es desplegar un
servidor web, protegerlo contra ataques de denegación de servicio y evaluarlo con una
herramienta de análisis de vulnerabilidades.

## Contexto

| Elemento | Detalle |
|---|---|
| Hipervisor | Proxmox (`10.18.30.40`) |
| Red de la VM | `10.18.30.x`, bridge `vmbr0v817` |
| Sistema operativo | AlmaLinux 9 |
| Stack | Apache + PHP 8 + MariaDB (base de datos local) |
| Firewall | `iptables` (script `firewall.sh`) |
| Auditoría | [OWASP ZAP](https://www.zaproxy.org/) |
| Pruebas de ataque | `hping3` (SYN flood) y `slowhttptest` (Slowloris) |

### Qué se pide

1. Sitio web con **validación de usuario** y un **formulario CRUD** que grabe en una base de datos local.
2. **Script de cortafuegos** contra DDoS y un ataque de prueba, mostrando `iptables -L -n -v` antes y después (paquetes `DROP`).
3. Escaneo del sitio con **OWASP ZAP**.
4. Análisis de las alertas graves: implicaciones y solución. **Corregir las alertas rojas y naranjas.**
5. Conclusiones y referencias.

## La aplicación

Inventario de equipos de red. Tras iniciar sesión se pueden crear, listar, editar y eliminar equipos
(nombre, tipo, IP, ubicación).

```
.
├── install.sh          # Instala y configura todo (Apache, PHP, MariaDB, app, usuario admin)
├── firewall.sh         # Reglas iptables anti-DDoS
├── schema.sql          # Tablas `usuarios` y `equipos`
├── crear_usuario.php   # Alta de usuarios por consola
└── app/
    ├── config.php      # Credenciales de BD (install.sh reemplaza la clave)
    ├── inc.php         # Sesión, PDO, CSRF, validación y plantilla
    ├── login.php  logout.php
    ├── index.php       # Create + Read
    ├── editar.php      # Update
    ├── eliminar.php    # Delete
    └── style.css
```

Medidas de seguridad de la línea base: consultas preparadas (PDO), contraseñas con
`password_hash`, escape de salida, token CSRF en todos los formularios, cookies de sesión
`HttpOnly` + `SameSite=Strict`, regeneración del ID de sesión al iniciar sesión y usuario de BD con
privilegios mínimos (`SELECT/INSERT/UPDATE/DELETE`).

## Cómo montarlo

### 1. Crear la VM en Proxmox

- ISO de AlmaLinux 9, disco ~40 GB, RAM 2 GB.
- Tarjeta de red conectada al bridge `vmbr0v817`.

### 2. Desplegar el sitio

Con el VPN activo hacia la red `10.18.30.x`:

```bash
scp -r webserver-test-ddos usuario@10.18.30.XX:~
ssh usuario@10.18.30.XX
cd webserver-test-ddos
sudo ./install.sh 'MiClaveAdmin#2026'
```

O, si se tiene instalado git, bajar el repositorio directamente en la VM:

```bash
ssh usuario@10.18.30.XX
git clone https://github.com/FelipeUH/webserver-test-ddos.git
cd webserver-test-ddos
sudo ./install.sh 'MiClaveAdmin#2026'
```

> Cambiar 'MiClaveAdmin#2026' por la clave deseada

`install.sh` instala Apache, PHP y MariaDB, crea la base `crud_app`, genera un usuario de BD con clave
aleatoria, publica la app en `/var/www/html` y crea el usuario `admin`.

Usuarios adicionales:

```bash
sudo php crear_usuario.php otro_usuario 'SuClave'
```

### 3. Aplicar el firewall

```bash
sudo bash firewall.sh
sudo iptables -L -n -v --line-numbers
```

El script desactiva `firewalld` (no debe convivir con `iptables-services`), define política
`DROP` por defecto y abre solo SSH (22) y HTTP (80). Acepta HTTP tras aplicar dos límites:

| Regla | Mitiga | Ataque de prueba |
|---|---|---|
| `connlimit` (máx. 30 conexiones por IP al puerto 80) | Conexiones lentas | `slowhttptest` |
| `hashlimit` (tasa de SYN por IP de origen) | SYN flood | `hping3` |

> Dejar abierta la consola de la VM en Proxmox mientras se aplican las reglas, por si se pierde el SSH.

Comprueba el sitio en `http://10.18.30.XX/`.

### 4. Ataque de prueba y evidencias

**Antes del ataque** (en el servidor):

```bash
sudo iptables -Z                 # contadores a cero
sudo iptables -L -n -v > antes.txt
```

**Ataque** (desde otra máquina):

```bash
sudo hping3 --flood -S -p 80 10.18.30.XX

slowhttptest -c 1000 -H -g -o slowhttp -i 10 -r 200 -t GET \
  -u http://10.18.30.XX/ -x 24 -p 3
```

**Después del ataque** (en el servidor):

```bash
sudo iptables -L -n -v > despues.txt
```

La evidencia de que el firewall funciona está en la primera columna (paquetes) de las reglas
`DROP` de `connlimit` y `hashlimit`, y en el encabezado `Chain INPUT (policy DROP ...)`.

### 5. Escaneo con OWASP ZAP

Realizar el escaneo **antes** del ataque, o esperar un rato entre ambos, para que los límites del firewall no
bloqueen a ZAP (si ocurre, sube el `hashlimit` de `50/sec` a `100/sec`).

1. Instalar ZAP en el PC desde <https://www.zaproxy.org/>.
2. Con el VPN activo, indicar `http://10.18.30.XX/` en *Quick Start*.
3. Ejecutar **Spider** y luego **Active Scan**.
4. Para probar también las páginas protegidas, configurar el contexto de autenticación
   (Context → Authentication) con un usuario válido.
5. Exportar el reporte (Report → Generate Report) y anotar cada alerta con su solución.

### 6. Corrección de alertas rojas y naranjas

Puntos que normalmente aparecen en esta línea base y cómo resolverlos:

| Hallazgo típico | Corrección |
|---|---|
| Sin Content-Security-Policy | `Header always set Content-Security-Policy "default-src 'self'"` en Apache |
| Sin protección anti-clickjacking | `Header always set X-Frame-Options "DENY"` |
| Sin `X-Content-Type-Options` | `Header always set X-Content-Type-Options "nosniff"` |
| Versión del servidor expuesta | `ServerTokens Prod`, `ServerSignature Off` y `expose_php = Off` |
| Tráfico sin cifrar | Habilitar HTTPS con `mod_ssl` (certificado autofirmado) y redirigir 80 → 443 |
| Cookie sin `Secure` | Se activa sola en `inc.php` cuando la conexión es HTTPS |

## Referencias

- OWASP ZAP: <https://www.zaproxy.org/docs/>
- OWASP Top 10: <https://owasp.org/www-project-top-ten/>
- OWASP Cheat Sheet Series: <https://cheatsheetseries.owasp.org/>
- Manual de iptables: <https://man7.org/linux/man-pages/man8/iptables.8.html>
- Módulos `connlimit` y `hashlimit`: <https://man7.org/linux/man-pages/man8/iptables-extensions.8.html>
- Documentación de AlmaLinux: <https://wiki.almalinux.org/>
