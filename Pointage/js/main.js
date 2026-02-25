// Configuration globale
const CONFIG = {
    DEFAULT_DURATION: '01:00',
    AFTERNOON_START: '13:00',
    AFTERNOON_END: '23:30',
    isShowingAfternoonOnly: true
};

let pendingGroupAssignment = null;

// Tracker des nouvelles entrées cross-clients
const NewEntryTracker = {
    recentIds: new Map(), // Change Set → Map pour stocker timestamps

    add: function(id) {
        const startTime = Date.now();
        this.recentIds.set(id.toString(), startTime);
        setTimeout(() => {
            this.recentIds.delete(id.toString());
        }, 20000);
    },

    has: function(id) {
        return this.recentIds.has(id.toString());
    },

    getElapsedTime: function(id) {
        const startTime = this.recentIds.get(id.toString());
        return startTime ? Date.now() - startTime : 20000; //TimerBadge
    }
};

// Utilitaires
const Utils = {
    formatDateTime: function(useSelectedDate = true) {
        const now = new Date();
        const selectedDate = $("#dateSelector").val();
        if (useSelectedDate && selectedDate) {
            return selectedDate + ' ' +
                String(now.getHours()).padStart(2, '0') + ':' +
                String(now.getMinutes()).padStart(2, '0') + ':' +
                String(now.getSeconds()).padStart(2, '0');
        }
        return now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0') + ' ' +
            String(now.getHours()).padStart(2, '0') + ':' +
            String(now.getMinutes()).padStart(2, '0') + ':' +
            String(now.getSeconds()).padStart(2, '0');
    }
};

// Gestionnaire de données
const DataManager = {
    localData: {
        individuals: new Map(),
        partners: new Map()
    },

    getFilteredIndividuals: function() {
        let displayList = Array.from(this.localData.individuals.values());
        const today = new Date().toISOString().split('T')[0];
        const selectedDate = $("#dateSelector").val();
        const isToday = selectedDate === today;

        // Mettre à jour l'icône
        const icon = $('#toggleViewIcon');
        if (isToday && UI.isAfternoon()) {
            if (CONFIG.isShowingAfternoonOnly) {
                icon.removeClass('fa-user-times').addClass('fa fa-user-plus').css('color', '#237034');
                displayList = displayList.filter(ind =>
                    ind.arrival_time.split(' ')[1] >= CONFIG.AFTERNOON_START
                );
            } else {
                icon.removeClass('fa-user-plus').addClass('fa fa-user-times').css('color', '#b52d2d');
            }
        } else {
            icon.removeClass('fa fa-user-plus fa-user-times').css('color', '');
        }

        return displayList;
    }
};

