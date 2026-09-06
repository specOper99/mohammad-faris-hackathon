#!/bin/sh
set -e

i=0
until mc alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"; do
  i=$((i + 1))
  if [ "$i" -ge 30 ]; then
    echo "minio not reachable after 60s"
    exit 1
  fi
  echo "waiting for minio..."
  sleep 2
done

mc mb -p local/exoplanet-files || true
mc anonymous set none local/exoplanet-files || true
mc cors set local/exoplanet-files /config/cors.xml || echo "cors set skipped (bucket still usable)"
echo "minio ready"
