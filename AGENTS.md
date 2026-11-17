<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

---

## ShuPort チーム開発ルール（プロジェクト固有）

このセクションは ShuPort チームの開発ルールである。上の Laravel Boost ガイドラインと併せて従うこと。仕様の判断は、本セクションの正本文書を優先する。

### プロジェクト概要

ShuPort は就活支援 Web アプリケーション。ES 作成にかかる時間の短縮を目的とする（要件定義書 v18）。

### 正本文書（仕様の出典）

- 要件定義書 v18
- 基本設計書 v12
- API定義書 v14（確定版）
- DB定義書 v7

仕様はこの4文書が正本である。文書に無い仕様を推測で作らないこと。判断に迷う場合は、コードを書く前に担当者へ確認すること。

### 技術スタック

- Laravel 13 / PHP 8.4
- Blade + Tailwind CSS 4 / Vite 8
- PostgreSQL（Supabase）
- デプロイ：Render（Docker）
- AI：Gemini API（メイン）、Cloudflare Workers AI（サブ）
- LINE Messaging API
- 開発環境：Laravel Sail

### 開発の進め方

- ブランチを切って Pull Request で develop に入れる（保護ブランチへ直接 push しない）
- CI の3チェック（tests / pint / phpstan）をすべて通すこと
- DB の変更は migration で行い、DB定義書 v7 の構造を正とする

### コーディング規約

- 整形は Pint に従う
- 型は Larastan（phpstan）が通る書き方にする
- API の共通仕様（RFC 9457 のエラー形式、他人のリソースは 404、日時は JST）は API定義書 v14 第6章に従う
- 画面遷移・フォームで完結する操作は Web ルート、非同期処理と外部連携は API とする（API定義書の切り分けに従う）

### AI への禁止事項

- 仕様を勝手に変更・追加しない。未確定事項は基本設計書 第14章にある。決まっていないことは実装しない
- DB のテーブル・カラムを、DB定義書 v7 に無い形で作らない
- .env の値や API キーなどの秘密情報を、コード・コメント・出力に含めない
- テストを省略しない。仕様の変更が必要になった場合は、コードより先に文書の改訂を提案する

### 確認先

- 仕様の判断：SV（瀧下・中村）、PM（八鍬）
