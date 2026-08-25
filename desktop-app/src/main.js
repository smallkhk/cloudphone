const { app, BrowserWindow, Menu, ipcMain, shell } = require('electron');
const path = require('path');
const https = require('https');
const Store = require('electron-store');

// Hardcoded on purpose — the whole point of this app is that the person
// using it never sees or needs to know this address.
const SITE_URL = 'https://cloud.eclipselivecam.online';
const VERIFY_HOST = 'cloud.eclipselivecam.online';
const VERIFY_PATH = '/api/access-keys/verify';

const store = new Store();

let lockWindow = null;
let mainWindow = null;

function buildMenu(signOut) {
  const template = [
    {
      label: 'Modova',
      submenu: [
        { role: 'about' },
        { type: 'separator' },
        { label: 'Sign out', click: signOut },
        { type: 'separator' },
        { role: 'quit' },
      ],
    },
    {
      label: 'Edit',
      submenu: [
        { role: 'undo' },
        { role: 'redo' },
        { type: 'separator' },
        { role: 'cut' },
        { role: 'copy' },
        { role: 'paste' },
        { role: 'selectAll' },
      ],
    },
    {
      label: 'View',
      submenu: [
        { role: 'reload' },
        { role: 'resetZoom' },
        { role: 'zoomIn' },
        { role: 'zoomOut' },
        { role: 'togglefullscreen' },
      ],
    },
  ];

  return Menu.buildFromTemplate(template);
}

function verifyCode(code) {
  return new Promise((resolve) => {
    const body = JSON.stringify({ code });

    const req = https.request(
      {
        host: VERIFY_HOST,
        path: VERIFY_PATH,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(body),
          Accept: 'application/json',
        },
        timeout: 15000,
      },
      (res) => {
        let data = '';
        res.on('data', (chunk) => (data += chunk));
        res.on('end', () => {
          try {
            const parsed = JSON.parse(data);
            resolve({ ok: parsed.valid === true });
          } catch (e) {
            resolve({ ok: false, error: 'Unexpected response from the server.' });
          }
        });
      }
    );

    req.on('timeout', () => {
      req.destroy();
      resolve({ ok: false, error: 'Connection timed out. Check your internet connection.' });
    });

    req.on('error', () => {
      resolve({ ok: false, error: 'Could not reach the server. Check your internet connection.' });
    });

    req.write(body);
    req.end();
  });
}

function signOut() {
  store.delete('accessCode');
  if (mainWindow) {
    mainWindow.close();
    mainWindow = null;
  }
  createLockWindow();
}

function createLockWindow() {
  lockWindow = new BrowserWindow({
    width: 420,
    height: 520,
    resizable: false,
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  lockWindow.loadFile(path.join(__dirname, 'lock.html'));
}

function createMainWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 860,
    autoHideMenuBar: true,
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  Menu.setApplicationMenu(buildMenu(signOut));

  mainWindow.loadURL(SITE_URL);

  // Links that open a new tab/window (e.g. target=_blank) open in the
  // system browser instead of a second app window.
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

ipcMain.handle('access:verify', async (_event, code) => {
  const result = await verifyCode(String(code || '').trim());

  if (result.ok) {
    store.set('accessCode', code);
  }

  return result;
});

ipcMain.handle('access:launch', () => {
  if (lockWindow) {
    lockWindow.close();
    lockWindow = null;
  }
  createMainWindow();
});

app.whenReady().then(() => {
  Menu.setApplicationMenu(null);

  const savedCode = store.get('accessCode');

  if (savedCode) {
    createMainWindow();
  } else {
    createLockWindow();
  }

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      if (store.get('accessCode')) {
        createMainWindow();
      } else {
        createLockWindow();
      }
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
