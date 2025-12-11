// ============================================
// 共通JavaScript - 勤怠管理システム
// ============================================

// ============================================
// ログキャプチャ機能
// ============================================
// 環境変数またはグローバル設定から読み込む
const LOG_CAPTURE_ENABLED = (function() {
  if (window.APP_CONFIG && window.APP_CONFIG.LOG_CAPTURE_ENABLED !== undefined) {
    return window.APP_CONFIG.LOG_CAPTURE_ENABLED;
  }
  // デフォルトは開発環境で有効、本番環境では無効
  return (window.APP_CONFIG?.APP_ENV || 'development') !== 'production';
})();

const LOG_LEVEL = (function() {
  if (window.APP_CONFIG && window.APP_CONFIG.LOG_LEVEL) {
    return window.APP_CONFIG.LOG_LEVEL;
  }
  // デフォルトは開発環境でdebug、本番環境ではerror
  return (window.APP_CONFIG?.APP_ENV || 'development') === 'production' ? 'error' : 'debug';
})();

const LOG_STORAGE_KEY = 'attendance_logs';
const MAX_LOG_ENTRIES = 1000; // 最大ログ数

let logEntries = [];

// ログキャプチャを初期化
function initLogCapture() {
  if (!LOG_CAPTURE_ENABLED) return;
  
  // 既存のログを読み込み
  try {
    const stored = localStorage.getItem(LOG_STORAGE_KEY);
    if (stored) {
      logEntries = JSON.parse(stored);
    }
  } catch (e) {
    console.warn('ログの読み込みに失敗しました:', e);
  }
  
  // コンソールメソッドをオーバーライド
  const originalLog = console.log;
  const originalError = console.error;
  const originalWarn = console.warn;
  const originalInfo = console.info;
  const originalDebug = console.debug;
  
  function captureLog(level, args) {
    const timestamp = new Date().toISOString();
    const message = args.map(arg => {
      if (typeof arg === 'object') {
        try {
          return JSON.stringify(arg, null, 2);
        } catch (e) {
          return String(arg);
        }
      }
      return String(arg);
    }).join(' ');
    
    const logEntry = {
      timestamp,
      level,
      message,
      url: window.location.href,
      userAgent: navigator.userAgent
    };
    
    logEntries.push(logEntry);
    
    // 最大ログ数を超えた場合は古いログを削除
    if (logEntries.length > MAX_LOG_ENTRIES) {
      logEntries = logEntries.slice(-MAX_LOG_ENTRIES);
    }
    
    // localStorageに保存
    try {
      localStorage.setItem(LOG_STORAGE_KEY, JSON.stringify(logEntries));
    } catch (e) {
      console.warn('ログの保存に失敗しました（localStorage容量不足の可能性）:', e);
    }
  }
  
  console.log = function(...args) {
    captureLog('log', args);
    originalLog.apply(console, args);
  };
  
  console.error = function(...args) {
    captureLog('error', args);
    originalError.apply(console, args);
  };
  
  console.warn = function(...args) {
    captureLog('warn', args);
    originalWarn.apply(console, args);
  };
  
  console.info = function(...args) {
    captureLog('info', args);
    originalInfo.apply(console, args);
  };
  
  console.debug = function(...args) {
    // 本番環境ではconsole.debugを無効化
    if (LOG_LEVEL === 'error' || LOG_LEVEL === 'warn') {
      return; // 本番環境では何も出力しない
    }
    captureLog('debug', args);
    originalDebug.apply(console, args);
  };
  
  // エラーハンドリング
  window.addEventListener('error', function(event) {
    captureLog('error', [
      `Uncaught Error: ${event.message}`,
      `File: ${event.filename}`,
      `Line: ${event.lineno}`,
      `Column: ${event.colno}`,
      event.error ? event.error.stack : ''
    ]);
  });
  
  window.addEventListener('unhandledrejection', function(event) {
    captureLog('error', [
      `Unhandled Promise Rejection: ${event.reason}`,
      event.reason && event.reason.stack ? event.reason.stack : ''
    ]);
  });
  
  console.log('ログキャプチャ機能が有効になりました');
}

