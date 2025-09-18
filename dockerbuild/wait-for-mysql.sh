#!/bin/bash

set -e

echo "Waiting for mysql"
TIMEOUT=${MYSQL_TIMEOUT:-300}  # 5分でタイムアウト
ELAPSED=0

until mysql -h "${DB_SERVER}" --password="${DB_PASSWORD}" -u"${DB_USER}" -e "SELECT 1;" "${DB_NAME}" &> /dev/null
do
  printf "."
  sleep 1
  ELAPSED=$((ELAPSED + 1))

  # Show progress every 30 seconds
  if [ $((ELAPSED % 30)) -eq 0 ]; then
    echo ""
    echo "Still waiting for MySQL... (${ELAPSED}s elapsed)"
    # Test basic connectivity
    mysqladmin ping -h "${DB_SERVER}" &> /dev/null && echo "MySQL server is responding to ping" || echo "MySQL server not responding to ping"
  fi

  if [ $ELAPSED -ge $TIMEOUT ]; then
    echo ""
    echo "ERROR: MySQL connection timeout after ${TIMEOUT} seconds"
    echo "Connection details:"
    echo "  Host: ${DB_SERVER}"
    echo "  User: ${DB_USER}"
    echo "  Database: ${DB_NAME}"
    echo "  Password length: ${#DB_PASSWORD}"

    # Comprehensive connectivity tests
    echo ""
    echo "=== Debugging MySQL connectivity ==="
    echo "Testing MySQL server connectivity..."
    mysqladmin ping -h "${DB_SERVER}" || echo "MySQL server ping failed"

    echo "Testing root connection..."
    mysql -h "${DB_SERVER}" --password="${DB_PASSWORD}" -uroot -e "SELECT 1;" &> /dev/null && echo "Root connection OK" || echo "Root connection failed"

    echo "Testing without database name..."
    mysql -h "${DB_SERVER}" --password="${DB_PASSWORD}" -u"${DB_USER}" -e "SELECT 1;" &> /dev/null && echo "User connection without DB OK" || echo "User connection without DB failed"

    echo "Showing MySQL error logs..."
    mysql -h "${DB_SERVER}" --password="${DB_PASSWORD}" -u"${DB_USER}" -e "SELECT 1;" "${DB_NAME}" 2>&1 || true

    exit 1
  fi
done

>&2 echo "MySQL Ready"

if [ ! -f /var/www/app/data/config/config.php ]
then
    echo "Install to ec-cube"
    DBUSER=$DB_USER DBPASS=$DB_PASSWORD DBNAME=$DB_NAME DBPORT=$DB_PORT DBSERVER=$DB_SERVER /var/www/app/eccube_install.sh mysql
fi

exec docker-php-entrypoint "$@"
