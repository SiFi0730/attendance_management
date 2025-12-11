// ============================================
// アプリケーション設定
// ============================================

// APIベースURLの設定
// 環境変数またはグローバル設定から読み込む
// デフォルトは開発環境のURL
const API_BASE_URL = (function() {
  // 1. window.APP_CONFIGから読み込む（HTMLで設定可能）
  if (window.APP_CONFIG && window.APP_CONFIG.API_BASE_URL) {
    return window.APP_CONFIG.API_BASE_URL;
  }
  
  // 2. 環境変数から読み込む（ビルド時に設定）
  // 本番環境では、ビルド時にこの値を置き換える
  if (typeof process !== 'undefined' && process.env && process.env.API_BASE_URL) {
    return process.env.API_BASE_URL;
  }
  
  // 3. デフォルト値（開発環境）
  // 本番環境では、このファイルをビルド時に置き換えるか、
  // HTMLでwindow.APP_CONFIGを設定する
  return 'http://localhost:8080/api.php';
})();

// 設定をエクスポート（モジュール環境の場合）
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { API_BASE_URL };
}

