#!/bin/bash
set -euo pipefail

# Default document root (can be overridden at build time via APACHE_DOCUMENT_ROOT)
APACHE_DOCUMENT_ROOT=${APACHE_DOCUMENT_ROOT:-/var/www/html/public}

# Allow Render (or other platforms) to set PORT at runtime. Default to 80.
PORT=${PORT:-80}

WWW_ROOT=/var/www/html

echo ">>> docker-entrypoint: using APACHE_DOCUMENT_ROOT='${APACHE_DOCUMENT_ROOT}' PORT=${PORT}"

# Update Apache ports and vhost to listen on the runtime PORT
if [ -f /etc/apache2/ports.conf ]; then
  sed -ri "s/Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf || true
fi

if [ -f /etc/apache2/sites-available/000-default.conf ]; then
  # replace port numbers in vhost
  sed -ri "s/:?[0-9]*>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf || true
  # ensure the vhost DocumentRoot points to APACHE_DOCUMENT_ROOT
  sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf || true
fi

# Ensure apache2.conf references the correct DocumentRoot
if [ -f /etc/apache2/apache2.conf ]; then
  sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf || true
fi

# Ensure ServerName is set globally to suppress AH00558
if [ ! -f /etc/apache2/conf-available/servername.conf ]; then
  echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf || true
  a2enconf servername || true
fi

# If the expected document root doesn't exist, attempt to normalize directory casing
if [ ! -d "${APACHE_DOCUMENT_ROOT}" ]; then
  echo ">>> docker-entrypoint: target '${APACHE_DOCUMENT_ROOT}' does not exist, attempting to find a case-insensitive match under ${WWW_ROOT}"

  # Look for any dir under WWW_ROOT matching 'public' case-insensitively
  match=$(find "${WWW_ROOT}" -maxdepth 1 -type d -iname public -print -quit || true)
  if [ -n "${match}" ]; then
    echo ">>> docker-entrypoint: found case-insensitive match: ${match} -> will move to ${APACHE_DOCUMENT_ROOT}"
    mkdir -p "$(dirname "${APACHE_DOCUMENT_ROOT}")"
    mkdir -p "${APACHE_DOCUMENT_ROOT}"
    # Move contents safely (preserve permissions)
    shopt -s dotglob || true
    if [ -d "${match}" ]; then
      mv "${match}"/* "${APACHE_DOCUMENT_ROOT}" 2>/dev/null || true
      # If match is empty now, remove it
      rmdir "${match}" 2>/dev/null || true
    fi
  else
    echo ">>> docker-entrypoint: no case-insensitive match found. Creating empty ${APACHE_DOCUMENT_ROOT} to avoid DocumentRoot missing error"
    mkdir -p "${APACHE_DOCUMENT_ROOT}"
  fi
fi

# Final ownership fix to ensure apache can read files
chown -R www-data:www-data /var/www/html || true

echo ">>> docker-entrypoint: starting apache (foreground)"
exec "${@:-apache2-foreground}"
