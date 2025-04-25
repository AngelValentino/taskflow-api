#!/bin/bash

FLAG_FILE="../maintenance.flag"

case "$1" in
    on)
        if [ -f "$FLAG_FILE" ]; then
            echo "Maintenance mode is already ENABLED"
        else
            touch "$FLAG_FILE"
            echo "Maintenance mode ENABLED"
        fi
        ;;
    off)
        if [ -f "$FLAG_FILE" ]; then
            rm -f "$FLAG_FILE"
            echo "Maintenance mode DISABLED"
        else
            echo "Maintenance mode is already DISABLED"
        fi
        ;;
    status)
        if [ -f "$FLAG_FILE" ]; then
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