// ログをダウンロード
function downloadLogs(format = 'json') {
  if (logEntries.length === 0) {
    alert('保存されているログがありません');
    return;
  }
  
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
  let content, filename, mimeType;
  
  if (format === 'json') {
    content = JSON.stringify(logEntries, null, 2);
    filename = `attendance-logs-${timestamp}.json`;
    mimeType = 'application/json';
  } else if (format === 'txt') {
    content = logEntries.map(entry => {
      return `[${entry.timestamp}] [${entry.level.toUpperCase()}] ${entry.message}`;
    }).join('\n');
    filename = `attendance-logs-${timestamp}.txt`;
    mimeType = 'text/plain';
  } else if (format === 'csv') {
    const headers = ['Timestamp', 'Level', 'Message', 'URL'];
    const rows = logEntries.map(entry => {
      const message = entry.message.replace(/"/g, '""'); // CSVエスケープ
      return `"${entry.timestamp}","${entry.level}","${message}","${entry.url}"`;
    });
    content = [headers.join(','), ...rows].join('\n');
    filename = `attendance-logs-${timestamp}.csv`;
    mimeType = 'text/csv';
  }
  
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  
  console.log(`ログをダウンロードしました: ${filename} (${logEntries.length}件)`);
}

// ログをクリア
function clearLogs() {
  if (confirm('保存されているログをすべて削除しますか？')) {
    logEntries = [];
    localStorage.removeItem(LOG_STORAGE_KEY);
    console.log('ログをクリアしました');
  }
}

// ログ統計を表示
function showLogStats() {
  const stats = {
    total: logEntries.length,
    byLevel: {},
    byUrl: {},
    oldest: logEntries[0]?.timestamp,
    newest: logEntries[logEntries.length - 1]?.timestamp
  };
  
  logEntries.forEach(entry => {
    stats.byLevel[entry.level] = (stats.byLevel[entry.level] || 0) + 1;
    stats.byUrl[entry.url] = (stats.byUrl[entry.url] || 0) + 1;
  });
  
  console.log('ログ統計:', stats);
  return stats;
}

// ページ読み込み時にログキャプチャを初期化
if (LOG_CAPTURE_ENABLED) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogCapture);
  } else {
    initLogCapture();
  }
}

// ============================================
// API設定
// ============================================
// config.jsから読み込む（設定ファイル化）
// 設定ファイルが読み込まれていない場合はデフォルト値を使用
const API_BASE_URL = (typeof API_BASE_URL !== 'undefined' && API_BASE_URL) 
  ? API_BASE_URL 
  : (window.APP_CONFIG?.API_BASE_URL || 'http://localhost:8080/api.php');

// API呼び出し時に仮想時間をヘッダーとして送信するための共通関数
function getApiHeaders() {
  const headers = {
    'Content-Type': 'application/json',
  };
  
  // 仮想時間モードの場合、仮想時間をヘッダーに追加
  if (getTimeMode() === 'virtual') {
    const virtualTime = getVirtualTime();
    if (virtualTime) {
      headers['X-Virtual-Time'] = virtualTime.toISOString();
    }
  }
  
  return headers;
}

// fetch APIのラッパー関数（仮想時間ヘッダーを自動的に追加）
function apiFetch(url, options = {}) {
  const defaultHeaders = getApiHeaders();
  const mergedHeaders = {
    ...defaultHeaders,
    ...(options.headers || {}),
  };
  
  return fetch(url, {
    ...options,
    headers: mergedHeaders,
    credentials: 'include', // セッションCookieを送信
  });
}

// ============================================
// 時間モード管理（デバッグ用）
// ============================================
const TIME_MODE_STORAGE_KEY = 'attendance_time_mode';
const VIRTUAL_TIME_STORAGE_KEY = 'attendance_virtual_time';

// 時間モードを取得（デフォルト: 現実時間）
function getTimeMode() {
  const mode = localStorage.getItem(TIME_MODE_STORAGE_KEY);
  return mode || 'realtime'; // 'realtime' または 'virtual'
}

// 時間モードを設定
function setTimeMode(mode) {
  if (mode === 'realtime' || mode === 'virtual') {
    localStorage.setItem(TIME_MODE_STORAGE_KEY, mode);
    if (mode === 'realtime') {
      localStorage.removeItem(VIRTUAL_TIME_STORAGE_KEY);
    }
    updateTimeModeButton();
  }
}

// 仮想時間を取得（固定値、現実時間の経過に影響されない）
function getVirtualTime() {
  const timeStr = localStorage.getItem(VIRTUAL_TIME_STORAGE_KEY);
  if (timeStr) {
    // 保存された時間をそのまま返す（固定値）
    return new Date(timeStr);
  }
  return null; // 仮想時間が設定されていない場合
}

