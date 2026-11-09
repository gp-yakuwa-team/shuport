<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>shuport - Local Dev Check</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        h1 { margin-top: 0; font-size: 1.8rem; color: #0f172a; }
        p { color: #64748b; margin-bottom: 1.5rem; }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .success { background-color: #dcfce7; color: #166534; }
        .danger { background-color: #fee2e2; color: #991b1b; }
        .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .dot-success { background-color: #22c55e; }
        .dot-danger { background-color: #ef4444; }
        .info { margin-top: 1.5rem; font-size: 0.85rem; color: #94a3b8; word-break: break-all; }
    </style>
</head>
<body>
    <div class="card">
        <h1>shuport 開発環境</h1>
        <p>ローカル環境の動作確認画面です</p>

        @if($dbConnected)
            <div class="status success">
                <span class="dot dot-success"></span>
                Supabase DB 接続完了
            </div>
            <div class="info">Database: <code>{{ $dbName }}</code></div>
        @else
            <div class="status danger">
                <span class="dot dot-danger"></span>
                DB 接続エラー
            </div>
            <div class="info">.env の DB 設定を確認してください</div>
        @endif
    </div>
</body>
</html>