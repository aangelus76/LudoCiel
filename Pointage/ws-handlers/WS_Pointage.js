/**
 * Client WebSocket pour LudoPresence
 * Gère connexion, élection serveur, et synchronisation temps réel
 */

// ==================== CONFIGURATION ====================

const WS_CONFIG = {
    PORT: 8080,
    PING_TIMEOUT: 10000,
    RECONNECT_DELAY: 2000,
    ELECTION_RETRY_DELAY: 1000,
    MAX_ELECTION_RETRIES: 5
};

// ==================== VARIABLES GLOBALES ====================

let ws = null;
let clientId = 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
let lastPingReceived = Date.now();
let pingCheckInterval = null;
let isElecting = false;
let reconnectTimeout = null;

// ==================== CLIENT WEBSOCKET ====================

const WebSocketClient = {

    init: function() {
        console.log('[WS] Initialisation client:', clientId);
        this.showConnecting();
        this.disableButtons();
        this.updateIpDisplay(null);
        this.setupTooltip();

        $.ajax({
            url: 'websocket_server/clear-ws-ip.php',
            method: 'GET',
            complete: () => {
                this.connect();
                this.startPingMonitor();
            }
        });
    },

    showConnecting: function() {
        $('#wsConnecting').show();
        $('#wsConnected').hide();
        $('#wsIpBadge').text('...').removeClass('connected').addClass('searching').show();
    },

    showConnected: function() {
        $('#wsConnecting').hide();
        $('#wsConnected').show();
    },

    disableButtons: function() {
        $('#addADULTE, #addEnfant, #addJeune, #openGroupModal, #associateButton, #addToGroupButton, #groupsButton').css({
            'opacity': '0.5',
            'pointer-events': 'none'
        });
    },

    enableButtons: function() {
        $('#addADULTE, #addEnfant, #addJeune, #openGroupModal, #associateButton, #addToGroupButton, #groupsButton').css({
            'opacity': '1',
            'pointer-events': 'auto'
        });
    },

    updateIpDisplay: function(ip) {
        if (ip) {
            const lastSegment = ip.split('.').pop();
            $('#wsIpBadge')
                .text('.' + lastSegment)
                .removeClass('searching')
                .addClass('connected')
                .show();

            $('#wsStatusIndicator').data('wsIp', ip);
        } else {
            $('#wsIpBadge').text('?').removeClass('connected').addClass('searching');
            $('#wsStatusIndicator').data('wsIp', 'Recherche...');
        }
    },

    setupTooltip: function() {
        const $indicator = $('#wsStatusIndicator');
        const $tooltip = $('<div class="ws-tooltip"></div>').appendTo('body');

        $indicator.hover(
            function(e) {
                const ip = $(this).data('wsIp') || 'Non connecté';
                const status = $('#wsConnected').is(':visible') ? 'Connecté' : 'Recherche...';

                $tooltip.html(`
                    <strong>WebSocket</strong><br>
                    Statut: ${status}<br>
                    Serveur: ${ip}
                `);

                const offset = $(this).offset();
                $tooltip.css({
                    top: offset.top + $(this).outerHeight() + 10,
                    left: offset.left + ($(this).width() / 2) - ($tooltip.width() / 2)
                }).fadeIn(200);
            },
            function() {
                $tooltip.fadeOut(200);
            }
        );
    },

    connect: function() {
        $.ajax({
            url: 'websocket_server/get-ws-ip.php',
            method: 'GET',
            dataType: 'json',
            success: (response) => {
                if (response.ip) {
                    console.log('[WS] Serveur trouvé:', response.ip);
                    this.connectToServer(response.ip);
                } else {
                    console.log('[WS] Aucun serveur actif, lancement élection');
                    ServerElection.start();
                }
            },
            error: () => {
                console.log('[WS] Erreur récupération IP, lancement élection');
                ServerElection.start();
            }
        });
    },

    connectToServer: function(ip) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            return;
        }

        const self = this;
        const wsUrl = `ws://${ip}:${WS_CONFIG.PORT}`;
        console.log('[WS] Connexion à :', wsUrl);

        try {
            ws = new WebSocket(wsUrl);

            ws.onopen = () => {
                console.log('[WS] Connecté au serveur');
                lastPingReceived = Date.now();
                self.showConnected();
                self.enableButtons();
                self.updateIpDisplay(ip);
                self.startPingMonitor();

                if (reconnectTimeout) {
                    clearTimeout(reconnectTimeout);
                    reconnectTimeout = null;
                }

                self.send('get_initial_state', {
                    date: $("#dateSelector").val()
                });
            };

            ws.onmessage = (event) => {
                self.handleMessage(event.data);
            };

            ws.onerror = (error) => {
                console.error('[WS] Erreur connexion:', error);
            };

            ws.onclose = () => {
                console.log('[WS] Connexion fermée');
                self.handleDisconnect();
                self.showConnecting();
                self.disableButtons();
            };

        } catch (e) {
            console.error('[WS] Erreur création WebSocket:', e);
            self.handleDisconnect();
        }
    },

    handleDisconnect: function() {
        ws = null;
        this.showConnecting();
        this.disableButtons();
        this.updateIpDisplay(null);

        $.ajax({
            url: 'websocket_server/clear-ws-ip.php?force=true',
            method: 'GET'
        });

        if (!reconnectTimeout) {
            reconnectTimeout = setTimeout(() => {
                console.log('[WS] Tentative de reconnexion...');
                this.connect();
            }, WS_CONFIG.RECONNECT_DELAY);
        }
    },

    send: function(action, data) {
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            console.error('[WS] Impossible d\'envoyer, non connecté');
            return false;
        }

        const message = {
            action: action,
            data: data,
            sender: clientId,
            date: data.date || $("#dateSelector").val()
        };

        ws.send(JSON.stringify(message));
        return true;
    },

    handleMessage: function(data) {
        try {
            const message = JSON.parse(data);

            if (message.action === 'ping' || !message.action) {
                lastPingReceived = Date.now();
                return;
            }

            console.log('[WS] Message reçu:', message.action);
            this.dispatchMessage(message);

        } catch (e) {
            console.error('[WS] Erreur traitement message:', e);
        }
    },

    dispatchMessage: function(message) {
        const action = message.action;
        const data = message.data;

        switch (action) {
            case 'initial_state':
                this.handleInitialState(data, message);
                break;

            case 'individual_adding':
                this.handleIndividualAdding(data, message);
                break;

            case 'individual_added':
                this.handleIndividualAdded(data, message);
                break;

            case 'individual_time_updated':
                this.handleIndividualTimeUpdated(data, message);
                break;

            case 'individual_left_updated':
                this.handleIndividualLeftUpdated(data, message);
                break;

            case 'individual_deleted':
                this.handleIndividualDeleted(data, message);
                break;

            case 'individual_who_updated':
                this.handleIndividualWhoUpdated(data, message);
                break;

            case 'group_creating':
                this.handleGroupCreating(data, message);
                break;

            case 'group_created':
                this.handleGroupCreated(data, message);
                break;

            case 'group_assigned':
                this.handleGroupAssigned(data, message);
                break;

            case 'group_comment_updated':
                this.handleGroupCommentUpdated(data, message);
                break;

            case 'partner_adding':
                this.handlePartnerAdding(data, message);
                break;

            case 'partner_added':
                this.handlePartnerAdded(data, message);
                break;

            case 'partner_updated':
                this.handlePartnerUpdated(data, message);
                break;

            case 'partner_left_updated':
                this.handlePartnerLeftUpdated(data, message);
                break;

            case 'partner_deleted':
                this.handlePartnerDeleted(data, message);
                break;

            case 'individual_type_updated':
                this.handleIndividualTypeUpdated(data, message);
                break;
        }
    },

    // ==================== HANDLERS ====================

    handleInitialState: function(data, message) {
        DataManager.localData.individuals.clear();
        DataManager.localData.partners.clear();

        data.individuals.forEach(ind => {
            DataManager.localData.individuals.set(ind.id.toString(), ind);
        });

        data.partners.forEach(partner => {
            DataManager.localData.partners.set(partner.id.toString(), partner);
        });

        UI.renderParticipantList(DataManager.getFilteredIndividuals());
        UI.updatePartnerPills(Array.from(DataManager.localData.partners.values()));
        StatsManager.updateLocalStats();
        StatsManager.updateWeeklyStats();
    },

    handleIndividualAdding: function(data, message) {
        DataManager.localData.individuals.set(data.id.toString(), data);
        UI.renderParticipantList(DataManager.getFilteredIndividuals());
        StatsManager.updateLocalStats();
    },

    handleIndividualAdded: function(data, message) {
        DataManager.localData.individuals.delete(data.temp_id.toString());
        DataManager.localData.individuals.set(data.real_id.toString(), {
            id: data.real_id,
            type: data.type,
            arrival_time: data.arrival_time,
            duration: data.duration,
            has_left: data.has_left
        });
        if (message.sender && message.sender !== clientId) {
            NewEntryTracker.add(data.real_id);
        }
        UI.renderParticipantList(DataManager.getFilteredIndividuals());
        StatsManager.updateWeeklyStats();
    },

    handleIndividualTimeUpdated: function(data, message) {
        const ind = DataManager.localData.individuals.get(data.id.toString());
        if (ind) {
            ind.duration = data.duration;
            DataManager.localData.individuals.set(data.id.toString(), ind);
            $(`[data-id="${data.id}"] .C_Duration`).text(data.duration);
            StatsManager.updateLocalStats();
        }
        StatsManager.updateWeeklyStats();
    },

    handleIndividualLeftUpdated: function(data, message) {
        const ind = DataManager.localData.individuals.get(data.id.toString());
        if (ind) {
            ind.has_left = data.has_left;
            DataManager.localData.individuals.set(data.id.toString(), ind);

            const $row = $(`[data-id="${data.id}"]`);
            $row.toggleClass('participant-left', data.has_left == 1);

            const $icon = $row.find('.fa-sign-out, .fa-sign-in');
            $icon.removeClass('fa-sign-out fa-sign-in')
                .addClass(data.has_left == 1 ? 'fa-sign-in' : 'fa-sign-out')
                .css('color', data.has_left == 1 ? '#4CAF50' : '#bd514a');

            UI.renderParticipantList(DataManager.getFilteredIndividuals());

            StatsManager.updateLocalStats();
        }
    },

    handleIndividualDeleted: function(data, message) {
        DataManager.localData.individuals.delete(data.id.toString());
        $(`[data-id="${data.id}"]`).remove();
        StatsManager.updateLocalStats();
        StatsManager.updateWeeklyStats();
    },

    handleIndividualWhoUpdated: function(data, message) {
        const ind = DataManager.localData.individuals.get(data.id.toString());
        if (ind) {
            ind.whoIs = data.whoIs;
            DataManager.localData.individuals.set(data.id.toString(), ind);

            const $icon = $(`[data-id="${data.id}"] .fa-user-circle`);
            $icon.css('color', data.whoIs ? '#4CAF50' : '#c4c4c4');
        }
    },

    handleIndividualTypeUpdated: function(data, message) {
        const ind = DataManager.localData.individuals.get(data.id.toString());
        if (ind) {
            ind.type = data.type;
            DataManager.localData.individuals.set(data.id.toString(), ind);
            UI.renderParticipantList(DataManager.getFilteredIndividuals());
        }
    },

    handleGroupCreating: function(data, message) {
        console.log('[WS] Groupe en création:', data.group_id);
    },

    handleGroupCreated: function(data, message) {
        console.log('[WS] Groupe créé:', data.group_id);

        if (window.onGroupCreated) {
            window.onGroupCreated(data.group_id);
        }
    },

    handleGroupAssigned: function(data, message) {
        let groupComment = null;
        if (data.group_id !== '') {
            const existingMember = Array.from(DataManager.localData.individuals.values())
                .find(ind => ind.group_id === data.group_id);
            if (existingMember && existingMember.comment) {
                groupComment = existingMember.comment;
            }
        }

        data.ids.forEach(id => {
            const ind = DataManager.localData.individuals.get(id.toString());
            if (ind) {
                ind.group_id = data.group_id;
                ind.color = data.color;

                if (data.group_id === '') {
                    delete ind.comment;
                } else if (groupComment) {
                    ind.comment = groupComment;
                }

                DataManager.localData.individuals.set(id.toString(), ind);
            }
        });

        UI.renderParticipantList(DataManager.getFilteredIndividuals());
    },

    handleGroupCommentUpdated: function(data, message) {
        DataManager.localData.individuals.forEach(ind => {
            if (ind.group_id === data.group_id) {
                ind.comment = data.comment;
            }
        });

        UI.renderParticipantList(DataManager.getFilteredIndividuals());
    },

    handlePartnerAdding: function(data, message) {
        DataManager.localData.partners.set(data.id.toString(), data);
        UI.updatePartnerPills(Array.from(DataManager.localData.partners.values()));
        StatsManager.updateLocalStats();
    },

    handlePartnerAdded: function(data, message) {
        DataManager.localData.partners.delete(data.temp_id.toString());
        DataManager.localData.partners.set(data.real_id.toString(), {
            id: data.real_id,
            name: data.name,
            size: data.size,
            input_duration: data.input_duration,
            total_duration: data.total_duration,
            has_left: data.has_left
        });

        UI.updatePartnerPills(Array.from(DataManager.localData.partners.values()));
        StatsManager.updateWeeklyStats();
    },

    handlePartnerUpdated: function(data, message) {
        const partner = DataManager.localData.partners.get(data.id.toString());
        if (partner) {
            Object.assign(partner, data);
            DataManager.localData.partners.set(data.id.toString(), partner);
            UI.updatePartnerPills(Array.from(DataManager.localData.partners.values()));
            StatsManager.updateLocalStats();
            StatsManager.updateWeeklyStats();
            
            // Rafraîchir le modal si ouvert
            if ($('#partnersListModal').is(':visible')) {
                PartnerManager.loadPartners();
            }
        }
    },

    handlePartnerLeftUpdated: function(data, message) {
        const partner = DataManager.localData.partners.get(data.id.toString());
        if (partner) {
            partner.has_left = data.has_left;
            DataManager.localData.partners.set(data.id.toString(), partner);
            UI.updatePartnerPills(Array.from(DataManager.localData.partners.values()));
            StatsManager.updateLocalStats();
            StatsManager.updateWeeklyStats();
            
            // Rafraîchir le modal si ouvert
            if ($('#partnersListModal').is(':visible')) {
                PartnerManager.loadPartners();
            }
        }
    },

    handlePartnerDeleted: function(data, message) {
        DataManager.localData.partners.delete(data.id.toString());
        UI.updatePartnerPills(Array.from(DataManager.localData.partners.values()));
        StatsManager.updateLocalStats();
        StatsManager.updateWeeklyStats();
        
        // Fermer le modal
        $('#partnersListModal').hide();
    },

    // ==================== MONITORING ====================

    startPingMonitor: function() {
        if (pingCheckInterval) {
            clearInterval(pingCheckInterval);
        }

        pingCheckInterval = setInterval(() => {
            const timeSinceLastPing = Date.now() - lastPingReceived;

            if (timeSinceLastPing > WS_CONFIG.PING_TIMEOUT) {
                console.log('[WS] Timeout ping détecté');
                this.handleServerCrash();
            }
        }, 1000);
    },

    handleServerCrash: function() {
        console.log('[WS] Détection crash serveur');

        if (pingCheckInterval) {
            clearInterval(pingCheckInterval);
            pingCheckInterval = null;
        }

        if (isElecting) {
            console.log('[WS] Élection déjà en cours, ignoré');
            return;
        }

        if (ws) {
            ws.close();
            ws = null;
        }

        const randomDelay = Math.random() * 2000;
        console.log('[WS] Attente ' + Math.round(randomDelay) + 'ms avant élection');

        setTimeout(() => {
            $.ajax({
                url: 'websocket_server/clear-ws-ip.php?force=true',
                method: 'GET',
                success: () => {
                    console.log('[WS] Relancement élection');
                    ServerElection.start();
                }
            });
        }, randomDelay);
    }
};

