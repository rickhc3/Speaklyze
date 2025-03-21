#!/bin/sh

# Inicia o worker da fila
php artisan queue:work --timeout=0 > /proc/1/fd/1 2>&1 &

# Inicia o Reverb WebSocket server
php artisan reverb:start > /proc/1/fd/1 2>&1 &

# Espera por qualquer um dos processos sair
wait -n
