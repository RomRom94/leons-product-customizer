#!/usr/bin/env bash
# Déploie le plugin sur le STAGING OVH (jamais la production).
#
# Usage : bash scripts/deploy-staging.sh            # aperçu, confirmation, envoi
#         bash scripts/deploy-staging.sh --dry-run  # aperçu seulement
#         bash scripts/deploy-staging.sh --yes      # sans confirmation (CI)
#
# Configuration : .deploy.env (non versionné, modèle : .deploy.env.example)
#   DEPLOY_HOST, DEPLOY_USER, DEPLOY_PATH, DEPLOY_KEY
set -euo pipefail
cd "$(dirname "$0")/.."

DRY=0; YES=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY=1 ;;
    --yes) YES=1 ;;
    *) echo "Option inconnue : $arg" >&2; exit 2 ;;
  esac
done

[ -f .deploy.env ] && . ./.deploy.env
: "${DEPLOY_HOST:?DEPLOY_HOST manquant (voir .deploy.env.example)}"
: "${DEPLOY_USER:?DEPLOY_USER manquant}"
: "${DEPLOY_PATH:?DEPLOY_PATH manquant}"
DEPLOY_KEY="${DEPLOY_KEY:-$HOME/.ssh/ovh_deploy}"

# ── Garde-fou : uniquement le dossier du plugin sur le staging ──────────────────
case "${DEPLOY_PATH%/}" in
  www/staging/wp-content/plugins/leons-product-customizer) ;;
  *) echo "REFUS : DEPLOY_PATH doit être www/staging/wp-content/plugins/leons-product-customizer (reçu : $DEPLOY_PATH)" >&2; exit 1 ;;
esac
DEPLOY_PATH="${DEPLOY_PATH%/}/"

SSH="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o BatchMode=yes"
DEST="$DEPLOY_USER@$DEPLOY_HOST:$DEPLOY_PATH"

# Ce qui ne part jamais (et n'est jamais supprimé côté serveur)
RSYNC=(rsync -rlc --omit-dir-times --chmod=D755,F644 --delete --itemize-changes
  --exclude '.git' --exclude '.github' --exclude '.vscode' --exclude '.idea'
  --exclude '.deploy.env*' --exclude 'scripts/' --exclude '.DS_Store'
  --exclude '*Zone.Identifier'
  -e "$SSH")

echo "→ Destination : $DEST"
"${RSYNC[@]}" --dry-run ./ "$DEST" | grep -vE '^\.d' || true
[ "$DRY" = 1 ] && { echo "(aperçu seulement, rien n'a été envoyé)"; exit 0; }

if [ "$YES" != 1 ]; then
  read -r -p "Envoyer sur le STAGING ? [o/N] " ans
  [[ "$ans" =~ ^[oOyY]$ ]] || { echo "Annulé."; exit 1; }
fi
"${RSYNC[@]}" ./ "$DEST" | grep -vE '^\.d' || true
echo "✓ Plugin à jour sur le staging."