// Gestionnaire des individus
const IndividualManager = {
    add: function(type) {
        WebSocketClient.send('individual_add', {
            type: type,
            arrival_time: Utils.formatDateTime(true),
            duration: CONFIG.DEFAULT_DURATION,
            date: $("#dateSelector").val()
        });
    },

    delete: function(id) {
        $("#customDialog").dialog({
            title: "Confirmation",
            modal: true,
            buttons: {
                "Supprimer": function() {
                    WebSocketClient.send('individual_delete', {
                        id: id,
                        date: $("#dateSelector").val()
                    });
                    $(this).dialog("close");
                },
                "Annuler": function() {
                    $(this).dialog("close");
                }
            },
            open: function() {
                $("#dialogMessage").text("Voulez-vous vraiment supprimer cet individu ?");
                $("#dialogInput").hide();
            }
        });
    },

    adjustDuration: function(id, minuteChange) {
        const individual = DataManager.localData.individuals.get(id.toString());
        if (!individual) return;

        let [hours, minutes] = individual.duration.split(':').map(Number);
        let totalMinutes = hours * 60 + minutes + minuteChange;
        if (totalMinutes < 60) totalMinutes = 60;

        const newDuration =
            Math.floor(totalMinutes / 60).toString().padStart(2, '0') + ':' +
            (totalMinutes % 60).toString().padStart(2, '0');

        WebSocketClient.send('individual_update_time', {
            id: id,
            duration: newDuration,
            date: $("#dateSelector").val()
        });
    },

    toggleLeft: function(id) {
        const individual = DataManager.localData.individuals.get(id.toString());
        if (!individual) return;

        WebSocketClient.send('individual_update_left', {
            id: id,
            has_left: individual.has_left === 1 ? 0 : 1,
            date: $("#dateSelector").val()
        });
    },

    associateParticipants: function() {
        const checkedItems = $('input[type="checkbox"]:checked');
        if (checkedItems.length < 2) {
            UI.showAlert("Vous devez sélectionner au moins deux individus pour les associer.", "Erreur");
            return;
        }

        let participantIds = [];
        let hasGroupedItem = false;

        checkedItems.each(function() {
            const id = $(this).closest('div.list-item').data('id');
            const individual = DataManager.localData.individuals.get(id.toString());
            if (individual && individual.group_id) {
                hasGroupedItem = true;
                return false;
            }
            participantIds.push(id);
        });

        if (hasGroupedItem) {
            UI.showAlert("Un ou plusieurs individus sont déjà dans un groupe.", "Erreur");
            return;
        }

        pendingGroupAssignment = participantIds;

        WebSocketClient.send('group_create', {
            created_at: Utils.formatDateTime(),
            date: $("#dateSelector").val()
        });

        checkedItems.prop('checked', false);
    },

    addParticipantToGroup: function() {
        const checkedItems = $('input[type="checkbox"]:checked');
        if (checkedItems.length !== 1) {
            UI.showAlert("Vous devez sélectionner un individu à ajouter à un groupe.", "Erreur");
            return;
        }

        const row = checkedItems.closest('div.list-item');
        const participantId = row.data('id');
        const individual = DataManager.localData.individuals.get(participantId.toString());

        if (individual.group_id) {
            UI.showAlert("Cet individu fait déjà partie d'un groupe.", "Erreur");
            return;
        }

        $("#customDialog").dialog({
            title: "Ajouter au groupe",
            modal: true,
            buttons: {
                "Ajouter": function() {
                    const groupToJoin = $("#dialogInput").val().toUpperCase();
                    const existingGroup = Array.from(DataManager.localData.individuals.values())
                        .find(ind => ind.group_id === groupToJoin);

                    if (!existingGroup) {
                        $(this).dialog("close");
                        setTimeout(() => {
                            UI.showAlert("Ce groupe n'existe pas.", "Erreur");
                        }, 100);
                        return;
                    }

                    WebSocketClient.send('group_assign', {
                        id: participantId,
                        group_id: groupToJoin,
                        created_at: Utils.formatDateTime(),
                        date: $("#dateSelector").val()
                    });

                    checkedItems.prop('checked', false);
                    $(this).dialog("close");
                },
                "Annuler": function() {
                    $(this).dialog("close");
                }
            },
            open: function() {
                $("#dialogMessage").html("Entrez l'ID du groupe (4 caractères)");
                $("#dialogInput").show().val('').focus();
            }
        });
    },

    removeFromGroup: function(id) {
        WebSocketClient.send('group_assign', {
            id: id,
            group_id: '',
            created_at: Utils.formatDateTime(),
            date: $("#dateSelector").val()
        });
    },
    changeType: function(id, currentType) {
        const types = ['ADULTE', 'JEUNE', 'ENFANT'];
        const buttons = {};

        types.forEach(type => {
            if (type !== currentType) {
                buttons[type] = function() {
                    WebSocketClient.send('individual_update_type', {
                        id: id,
                        type: type,
                        date: $("#dateSelector").val()
                    });
                    $(this).dialog("close");
                };
            }
        });

        buttons["Annuler"] = function() {
            $(this).dialog("close");
        };

        $("#customDialog").dialog({
            title: "Modifier le type",
            modal: true,
            buttons: buttons,
            open: function() {
                $("#dialogMessage").text("Choisir le nouveau type :");
                $("#dialogInput").hide();

                // Enlever le focus du premier bouton
                $(this).parent().find('button').blur();

                // Mettre Annuler en rouge
                $(this).parent().find('button:contains("Annuler")').css({
                    'background-color': '#d47d86',
                    'border-color': '#ba636c',
                    'color': 'white'
                });
            }
        });
    }
};

