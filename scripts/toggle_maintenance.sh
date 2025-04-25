#!/bin/bash

# Check if running in a WSL environment
if grep -qEi "(Microsoft|WSL)" /proc/version; then
    # WSL Environment
    MAINTENANCE_FLAG_FILE="/mnt/c/xampp/htdocs/taskflow-api/maintenance.flag"
else
    # Linux/Production Environment
    MAINTENANCE_FLAG_FILE="/var/www/taskflow-api/maintenance.flag"
fi

case "$1" in
    on)
        if [ -f "$MAINTENANCE_FLAG_FILE" ]; then
            echo "Maintenance mode is already ENABLED"
        else
            touch "$MAINTENANCE_FLAG_FILE"
            echo "Maintenance mode ENABLED"
        fi
        ;;
    off)
        if [ -f "$MAINTENANCE_FLAG_FILE" ]; then
            rm -f "$MAINTENANCE_FLAG_FILE"
            echo "Maintenance mode DISABLED"
        else
            echo "Maintenance mode is already DISABLED"
        fi
        ;;
    status)
        if [ -f "$MAINTENANCE_FLAG_FILE" ]; then
            echo "Maintenance mode is ENABLED"
        else
            echo "Maintenance mode is DISABLED"
        fi
        ;;
    *)
        echo "Usage: $0 [on|off|status]"
        exit 1
        ;;
esac