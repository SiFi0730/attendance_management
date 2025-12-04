# サーバー起動手順ガイド

## 🚀 PHPサーバーの起動方法

### 方法1: バッチスクリプトを使用（推奨）

1. **コマンドプロンプトまたはPowerShellを開く**

2. **プロジェクトディレクトリに移動**
   ```cmd
   cd C:\Users\shohe\Documents\00_AI\05_attendance_management
   ```

3. **起動スクリプトを実行**
   ```cmd
   start-local-server.bat
   ```

   または、PowerShellの場合：
   ```powershell
   .\start-local-server.bat
   ```

4. **サーバーが起動したら、新しいウィンドウが開きます**
   - サーバーURL: `http://localhost:8080`
   - 停止するには `Ctrl+C` を押してください

### 方法2: 手動で起動

1. **コマンドプロンプトまたはPowerShellを開く**

2. **プロジェクトディレクトリに移動**
   ```cmd
   cd C:\Users\shohe\Documents\00_AI\05_attendance_management
   ```

3. **backend/publicディレクトリに移動**
   ```cmd
   cd backend\public
   ```

4. **PHPサーバーを起動**
   ```cmd
   php -S localhost:8080
   ```

5. **サーバーが起動したら、以下のメッセージが表示されます**
   ```
   PHP 8.2.12 Development Server (http://localhost:8080) started
   ```

### 方法3: バックグラウンドで起動（PowerShell）

```powershell
cd backend\public
Start-Process powershell -ArgumentList "-NoExit", "-Command", "php -S localhost:8080"
```

---

## ✅ サーバー起動確認

### 1. ポート8080が使用されているか確認

```cmd
netstat -an | findstr :8080
```

**期待される出力:**
```
TCP    [::1]:8080    [::]:0    LISTENING
```

### 2. ブラウザでアクセス

以下のURLにアクセスして確認：

- **開発環境確認**: http://localhost:8080/index.php
- **APIエンドポイント**: http://localhost:8080/api.php
- **ログインページ**: http://localhost:8080/index.html
- **会員登録ページ**: http://localhost:8080/pages/register.html

### 3. API動作確認

ブラウザの開発者ツール（F12）のNetworkタブで、APIリクエストが正常に送信されているか確認してください。

---

## 🔧 トラブルシューティング

### 問題1: ポート8080が既に使用されている

**エラーメッセージ:**
```
Address already in use
```

**解決方法:**
1. 別のポートを使用する：
   ```cmd
   php -S localhost:8081
   ```
2. または、使用中のプロセスを終了する：
   ```cmd
   netstat -ano | findstr :8080
   taskkill /PID <プロセスID> /F
   ```

### 問題2: PHPが見つからない

**エラーメッセージ:**
```
'php' は、内部コマンドまたは外部コマンド、操作可能なプログラムまたはバッチ ファイルとして認識されていません。
```

**解決方法:**
1. PHPがインストールされているか確認：
   ```cmd
   php -v
   ```

2. 環境変数PATHにPHPのパスが追加されているか確認：
   - `C:\php` がPATHに含まれている必要があります

3. コマンドプロンプトを再起動

### 問題3: データベース接続エラー

**エラーメッセージ:**
```
データベース接続に失敗しました
```

**解決方法:**
1. PostgreSQLサービスが起動しているか確認：
   ```cmd
   sc query postgresql-x64-18
   ```

2. 起動していない場合：
   ```cmd
   net start postgresql-x64-18
   ```

3. `backend/.env` ファイルの設定を確認

### 問題4: ネットワークエラー（CORS）

**症状:**
- ブラウザで「ネットワークエラーが発生しました」と表示される
- OPTIONSリクエストは成功するが、POSTリクエストが失敗する

**解決方法:**
1. サーバーが正しく起動しているか確認
2. ブラウザの開発者ツール（F12）のConsoleタブでエラーを確認
3. Networkタブでリクエストの詳細を確認

---

## 📝 よくある質問

### Q: サーバーを停止するには？

A: サーバーが起動しているウィンドウで `Ctrl+C` を押してください。

### Q: 複数のサーバーを起動できますか？

A: はい、異なるポートを使用すれば可能です：
```cmd
php -S localhost:8080  # サーバー1
php -S localhost:8081  # サーバー2
```

### Q: サーバーをバックグラウンドで起動したい

A: PowerShellを使用：
```powershell
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd backend\public; php -S localhost:8080"
```

---

## 🔗 関連ドキュメント

- **開発環境セットアップ**: `README_STANDALONE_DEV.md`
- **クイックスタート**: `QUICK_START_STANDALONE.md`
- **APIドキュメント**: `backend/API_DOCUMENTATION.md`

---

最終更新: 2025-01-15