// Gestionnaire partenaires
const PartnerManager = {
    add: function() {
        const name = $('#partnerName').val();
        const size = $('#partnerSize').val();
        const duration = $('#partnerHours').val();

        $('#partnersModal').hide();
        $('#partnerName').val('');
        $('#partnerSize').val('1');
        $('#partnerHours').val('01:00');

        WebSocketClient.send('partner_add', {
            name: name,
            size: size,
            input_duration: duration,
            date: $("#dateSelector").val()
        });
    },

    edit: function(id, name, size, duration) {
        WebSocketClient.send('partner_update', {
            id: id,
            name: name,
            size: size,
            input_duration: duration,
            date: $("#dateSelector").val()
        });

        $('#editPartnerModal').hide();
    },

    toggleLeft: function(id) {
        const partner = DataManager.localData.partners.get(id.toString());
        if (!partner) return;

        WebSocketClient.send('partner_update_left', {
            id: id,
            has_left: partner.has_left === 1 ? 0 : 1,
            date: $("#dateSelector").val()
        });
    },

    delete: function(id) {
        $("#customDialog").dialog({
            title: "Confirmation",
            modal: true,
            buttons: {
                "Supprimer": function() {
                    WebSocketClient.send('partner_delete', {
                        id: id,
                        date: $("#dateSelector").val()
                    });
                    $(this).dialog("close");
                },
                "Annuler": function() {
                    $(this).dialog("close");
                }
            },
            open: function() {
                $("#dialogMessage").text("Voulez-vous vraiment supprimer ce partenaire ?");
                $("#dialogInput").hide();
            }
        });
    },

    loadPartners: function() {
        const date = $("#dateSelector").val();
        $.ajax({
            url: 'ws-handlers/api_Pointage.php',
            data: {
                action: 'getPartnersWeek',
                date: date
            },
            success: function(partners) {
                if (Array.isArray(partners)) {
                    UI.updatePartnersList(partners);
                }
            }
        });
    },

    bindEvents: function() {
        $('#partnerForm').submit(function(e) {
            e.preventDefault();
            PartnerManager.add();
        });

        $('#editPartnerForm').submit(function(e) {
            e.preventDefault();
            PartnerManager.edit(
                $('#editPartnerId').val(),
                $('#editPartnerName').val(),
                $('#editPartnerSize').val(),
                $('#editPartnerHours').val()
            );
        });

        $('.close').click(function() {
            $(this).closest('.modal').hide();
        });
    }
};

