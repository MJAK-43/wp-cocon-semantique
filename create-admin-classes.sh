#!/bin/bash

# Répertoire de base
BASE_DIR="includes/admin"

# Crée le répertoire s'il n'existe pas
mkdir -p "$BASE_DIR"

# Liste des fichiers à créer
FILES=(
  "CSB_Admin_Controller.php"
  "AdminRenderer.php"
  "NodeProcessor.php"
  "StructureManager.php"
  "PromptContextFactory.php"
)

# Création des fichiers avec des templates de base
for FILE in "${FILES[@]}"; do
  FILE_PATH="$BASE_DIR/$FILE"
  touch "$FILE_PATH"
  CLASS_NAME=$(basename "$FILE" .php)
  echo "<?php

if (!defined('ABSPATH')) exit;

class $CLASS_NAME {
    // TODO: Implémenter la classe $CLASS_NAME
}
" > "$FILE_PATH"
done

echo "✅ Fichiers créés dans $BASE_DIR :"
for FILE in "${FILES[@]}"; do
  echo " - $FILE"
done
