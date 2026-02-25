// Variables globales
var currentAnimationId = null;
var currentAnimationData = null;
var types = [];
var animateurs = [];
var currentMonth = new Date().getMonth();
var currentYear = new Date().getFullYear();
var allAnimations = [];
var selectedDates = [];

var isPeriodMode = false;
var startMonth = currentMonth;
var startYear = currentYear;
var endMonth = currentMonth;
var endYear = currentYear;



// ==================== TIME PICKER ====================

/**
 * Configure un groupe time picker (heures + minutes)
 */
function setupTimePicker(hoursId, minutesId) {
    var hoursInput = document.getElementById(hoursId);
    var minutesInput = document.getElementById(minutesId);
    
    if (!hoursInput || !minutesInput) return;
    
    // Heures : uniquement chiffres, max 23, auto-jump vers minutes
    hoursInput.addEventListener('input', function(e) {
        var value = this.value.replace(/[^0-9]/g, '');
        
        if (value.length > 2) {
            value = value.substring(0, 2);
        }
        
        if (value.length === 2) {
            var num = parseInt(value, 10);
            if (num > 23) {
                value = '23';
            }
        }
        
        this.value = value;
        
        // Auto-jump vers minutes après 2 chiffres
        if (value.length === 2) {
            minutesInput.focus();
            minutesInput.select();
        }
    });
    
    // Formater à 2 chiffres quand on quitte le champ
    hoursInput.addEventListener('blur', function() {
        if (this.value.length === 1) {
            this.value = '0' + this.value;
        }
    });
    
    // Minutes : uniquement chiffres, max 59
    minutesInput.addEventListener('input', function(e) {
        var value = this.value.replace(/[^0-9]/g, '');
        
        if (value.length > 2) {
            value = value.substring(0, 2);
        }
        
        if (value.length === 2) {
            var num = parseInt(value, 10);
            if (num > 59) {
                value = '59';
            }
        }
        
        this.value = value;
    });
    
    // Formater à 2 chiffres quand on quitte le champ
    minutesInput.addEventListener('blur', function() {
        if (this.value === '') {
            this.value = '00';
        } else if (this.value.length === 1) {
            this.value = '0' + this.value;
        }
    });
    
    // Sélectionner tout au focus
    hoursInput.addEventListener('focus', function() {
        this.select();
    });
    minutesInput.addEventListener('focus', function() {
        this.select();
    });
}

/**
 * Récupère l'heure au format HH:MM depuis les inputs
 */
function getTimeValue(prefix) {
    var h = $('#' + prefix + '_h').val();
    var m = $('#' + prefix + '_m').val();
    
    if (!h || h === '') {
        return null;
    }
    
    // Formater h à 2 chiffres
    if (h.length === 1) {
        h = '0' + h;
    }
    
    // Formater m à 2 chiffres (défaut 00)
    if (!m || m === '') {
        m = '00';
    } else if (m.length === 1) {
        m = '0' + m;
    }
    
    return h + ':' + m;
}

/**
 * Définit l'heure dans les inputs depuis une chaine HH:MM
 */
function setTimeValue(prefix, timeStr) {
    if (!timeStr || timeStr === '') {
        $('#' + prefix + '_h').val('');
        $('#' + prefix + '_m').val('00');
        return;
    }
    
    var parts = timeStr.split(':');
    $('#' + prefix + '_h').val(parts[0] || '');
    $('#' + prefix + '_m').val(parts[1] || '00');
}

/**
 * Réinitialise tous les time pickers du formulaire
 */
function resetTimePickers() {
    $('#heure_debut_h').val('');
    $('#heure_debut_m').val('00');
    $('#heure_fin_h').val('');
    $('#heure_fin_m').val('00');
}

// ==================== INITIALISATION ====================

