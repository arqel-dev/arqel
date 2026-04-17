#!/usr/bin/env bash
# Arqel — Mostra ticket ativo corrente
# Uso: ./scripts/next-ticket.sh

set -euo pipefail

CURRENT_FILE="docs/tickets/current.md"

if [[ ! -f "$CURRENT_FILE" ]]; then
    echo "Error: $CURRENT_FILE não encontrado."
    exit 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  TICKET ATIVO"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Extrai título do ticket corrente
TICKET_LINE=$(grep "^**\[" "$CURRENT_FILE" | head -1 || true)
if [[ -z "$TICKET_LINE" ]]; then
    TICKET_LINE=$(grep "^\*\*\[" "$CURRENT_FILE" | head -1 || true)
fi

if [[ -n "$TICKET_LINE" ]]; then
    echo "  $TICKET_LINE"
else
    # Fallback: mostra seção "Ticket corrente"
    awk '/## 🎯 Ticket corrente/,/^## /' "$CURRENT_FILE" | head -20
fi

echo ""
echo "Ver ticket completo:"
echo "  cat $CURRENT_FILE"
echo ""
echo "Ver planejamento da fase 1:"
echo "  cat PLANNING/08-fase-1-mvp.md"
echo ""
