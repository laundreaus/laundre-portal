#!/usr/bin/env bash
# Run inside the unzipped laundre-portal folder to push to GitHub.
set -e
git init
git add .
git commit -m "Laravel 11 foundation: schema, auth, roles, API, seeders"
git branch -M main
git remote add origin https://github.com/laundreaus/laundre-portal.git
git push -u origin main