$(document).ready(function() {
    // Initialiser les time pickers
    setupTimePicker('heure_debut_h', 'heure_debut_m');
    setupTimePicker('heure_fin_h', 'heure_fin_m');
    
    
    // Toggle présence réelle selon checkbox compter_stats
    $('#compter_stats').on('change', function() {
        if (this.checked) {
            $('#presenceReelleGroup').slideDown(200);
        } else {
            $('#presenceReelleGroup').slideUp(200);
            $('#presence_reelle').val('');
        }
    });
    // Toggle période personnalisée
    $('#periodToggle').on('change', function() {
        if (this.checked) {
            isPeriodMode = true;
            $('#periodSelectors').slideDown(200);
            $('.month-selector').hide();
        } else {
            isPeriodMode = false;
            $('#periodSelectors').slideUp(200);
            $('.month-selector').show();
        }
        loadAnimations();
    });
    
    // Navigation mois simple
    $('#prevMonth').on('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        updateMonthDisplay();
        loadAnimations();
    });
    
    $('#nextMonth').on('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        updateMonthDisplay();
        loadAnimations();
    });
    
    // Navigation période - début
    $('#prevMonthStart').on('click', function() {
        startMonth--;
        if (startMonth < 0) {
            startMonth = 11;
            startYear--;
        }
        updateMonthDisplay();
        loadAnimations();
    });
    
    $('#nextMonthStart').on('click', function() {
        startMonth++;
        if (startMonth > 11) {
            startMonth = 0;
            startYear++;
        }
        updateMonthDisplay();
        loadAnimations();
    });
    
    // Navigation période - fin
    $('#prevMonthEnd').on('click', function() {
        endMonth--;
        if (endMonth < 0) {
            endMonth = 11;
            endYear--;
        }
        updateMonthDisplay();
        loadAnimations();
    });
    
    $('#nextMonthEnd').on('click', function() {
        endMonth++;
        if (endMonth > 11) {
            endMonth = 0;
            endYear++;
        }
        updateMonthDisplay();
        loadAnimations();
    });
    
    // Datepicker multi-dates
    $('#date_temp').datepicker({
        onSelect: function(dateText) {
            if (dateText && selectedDates.indexOf(dateText) === -1) {
                selectedDates.push(dateText);
                refreshDatesList();
                $('#date_temp').val('');
            }
        }
    });
    
    updateMonthDisplay();
    loadAnimations();
    loadTypes();
    loadAnimateurs();
    
    $('#animationForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        saveAnimation();
    });
    
    $('#inscriptionForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        addInscription();
    });
    
    $('#typeForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        addType();
    });
    
    $('#animateurForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        addAnimateur();
    });
});

// ==================== AFFICHAGE ====================

function updateMonthDisplay() {
    var mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    
    $('#currentMonth').text(mois[currentMonth] + ' ' + currentYear);
    $('#startMonth').text(mois[startMonth] + ' ' + startYear);
    $('#endMonth').text(mois[endMonth] + ' ' + endYear);
}

function refreshDatesList() {
    var html = '';
    for (var i = 0; i < selectedDates.length; i++) {
        html += '<div class="date-badge">';
        html += '<span>' + selectedDates[i] + '</span>';
        html += '<button type="button" class="remove-date" data-date="' + selectedDates[i] + '">X</button>';
        html += '</div>';
    }
    
    $('#datesList').html(html);
    
    $('.remove-date').off('click').on('click', function() {
        var dateToRemove = $(this).data('date');
        selectedDates = selectedDates.filter(function(d) { return d !== dateToRemove; });
        refreshDatesList();
    });
}

// ==================== CHARGEMENT DONNÉES ====================

function loadAnimations() {
    var params = { action: 'getAnimations' };
    
    if (isPeriodMode) {
        params.periodMode = 1;
        params.startMonth = startMonth + 1;
        params.startYear = startYear;
        params.endMonth = endMonth + 1;
        params.endYear = endYear;
    } else {
        params.month = currentMonth + 1;
        params.year = currentYear;
    }
    
    $.ajax({
        url: 'animations_api.php',
        data: params,
        success: function(data) {
            allAnimations = data;
            filterAnimations();
        },
        error: function(xhr) {
            customAlert('Erreur lors du chargement des animations', 'Erreur');
        }
    });
}

function filterAnimations() {
    var selectedTypes = [];
    $('.filter-checkbox-type:checked').each(function() {
        selectedTypes.push($(this).val());
    });
    
    var selectedAnimateurs = [];
    $('.filter-checkbox-animateur:checked').each(function() {
        selectedAnimateurs.push($(this).val());
    });
    
    if (selectedTypes.length === 0 && selectedAnimateurs.length === 0) {
        displayAnimations(allAnimations);
        return;
    }
    
    var filtered = allAnimations.filter(function(anim) {
        var matchType = selectedTypes.indexOf(anim.type_nom) >= 0;
        var matchAnimateur = selectedAnimateurs.indexOf(anim.animateur_nom) >= 0;
        return matchType || matchAnimateur;
    });
    
    displayAnimations(filtered);
}