// Interface utilisateur
const UI = {
    init: function() {
        this.initDatePicker();
        this.initButtons();
        this.initEventListeners();

        $('.close').click(function() {
            $(this).closest('.modal').hide();
        });
    },

    showAlert: function(message, title = "Information") {
        $("#customDialog").dialog({
            title: title,
            modal: true,
            buttons: {
                "OK": function() {
                    $(this).dialog("close");
                }
            },
            open: function() {
                $("#dialogMessage").text(message);
                $("#dialogInput").hide();
            }
        });
    },

    initDatePicker: function() {
        $("#dateSelector").datepicker({
            dateFormat: 'yy-mm-dd',
            onSelect: function(selectedDate) {
                UI.updateDateDisplay($(this).datepicker('getDate'));

                // Demander les nouvelles données au serveur WS
                WebSocketClient.send('get_initial_state', {
                    date: selectedDate
                });
            }
        });

        $("#dateSelector").datepicker("setDate", new Date());
        this.updateDateDisplay($("#dateSelector").datepicker('getDate'));
    },

    updateDateDisplay: function(date) {
        const week = $.datepicker.iso8601Week(date);
        const dayName = $.datepicker.regional['fr'].dayNames[date.getDay()];
        const dayNumber = date.getDate();
        const monthName = $.datepicker.regional['fr'].monthNames[date.getMonth()];
        const displayText = dayName + " <strong>" + dayNumber + "</strong> " + monthName +
            " [ semaine <strong>" + week + "</strong> ]";
        $("#SayDay").html(displayText);
    },

    initButtons: function() {
        this.initializeButtonImg("#addADULTE", "images/Adulte.png", () => IndividualManager.add('ADULTE'));
        this.initializeButtonImg("#addEnfant", "images/Enfant.png", () => IndividualManager.add('ENFANT'));
        this.initializeButtonImg("#addJeune", "images/Jeune.png", () => IndividualManager.add('JEUNE'));
        this.initializeButtonImg("#openGroupModal", "images/Autre.png", function() {
            $('#partnersModal').show();
            $('#partnerName').val('');
            $('#partnerSize').val(1);
            $('#partnerHours').val('01:00');
        });

        this.initializeButtonTxt("#associateButton", "ASSOCIER", () => IndividualManager.associateParticipants());
        this.initializeButtonTxt("#addToGroupButton", "GROUPER QUELQU'UN", () => IndividualManager.addParticipantToGroup());
        this.initializeButtonTxt("#groupsButton", "LISTE DE GROUPES/PARTENAIRES", function() {
            PartnerManager.loadPartners();
            $('#partnersListModal').show();
        });
        this.initializeButtonTxt("#Statistique", "STATS", function() {
            window.location.href = 'Stat.html';
        });
    },

    initializeButtonImg: function(selector, imagePath, onClickHandler) {
        $(selector).dxButton({
            stylingMode: "contained",
            width: 165,
            height: 98,
            elementAttr: {
                'style': 'border: 1px solid #ddd; display: flex; justify-content: center; align-items: center; padding: 0;'
            },
            onClick: function(e) {
                onClickHandler(e);
                $(e.element).blur();
            },
            template: function(data, container) {
                $('<img>', {
                    src: imagePath,
                    css: {
                        width: '149px',
                        height: '82px'
                    }
                }).appendTo(container);
            }
        });
    },

    initializeButtonTxt: function(selector, text, onClickHandler) {
        $(selector).dxButton({
            text: text,
            onClick: function(e) {
                onClickHandler(e);
                $(e.element).blur();
            }
        });
    },

    isAfternoon: function() {
        const now = new Date();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();
        const [startHours, startMinutes] = CONFIG.AFTERNOON_START.split(':').map(Number);
        const afternoonStartMinutes = startHours * 60 + startMinutes;
        return currentMinutes >= afternoonStartMinutes;
    },

    initEventListeners: function() {
        $('#participantList').on('click', '[data-action]', function(e) {
            const action = $(this).data('action');
            const participantId = $(this).data('id');

            switch (action) {
                case 'add':
                    IndividualManager.adjustDuration(participantId, 60);
                    break;
                case 'remove':
                    IndividualManager.adjustDuration(participantId, -60);
                    break;
                case 'toggle-left':
                    IndividualManager.toggleLeft(participantId);
                    break;
                case 'delete':
                    IndividualManager.delete(participantId);
                    break;
                case 'ungroup':
                    IndividualManager.removeFromGroup(participantId);
                    break;
            }
            e.stopPropagation();
        });

        $('#toggleViewIcon').on('click', () => {
            CONFIG.isShowingAfternoonOnly = !CONFIG.isShowingAfternoonOnly;
            UI.renderParticipantList(DataManager.getFilteredIndividuals());
            StatsManager.updateLocalStats();
        });
    },

    showTooltip: function(element, text) {
        let tooltip = $('#customTooltip');
        if (!tooltip.length) {
            tooltip = $('<div id="customTooltip" class="custom-tooltip"></div>').appendTo('body');
        }
        tooltip.text(text);
        const offset = element.offset();
        const tooltipHeight = tooltip.outerHeight();
        const tooltipWidth = tooltip.outerWidth();
        const elementWidth = element.outerWidth();
        tooltip.css({
            top: offset.top - tooltipHeight - 10,
            left: offset.left + elementWidth / 2 - tooltipWidth / 2
        }).stop(true, true).fadeIn(200);
    },

    hideTooltip: function() {
        $('#customTooltip').stop(true, true).fadeOut(200);
    },

    renderParticipantList: function(participants) {
        const tbody = $("#participantList");
        tbody.empty();

        participants.sort((a, b) => {
            const timeA = new Date(a.arrival_time);
            const timeB = new Date(b.arrival_time);
            if (timeB - timeA !== 0) return timeB - timeA;
            return b.id - a.id;
        });

        const seen = new Set();
        let number = participants.length;

        participants.forEach(participant => {
            if (seen.has(participant.id)) return;

            if (participant.group_id) {
                const groupMembers = participants
                    .filter(p => p.group_id === participant.group_id)
                    .sort((a, b) => {
                        // ADULTE en premier
                        if (a.type === 'ADULTE' && b.type !== 'ADULTE') return -1;
                        if (a.type !== 'ADULTE' && b.type === 'ADULTE') return 1;

                        // JEUNE avant ENFANT si pas d'ADULTE
                        if (a.type === 'JEUNE' && b.type === 'ENFANT') return -1;
                        if (a.type === 'ENFANT' && b.type === 'JEUNE') return 1;

                        return 0;
                    });

                const groupWrapper = $('<div>', {
                    class: 'group-wrapper'
                });

                groupMembers.forEach((member, index) => {
                    const row = this.createParticipantRow(member, number--, index === 0);
                    if (index > 0) row.addClass('group-member-divider');
                    groupWrapper.append(row);
                    seen.add(member.id);
                });

                tbody.append(groupWrapper);
            } else {
                const row = this.createParticipantRow(participant, number--, false);
                tbody.append(row);
                seen.add(participant.id);
            }
        });
    },

    // Fonctions helper à ajouter dans l'objet UI (avant createParticipantRow)
    // Compatible Chrome v57+

    _createDialog: function(title, message, initialValue, onSave) {
        var self = this;
        $("#customDialog").dialog({
            title: title,
            modal: true,
            buttons: {
                "Enregistrer": function() {
                    onSave($("#dialogInput").val());
                    $(this).dialog("close");
                },
                "Annuler": function() {
                    $(this).dialog("close");
                }
            },
            open: function() {
                $("#dialogMessage").html(message);
                $("#dialogInput").show().val(initialValue || '').focus();
            }
        });
    },

    _createIconWithTooltip: function(iconClass, styles, onClick, tooltipText) {
        var icon = $('<i>', {
            class: iconClass,
            css: styles
        });
        if (onClick) {
            icon.on('click', onClick);
        }
        if (tooltipText) {
            icon.hover(
                function() {
                    UI.showTooltip($(this), tooltipText);
                },
                function() {
                    UI.hideTooltip();
                }
            );
        }
        return icon;
    },

    _createButton: function(action, participant, styles) {
        styles = styles || {};
        var icons = {
            add: 'fa-plus',
            remove: 'fa-minus',
            delete: 'fa-trash'
        };
        var defaultStyles = {
            add: {
                backgroundColor: '#7dd494',
                borderColor: '#63ba6a',
                color: 'white',
                marginRight: '5px',
                height: '25px'
            },
            remove: {
                backgroundColor: '#7dd494',
                borderColor: '#63ba6a',
                color: 'white',
                marginRight: '5px',
                height: '25px'
            },
            delete: {
                backgroundColor: '#d47d86',
                borderColor: '#ba636c',
                color: 'white'
            }
        };

        return $('<button>', {
            class: 'dx-button' + (action === 'delete' ? ' delete-button' : ''),
            'data-action': action,
            'data-id': participant.id,
            css: $.extend({}, defaultStyles[action], styles)
        }).append($('<i>', {
            class: 'fa ' + icons[action]
        }));
    },

    // Version optimisée de createParticipantRow - Compatible Chrome v57
    createParticipantRow: function(participant, number, isFirstInGroup) {
        var self = this;
        var formattedNumber = (number < 10 ? '0' : '') + number;

        var card = $('<div>', {
            class: 'list-item',
            'data-id': participant.id,
            'data-group': participant.group_id || ''
        }).toggleClass('participant-left', participant.has_left == 1);

        var cols = {
            col1: $('<div>', {
                class: 'item-col T_ID'
            }),
            col2: $('<div>', {
                class: 'item-col T_Ind'
            }),
            col3: $('<div>', {
                class: 'item-col T_TCom'
            }),
            col4: $('<div>', {
                class: 'item-col T_TUse'
            }),
            col5: $('<div>', {
                class: 'item-col T_Act'
            }),
            col6: $('<div>', {
                class: 'item-col T_IDG'
            })
        };

        // Colonne 1 : Numéro + Checkbox
        cols.col1.append([
            $('<span>', {
                class: 'number-zone',
                text: formattedNumber
            }),
            $('<input>', {
                type: 'checkbox'
            })
        ]);

        // Colonne 2 : Icône principale (commentaire/prénom/invisible)
        if (participant.group_id && isFirstInGroup) {
            // Premier du groupe : icône commentaire
            cols.col2.append(this._createIconWithTooltip(
                'fa fa-' + (participant.comment ? 'eye' : 'comment'), {
                    cursor: 'pointer',
                    marginRight: '8px'
                },
                function() {
                    self._createDialog(
                        "Commentaire du groupe",
                        "Saisissez un commentaire.",
                        participant.comment,
                        function(value) {
                            WebSocketClient.send('group_comment', {
                                group_id: participant.group_id,
                                comment: value,
                                date: $("#dateSelector").val()
                            });
                        }
                    );
                },
                participant.comment
            ));
        } else if (participant.group_id) {
            // Autre membre : icône invisible
            cols.col2.append($('<i>', {
                class: 'fa fa-invisible',
                css: {
                    marginRight: '8px'
                }
            }));
        } else {
            // Individu seul : icône prénom
            cols.col2.append(this._createIconWithTooltip(
                'fa fa-user-circle', {
                    color: participant.whoIs ? '#4CAF50' : '#c4c4c4',
                    marginRight: '8px',
                    cursor: 'pointer'
                },
                function() {
                    self._createDialog(
                        "Prénom",
                        "Saisissez un prénom.",
                        participant.whoIs,
                        function(value) {
                            WebSocketClient.send('individual_who', {
                                id: participant.id,
                                whoIs: value,
                                date: $("#dateSelector").val()
                            });
                        }
                    );
                },
                participant.whoIs
            ));
        }

        // Icône entrée/sortie
        cols.col2.append($('<i>', {
            class: participant.has_left == 1 ? 'fa fa-sign-in' : 'fa fa-sign-out',
            css: {
                color: participant.has_left == 1 ? '#4CAF50' : '#bd514a',
                marginRight: '8px',
                cursor: 'pointer'
            }
        }).on('click', function(e) {
            e.stopPropagation();
            IndividualManager.toggleLeft(participant.id);
        }));

        // Icône changement type + Label type
        cols.col2.append([
            $('<i>', {
                class: 'fa fa-refresh',
                css: {
                    cursor: 'pointer',
                    marginRight: '5px',
                    fontSize: '14px',
                    color: '#666'
                }
            }).on('click', function(e) {
                e.stopPropagation();
                IndividualManager.changeType(participant.id, participant.type);
            }),
            $('<span>', {
                class: 'C_Type',
                text: participant.type
            })
        ]);

        // Colonnes 3-4 : Arrivée + Durée
        cols.col3.text(participant.arrival_time.split(' ')[1].substring(0, 5));
        cols.col4.append($('<span>', {
            class: 'C_Duration',
            text: participant.duration
        }));

        // Colonne 5 : Boutons actions
        if (participant.has_left == 1) {
            cols.col5.append(this._createButton('add', participant, {
                backgroundColor: '#bdbdbd',
                color: '#934d4d',
                marginRight: '58px',
                borderColor: '#a1a1a1'
            }));
        } else {
            cols.col5.append([
                this._createButton('add', participant),
                this._createButton('remove', participant),
                this._createButton('delete', participant)
            ]);
        }

        // Colonne 6 : ID Groupe + Badge
        if (participant.group_id) {
            const badgeContainer = $('<span>', {
                class: 'new-entry-badge-container',
                css: {
                    position: 'relative',
                    display: 'inline-block',
                    marginLeft: '-8px'
                }
            });

            if (NewEntryTracker.has(participant.id)) {
                const elapsed = NewEntryTracker.getElapsedTime(participant.id);
                const remaining = 20000 - elapsed;

                if (remaining > 0) {
                    const animName = 'fadeOut_' + participant.id + '_' + Date.now();
                    const visibleDuration = Math.min(15000, remaining);
                    const visiblePercent = (visibleDuration / remaining) * 100;

                    const style = document.createElement('style');
                    style.textContent = `
                @keyframes ${animName} {
                    0%, ${visiblePercent}% { opacity: 1; }
                    100% { opacity: 0; }
                }
                [data-id="${participant.id}"] .new-entry-badge-container.show-badge::after {
                    animation: ${animName} ${remaining}ms ease-out forwards;
                }
            `;
                    document.head.appendChild(style);
                    badgeContainer.addClass('show-badge');
                }
            }

            cols.col6.append([
                $('<i>', {
                    class: 'fa fa-chain-broken',
                    css: {
                        color: '#cc5047',
                        cursor: 'pointer',
                        marginRight: '5px'
                    },
                    'data-action': 'ungroup',
                    'data-id': participant.id
                }),
                participant.group_id,
                badgeContainer
            ]);
        } else {
            const badgeContainer = $('<span>', {
                class: 'new-entry-badge-container',
                css: {
                    position: 'relative',
                    display: 'inline-block',
                    height: '20px',
                    marginLeft: '35px'
                }
            });

            if (NewEntryTracker.has(participant.id)) {
                const elapsed = NewEntryTracker.getElapsedTime(participant.id);
                const remaining = 20000 - elapsed;

                if (remaining > 0) {
                    const animName = 'fadeOut_' + participant.id + '_' + Date.now();
                    const visibleDuration = Math.min(15000, remaining);
                    const visiblePercent = (visibleDuration / remaining) * 100;

                    const style = document.createElement('style');
                    style.textContent = `
                @keyframes ${animName} {
                    0%, ${visiblePercent}% { opacity: 1; }
                    100% { opacity: 0; }
                }
                [data-id="${participant.id}"] .new-entry-badge-container.show-badge::after {
                    animation: ${animName} ${remaining}ms ease-out forwards;
                }
            `;
                    document.head.appendChild(style);
                    badgeContainer.addClass('show-badge');
                }
            }

            cols.col6.append(badgeContainer);
        }

        card.append(cols.col1, cols.col2, cols.col3, cols.col4, cols.col5, cols.col6);

        if (participant.group_id) {
            card.css('backgroundColor', participant.color || '#ffffff');
        }

        return card;
    },

    updatePartnerPills: function(partners) {
        const pillsContainer = $('#partnersPills');
        pillsContainer.empty();

        partners.forEach(function(partner) {
            const pill = $('<div>').addClass('partner-pill').css({
                borderColor: partner.has_left == 1 ? '#f44336' : '#4CAF50'
            });

            const icon = $('<i>', {
                class: partner.has_left == 1 ? 'fa fa-sign-in' : 'fa fa-sign-out',
                css: {
                    color: partner.has_left == 1 ? '#4CAF50' : '#bd514a',
                    marginRight: '8px',
                    cursor: 'pointer'
                }
            }).on('click', function(e) {
                e.stopPropagation();
                PartnerManager.toggleLeft(partner.id);
            });

            if (partner.has_left == 1) {
                pill.addClass('partner-left');
            }

            pill.append(icon);
            pill.append('<span style="color:#212020; font-weight:bold">' + partner.name +
                ' </span><span style="color:green" class="count">' + partner.size + '</span>');
            pillsContainer.append(pill);
        });
    },

    updatePartnersList: function(partners) {
        const partnersList = $('#partnersList');
        partnersList.empty();
        const daysOfWeek = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE'];
        const byDay = {};

        partners.forEach(function(partner) {
            const date = new Date(partner.created_at);
            let dayIndex = date.getDay();
            if (dayIndex === 0) dayIndex = 7;
            const day = daysOfWeek[dayIndex - 1];

            if (!byDay[day]) byDay[day] = [];
            byDay[day].push(partner);
        });

        daysOfWeek.forEach(function(day) {
            if (byDay[day] && byDay[day].length > 0) {
                const dayHeader = $('<tr class="day-header">')
                    .append($('<td colspan="5">').text(day));
                partnersList.append(dayHeader);

                byDay[day].forEach(function(partner) {
                    const row = UI.createPartnerRow(partner);
                    partnersList.append(row);
                });
            }
        });
    },

    createPartnerRow: function(partner) {
        const row = $('<tr class="partner-row">');
        row.append($('<td>').text(partner.name));
        row.append($('<td class="text-center">').text(partner.size));
        row.append($('<td class="text-center">').text(partner.input_duration));
        row.append($('<td class="text-center">').text(partner.total_duration));

        const actionCell = $('<td class="text-center">');
        const editBtn = $('<button class="edit-btn">')
            .html('<i class="fa fa-pencil"></i>')
            .click(function() {
                $('#editPartnerId').val(partner.id);
                $('#editPartnerName').val(partner.name);
                $('#editPartnerSize').val(partner.size);
                $('#editPartnerHours').val(partner.input_duration);
                $('#editPartnerModal').show();
            });

        const deleteBtn = $('<button class="delete-btn">')
            .html('<i class="fa fa-trash"></i>')
            .click(function() {
                PartnerManager.delete(partner.id);
            });

        actionCell.append(editBtn, deleteBtn);
        row.append(actionCell);
        return row;
    }
};

