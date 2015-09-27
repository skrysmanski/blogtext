#!/bin/sh

SOURCES="admin api js markup style thirdparty blogtext.php error-checking.php license.txt readme.txt upgrade.php util.php"
DEST_DIR="dist"
CSS_FILE="style/blogtext-default.css"

mkdir -p "$DEST_DIR"

rsync --recursive --human-readable --times --delete --exclude=.svn $SOURCES "$DEST_DIR/"

# Install all necessary node modules via package.json
npm install

# Minify CSS file
node_modules/.bin/cleancss -o "$DEST_DIR/$CSS_FILE" "$CSS_FILE"
