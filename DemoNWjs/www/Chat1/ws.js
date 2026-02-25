const manager = require('../socket-manager.js');

const PORT = manager.getPort('./Chat1/ws.js');
const MAX_RECONNECT   = 2;
const RECONNECT_DELAY = 800;

let ws             = null;
let isConnected    = false;
let currentAddress = null;
let reconnectCount = 0;
let reelecting     = false;

manager.onReady((address) => {
  currentAddress = address;
  reconnectCount = 0;
  reelecting     = false;
  connect(address);
});

function connect(address) {
  if (ws) {
    ws.onopen = ws.onmessage = ws.onclose = ws.onerror = null;
    try { ws.close(); } catch(_) {}
    ws = null;
  }

  const url = `ws://${address}:${PORT}`;
  global.appLog('[CHAT1] Connexion à', url);
  ws = new WebSocket(url);

  ws.onopen = () => {
    reconnectCount = 0;
    isConnected    = true;
    reelecting     = false;
    global.appEvents.emit('chat1-status', manager.getIsServer() ? 'server' : 'connected');
    global.appEvents.emit('ws-ready');
  };

  ws.onmessage = (e) => {
    global.appEvents.emit('chat1-message', e.data);
  };

  ws.onclose = () => {
    isConnected = false;
    global.appEvents.emit('chat1-status', 'disconnected');
    if (reelecting) return;

    if (reconnectCount < MAX_RECONNECT) {
      reconnectCount++;
      setTimeout(() => connect(currentAddress), RECONNECT_DELAY + Math.floor(Math.random() * 500));
    } else {
      reelecting = true;
      manager.reelect();
    }
  };

  ws.onerror = () => {};
}

function send(text) {
  if (ws && ws.readyState === WebSocket.OPEN) ws.send(text);
}

function requestStatus() {
  if (manager.getIsServer() && isConnected) global.appEvents.emit('chat1-status', 'server');
  else if (isConnected)                     global.appEvents.emit('chat1-status', 'connected');
  else                                      global.appEvents.emit('chat1-status', 'disconnected');
}

module.exports = { send, requestStatus };
