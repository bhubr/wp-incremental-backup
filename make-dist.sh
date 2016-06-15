#!/bin/sh
mkdir -p wp-incremental-backup/vendor
mv wp-incremental-backup.zip wp-incremental-backup.zip.bak
cp -R class-t1z-* common composer.* download-script.php forms tasks trait-t1z-walker-common.php wp-incremental-backup.php wp-incremental-backup/
cp -R vendor/autoload.php wp-incremental-backup/vendor/
cp -R vendor/composer wp-incremental-backup/vendor/
cp -R vendor/ifsnop wp-incremental-backup/vendor/
cp -R vendor/diversen wp-incremental-backup/vendor/
zip -r wp-incremental-backup.zip wp-incremental-backup/
rm -r dist/wp-incremental-backup
mv wp-incremental-backup dist/
