#!/bin/bash
# データベースのマイグレーションを実行（失敗してもコンテナが落ちないように / デプロイ時に自動適用）
php artisan migrate --force

# Apacheをフォアグラウンドで起動
apache2-foreground