// 仮想時間を設定（過去の時間には戻せない）
function setVirtualTime(dateTime) {
  const newTime = new Date(dateTime);
  
  // 既存の仮想時間がある場合、過去の時間には設定できない
  const currentVirtualTime = getVirtualTime();
  if (currentVirtualTime && newTime < currentVirtualTime) {
    throw new Error('仮想時間は過去の時間に戻すことはできません');
  }
  
  // 仮想時間を固定値として保存（ISO文字列で保存）
  localStorage.setItem(VIRTUAL_TIME_STORAGE_KEY, newTime.toISOString());
  updateTimeModeButton();
}

// 現在の時間を取得（現実時間または仮想時間）
function getCurrentTime() {
  if (getTimeMode() === 'virtual') {
    const virtualTime = getVirtualTime();
    if (virtualTime) {
      // 仮想時間が設定されている場合は、その固定値を返す
      return virtualTime;
    }
    // 仮想時間が設定されていない場合は現実時間を返す
    return new Date();
  }
  return new Date();
}

// 時間モードボタンを更新
function updateTimeModeButton() {
  const button = document.getElementById('time-mode-button');
  if (button) {
    const mode = getTimeMode();
    if (mode === 'virtual') {
      const virtualTime = getVirtualTime();
      if (virtualTime) {
        // 仮想時間が設定されている場合は、その時間を表示
        button.textContent = `仮想時間: ${formatDateTime(virtualTime)}`;
        button.className = 'btn btn-sm btn-warning';
      } else {
        // 仮想時間が設定されていない場合は、現実時間を表示
        button.textContent = '仮想時間: 未設定';
        button.className = 'btn btn-sm btn-warning';
      }
    } else {
      button.textContent = '現実時間';
      button.className = 'btn btn-sm btn-secondary';
    }
  }
}

// 日時をフォーマット（仮想時間モード対応）
function formatDateTime(date) {
  const d = new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day} ${hours}:${minutes}`;
}

// 日付をフォーマット（YYYY-MM-DD）（仮想時間モード対応）
function formatDate(date) {
  const d = date instanceof Date ? date : new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// 時刻をフォーマット（HH:mm）（仮想時間モード対応）
function formatTime(dateTimeStr) {
  const date = dateTimeStr instanceof Date ? dateTimeStr : new Date(dateTimeStr);
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${hours}:${minutes}`;
}