function displayAnimations(data) {
    var html = '';
    
    if (data.length === 0) {
        html = '<tr><td colspan="6" style="text-align:center;">Aucune animation</td></tr>';
    } else {
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (var i = 0; i < data.length; i++) {
            var anim = data[i];
            var placesText = '-';
            
            if (!anim.date) continue;
            if (!anim.date) continue;
            
            var dateParts = anim.date.split('-');
            var animDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            animDate.setHours(0, 0, 0, 0);
            var isPast = animDate < today;
            
            if (anim.nb_places) {
                var confirmes = (anim.total_inscrits || 0);
                var attente = anim.total_liste_attente || 0;
                var restantes = anim.nb_places - confirmes;
                
                if (restantes < 0) {
                    var depassement = Math.abs(restantes);
                    placesText = '0/' + anim.nb_places + ' <span style="color:red;">[+' + depassement + ']</span>';
                } else {
                    var color = '';
                    if (restantes === 4) {
                        color = 'orange';
                    } else if (restantes < 4) {
                        color = 'red';
                    }
                    
                    if (color) {
                        placesText = '<span style="color:' + color + ';">' + restantes + '</span>/' + anim.nb_places;
                    } else {
                        placesText = restantes + '/' + anim.nb_places;
                    }
                }
                
                if (attente > 0) {
                    placesText += ' <span style="color:orange;">(' + attente + ')</span>';
                }
            }
            
            var date = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            var moisComplet = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                              'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            var dateFormatted = date.getDate() + ' ' + moisComplet[date.getMonth()].substring(0, 12);
            var horaire = (anim.heure_debut || '').substring(0, 5) + ' - ' + (anim.heure_fin || '').substring(0, 5);
            
            html += '<tr' + (isPast ? ' class="animation-passee"' : '') + '>';
            html += '<td>' + dateFormatted + '</td>';
            html += '<td>' + horaire + '</td>';
            html += '<td>' + anim.nom + '</td>';
            html += '<td>' + (anim.type_nom || '-') + '</td>';
            html += '<td style="text-align: center;">' + placesText + '</td>';
            html += '<td style="white-space: nowrap;">';
            html += '<button class="btn-table" onclick="showInscriptionsModal(' + anim.id + ')" title="Inscriptions - '+ anim.nom +'"><i class="fa fa-user-plus"></i></button> ';
            html += '<button class="btn-table-orange" onclick="editAnimation(' + anim.id + ')" title="Modifier"><i class="fa fa-pencil"></i></button> ';
            html += '<button class="btn-table btn-table-red" onclick="deleteAnimation(' + anim.id + ')" title="Supprimer"><i class="fa fa-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        }
    }
    
    $('#animationsList').html(html);
}

function showCreateModal() {
    currentAnimationId = null;
    $('.modal').hide();
    currentAnimationData = null;
    selectedDates = [];
    $('#modalTitle').text('Créer une animation');
    $('#animationForm')[0].reset();
    $('#animationId').val('');
    $('#presenceReelleGroup').hide();
    $('#date_temp').val('');
    refreshDatesList();
	resetTimePickers();
    $('#animationModal').show();
}

function editAnimation(id) {
    currentAnimationId = id;
    
    $.ajax({
        url: 'animations_api.php',
        data: { action: 'getAnimation', id: id },
        success: function(data) {
            currentAnimationData = data;
            selectedDates = [data.date];
            
            $('#modalTitle').text('Modifier l\'animation');
            $('#animationId').val(data.id);
            $('#nom').val(data.nom);
            
            setTimeValue('heure_debut', data.heure_debut);
            setTimeValue('heure_fin', data.heure_fin);
            
            $('#type_id').val(data.type_id || '');
            $('#nb_places').val(data.nb_places || '');
            $('#lieu').val(data.lieu || '');
            $('#animateur_id').val(data.animateur_id || '');
            $('#compter_stats').prop('checked', data.compter_stats == 1);
            
            if (data.presence_reelle !== null) {
                $('#presenceReelleGroup').show();
                $('#presence_reelle').val(data.presence_reelle);
            } else {
                $('#presenceReelleGroup').hide();
                $('#presence_reelle').val('');
            }
            
            refreshDatesList();
            $('#animationModal').show();
        }
    });
}

function saveAnimation() {
    var animId = $('#animationId').val();
    
    // Validation des dates
    if (!animId && selectedDates.length === 0) {
        customAlert('Ajoutez au moins une date', 'Erreur');
        return;
    }
    
    // Récupérer les heures
    var heureDebut = getTimeValue('heure_debut');
    var heureFin = getTimeValue('heure_fin');
    
    // Validation heures obligatoires
    if (!heureDebut) {
        customAlert('Veuillez saisir l\'heure de début', 'Erreur');
        $('#heure_debut_h').focus();
        return;
    }
    if (!heureFin) {
        customAlert('Veuillez saisir l\'heure de fin', 'Erreur');
        $('#heure_fin_h').focus();
        return;
    }
    
    // Validation heure fin >= heure début
    var debutMinutes = parseInt(heureDebut.split(':')[0], 10) * 60 + parseInt(heureDebut.split(':')[1], 10);
    var finMinutes = parseInt(heureFin.split(':')[0], 10) * 60 + parseInt(heureFin.split(':')[1], 10);
    if (finMinutes < debutMinutes) {
        customAlert('L\'heure de fin ne peut pas être avant l\'heure de début', 'Erreur');
        $('#heure_fin_h').focus();
        return;
    }
    
    var baseData = {
        nom: $('#nom').val(),
        heure_debut: heureDebut,
        heure_fin: heureFin,
        type_id: $('#type_id').val() || null,
        nb_places: $('#nb_places').val() || null,
        lieu: $('#lieu').val() || null,
        animateur_id: $('#animateur_id').val() || null,
        compter_stats: $('#compter_stats').is(':checked') ? 1 : 0,
        presence_reelle: $('#presence_reelle').val() || null
    };
    
    if (animId) {
        // Mode édition : WebSocket
        baseData.id = animId;
        baseData.date = selectedDates[0];
        
        wsUpdateAnimation(baseData, function() {
            closeModal('animationModal');
            // Recharger pour le client modifieur (les autres reçoivent via WS)
            loadAnimations();
        });
    } else {
        // Mode création : WebSocket
        var completedCount = 0;
        var totalDates = selectedDates.length;
        
        for (var i = 0; i < selectedDates.length; i++) {
            var data = $.extend({}, baseData);
            data.date = selectedDates[i];
            
            wsCreateAnimation(data, function() {
                completedCount++;
                if (completedCount === totalDates) {
                    closeModal('animationModal');
                    // Recharger pour le client créateur (les autres reçoivent via WS)
                    loadAnimations();
                }
            });
        }
    }
}

function deleteAnimation(id) {
    customConfirm('Voulez-vous vraiment supprimer cette animation ?', 'Confirmation', function() {
        wsDeleteAnimation(id, function() {
            // Recharger pour le client suppresseur (les autres reçoivent via WS)
            loadAnimations();
        });
    });
}

function loadTypes() {
    $.ajax({
        url: 'animations_api.php?action=getTypes',
        success: function(data) {
            types = data;
            renderTypes();
        }
    });
}

function renderTypes() {
    var data = types;
    
    // Render select options
    var optionsHtml = '<option value="">-- Aucun --</option>';
    for (var i = 0; i < data.length; i++) {
        if (data[i].actif == 1) {
            optionsHtml += '<option value="' + data[i].id + '">' + (data[i].nom || '') + '</option>';
        }
    }
    $('#type_id').html(optionsHtml);
    
    // Render filters
    var filtersHtml = '';
    for (var i = 0; i < data.length; i++) {
        if (data[i].actif == 1) {
            filtersHtml += '<label class="filter-item">';
            filtersHtml += '<input type="checkbox" class="filter-checkbox filter-checkbox-type" value="' + (data[i].nom || '') + '">';
            filtersHtml += '<span>' + (data[i].nom || '') + '</span>';
            filtersHtml += '</label>';
        }
    }
    $('#filterTypes').html(filtersHtml);
    
    $('.filter-checkbox-type').off('change').on('change', function() {
        filterAnimations();
    });
    
    // Render list modal
    var listHtml = '';
    for (var i = 0; i < data.length; i++) {
        var statusClass = data[i].actif == 1 ? 'actif' : 'inactif';
        var statusText = data[i].actif == 1 ? 'Actif' : 'Archivé';
        var btnText = data[i].actif == 1 ? 'Archiver' : 'Réactiver';
        
        listHtml += '<li class="' + statusClass + '">';
        listHtml += '<span class="item-name">' + (data[i].nom || '') + '</span>';
        listHtml += '<span class="item-status">(' + statusText + ')</span>';
        listHtml += '<div class="item-actions">';
        listHtml += '<button class="btn-mini btn-mini-grey" onclick="editType(' + data[i].id + ', \'' + (data[i].nom || '').replace(/'/g, "\\'") + '\')"><i class="fa fa-pencil"></i></button> ';
        listHtml += '<button class="btn-mini btn-mini-grey" onclick="toggleType(' + data[i].id + ')">' + btnText + '</button>';
        listHtml += '</div>';
        listHtml += '</li>';
    }
    $('#typesList').html(listHtml);
}

function loadAnimateurs() {
    $.ajax({
        url: 'animations_api.php?action=getAnimateurs',
        success: function(data) {
            animateurs = data;
            renderAnimateurs();
        }
    });
}

function renderAnimateurs() {
    var data = animateurs;
    
    // Render select options
    var optionsHtml = '<option value="">-- Aucun --</option>';
    for (var i = 0; i < data.length; i++) {
        if (data[i].actif == 1) {
            optionsHtml += '<option value="' + data[i].id + '">' + (data[i].nom || '') + '</option>';
        }
    }
    $('#animateur_id').html(optionsHtml);
    
    // Render filters
    var filtersHtml = '';
    for (var i = 0; i < data.length; i++) {
        if (data[i].actif == 1) {
            filtersHtml += '<label class="filter-item">';
            filtersHtml += '<input type="checkbox" class="filter-checkbox filter-checkbox-animateur" value="' + (data[i].nom || '') + '">';
            filtersHtml += '<span>' + (data[i].nom || '') + '</span>';
            filtersHtml += '</label>';
        }
    }
    $('#filterAnimateurs').html(filtersHtml);
    
    $('.filter-checkbox-animateur').off('change').on('change', function() {
        filterAnimations();
    });
    
    // Render list modal
    var listHtml = '';
    for (var i = 0; i < data.length; i++) {
        var statusClass = data[i].actif == 1 ? 'actif' : 'inactif';
        var statusText = data[i].actif == 1 ? 'Actif' : 'Archivé';
        var btnText = data[i].actif == 1 ? 'Archiver' : 'Réactiver';
        
        listHtml += '<li class="' + statusClass + '">';
        listHtml += '<span class="item-name">' + (data[i].nom || '') + '</span>';
        listHtml += '<span class="item-status">(' + statusText + ')</span>';
        listHtml += '<div class="item-actions">';
        listHtml += '<button class="btn-mini btn-mini-grey" onclick="editAnimateur(' + data[i].id + ', \'' + (data[i].nom || '').replace(/'/g, "\\'") + '\')"><i class="fa fa-pencil"></i></button> ';
        listHtml += '<button class="btn-mini btn-mini-grey" onclick="toggleAnimateur(' + data[i].id + ')">' + btnText + '</button>';
        listHtml += '</div>';
        listHtml += '</li>';
    }
    $('#animateursList').html(listHtml);
}

function showInscriptionsModal(animationId) {
    currentAnimationId = animationId;
    $('.modal').hide();
    
    $.ajax({
        url: 'animations_api.php',
        data: { action: 'getAnimationWithInscriptions', id: animationId },
        success: function(data) {
            currentAnimationData = data.animation;
            
            var moisComplet = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                              'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            var dateParts = data.animation.date.split('-');
            var date = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            var dateFormatted = date.getDate() + ' ' + moisComplet[date.getMonth()];
            
            $('#inscriptionsTitle').html('Inscriptions : ' + data.animation.nom);
            $('#animationInfo').html(
                '<strong>Date :</strong> ' + dateFormatted + ' | ' +
                '<strong>Horaire :</strong> ' + data.animation.heure_debut.substring(0, 5) + ' - ' + data.animation.heure_fin.substring(0, 5) + (data.animation.lieu ? ' | <strong>Lieu :</strong> ' + data.animation.lieu : '')
            );
            
            $('#inscription_animation_id').val(animationId);
            displayInscriptions(data.inscriptions);
            updatePlacesInfoFromInscriptions(data.animation, data.inscriptions);
            $('#inscriptionsModal').show();
        }
    });
}

function loadInscriptions(animationId) {
    $.ajax({
        url: 'animations_api.php',
        data: { action: 'getAnimationWithInscriptions', id: animationId },
        success: function(data) {
            currentAnimationData = data.animation;
            displayInscriptions(data.inscriptions);
            updatePlacesInfoFromInscriptions(data.animation, data.inscriptions);
        }
    });
}

function updatePlacesInfoFromInscriptions(animation, inscriptions) {
    var totalInscrits = 0;
    var totalAttente = 0;
    
    for (var i = 0; i < inscriptions.length; i++) {
        if (inscriptions[i].statut === 'inscrit') {
            totalInscrits++;
        } else if (inscriptions[i].statut === 'attente') {
            totalAttente++;
        }
    }
    
    var placesInfo = 'Inscrits: ' + totalInscrits;
    if (animation.nb_places) {
        placesInfo += ' / ' + animation.nb_places;
    }
    if (totalAttente > 0) {
        placesInfo += ' | En attente: ' + totalAttente;
    }
	placesInfo += ' | Places restantes: ' + (animation.nb_places-totalInscrits);
    $('#placesInfo').text(placesInfo);
}

function formatDateInscription(dateStr) {
    var parts = dateStr.substring(0, 16).replace('T', ' ').split(' ');
    var dateParts = parts[0].split('-');
    var heure = parts[1];
    return dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0].substring(2) + ' - ' + heure;
}

function displayInscriptions(inscriptions) {
    var html = '';
    
    if (inscriptions.length === 0) {
        html = '<tr><td colspan="6" style="text-align:center;">Aucune inscription</td></tr>';
    } else {
        for (var i = 0; i < inscriptions.length; i++) {
            var insc = inscriptions[i];
            var isInvite = insc.parent_id !== null;
            var statutClass = insc.statut === 'inscrit' ? 'statut-inscrit' : 'statut-attente';
            var statutText = insc.statut === 'inscrit' ? 'Inscrit' : 'Attente';
            
            html += '<tr' + (isInvite ? ' class="invite-row"' : '') + '>';
            html += '<td>' + (isInvite ? '↪  <span style="color:#292964">'+insc.identite+'</span>' : '<span style="color:#464e6c;font-weight:bold;font-size:16px;">'+insc.identite+'</span>')+ '</td>';
            html += '<td>' + (insc.telephone || '-') + '</td>';
            html += '<td style="text-align: center;">' + (insc.nb_personnes || '-') + '</td>';
            html += '<td class="' + statutClass + '">' + statutText + '</td>';
            html += '<td>' + (insc.date_inscription ? formatDateInscription(insc.date_inscription) : '-') + '</td>';
            html += '<td style="white-space: nowrap;">';
            
           
            if (!isInvite) {
                html += '<button class="btn-mini btn-mini-grey" onclick="editInscriptionIdentite(' + insc.id + ', \'' + (insc.identite || '').replace(/'/g, "\\'") + '\')"><i class="fa fa-pencil"></i></button> ';
                html += '<button class="btn-mini btn-mini-grey" onclick="editInscriptionTelephone(' + insc.id + ', \'' + (insc.telephone || '').replace(/'/g, "\\'") + '\')"><i class="fa fa-phone"></i></button> ';
                html += '<button class="btn-mini btn-mini-green" onclick="addInvite(' + insc.id + ')"><i class="fa fa-plus"></i></button> ';
            } else {
                html += '<button class="btn-mini btn-mini-grey" onclick="editInscriptionTelephone(' + insc.id + ', \'' + (insc.telephone || '').replace(/'/g, "\\'") + '\')"><i class="fa fa-phone"></i></button> ';
            }
            
            if (insc.statut === 'attente') {
                html += '<button class="btn-mini btn-mini-green" onclick="validerInscription(' + insc.id + ')"><i class="fa fa-check"></i></button> ';
            }
            
            html += '<button class="btn-mini btn-mini-red" onclick="deleteInscription(' + insc.id + ')"><i class="fa fa-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        
    }
	}
    
    $('#inscriptionsList').html(html);
}

function addInscription() {
    var data = {
        animation_id: currentAnimationId,
        identite: $('#inscription_identite').val(),
        telephone: $('#inscription_telephone').val(),
        nb_personnes: $('#inscription_nb_personnes').val()
    };
    
    wsAddInscription(data, function() {
        $('#inscriptionForm')[0].reset();
        $('#inscription_nb_personnes').val(1);
        loadInscriptions(currentAnimationId);
    });
}

function deleteInscription(id) {
    customConfirm('Voulez-vous vraiment supprimer cette inscription ?', 'Confirmation', function() {
        wsDeleteInscription(id, function() {
            loadInscriptions(currentAnimationId);
        });
    });
}

function addInvite(parentId) {
    var currentNb = 0;
    $('#inscriptionsList tr').each(function() {
        var btn = $(this).find('button[onclick*="editInscriptionIdentite(' + parentId + '"]');
        if (btn.length > 0) {
            currentNb = parseInt($(this).find('td:eq(2)').text());
            return false;
        }
    });
    
    if (currentNb === 0) {
        return;
    }
    
    wsUpdateInscription({
        id: parentId,
        nb_personnes: currentNb + 1
    }, function() {
        loadInscriptions(currentAnimationId);
    });
}

function editInscriptionIdentite(id, currentIdentite) {
    customPrompt('Nouvelle identité:', 'Modifier l\'identité', currentIdentite, function(newIdentite) {
        if (!newIdentite || newIdentite === currentIdentite) {
            return;
        }
        
        wsUpdateInscription({
            id: id,
            identite: newIdentite
        }, function() {
            loadInscriptions(currentAnimationId);
        });
    });
}

function validerInscription(id) {
    customConfirm('Confirmer cette inscription ?', 'Confirmation', function() {
        wsValiderInscription(id, function() {
            loadInscriptions(currentAnimationId);
        });
    });
}

function showTypesModal() {
    $('#typeForm')[0].reset();
    $('.modal').hide();
    renderTypes();
    $('#typesModal').show();
}

function addType() {
    var nom = $('#nouveau_type').val().trim();
    if (!nom) {
        return;
    }
    
    $.ajax({
        url: 'animations_api.php?action=addType',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ nom: nom }),
        success: function(response) {
            if (response.error) {
                customAlert('Erreur: ' + response.error, 'Erreur');
            } else {
                $('#nouveau_type').val('');
                types.push({ id: response.id, nom: nom, actif: 1 });
                renderTypes();
            }
        }
    });
}

function editType(id, currentNom) {
    customPrompt('Nouveau nom:', 'Modifier le projet', currentNom, function(newNom) {
        if (!newNom || newNom === currentNom) {
            return;
        }
        
        $.ajax({
            url: 'animations_api.php?action=updateType',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id, nom: newNom }),
            success: function(resp) {
                if (resp.error) {
                    customAlert('Erreur: ' + resp.error, 'Erreur');
                } else {
                    for (var i = 0; i < types.length; i++) {
                        if (types[i].id == id) {
                            types[i].nom = newNom;
                            break;
                        }
                    }
                    renderTypes();
                    loadAnimations();
                }
            }
        });
    });
}

function toggleType(id) {
    $.ajax({
        url: 'animations_api.php?action=toggleType',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        success: function(resp) {
            if (resp.error) {
                customAlert('Erreur: ' + resp.error, 'Erreur');
            } else {
                for (var i = 0; i < types.length; i++) {
                    if (types[i].id == id) {
                        types[i].actif = types[i].actif == 1 ? 0 : 1;
                        break;
                    }
                }
                renderTypes();
            }
        }
    });
}

function showAnimateursModal() {
    $('#animateurForm')[0].reset();
    $('.modal').hide();
    renderAnimateurs();
    $('#animateursModal').show();
}

function addAnimateur() {
    var nom = $('#nouveau_animateur').val().trim();
    if (!nom) {
        return;
    }
    
    $.ajax({
        url: 'animations_api.php?action=addAnimateur',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ nom: nom }),
        success: function(response) {
            if (response.error) {
                customAlert('Erreur: ' + response.error, 'Erreur');
            } else {
                $('#nouveau_animateur').val('');
                animateurs.push({ id: response.id, nom: nom, actif: 1 });
                renderAnimateurs();
            }
        }
    });
}

