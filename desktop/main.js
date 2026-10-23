const { app, BrowserWindow, shell, Menu } = require('electron');
const path = require('path');
const windowStateKeeper = require('electron-window-state');
const { autoUpdater } = require('electron-updater');

const APP_URL = process.env.TMK_APP_URL || 'https://example.com';

// The GitHub release repo is private, so checking/downloading updates needs
// a token. Read it from the gitignored update-token.js (baked into the
// packaged app at build time) rather than relying on a runtime env var,
// since end-user machines won't have GH_TOKEN set.
const { GH_TOKEN } = require('./update-token');
if (GH_TOKEN) process.env.GH_TOKEN = GH_TOKEN;

// Only one window/instance at a time.
const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
}

let mainWindow;

function createWindow() {
  const windowState = windowStateKeeper({
    defaultWidth: 1280,
    defaultHeight: 800,
  });

  mainWindow = new BrowserWindow({
    x: windowState.x,
    y: windowState.y,
    width: windowState.width,
    height: windowState.height,
    minWidth: 1024,
    minHeight: 640,
    icon: path.join(__dirname, 'icons', 'icon.png'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
    show: false,
  });

  windowState.manage(mainWindow);

  mainWindow.loadURL(APP_URL);

  mainWindow.once('ready-to-show', () => mainWindow.show());

  // Keep navigation inside the app for the admin domain; anything else
  // (e.g. links opened via target="_blank") goes to the system browser.
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.webContents.on('will-navigate', (event, url) => {
    const appHost = new URL(APP_URL).host;
    const targetHost = new URL(url).host;
    if (targetHost !== appHost) {
      event.preventDefault();
      shell.openExternal(url);
    }
  });

  // No application menu, so the default DevTools accelerator (tied to a
  // menu item) doesn't fire — bind F12 / Ctrl+Shift+I / Cmd+Option+I
  // directly so support issues can be diagnosed on a user's machine.
  mainWindow.webContents.on('before-input-event', (event, input) => {
    const isToggleCombo =
      input.key === 'F12' ||
      (input.control && input.shift && input.key.toLowerCase() === 'i') ||
      (input.meta && input.alt && input.key.toLowerCase() === 'i');
    if (isToggleCombo) mainWindow.webContents.toggleDevTools();
  });
}

Menu.setApplicationMenu(null);

app.on('second-instance', () => {
  if (mainWindow) {
    if (mainWindow.isMinimized()) mainWindow.restore();
    mainWindow.focus();
  }
});

app.whenReady().then(() => {
  createWindow();
  // Checks GitHub Releases; if a newer version is published it downloads
  // in the background and shows a native notification. Installs on next quit.
  if (GH_TOKEN) autoUpdater.checkForUpdatesAndNotify();
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) createWindow();
});
