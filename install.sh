#!/bin/bash
# Uso (como root, dentro de la carpeta descomprimida):  ./install.sh [clave_admin]
set -e
[ "$EUID" -eq 0 ] || { echo "Ejecutar como root"; exit 1; }

DB_PASS=$(openssl rand -base64 18 | tr -d '/+=')
ADMIN_PASS="${1:-Admin#2026}"

dnf install -y httpd php php-mysqlnd php-pdo mariadb-server
systemctl enable --now mariadb httpd

mysql < schema.sql
mysql -e "CREATE OR REPLACE USER 'app_user'@'localhost' IDENTIFIED BY '$DB_PASS';
          GRANT SELECT,INSERT,UPDATE,DELETE ON crud_app.* TO 'app_user'@'localhost';
          FLUSH PRIVILEGES;"

cp app/* /var/www/html/
sed -i "s/CAMBIAR_CLAVE/$DB_PASS/" /var/www/html/config.php
chown -R apache:apache /var/www/html
chmod 640 /var/www/html/config.php
restorecon -R /var/www/html 2>/dev/null || true

php crear_usuario.php admin "$ADMIN_PASS"
systemctl restart httpd
echo "Listo. Entra a http://$(hostname -I | awk '{print $1}')/  usuario: admin  clave: $ADMIN_PASS"