// Statistiques
const StatsManager = {
    updateLocalStats: function() {
        const individuals = Array.from(DataManager.localData.individuals.values());
        const now = new Date();
        const isAfternoon = now.getHours() >= 13;
        const today = new Date().toISOString().split('T')[0];
        const selectedDate = $("#dateSelector").val();
        const isToday = selectedDate === today;

        const presentCount = individuals.filter(ind => {
            if (!isToday || !isAfternoon) {
                return ind.has_left == 0;
            } else {
                const arrivalTime = ind.arrival_time.split(' ')[1];
                return ind.has_left == 0 && arrivalTime >= CONFIG.AFTERNOON_START;
            }
        }).length;

        let indivMinutes = 0;
        individuals.forEach(function(ind) {
            const [hours, minutes] = ind.duration.split(':').map(Number);
            indivMinutes += hours * 60 + minutes;
        });

        const partners = Array.from(DataManager.localData.partners.values());
        let partnerCount = 0;
        let partnerMinutes = 0;
        let presentPartnerCount = 0;

        partners.forEach(function(partner) {
            const size = parseInt(partner.size);
            partnerCount += size;
            if (partner.has_left != 1) {
                presentPartnerCount += size;
            }
            const [hours, minutes] = partner.total_duration.split(':').map(Number);
            partnerMinutes += hours * 60 + minutes;
        });

        const indivHours = this.formatDuration(indivMinutes);
        const partnerHours = this.formatDuration(partnerMinutes);
        const totalHours = this.formatDuration(indivMinutes + partnerMinutes);

        if (presentPartnerCount >= 1) {
            $('.CountInside').html(presentCount + presentPartnerCount +
                '<small style="font-size:10px; color:#570808"> ( ' + presentCount + '+' +
                presentPartnerCount + ' )</small>');
        } else {
            $('.CountInside').html(presentCount + presentPartnerCount);
        }

        $('#publicCount').text(individuals.length);
        $('#publicHours').text(indivHours);
        $('#partenaireCount').text(partnerCount);
        $('#partenaireHours').text(partnerHours);
        $('#totalPresences').text(individuals.length + partnerCount);
        $('#totalHours').text(totalHours);
    },

    updateWeeklyStats: function() {
        const date = $("#dateSelector").val();
        $.ajax({
            url: 'ws-handlers/api_Pointage.php',
            data: {
                action: 'getStatsWeek',
                date: date
            },
            success: function(data) {
                if (data.error) {
                    console.error('Erreur stats hebdo:', data.error);
                    return;
                }

                $('#publicCountW').text(data.individuals_count || 0);
                $('#publicHoursW').text(data.individuals_hours || '00:00');
                $('#partenaireCountW').text(data.partners_count || 0);
                $('#partenaireHoursW').text(data.partners_hours || '00:00');
                $('#totalPresencesW').text(data.total_count || 0);
                $('#totalHoursW').text(data.total_hours || '00:00');
            }
        });
    },

    formatDuration: function(minutes) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
    }
};

