/**
 * Client WebSocket pour module Animations
 * Gère connexion et synchronisation temps réel des animations/inscriptions
 */

// ==================== CONFIGURATION ====================

var WS_ANIM_CONFIG = {
    PORT: 8080,
    PING_TIMEOUT: 10000,
    RECONNECT_DELAY: 2000
};

// ==================== VARIABLES GLOBALES ====================

var wsAnim = null;
var animClientId = 'anim_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
var lastAnimPing = Date.now();
var animPingInterval = null;
var animReconnectTimeout = null;

// ==================== CLIENT WEBSOCKET ANIMATIONS ====================

var WSAnimations = {

    init: function() {
        console.log('[WS-ANIM] Initialisation client:', animClientId);
        this.connect();
    },

    connect: function() {
        var self = this;
        
        $.ajax({
            url: '../websocket_server/get-ws-ip.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.ip) {
                    console.log('[WS-ANIM] Serveur trouvé:', response.ip);
                    self.connectToServer(response.ip);
                } else {
                    console.log('[WS-ANIM] Aucun serveur WS actif');
                    setTimeout(function() { self.connect(); }, WS_ANIM_CONFIG.RECONNECT_DELAY);
                }
            },
            error: function() {
                console.log('[WS-ANIM] Erreur récupération IP');
                setTimeout(function() { self.connect(); }, WS_ANIM_CONFIG.RECONNECT_DELAY);
            }
        });
    },

    connectToServer: function(ip) {
        if (wsAnim && wsAnim.readyState === WebSocket.OPEN) {
            return;
        }

        var self = this;
        var wsUrl = 'ws://' + ip + ':' + WS_ANIM_CONFIG.PORT;
        console.log('[WS-ANIM] Connexion à :', wsUrl);

        try {
            wsAnim = new WebSocket(wsUrl);

            wsAnim.onopen = function() {
                console.log('[WS-ANIM] Connecté');
                lastAnimPing = Date.now();
                self.startPingMonitor();

                if (animReconnectTimeout) {
                    clearTimeout(animReconnectTimeout);
                    animReconnectTimeout = null;
                }
                
                // Enregistrer immédiatement le projet
                self.register();
            };

            wsAnim.onmessage = function(event) {
                self.handleMessage(event.data);
            };

            wsAnim.onerror = function(error) {
                console.error('[WS-ANIM] Erreur:', error);
            };

            wsAnim.onclose = function() {
                console.log('[WS-ANIM] Connexion fermée');
                wsAnim = null;
                
                if (!animReconnectTimeout) {
                    animReconnectTimeout = setTimeout(function() {
                        self.connect();
                    }, WS_ANIM_CONFIG.RECONNECT_DELAY);
                }
            };

        } catch (e) {
            console.error('[WS-ANIM] Erreur création WebSocket:', e);
        }
    },

    register: function() {
        if (!wsAnim || wsAnim.readyState !== WebSocket.OPEN) {
            return;
        }
        
        var message = {
            action: 'register',
            projet: 'animations',
            sender: animClientId
        };
        
        wsAnim.send(JSON.stringify(message));
        console.log('[WS-ANIM] Projet enregistré: animations');
    },

    send: function(action, data, callback) {
        if (!wsAnim || wsAnim.readyState !== WebSocket.OPEN) {
            console.error('[WS-ANIM] Non connecté');
            if (callback) callback();
            return false;
        }

        var message = {
            action: action,
            data: data,
            sender: animClientId,
            projet: 'animations'
        };

        wsAnim.send(JSON.stringify(message));
        
        if (callback) {
            setTimeout(callback, 100);
        }
        
        return true;
    },

    handleMessage: function(data) {
        try {
            var message = JSON.parse(data);

            if (message.action === 'ping') {
                lastAnimPing = Date.now();
                return;
            }

            // Ignorer nos propres messages
            if (message.sender === animClientId) {
                console.log('[WS-ANIM] Message ignoré (propre client)');
                return;
            }

            console.log('[WS-ANIM] Message reçu:', message.action);
            this.dispatchMessage(message);

        } catch (e) {
            console.error('[WS-ANIM] Erreur parsing:', e);
        }
    },

    dispatchMessage: function(message) {
        var action = message.action;
        var data = message.data;

        switch (action) {
            case 'animation_created':
                this.handleAnimationCreated(data);
                break;
            case 'animation_updated':
                this.handleAnimationUpdated(data);
                break;
            case 'animation_deleted':
                this.handleAnimationDeleted(data);
                break;
            case 'inscription_added':
            case 'inscription_updated':
            case 'inscription_deleted':
            case 'inscription_validated':
                this.handleInscriptionChange(data);
                break;
        }
    },

    // ==================== HANDLERS ====================

    handleAnimationCreated: function(data) {
        console.log('[WS-ANIM] Animation créée:', data);
        
        if (typeof allAnimations !== 'undefined' && this.isAnimationInCurrentView(data)) {
            allAnimations.push(data);
            
            // Tri chronologique décroissant
            allAnimations.sort(function(a, b) {
                var dateA = a.date.split('-');
                var dateB = b.date.split('-');
                var valA = dateA[2] + dateA[1] + dateA[0];
                var valB = dateB[2] + dateB[1] + dateB[0];
                if (valB !== valA) {
                    return valB.localeCompare(valA);
                }
                var heureA = a.heure_debut || '00:00';
                var heureB = b.heure_debut || '00:00';
                return heureB.localeCompare(heureA);
            });
            
            if (typeof filterAnimations === 'function') {
                filterAnimations();
            }
        }
    },

    handleAnimationUpdated: function(data) {
        console.log('[WS-ANIM] Animation modifiée:', data);
        
        if (typeof allAnimations !== 'undefined') {
            for (var i = 0; i < allAnimations.length; i++) {
                if (allAnimations[i].id == data.id) {
                    allAnimations[i] = data;
                    break;
                }
            }
            if (typeof filterAnimations === 'function') {
                filterAnimations();
            }
        }
    },

    handleAnimationDeleted: function(data) {
        console.log('[WS-ANIM] Animation supprimée:', data.id);
        
        if (typeof allAnimations !== 'undefined') {
            allAnimations = allAnimations.filter(function(a) {
                return a.id != data.id;
            });
            if (typeof filterAnimations === 'function') {
                filterAnimations();
            }
        }
    },

    handleInscriptionChange: function(data) {
        console.log('[WS-ANIM] Inscription modifiée (autre client):', data);
        
        // Rafraîchir si modal ouverte sur cette animation
        if (typeof currentAnimationId !== 'undefined' && 
            currentAnimationId == data.animation_id &&
            typeof loadInscriptions === 'function') {
            loadInscriptions(currentAnimationId);
        }
        
        // Mettre à jour les compteurs dans la liste (si fournis)
        if (data.compteurs && typeof allAnimations !== 'undefined') {
            for (var i = 0; i < allAnimations.length; i++) {
                if (allAnimations[i].id == data.animation_id) {
                    allAnimations[i].total_inscrits = data.compteurs.total_inscrits;
                    allAnimations[i].total_places_prises = data.compteurs.total_places_prises;
                    allAnimations[i].total_liste_attente = data.compteurs.total_liste_attente;
                    break;
                }
            }
            // Rafraîchir l'affichage
            if (typeof filterAnimations === 'function') {
                filterAnimations();
            }
        }
    },

    isAnimationInCurrentView: function(anim) {
        var dateParts = anim.date.split('-');
        var animMonth, animYear;
        
        if (dateParts[0].length === 4) {
            animYear = parseInt(dateParts[0]);
            animMonth = parseInt(dateParts[1]);
        } else {
            animYear = parseInt(dateParts[2]);
            animMonth = parseInt(dateParts[1]);
        }
        
        if (typeof isPeriodMode !== 'undefined' && isPeriodMode) {
            return animYear >= startYear && animYear <= endYear &&
                   animMonth >= (startMonth + 1) && animMonth <= (endMonth + 1);
        } else {
            return animMonth === (currentMonth + 1) && animYear === currentYear;
        }
    },

    startPingMonitor: function() {
        var self = this;
        
        if (animPingInterval) {
            clearInterval(animPingInterval);
        }

        animPingInterval = setInterval(function() {
            var timeSinceLastPing = Date.now() - lastAnimPing;

            if (timeSinceLastPing > WS_ANIM_CONFIG.PING_TIMEOUT) {
                console.log('[WS-ANIM] Timeout ping');
                if (wsAnim) {
                    wsAnim.close();
                }
            }
        }, 1000);
    }
};