function editAnimateur(id, currentNom) {
    customPrompt('Nouveau nom:', 'Modifier l\'animateur', currentNom, function(newNom) {
        if (!newNom || newNom === currentNom) {
            return;
        }
        
        $.ajax({
            url: 'animations_api.php?action=updateAnimateur',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id, nom: newNom }),
            success: function(resp) {
                if (resp.error) {
                    customAlert('Erreur: ' + resp.error, 'Erreur');
                } else {
                    for (var i = 0; i < animateurs.length; i++) {
                        if (animateurs[i].id == id) {
                            animateurs[i].nom = newNom;
                            break;
                        }
                    }
                    renderAnimateurs();
                    loadAnimations();
                }
            }
        });
    });
}

function toggleAnimateur(id) {
    $.ajax({
        url: 'animations_api.php?action=toggleAnimateur',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        success: function(resp) {
            if (resp.error) {
                customAlert('Erreur: ' + resp.error, 'Erreur');
            } else {
                for (var i = 0; i < animateurs.length; i++) {
                    if (animateurs[i].id == id) {
                        animateurs[i].actif = animateurs[i].actif == 1 ? 0 : 1;
                        break;
                    }
                }
                renderAnimateurs();
            }
        }
    });
}

function closeModal(modalId) {
    $('#' + modalId).hide();
    
    if (modalId === 'inscriptionsModal') {
        currentAnimationData = null;
        // Recharger la liste pour mettre à jour les compteurs de places
        loadAnimations();
    }
}