// 日時をAPI形式にフォーマット（YYYY-MM-DD HH:mm:ss）（仮想時間モード対応）
function formatDateTimeForAPI(date) {
  const d = date instanceof Date ? date : new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  const seconds = String(d.getSeconds()).padStart(2, '0');
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

// 時間モード設定モーダルを表示
function showTimeModeModal() {
  const modal = document.getElementById('time-mode-modal');
  if (modal) {
    modal.style.display = 'flex';
    
    // 現在の設定を反映
    const mode = getTimeMode();
    const realtimeRadio = document.getElementById('time-mode-realtime');
    const virtualRadio = document.getElementById('time-mode-virtual');
    const virtualSettings = document.getElementById('virtual-time-settings');
    
    if (realtimeRadio) realtimeRadio.checked = mode === 'realtime';
    if (virtualRadio) virtualRadio.checked = mode === 'virtual';
    
    // 仮想時間設定の表示/非表示
    if (virtualSettings) {
      virtualSettings.style.display = mode === 'virtual' ? 'block' : 'none';
    }
    
    if (mode === 'virtual') {
      const virtualTime = getVirtualTime();
      const dateInput = document.getElementById('virtual-date');
      const timeInput = document.getElementById('virtual-time');
      if (dateInput && timeInput) {
        if (virtualTime) {
          // 仮想時間が設定されている場合は、その時間を表示
          dateInput.value = virtualTime.toISOString().split('T')[0];
          timeInput.value = virtualTime.toTimeString().slice(0, 5);
        } else {
          // 仮想時間が設定されていない場合は、現在時刻（現実時間または仮想時間）を初期値として表示
          const now = getCurrentTime();
          dateInput.value = now.toISOString().split('T')[0];
          timeInput.value = now.toTimeString().slice(0, 5);
        }
      }
    }
    
    // ラジオボタンの変更イベント
    if (realtimeRadio) {
      realtimeRadio.onchange = function() {
        if (virtualSettings) virtualSettings.style.display = 'none';
      };
    }
        if (virtualRadio) {
          virtualRadio.onchange = function() {
            if (virtualSettings) virtualSettings.style.display = 'block';
            const dateInput = document.getElementById('virtual-date');
            const timeInput = document.getElementById('virtual-time');
            if (dateInput && !dateInput.value) {
              const now = getCurrentTime();
              dateInput.value = now.toISOString().split('T')[0];
              timeInput.value = now.toTimeString().slice(0, 5);
            }
          };
        }
  }
}

// 時間モード設定モーダルを閉じる
function closeTimeModeModal() {
  const modal = document.getElementById('time-mode-modal');
  if (modal) {
    modal.style.display = 'none';
  }
}

// モーダルの外側をクリックしたときに閉じる
document.addEventListener('click', function(e) {
  const modal = document.getElementById('time-mode-modal');
  if (modal && modal.style.display === 'flex' && e.target === modal) {
    closeTimeModeModal();
  }
});

// 時間モードを保存
function saveTimeMode() {
  const mode = document.querySelector('input[name="time-mode"]:checked')?.value;
  
  if (mode === 'virtual') {
    const dateInput = document.getElementById('virtual-date');
    const timeInput = document.getElementById('virtual-time');
    
    if (!dateInput || !timeInput || !dateInput.value || !timeInput.value) {
      alert('日付と時間を入力してください');
      return;
    }
    
    try {
      const dateTime = new Date(`${dateInput.value}T${timeInput.value}`);
      setVirtualTime(dateTime);
      // 時間モードを'virtual'に設定（重要：これがないとボタンが更新されない）
      setTimeMode('virtual');
      closeTimeModeModal();
      
      // 時間モード変更イベントを発火（カスタムイベント）
      window.dispatchEvent(new CustomEvent('timeModeChanged', { detail: { mode: 'virtual', time: dateTime } }));
    } catch (error) {
      alert(error.message);
    }
  } else {
    setTimeMode('realtime');
    closeTimeModeModal();
    
    // 時間モード変更イベントを発火（カスタムイベント）
    window.dispatchEvent(new CustomEvent('timeModeChanged', { detail: { mode: 'realtime' } }));
  }
}

// データベースをリセット
async function resetDatabase() {
  if (!confirm('データベースをリセットしますか？\n\nすべてのデータが削除され、スキーマが再適用されます。\nこの操作は取り消せません。')) {
    return;
  }
  
  if (!confirm('本当にリセットしますか？\n\nこの操作は取り消せません。')) {
    return;
  }
  
  try {
    const response = await apiFetch(`${API_BASE_URL}/dev/reset-database`, {
      method: 'POST',
    });
    
    const result = await response.json();
    
    if (!response.ok) {
      throw new Error(result.error?.message || 'データベースのリセットに失敗しました');
    }
    
    if (result.success) {
      // 時間モードもリセット
      setTimeMode('realtime');
      alert('データベースをリセットしました。\nページをリロードします。');
      window.location.reload();
    }
  } catch (error) {
    console.error('Error resetting database:', error);
    alert('データベースのリセットに失敗しました: ' + error.message);
  }
}

// ページ読み込み時に実行
document.addEventListener('DOMContentLoaded', function() {
  // タブ制御の初期化
  initTabs();
  
  // サイドバーリンクのスクロール処理
  initSidebarLinks();
  
  // URLハッシュに基づいてタブを表示
  handleHashChange();
  
  // ヘッダーのユーザー情報を更新（ログインページとプロフィールページ以外）
  // プロフィールページは独自のスクリプトで更新するためスキップ
  if (!window.location.pathname.includes('index.html') && 
      !window.location.pathname.includes('register.html') && 
      !window.location.pathname.includes('password-reset.html') &&
      !window.location.pathname.includes('tenant-select.html') &&
      !window.location.pathname.includes('profile.html')) {
    updateHeaderUserInfo();
  }
  
  // 時間モードボタンを更新（ログインページ以外）
  if (!window.location.pathname.includes('index.html') && 
      !window.location.pathname.includes('register.html') && 
      !window.location.pathname.includes('password-reset.html') &&
      !window.location.pathname.includes('tenant-select.html')) {
    // 時間モードボタンが存在しない場合は追加
    ensureTimeModeButton();
    updateTimeModeButton();
    // 時間モードモーダルが存在しない場合は追加
    ensureTimeModeModal();
  }
});

// 時間モードボタンが存在することを確認（存在しない場合は追加）
function ensureTimeModeButton() {
  const existingButton = document.getElementById('time-mode-button');
  if (existingButton) {
    return; // 既に存在する
  }
  
  const logoutButton = document.querySelector('button[onclick="logout()"]');
  if (logoutButton) {
    const timeModeButton = document.createElement('button');
    timeModeButton.id = 'time-mode-button';
    timeModeButton.onclick = showTimeModeModal;
    timeModeButton.className = 'btn btn-sm btn-secondary';
    timeModeButton.style.marginRight = 'var(--spacing-sm)';
    timeModeButton.textContent = '現実時間';
    logoutButton.parentNode.insertBefore(timeModeButton, logoutButton);
  }
}

// 時間モードモーダルが存在することを確認（存在しない場合は追加）
function ensureTimeModeModal() {
  const existingModal = document.getElementById('time-mode-modal');
  if (existingModal) {
    return; // 既に存在する
  }
  
  const modalHTML = `
    <div id="time-mode-modal" class="modal" style="display: none;">
      <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
          <h2 class="modal-title">時間モード設定</h2>
          <button class="modal-close" onclick="closeTimeModeModal()">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label style="display: flex; align-items: center; gap: var(--spacing-xs); cursor: pointer; margin-bottom: var(--spacing-md);">
              <input type="radio" name="time-mode" id="time-mode-realtime" value="realtime" checked style="cursor: pointer;">
              <span>現実時間を採用</span>
            </label>
            <label style="display: flex; align-items: center; gap: var(--spacing-xs); cursor: pointer; margin-bottom: var(--spacing-md);">
              <input type="radio" name="time-mode" id="time-mode-virtual" value="virtual" style="cursor: pointer;">
              <span>仮想時間を採用</span>
            </label>
          </div>
          
          <div id="virtual-time-settings" style="display: none; margin-top: var(--spacing-lg); padding: var(--spacing-md); background: var(--bg-secondary); border-radius: var(--radius-md);">
            <div class="form-group">
              <label for="virtual-date" class="form-label required">日付</label>
              <input type="date" id="virtual-date" class="form-input" required>
            </div>
            <div class="form-group">
              <label for="virtual-time" class="form-label required">時刻</label>
              <input type="time" id="virtual-time" class="form-input" required>
            </div>
            <div class="form-help" style="color: var(--text-secondary); font-size: var(--font-size-sm);">
              注意: 仮想時間は過去の時間に戻すことはできません。
            </div>
          </div>
          
          <div style="margin-top: var(--spacing-xl); padding-top: var(--spacing-md); border-top: 1px solid var(--border-color);">
            <button onclick="resetDatabase()" class="btn btn-sm btn-danger" style="width: 100%;">データベースをリセット</button>
            <div class="form-help" style="color: var(--text-secondary); font-size: var(--font-size-sm); margin-top: var(--spacing-xs);">
              すべてのデータを削除し、スキーマを再適用します。時間モードも現実時間にリセットされます。
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button onclick="saveTimeMode()" class="btn btn-primary">保存</button>
          <button onclick="closeTimeModeModal()" class="btn btn-secondary">キャンセル</button>
        </div>
      </div>
    </div>
  `;
  
  const body = document.body;
  if (body) {
    body.insertAdjacentHTML('beforeend', modalHTML);
  }
}

// タブ制御の初期化
function initTabs() {
  const tabLinks = document.querySelectorAll('.tab-link');
  const tabContents = document.querySelectorAll('.tab-content');
  
  // 各タブリンクにクリックイベントを追加
  tabLinks.forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      
      const targetId = this.getAttribute('href').substring(1);
      const targetContent = document.getElementById(targetId);
      
      if (targetContent) {
        // すべてのタブリンクとコンテンツからactiveクラスを削除
        tabLinks.forEach(function(l) {
          l.classList.remove('active');
        });
        tabContents.forEach(function(c) {
          c.classList.remove('active');
        });
        
        // クリックされたタブリンクとコンテンツにactiveクラスを追加
        this.classList.add('active');
        targetContent.classList.add('active');
        
        // URLハッシュを更新（履歴に追加しない）
        history.replaceState(null, null, '#' + targetId);
        
        // ページトップにスクロール（スムーズに）
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  });
  
  // デフォルトで最初のタブを表示（URLハッシュがない場合）
  if (!window.location.hash) {
    const firstTabLink = tabLinks[0];
    const firstTabContent = tabContents[0];
    
    if (firstTabLink && firstTabContent) {
      firstTabLink.classList.add('active');
      firstTabContent.classList.add('active');
    }
  }
}

