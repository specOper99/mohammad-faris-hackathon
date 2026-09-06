#!/bin/sh
set -e
mc alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"
mc mb -p local/exoplanet-files || true
mc anonymous set none local/exoplanet-files || true
mc cors set /config/cors.json local/exoplanet-files || true
echo "minio ready"
