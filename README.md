# ShuPort（シュポート）

就活生のエントリーシート（ES）作成にかかる負担を減らすことを目的とした、就活支援Webアプリケーションです。

## 概要

開発中です。現在のアプリケーションには、トップページに開発環境の確認画面があり、データベースへの接続状態を表示します。企業機能はモデルとテーブルの雛形までで、就活支援機能はまだ実装されていません。

## 技術スタック

以下はプロジェクトで採用する技術です。AI機能とLINE連携は現時点では未実装です。

| 分類 | 使用技術 |
| --- | --- |
| バックエンド | PHP 8.4 / Laravel 13 |
| フロントエンド | Blade / Tailwind CSS 4 / Vite 8 |
| データベース | PostgreSQL（Supabase） |
| ホスティング | Render（Docker） |
| AI | Gemini API / Cloudflare Workers AI |
| 外部連携 | LINE Messaging API |

---

## 開発者向け情報

### 開発環境の要件

ローカルでの開発には以下のツールが必要です。

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)（Windows / Mac）
- Git

### ローカル環境のセットアップ手順

#### 1. リポジトリのクローン

```bash
git clone https://github.com/gp-yakuwa-team/shuport.git
cd shuport
```

#### 2. 環境変数の用意

実際のDB接続情報などは `.env.encrypted` で共有しています。復号に必要なキーはチーム内で別途共有しています。
手元で新規に用意する場合は、サンプルをコピーして作成してください。

```bash
cp .env.example .env
```

#### 3. 起動（Laravel Sail）

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

※ 初回などで `vendor` が無い場合は、先に `composer install` が必要です（ホストに PHP / Composer が無い場合は、Sail 公式の Docker コマンドを利用してください）。

#### 4. フロントエンドの開発サーバー

```bash
./vendor/bin/sail npm run dev
```

フロントエンドの本番用ビルドは `./vendor/bin/sail npm run build` で実行できます。

### 本番環境

- ホスティング: Render（`Dockerfile.render` を使用）
- DB / 認証: Supabase（PostgreSQL / Supabase Auth）
- 起動時にマイグレーションを自動実行します

### 開発フロー

- PR の向き先は `develop` を基本とします
- ブランチ名: `feature/<内容>`、`fix/<内容>`、`chore/<内容>`、`docs/<内容>`
- PR タイトル: Conventional Commits 形式（`feat:` / `fix:` / `chore:` / `docs:` など）＋日本語の要約
- PR 本文: 変更内容と影響範囲を日本語で簡潔に書いてください（`.github/pull_request_template.md` が自動で挿入されます）
- マージ条件: CI（テスト・Pint・PHPStan）がグリーンであること

### テスト・コード整形

テストと整形チェックは composer スクリプトに統一しています（Sail の場合は `./vendor/bin/sail` を先頭に付けてください）。

```bash
composer test      # テストの実行
composer lint      # Pint による整形チェック（CI と同じ）
composer lint:fix  # Pint による自動整形
composer analyse   # Larastan (PHPStan) による静的解析（CI と同じ）
```

#### 静的解析（Larastan / PHPStan）

静的解析には Larastan（PHPStan の Laravel 拡張）を導入しています。PR では CI の `phpstan` ジョブが自動で実行されます。

- 設定ファイル: `phpstan.neon`（解析レベルは当面 level 0。開発の進捗に合わせて段階的に上げていく方針です）
- 既存コードへの指摘は `phpstan-baseline.neon` に逃がしています。**新規コードの指摘は baseline に追加せず、コード側を直してください**
- baseline を再生成する場合: `./vendor/bin/phpstan analyse --generate-baseline`（Sail の場合は `./vendor/bin/sail php vendor/bin/phpstan analyse --generate-baseline`）

#### コミット前の自動整形（任意）

ステージした PHP ファイルへ Pint を自動適用する pre-commit フックを用意しています。使う場合は初回のみ、各自の環境で有効化してください。

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit
```

ホストに PHP が無い環境（Sail のみの場合）ではフックは自動でスキップされ、整形の最終確認は CI が行います。

#### その他の開発支援

- 依存パッケージの更新は Dependabot が週次で自動PRを作成します（`dependencies` ラベル）
- `.vscode/` にチーム共通のエディタ設定と推奨拡張機能を置いています


