#!/bin/sh

SOURCES="admin api js markup style thirdparty blogtext.php error-checking.php license.txt readme.txt upgrade.php util.php"
DEST_DIR="dist"

rm -rf $DEST_DIR

mkdir $DEST_DIR

rsync --recursive --human-readable --times --delete $SOURCES $DEST_DIR/