$(window).on('click', function(e) {
    if ($(e.target).hasClass('modal')) {
        var modalId = $(e.target).attr('id');
        closeModal(modalId);
    }
});

$(document).ready(function() {
    $.datepicker.regional['fr'] = {
        closeText: 'Fermer',
        prevText: '&#x3c;Préc',
        nextText: 'Suiv&#x3e;',
        currentText: 'Aujourd\'hui',
        monthNames: ['Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 
                     'Septembre', 'Octobre', 'Novembre', 'Decembre'],
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
});

// ==================== HELPERS DIALOG ====================

function customAlert(message, title) {
    $('#dialogMessage').text(message);
    $('#dialogInput').hide();
    $('#customDialog').dialog({
        title: title || 'Information',
        modal: true,
        width: 400,
        buttons: {
            'OK': function() {
                $(this).dialog('close');
            }
        }
    });
}

function customConfirm(message, title, callback) {
    $('#dialogMessage').text(message);
    $('#dialogInput').hide();
    $('#customDialog').dialog({
        title: title || 'Confirmation',
        modal: true,
        width: 400,
        buttons: {
            'Oui': function() {
                $(this).dialog('close');
                if (callback) callback();
            },
            'Non': function() {
                $(this).dialog('close');
            }
        }
    });
}

function customPrompt(message, title, defaultValue, callback) {
    $('#dialogMessage').text(message);
    $('#dialogInput').show().val(defaultValue || '');
    $('#customDialog').dialog({
        title: title || 'Saisie',
        modal: true,
        width: 400,
        buttons: {
            'OK': function() {
                var value = $('#dialogInput').val();
                $(this).dialog('close');
                if (callback) callback(value);
            },
            'Annuler': function() {
                $(this).dialog('close');
            }
        }
    });
}

function customDialog(message, title, content, callback) {
    $('#dialogMessage').html(message);
    if (content) {
        $('#dialogMessage').append('<div style="margin-top: 10px;">' + content + '</div>');
    }
    $('#dialogInput').hide();
    $('#customDialog').dialog({
        title: title || 'Information',
        modal: true,
        width: 400,
        buttons: {
            'OK': function() {
                $(this).dialog('close');
                if (callback) callback();
            },
            'Annuler': function() {
                $(this).dialog('close');
            }
        }
    });
}
// ==================== EMAILS ====================
var emails = [];

function loadEmails() {
    $.ajax({
        url: 'animations_api.php?action=getEmails',
        success: function(data) {
            emails = data;
            renderEmails();
        }
    });
}

function renderEmails() {
    var listHtml = '';
    for (var i = 0; i < emails.length; i++) {
        listHtml += '<li>';
        listHtml += '<span class="item-name">' + emails[i].email + '</span>';
        listHtml += '<div class="item-actions">';
        listHtml += '<button class="btn-mini btn-mini-grey" onclick="editEmail(' + emails[i].id + ', \'' + emails[i].email.replace(/'/g, "\\'") + '\')"><i class="fa fa-pencil"></i></button> ';
        listHtml += '<button class="btn-mini btn-mini-red" onclick="deleteEmail(' + emails[i].id + ')"><i class="fa fa-trash"></i></button>';
        listHtml += '</div>';
        listHtml += '</li>';
    }
    if (emails.length === 0) {
        listHtml = '<li style="text-align:center; color:#999; font-style:italic;">Aucun email enregistré</li>';
    }
    $('#emailsList').html(listHtml);
	$('#emailCount').text(emails.length + ' emails');
}

function showEmailsModal() {
    $('#emailForm')[0].reset();
    $('.modal').hide();
    loadEmails();
    $('#emailsModal').show();
}

function addEmail() {
    var email = $('#nouveau_email').val().trim();
    if (!email) {
        return;
    }
    
    $.ajax({
        url: 'animations_api.php?action=addEmail',
        method: 'POST',
        data: { email: email },
        success: function(response) {
            if (response.error) {
                customAlert('Erreur: ' + response.error, 'Erreur');
            } else {
                $('#nouveau_email').val('');
                emails.push({ id: response.id, email: email });
                renderEmails();
            }
        },
        error: function() {
            customAlert('Erreur lors de l\'ajout', 'Erreur');
        }
    });
}

function editEmail(id, currentEmail) {
    customPrompt('Nouvel email:', 'Modifier l\'email', currentEmail, function(newEmail) {
        if (!newEmail || newEmail === currentEmail) {
            return;
        }
        
        $.ajax({
            url: 'animations_api.php?action=updateEmail',
            method: 'POST',
            data: { id: id, email: newEmail },
            success: function(resp) {
                if (resp.error) {
                    customAlert('Erreur: ' + resp.error, 'Erreur');
                } else {
                    for (var i = 0; i < emails.length; i++) {
                        if (emails[i].id == id) {
                            emails[i].email = newEmail;
                            break;
                        }
                    }
                    renderEmails();
                }
            }
        });
    });
}

function deleteEmail(id) {
    customConfirm('Voulez-vous vraiment supprimer cet email ?', 'Confirmation', function() {
        $.ajax({
            url: 'animations_api.php?action=deleteEmail',
            method: 'POST',
            data: { id: id },
            success: function(resp) {
                if (resp.error) {
                    customAlert('Erreur: ' + resp.error, 'Erreur');
                } else {
                    emails = emails.filter(function(e) { return e.id != id; });
                    renderEmails();
                }
            }
        });
    });
}

function copyAllEmails() {
    if (emails.length === 0) {
        customAlert('Aucun email à  copier', 'Information');
        return;
    }
    
    var emailList = emails.map(function(e) { return e.email; }).join('; ');
    
    var textarea = document.createElement('textarea');
    textarea.value = emailList;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        customAlert(emails.length + ' email(s) copié(s) dans le presse-papier !', 'Succès');
    } catch (err) {
        customAlert('Erreur lors de la copie', 'Erreur');
    }
    
    document.body.removeChild(textarea);
}

$('#emailForm').on('submit', function(e) {
    e.preventDefault();
    addEmail();
});
function editInscriptionTelephone(id, currentTelephone) {
    customPrompt('Nouveau téléphone:', 'Modifier le téléphone', currentTelephone, function(newTelephone) {
        if (newTelephone === currentTelephone) {
            return;
        }
        
        wsUpdateInscription({
            id: id,
            telephone: newTelephone
        }, function() {
            loadInscriptions(currentAnimationId);
        });
    });
}