// Auto-refresh
const AutoRefresh = {
    REFRESH_HOUR: 13,
    START_HOUR: 0,

    init: function() {
        const now = new Date();
        const currentHour = now.getHours();

        if (currentHour >= this.START_HOUR && currentHour < this.REFRESH_HOUR) {
            this.scheduleRefresh();
        }
    },

    scheduleRefresh: function() {
        const now = new Date();
        const target = new Date(
            now.getFullYear(),
            now.getMonth(),
            now.getDate(),
            this.REFRESH_HOUR,
            0,
            0
        );

        const delay = target - now;

        if (delay > 0) {
            setTimeout(() => {
                location.reload();
            }, delay);
        }
    }
};

// Callback groupe
window.onGroupCreated = function(groupId) {
    if (pendingGroupAssignment && pendingGroupAssignment.length > 0) {
        const selectedDate = $("#dateSelector").val();
        WebSocketClient.send('group_assign', {
            id: pendingGroupAssignment.join(','),
            group_id: groupId,
            created_at: selectedDate + ' ' + new Date().toTimeString().split(' ')[0], // Date sélectionnée + heure actuelle
            date: selectedDate
        });

        pendingGroupAssignment = null;
    }
};

// Initialisation
$(document).ready(function() {
    $.datepicker.regional['fr'] = {
        closeText: 'Fermer',
        prevText: '&#x3c;Préc',
        nextText: 'Suiv&#x3e;',
        currentText: 'Aujourd\'hui',
        monthNames: ['Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août',
            'Septembre', 'Octobre', 'Novembre', 'Decembre'
        ],
        monthNamesShort: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'],
        dayNames: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
        dayNamesShort: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
        dayNamesMin: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'],
        weekHeader: ' ',
        dateFormat: 'dd-mm-yy',
        firstDay: 1,
        isRTL: false,
        showMonthAfterYear: false,
        yearSuffix: '',
        maxDate: '+0D',
        numberOfMonths: 1,
        showButtonPanel: false,
        showWeek: true,
        showAnim: "drop",
        showButtonPanel: true,
        showOn: "button",
        buttonImage: "images/calendar-color-icon.png",
        buttonImageOnly: true,
        buttonText: "Sélectionner une date"
    };
    $.datepicker.setDefaults($.datepicker.regional['fr']);

    $("#SayDay").html("DateTest");

    UI.init();
    WebSocketClient.init();
    PartnerManager.bindEvents();
    AutoRefresh.init();
});