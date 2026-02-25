/**
 * DÉCLARATION DES SOCKETS
 * Ajouter une ligne ici pour chaque app qui utilise un socket.
 */
const manager = require('./socket-manager.js');

manager.register('./Chat1/ws.js', 3456);
manager.register('./Chat2/ws.js', 3457);
manager.register('./Chat3/ws.js', 3458);
