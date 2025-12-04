# 勤怠管理システム

SaaS形式の勤怠管理ウェブアプリケーション

## 概要

マルチテナント対応の勤怠打刻、集計、可視化、CSV入出力、企業・従業員管理機能を提供するシステムです。

## 機能

- 出勤/退勤/休憩の打刻、修正申請、承認ワークフロー
- 企業側の従業員登録・部署/雇用区分管理
- 営業時間/就業ルールの登録・適用（フレックス/シフト/固定など）
- 勤怠のガントチャート可視化（日/週/月・個人/チーム）
- CSVインポート/エクスポート
- 社労士の招待/権限付与

## 技術スタック

- **フロントエンド**: HTML, CSS, JavaScript
- **バックエンド**: PHP 8.2
- **データベース**: PostgreSQL 18
- **依存関係管理**: Composer

## セットアップ

### 前提条件

- Windows 10/11
- 管理者権限
- インターネット接続

### 必要なソフトウェア

1. **PHP 8.2**
   - https://windows.php.net/download/
   - PHP 8.2 Thread Safe (TS) x64 ZIP形式をダウンロード

2. **PostgreSQL 18**
   - https://www.postgresql.org/download/windows/
   - PostgreSQL 18をダウンロード・インストール

3. **Composer**
   - https://getcomposer.org/download/
   - Composer-Setup.exeをダウンロード・インストール

### クイックスタート

詳細な手順は `QUICK_START_STANDALONE.md` を参照してください。

1. **自動セットアップスクリプトの実行**
   ```cmd
   setup-standalone-dev.bat
   ```

2. **データベースのセットアップ**
   ```cmd
   database\setup-database.bat
   ```

3. **開発サーバーの起動**
   ```cmd
   start-local-server.bat
   ```

4. **ブラウザでアクセス**
   - http://localhost:8080

### 詳細なセットアップ手順

詳細なセットアップ手順は `README_STANDALONE_DEV.md` を参照してください。

## プロジェクト構成

```
.
├── backend/              # バックエンド（PHP）
│   ├── public/          # Webサーバーのドキュメントルート
│   ├── src/             # アプリケーションコード
│   └── composer.json   # PHP依存関係
├── database/            # データベース関連
│   ├── schema.sql      # スキーマ定義
│   ├── rls_policies.sql # RLSポリシー
│   └── seed_data.sql   # シードデータ
├── doc/                 # ドキュメント
│   ├── 仕様書.md       # システム仕様書
│   └── 実装手順書.md   # 実装手順書
├── pages/               # フロントエンドページ
├── styles/              # CSS
└── scripts/             # JavaScript
```

## ドキュメント

- **仕様書**: `doc/仕様書.md`
- **実装手順書**: `doc/実装手順書.md`
- **開発環境セットアップ**: `README_STANDALONE_DEV.md`
- **クイックスタート**: `QUICK_START_STANDALONE.md`
- **データベース設計**: `database/README.md`

## ライセンス

（ライセンス情報をここに記載）

---

最終更新: 2025-01-15
