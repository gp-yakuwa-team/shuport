# shuport

Laravel 13 を使用した、IT学生向けのポートフォリオ＆AIエントリーシート評価支援Webアプリケーションです。

---

## 🛠 開発環境の要件
ローカルでの開発には以下のツールが必要です。事前にインストールしておいてください。
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows / Mac)
- Git

---

## 🚀 ローカル環境のセットアップ手順

リポジトリをクローンしてから、ローカルでアプリが動くようにするまでの手順です。

### 1. リポジトリのクローン
```bash
git clone https://github.com/gp-yakuwa-team/shuport.git
cd shuport
```

### 2. 環境変数の用意
実際のDB接続情報などは `.env.encrypted` で共有しています。復号に必要なキーはチーム内で別途共有しています。
手元で新規に用意する場合は、サンプルをコピーして作成してください。

```bash
cp .env.example .env
```

### 3. 起動 (Laravel Sail)
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

※ 初回などで `vendor` が無い場合は、先に `composer install` が必要です（ホストに PHP / Composer が無い場合は、Sail 公式の Docker コマンドを利用してください）。

### 4. フロントエンドの開発サーバー
```bash
./vendor/bin/sail npm run dev
```

---

## 🌐 本番環境
- ホスティング: Render（`Dockerfile.render` を使用）
- DB: Supabase (PostgreSQL)
- 起動時にマイグレーションを自動実行します

---

## 🔀 開発フロー
- PR の向き先は `develop` を基本とします。
- ブランチ名: `feature/<内容>`、`fix/<内容>`、`chore/<内容>`、`docs/<内容>`
- PR タイトル: Conventional Commits 形式（`feat:` / `fix:` / `chore:` / `docs:` など）＋日本語の要約
- PR 本文: 変更内容と影響範囲を日本語で簡潔に書いてください（`.github/pull_request_template.md` が自動で挿入されます）。
- マージ前に CI（テスト・Pint）が通っていることを確認してください。

---

## 🧪 テスト・コード整形
```bash
php artisan test
./vendor/bin/pint
```
