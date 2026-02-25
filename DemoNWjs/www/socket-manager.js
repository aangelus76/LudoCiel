/**
 * SOCKET MANAGER
 * Gère l'élection UDP et lance tous les serveurs WS enregistrés.
 */

const http  = require('http');
const dgram = require('dgram');
const os    = require('os');
const { Server: WsServer } = require('ws');

const UDP_PORT     = 3556;
const DISCOVER_MSG = 'APP_DISCOVER';
const RESPOND_MSG  = 'APP_HERE';

// Identifiant unique de cette instance (10 caractères alphanumériques)
const INSTANCE_ID = Array.from({length: 10}, () =>
  'abcdefghijklmnopqrstuvwxyz0123456789'[Math.floor(Math.random() * 36)]
).join('');

global.appLog('[MANAGER] Instance ID:', INSTANCE_ID);

let isServer       = false;
let currentServer  = null;
let udpDiscovery   = null;
let reelecting     = false; // empêche plusieurs réélections simultanées

const servers          = {};
const registry         = [];
const readyCallbacks   = [];

function getLocalIP() {
  const nets = os.networkInterfaces();
  for (const iface of Object.values(nets))
    for (const net of iface)
      if (net.family === 'IPv4' && !net.internal) return net.address;
  return '127.0.0.1';
}

// ─── API PUBLIQUE ─────────────────────────────────────────────────────────────

function register(modulePath, port) {
  registry.push({ modulePath, port });
}

function onReady(cb) {
  readyCallbacks.push(cb);
}

function getIsServer()    { return isServer; }
function getInstanceId()  { return INSTANCE_ID; }
function getCurrentServer() { return currentServer; }

function getPort(modulePath) {
  const entry = registry.find(r => r.modulePath === modulePath);
  return entry ? entry.port : null;
}

// ─── SERVEUR WS ───────────────────────────────────────────────────────────────

function startWsServer(port) {
  return new Promise((resolve, reject) => {
    const httpServer = http.createServer();
    const wss = new WsServer({ server: httpServer });
    const clients = new Set();
    let settled = false;

    function done(err) {
      if (settled) return;
      settled = true;
      if (err) { try { httpServer.close(); } catch(_){} reject(err); }
      else resolve();
    }

    wss.on('connection', (ws) => {
      clients.add(ws);
      ws.on('message', (data) => {
        for (const c of clients)
          if (c !== ws && c.readyState === 1) c.send(data.toString());
      });
      ws.on('close', () => clients.delete(ws));
    });

    httpServer.once('error', (err) => done(err));
    httpServer.listen(port, () => {
      servers[port] = { httpServer, wss, clients };
      done(null);
    });

    // Timeout de sécurité : si ni listen ni error dans 3s → on rejette
    setTimeout(() => done(new Error('TIMEOUT')), 3000);
  });
}

function stopAllServers() {
  if (udpDiscovery) { try { udpDiscovery.close(); } catch(_){} udpDiscovery = null; }
  for (const { httpServer, wss } of Object.values(servers)) {
    try { wss.close(); } catch(_) {}
    try { httpServer.close(); } catch(_) {}
  }
  for (const key of Object.keys(servers)) delete servers[key];
  isServer = false;
  currentServer = null;
}

async function startAllServers() {
  const results = await Promise.allSettled(
    registry.map(({ port }) => startWsServer(port))
  );
  const conflict = results.find(r => r.status === 'rejected' && r.reason?.code === 'EADDRINUSE');
  if (conflict) {
    // Stopper seulement les serveurs qui ont réussi à démarrer
    for (const { httpServer, wss } of Object.values(servers)) {
      try { wss.close(); } catch(_) {}
      try { httpServer.close(); } catch(_) {}
    }
    for (const key of Object.keys(servers)) delete servers[key];
    isServer = false;
    return false;
  }
  isServer = true;
  currentServer = { id: INSTANCE_ID, address: 'localhost' };
  global.appLog('[MANAGER] Serveur démarré — ID:', INSTANCE_ID, 'IP:', getLocalIP());
  listenForDiscovery();
  return true;
}

// ─── UDP ──────────────────────────────────────────────────────────────────────

function listenForDiscovery() {
  udpDiscovery = dgram.createSocket({ type: 'udp4', reuseAddr: true });
  udpDiscovery.on('message', (msg, rinfo) => {
    if (msg.toString() === DISCOVER_MSG) {
      // Répondre avec ID:IP pour que les clients sachent qui a gagné
      const response = `${RESPOND_MSG}:${INSTANCE_ID}:${getLocalIP()}`;
      udpDiscovery.send(Buffer.from(response), rinfo.port, rinfo.address);
    }
  });
  udpDiscovery.bind(UDP_PORT);
}

