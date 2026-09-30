#!/bin/bash
# Firewall anti-DDoS con iptables para el servidor web (AlmaLinux).
# Uso: sudo bash firewall.sh
set -e
[ "$EUID" -eq 0 ] || { echo "Ejecutar como root"; exit 1; }

# --- firewalld e iptables no deben convivir ---
if systemctl is-active --quiet firewalld; then
  echo "[*] Desactivando firewalld..."
  systemctl disable --now firewalld
fi
if ! rpm -q iptables-services >/dev/null 2>&1; then
  echo "[*] Instalando iptables-services..."
  dnf install -y iptables-services
fi
systemctl enable iptables

# --- Reglas ---
iptables -F
iptables -X
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT

# Loopback, conexiones ya establecidas y paquetes inválidos
iptables -A INPUT -i lo -j ACCEPT
iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
iptables -A INPUT -m conntrack --ctstate INVALID -j DROP

# SSH primero, para no perder el acceso
iptables -A INPUT -p tcp --dport 22 -j ACCEPT

# Slowloris / slowhttptest: máx. 30 conexiones simultáneas por IP al puerto 80
iptables -A INPUT -p tcp --syn --dport 80 \
  -m connlimit --connlimit-above 30 --connlimit-mask 32 -j DROP

# SYN flood (hping3): tasa máxima de SYN nuevos por IP de origen
# (si ZAP se bloquea durante el escaneo, sube 50/sec a 100/sec)
iptables -A INPUT -p tcp --syn --dport 80 \
  -m hashlimit --hashlimit-name http --hashlimit-mode srcip \
  --hashlimit-above 50/sec --hashlimit-burst 100 -j DROP

# Tráfico HTTP legítimo
iptables -A INPUT -p tcp --dport 80 -j ACCEPT

# ICMP limitado
iptables -A INPUT -p icmp -m limit --limit 5/s -j ACCEPT
iptables -A INPUT -p icmp -j DROP

# Persistir
service iptables save
echo "[OK] Reglas aplicadas. Revisa con: sudo iptables -L -n -v --line-numbers"
