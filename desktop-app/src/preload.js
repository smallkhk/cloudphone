const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('modova', {
  verify: (code) => ipcRenderer.invoke('access:verify', code),
  launch: () => ipcRenderer.invoke('access:launch'),
});