// ==================== FONCTIONS WRAPPER GLOBALES ====================
// FULL WEBSOCKET - Plus d'AJAX pour les opérations

function wsCreateAnimation(data, callback) {
    WSAnimations.send('animation_create', data, callback);
}

function wsUpdateAnimation(data, callback) {
    WSAnimations.send('animation_update', data, callback);
}

function wsDeleteAnimation(id, callback) {
    WSAnimations.send('animation_delete', { id: id }, callback);
}

function wsAddInscription(data, callback) {
    WSAnimations.send('inscription_add', data, callback);
}

function wsUpdateInscription(data, callback) {
    WSAnimations.send('inscription_update', data, callback);
}

function wsDeleteInscription(id, callback) {
    WSAnimations.send('inscription_delete', { id: id }, callback);
}

function wsValiderInscription(id, callback) {
    WSAnimations.send('inscription_validate', { id: id }, callback);
}

// ==================== INIT AUTO ====================

$(document).ready(function() {
    WSAnimations.init();
});

// Exposition globale
window.WSAnimations = WSAnimations;
window.wsCreateAnimation = wsCreateAnimation;
window.wsUpdateAnimation = wsUpdateAnimation;
window.wsDeleteAnimation = wsDeleteAnimation;
window.wsAddInscription = wsAddInscription;
window.wsUpdateInscription = wsUpdateInscription;
window.wsDeleteInscription = wsDeleteInscription;
window.wsValiderInscription = wsValiderInscription;