// URLハッシュに基づいてタブを表示
function handleHashChange() {
  const hash = window.location.hash;
  
  if (hash) {
    const targetContent = document.querySelector(hash);
    const targetLink = document.querySelector('.tab-link[href="' + hash + '"]');
    
    if (targetContent && targetLink) {
      // すべてのタブリンクとコンテンツからactiveクラスを削除
      document.querySelectorAll('.tab-link').forEach(function(l) {
        l.classList.remove('active');
      });
      document.querySelectorAll('.tab-content').forEach(function(c) {
        c.classList.remove('active');
      });
      
      // ターゲットのタブリンクとコンテンツにactiveクラスを追加
      targetLink.classList.add('active');
      targetContent.classList.add('active');
      
      // ページトップにスクロール
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }
}

// ハッシュ変更時の処理
window.addEventListener('hashchange', handleHashChange);

// サイドバーリンクのスクロール処理
function initSidebarLinks() {
  const sidebarLinks = document.querySelectorAll('.sidebar-menu-link[href^="."], .sidebar-menu-link[href^="/"], .sidebar-menu-link[href^="../"]');
  
  sidebarLinks.forEach(function(link) {
    link.addEventListener('click', function(e) {
      // ページ遷移時にトップにスクロールするために、sessionStorageにフラグを設定
      sessionStorage.setItem('scrollToTop', 'true');
    });
  });
}

// ページ読み込み時にトップにスクロール（ページ遷移時）
window.addEventListener('load', function() {
  // sessionStorageにフラグがある場合、トップにスクロール
  if (sessionStorage.getItem('scrollToTop') === 'true') {
    window.scrollTo({ top: 0, behavior: 'instant' });
    sessionStorage.removeItem('scrollToTop');
  } else if (!window.location.hash) {
    // ハッシュがない場合もトップにスクロール
    window.scrollTo({ top: 0, behavior: 'instant' });
  }
});

// DOMContentLoaded時にもトップにスクロール（より確実に）
document.addEventListener('DOMContentLoaded', function() {
  if (sessionStorage.getItem('scrollToTop') === 'true') {
    window.scrollTo({ top: 0, behavior: 'instant' });
  } else if (!window.location.hash) {
    window.scrollTo({ top: 0, behavior: 'instant' });
  }
});

// ============================================
// ログアウト機能
// ============================================
async function logout() {
  if (!confirm('ログアウトしますか？')) {
    return;
  }
  
  try {
    const response = await apiFetch(`${API_BASE_URL}/auth/logout`, {
      method: 'POST',
    });
    
    const result = await response.json();
    
    if (result.success) {
      // ログインページにリダイレクト
      // パスを現在のページの位置に応じて調整
      const basePath = window.location.pathname.includes('/pages/') ? '../' : '';
      window.location.href = basePath + 'index.html';
    } else {
      throw new Error(result.error?.message || 'ログアウトに失敗しました');
    }
  } catch (error) {
    console.error('Error logging out:', error);
    alert('ログアウトに失敗しました: ' + error.message);
  }
}

// ============================================
// ヘッダーのユーザー情報更新
// ============================================
async function updateHeaderUserInfo() {
  const userNameElement = document.querySelector('.header-user-name');
  const userAvatarElement = document.querySelector('.header-user-avatar');
  
  // ヘッダーにユーザー情報要素がない場合はスキップ
  if (!userNameElement && !userAvatarElement) {
    return;
  }
  
  try {
    const response = await apiFetch(`${API_BASE_URL}/auth/me`, {
      method: 'GET',
    });
    
    if (!response.ok) {
      // 認証エラーの場合は何もしない（ログインページにリダイレクトしない）
      if (response.status === 401) {
        return;
      }
      throw new Error('ユーザー情報の取得に失敗しました');
    }
    
    const result = await response.json();
    if (result.success && result.data && result.data.user) {
      const user = result.data.user;
      const name = user.name || '';
      
      if (userNameElement) {
        userNameElement.textContent = name;
      }
      
      if (userAvatarElement && name) {
        // 名前の最初の文字をアバターに表示
        userAvatarElement.textContent = name.charAt(0);
      }
    }
  } catch (error) {
    // エラーは無視（ログインしていない場合など）
    console.debug('Header user info update failed:', error);
  }
}

