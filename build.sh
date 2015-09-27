#!/bin/sh

SOURCES="admin api js markup style thirdparty blogtext.php error-checking.php license.txt readme.txt upgrade.php util.php"
DEST_DIR="dist"
CSS_FILE="style/blogtext-default.css"

rm -rf "$DEST_DIR"

mkdir "$DEST_DIR"

rsync --recursive --human-readable --times --delete $SOURCES "$DEST_DIR/"

# Install all necessary node modules via package.json
npm install

# Minify CSS file
node_modules/.bin/cleancss -o "$DEST_DIR/$CSS_FILE" "$CSS_FILE"