// ==================== ÉLECTION SERVEUR ====================

const ServerElection = {

    start: function() {
        if (isElecting) return;

        isElecting = true;
        console.log('[ELECTION] Début');
        this.tryAcquireLock(0);
    },

    tryAcquireLock: function(attempt) {
        if (attempt >= WS_CONFIG.MAX_ELECTION_RETRIES) {
            console.error('[ELECTION] Échec après', attempt, 'tentatives');
            isElecting = false;
            setTimeout(() => WebSocketClient.connect(), WS_CONFIG.RECONNECT_DELAY);
            return;
        }

        $.ajax({
            url: 'websocket_server/acquire-ws-lock.php',
            method: 'GET',
            data: {
                client_id: clientId
            },
            dataType: 'json',
            success: (response) => {
                if (response.acquired) {
                    console.log('[ELECTION] Verrou acquis');
                    this.becomeServer();
                } else {
                    console.log('[ELECTION] Verrou détenu, attente...');

                    setTimeout(() => {
                        $.ajax({
                            url: 'websocket_server/check-ws-server.php',
                            method: 'GET',
                            dataType: 'json',
                            success: (checkResponse) => {
                                if (checkResponse.active) {
                                    console.log('[ELECTION] Serveur trouvé');
                                    isElecting = false;
                                    WebSocketClient.connectToServer(checkResponse.ip);
                                } else {
                                    this.tryAcquireLock(attempt + 1);
                                }
                            }
                        });
                    }, WS_CONFIG.ELECTION_RETRY_DELAY);
                }
            },
            error: () => {
                console.error('[ELECTION] Erreur acquisition');
                isElecting = false;
            }
        });
    },

    becomeServer: function() {
        console.log('[ELECTION] Lancement serveur');

        $.ajax({
            url: 'websocket_server/start-ws-server.php',
            method: 'GET',
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    console.log('[ELECTION] Serveur démarré:', response.ip);
                    isElecting = false;
                    setTimeout(() => {
                        WebSocketClient.connectToServer(response.ip);
                    }, 2000);
                } else {
                    console.error('[ELECTION] Erreur:', response.error);
                    isElecting = false;
                }
            },
            error: () => {
                console.error('[ELECTION] Erreur lancement');
                isElecting = false;
            }
        });
    }
};

// ==================== EXPOSITION ====================

window.WebSocketClient = WebSocketClient;
window.ServerElection = ServerElection;
window.WS_CLIENT_ID = clientId;