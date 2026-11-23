const { app, BrowserWindow, Menu, shell, systemPreferences, powerSaveBlocker } = require('electron');

// Hardcoded on purpose — the person using the app never needs to know the address.
// No access code any more (retired 2026-11): the app opens straight to the site.
const SITE_URL = 'https://vhonixcloud.com';

// Keep the live screen streaming when the window is minimized or behind
// other windows instead of letting Chromium throttle it.
app.commandLine.appendSwitch('disable-background-timer-throttling');
app.commandLine.appendSwitch('disable-renderer-backgrounding');
app.commandLine.appendSwitch('disable-backgrounding-occluded-windows');

let mainWindow = null;

function buildMenu() {
  const template = [
    {
      label: 'Vhonix',
      submenu: [
        { role: 'about' },
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

function createMainWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 860,
    autoHideMenuBar: true,
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      backgroundThrottling: false,
    },
  });

  Menu.setApplicationMenu(buildMenu());

  // Let the site use the computer's camera/mic (forwarded into the cloud
  // phone when an app on it opens them) — only for our own site, nothing else.
  const siteOrigin = new URL(SITE_URL).origin;
  const ses = mainWindow.webContents.session;
  ses.setPermissionRequestHandler((wc, permission, callback, details) => {
    const origin = details.requestingUrl ? new URL(details.requestingUrl).origin : '';
    callback(permission === 'media' && origin === siteOrigin);
  });
  ses.setPermissionCheckHandler((wc, permission, requestingOrigin) =>
    permission === 'media' && requestingOrigin === siteOrigin
  );

  if (process.platform === 'darwin') {
    systemPreferences.askForMediaAccess('camera').catch(() => {});
    systemPreferences.askForMediaAccess('microphone').catch(() => {});
  }

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

// Stops the computer from sleeping the app while it's open in the background.
let blockerId = null;

app.whenReady().then(() => {
  blockerId = powerSaveBlocker.start('prevent-app-suspension');
  createMainWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createMainWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (blockerId !== null && powerSaveBlocker.isStarted(blockerId)) {
    powerSaveBlocker.stop(blockerId);
  }
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