function discoverServer(timeout = 2000) {
  return new Promise((resolve) => {
    const udp = dgram.createSocket({ type: 'udp4', reuseAddr: true });
    let resolved = false;

    udp.on('message', (msg, rinfo) => {
      const str = msg.toString();
      if (str.startsWith(RESPOND_MSG) && !resolved) {
        resolved = true;
        try { udp.close(); } catch(_) {}
        // Parser APP_HERE:instanceId:ip
        const parts = str.split(':');
        const serverId      = parts[1];
        const serverAddress = parts[2];
        resolve({ id: serverId, address: serverAddress });
      }
    });

    udp.bind(0, () => {
      udp.setBroadcast(true);
      udp.send(Buffer.from(DISCOVER_MSG), UDP_PORT, '255.255.255.255');
    });

    setTimeout(() => {
      if (!resolved) { try { udp.close(); } catch(_){} resolve(null); }
    }, timeout);
  });
}

function notifyReady(server) {
  reelecting = false;
  currentServer = server;
  const address = server.id === INSTANCE_ID ? 'localhost' : server.address;
  global.appLog('[MANAGER] Prêt — serveur:', server.id, 'adresse:', address);
  global.appLog('[MANAGER] Appel de', readyCallbacks.length, 'callbacks...');
  for (const cb of readyCallbacks) cb(address);
}

// ─── RÉÉLECTION ───────────────────────────────────────────────────────────────

async function reelect() {
  if (reelecting) {
    global.appLog('[MANAGER] Réélection déjà en cours, ignoré');
    return;
  }
  reelecting = true;
  global.appLog('[MANAGER] Réélection...');
  stopAllServers();
  global.appEvents.emit('status', 'disconnected');

  // Boucle jusqu'à trouver ou devenir serveur
  while (true) {
    const delay = Math.floor(Math.random() * 3000);
    global.appLog('[MANAGER] Attente', delay, 'ms — ID:', INSTANCE_ID);
    await new Promise(r => setTimeout(r, delay));

    // Quelqu'un est déjà serveur ?
    let found = await discoverServer(1000);
    global.appLog('[MANAGER] Découverte résultat:', found ? found.id + ' @ ' + found.address : 'null');
    if (found) {
      global.appLog('[MANAGER] Serveur trouvé:', found.id);
      notifyReady(found);
      return;
    }

    // Tenter de démarrer
    const won = await startAllServers();
    global.appLog('[MANAGER] startAllServers résultat:', won ? 'GAGNÉ' : 'COLLISION');
    if (won) {
      // Vérification post-démarrage — quelqu'un d'autre a démarré en même temps ?
      await new Promise(r => setTimeout(r, 500));
      const concurrent = await discoverServer(1000);
      if (concurrent && concurrent.id !== INSTANCE_ID) {
        global.appLog('[MANAGER] Doublon détecté ! Autre serveur:', concurrent.id, '— je cède.');
        stopAllServers();
        notifyReady(concurrent);
        return;
      }
      notifyReady({ id: INSTANCE_ID, address: 'localhost' });
      return;
    }

    // Collision — chercher le gagnant avec patience
    global.appLog('[MANAGER] Collision, je cherche le gagnant...');
    let gagnant = null;
    for (let i = 0; i < 8; i++) {
      await new Promise(r => setTimeout(r, 600));
      gagnant = await discoverServer(1000);
      if (gagnant) {
        global.appLog('[MANAGER] Gagnant trouvé après', i + 1, 'retry:', gagnant.id);
        notifyReady(gagnant);
        return;
      }
      global.appLog('[MANAGER] Pas encore prêt, retry', i + 1, '/8');
    }

    // Personne — reboucler
    global.appLog('[MANAGER] Personne trouvé, on reboucle...');
    stopAllServers();
  }
}

// ─── INIT ─────────────────────────────────────────────────────────────────────

async function init() {
  global.appLog('[MANAGER] Découverte réseau — ID:', INSTANCE_ID);
  const found = await discoverServer();
  if (found) {
    global.appLog('[MANAGER] Serveur trouvé:', found.id);
    notifyReady(found);
    return;
  }

  global.appLog('[MANAGER] Aucun serveur, je démarre...');
  const won = await startAllServers();
  if (won) {
    await new Promise(r => setTimeout(r, 500));
    const concurrent = await discoverServer(1000);
    if (concurrent && concurrent.id !== INSTANCE_ID) {
      global.appLog('[MANAGER] Doublon détecté au démarrage ! Je cède à:', concurrent.id);
      stopAllServers();
      notifyReady(concurrent);
    } else {
      notifyReady({ id: INSTANCE_ID, address: 'localhost' });
    }
  } else {
    global.appLog('[MANAGER] Collision au démarrage, je cherche le gagnant...');
    for (let i = 0; i < 8; i++) {
      await new Promise(r => setTimeout(r, 600));
      const f = await discoverServer(1000);
      if (f) {
        global.appLog('[MANAGER] Gagnant trouvé:', f.id);
        notifyReady(f);
        return;
      }
    }
    await reelect();
  }
}

module.exports = { register, onReady, init, getIsServer, getInstanceId, getCurrentServer, getPort, reelect };
