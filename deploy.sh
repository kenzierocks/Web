#!/bin/bash

echo "** Pulling changes..."
git pull

echo "** Assigning directory permissions..."
chmod -R 777 app/cache/dev

echo "** Checking for dependency changes"
git --no-pager log -1 HEAD --name-only | grep "composer.lock"
if [ $? -ne 1 ]; then
    echo "** Found composer lock change, performing install"
    composer install --no-scripts 2>&1
fi

echo "** Dumping asset files..."
php app/console assetic:dump

echo "** Clearing cache..."
php app/console cache:clear

echo "** Updating docs submodule"
git submodule init docs
cd docs/
git pull
