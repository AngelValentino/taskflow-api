#!/bin/bash

if grep -qEi "(Microsoft|WSL)" /proc/version; then
    # WSL Environment
    LOGS_DIR="/mnt/c/xampp/htdocs/taskflow-api/logs"
else
    # Linux/Production Environment
    LOGS_DIR="/var/www/taskflow-api/logs"
fi

[ ! -d "$LOGS_DIR" ] && mkdir -p "$LOGS_DIR"

> "$LOGS_DIR/errors.log"
> "$LOGS_DIR/audit.log"
> "$LOGS_DIR/cron.log"

echo "[`date '+%Y-%m-%d %H:%M:%S'`] Logs cleared successfully." >> "$LOGS_DIR/cron.log"