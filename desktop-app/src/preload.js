const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('vhonix', {
  verify: (code) => ipcRenderer.invoke('access:verify', code),
  launch: () => ipcRenderer.invoke('access:launch'),
});
