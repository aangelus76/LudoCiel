<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        #multipleReservationContainer {
            padding: 15px;
        }

        /* Zone formulaire en haut */
        #formSection {
            margin-bottom: 15px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            align-items: flex-start;
        }

        .form-field {
            flex: 1;
        }

        .form-field label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            margin-top: 0;
            font-size: 13px;
            color: #2c3e50;
        }

        .form-field.required label::after {
            content: " *";
            color: red;
        }

        #selectedCounter {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            margin-left: 15px;
        }

        #validateBtn, #cancelBtn {
            margin-left: 10px;
        }

        /* Zone basse avec grid + panel */
        #resourcesSection {
            display: none;
            margin-top: 10px;
        }

        #resourcesSection.visible {
            display: flex;
            gap: 20px;
        }

        /* Grid de pillules (70%) */
        #pillsGrid {
            flex: 0 0 68%;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-content: flex-start;
            padding: 12px;
            background-color: #fafafa;
            border-radius: 5px;
            border: 1px solid #ddd;
            max-height: 480px;
            overflow-y: scroll;
        }

        /* Scrollbar stylée pour pillsGrid */
        #pillsGrid::-webkit-scrollbar {
            width: 10px;
        }

        #pillsGrid::-webkit-scrollbar-track {
            background: #ecf0f1;
            border-radius: 5px;
        }

        #pillsGrid::-webkit-scrollbar-thumb {
            background: #bdc3c7;
            border-radius: 5px;
        }

        #pillsGrid::-webkit-scrollbar-thumb:hover {
            background: #95a5a6;
        }

        .resource-pill {
            padding: 6px 12px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            user-select: none;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        /* États des pillules */
        .resource-pill.available {
            background-color: white;
            border-color: #ccc;
            color: #333;
        }

        .resource-pill.available:hover {
            border-color: #3498db;
            box-shadow: 0 2px 6px rgba(52, 152, 219, 0.2);
        }

        .resource-pill.available.selected {
            background-color: #2ecc71 !important;
            border-color: #27ae60 !important;
            color: white !important;
            font-weight: 600;
        }

        .resource-pill.unavailable {
            background-color: #e74c3c;
            border-color: #c0392b;
            color: white;
            opacity: 0.7;
        }

        .resource-pill.unavailable:hover {
            opacity: 0.9;
        }

        .resource-pill.unavailable.selected {
            background-color: #8b0000 !important;
            border-color: #6b0000 !important;
            opacity: 1 !important;
            font-weight: 600;
        }

        /* Panel de droite (30%) */
        #selectedPanel {
            flex: 0 0 30%;
            background-color: #ecf0f1;
            border-radius: 5px;
            padding: 15px;
            max-height: 480px;
            display: flex;
            flex-direction: column;
            border: 1px solid #bdc3c7;
        }

        #selectedPanel h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }

        #selectedList {
            flex: 1;
            overflow-y: auto;
            background-color: white;
            border-radius: 4px;
            padding: 10px;
        }

        .selected-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            background-color: #f9f9f9;
            border-left: 4px solid #3498db;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            font-weight: 500;
        }

        .selected-item:hover {
            background-color: #fdecea;
            border-left-color: #e74c3c;
            transform: translateX(-3px);
        }

        .selected-item.conflict {
            border-left-color: #e74c3c;
            background-color: #fdecea;
        }

        .empty-message {
            text-align: center;
            color: #95a5a6;
            font-style: italic;
            padding: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div id="multipleReservationContainer">
        <!-- Section formulaire -->
        <div id="formSection">
            <div class="form-row">
                <div class="form-field required">
                    <label>Date de départ</label>
                    <div id="multiStartDate"></div>
                </div>
                <div class="form-field required">
                    <label>Date de retour</label>
                    <div id="multiEndDate"></div>
                </div>
                <div class="form-field required">
                    <label>Nom</label>
                    <div id="multiUser"></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label>Téléphone</label>
                    <div id="multiPhone"></div>
                </div>
                <div class="form-field">
                    <label>Commentaire</label>
                    <div id="multiInfo"></div>
                </div>
            </div>
            <div class="form-row">
                <div id="showResourcesBtn"></div>
                <span id="selectedCounter" style="display:none;">0 ressource sélectionnée</span>
                <div id="validateBtn" style="display:none;"></div>
                <div id="cancelBtn" style="display:none;"></div>
            </div>
        </div>

        <!-- Section ressources (grid + panel) -->
        <div id="resourcesSection">
            <div id="pillsGrid"></div>
            <div id="selectedPanel">
                <h3>Ressources sélectionnées</h3>
                <div id="selectedList">
                    <div class="empty-message">Aucune ressource sélectionnée</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            // Variables globales
            let allResources = [];
            let selectedResources = new Set();
            let resourcesData = {};
            let startDateBox, endDateBox, userBox, phoneBox, infoBox, showBtn, validateBtn, cancelBtn;

            // Initialiser les composants DevExtreme
            function initializeComponents() {
                // DateBox pour date de départ
                const today = new Date();
                const dayAfterTomorrow = new Date(today);
                dayAfterTomorrow.setDate(dayAfterTomorrow.getDate() + 2);
                
                startDateBox = $("#multiStartDate").dxDateBox({
                    type: "date",
                    displayFormat: "dd/MM/yyyy",
                    value: today,
                    onValueChanged: function(e) {
                        // Synchroniser le mois du DatePicker retour
                        if (e.value) {
                            var newReturnDate = new Date(e.value);
                            newReturnDate.setDate(newReturnDate.getDate() + 2);
                            endDateBox.option("value", newReturnDate);
                        }
                        
                        checkFormValidity();
                        hideResourcesGrid();
                    }
                }).dxDateBox("instance");

                // DateBox pour date de retour
                endDateBox = $("#multiEndDate").dxDateBox({
                    type: "date",
                    displayFormat: "dd/MM/yyyy",
                    value: dayAfterTomorrow,
                    onValueChanged: function(e) {
                        checkFormValidity();
                        hideResourcesGrid();
                    }
                }).dxDateBox("instance");

                // TextBox pour nom
                userBox = $("#multiUser").dxTextBox({
                    placeholder: "Nom de l'emprunteur",
                    valueChangeEvent: "keyup",
                    onValueChanged: function(e) {
                        checkFormValidity();
                    }
                }).dxTextBox("instance");

                // TextBox pour téléphone
                phoneBox = $("#multiPhone").dxTextBox({
                    placeholder: "Numéro de téléphone (optionnel)"
                }).dxTextBox("instance");

                // TextArea pour commentaire
                infoBox = $("#multiInfo").dxTextArea({
                    placeholder: "Commentaire (optionnel)",
                    height: 60
                }).dxTextArea("instance");

                // Bouton afficher ressources
                showBtn = $("#showResourcesBtn").dxButton({
                    text: "Afficher les ressources",
                    icon: "search",
                    type: "success",
                    disabled: true,
                    onClick: function() {
                        loadResources();
                    }
                }).dxButton("instance");

                // Bouton valider
                validateBtn = $("#validateBtn").dxButton({
                    text: "Valider les réservations",
                    icon: "check",
                    type: "default",
                    onClick: function() {
                        validateSelection();
                    }
                }).dxButton("instance");

                // Bouton annuler
                cancelBtn = $("#cancelBtn").dxButton({
                    text: "Annuler",
                    icon: "close",
                    type: "normal",
                    onClick: function() {
                        $("#popupMultiple").dxPopup("instance").hide();
                    }
                }).dxButton("instance");
            }

            // Vérifier si le formulaire est valide
            function checkFormValidity() {
                const startDate = startDateBox.option("value");
                const endDate = endDateBox.option("value");
                const user = userBox.option("value") || '';
                const isValid = startDate && endDate && user.trim().length >= 3;
                showBtn.option("disabled", !isValid);
            }

            // Masquer la grid si dates changent
            function hideResourcesGrid() {
                const resourcesSection = document.getElementById('resourcesSection');
                resourcesSection.classList.remove('visible');
                document.getElementById('selectedCounter').style.display = 'none';
                document.getElementById('validateBtn').style.display = 'none';
                document.getElementById('cancelBtn').style.display = 'none';
                
                // Vider complètement les sélections
                selectedResources.clear();
                
                // Vider le contenu des pillules
                document.getElementById('pillsGrid').innerHTML = '';
                
                // Réinitialiser la liste de droite
                document.getElementById('selectedList').innerHTML = '<div class="empty-message">Aucune ressource sélectionnée</div>';
                
                // Réinitialiser les données
                allResources = [];
                resourcesData = {};
                
                updateCounter();
            }

            // Charger les ressources
            function loadResources() {
                const startDate = startDateBox.option("value");
                const endDate = endDateBox.option("value");

                // Convertir en format attendu par le backend
                const formattedStartDate = encodeURIComponent(startDate.toString());
                const formattedEndDate = encodeURIComponent(endDate.toString());

                // Appel AJAX pour récupérer les ressources
                $.ajax({
                    url: 'CheckMultipleAvailability.php',
                    type: 'GET',
                    data: {
                        startDate: formattedStartDate,
                        endDate: formattedEndDate
                    },
                    success: function(response) {
                        if (response.success) {
                            allResources = response.resources;
                            resourcesData = {};
                            selectedResources.clear();
                            
                            // Stocker les données des ressources
                            allResources.forEach(r => {
                                resourcesData[r.id] = r;
                            });
                            
                            displayResourcePills();
                            document.getElementById('resourcesSection').classList.add('visible');
                            document.getElementById('selectedCounter').style.display = 'inline-block';
                            document.getElementById('validateBtn').style.display = 'inline-block';
                            document.getElementById('cancelBtn').style.display = 'inline-block';
                            updateCounter();
                        } else {
                            window.DevExpress.ui.notify({
                                message: 'Erreur : ' + response.error,
                                position: { my: "top", at: "top" },
                                shading: true,
                                shadingColor: "rgba(0, 0, 0, 0.5)"
                            }, 'error', 3000);
                        }
                    },
                    error: function(xhr, status, error) {
                        window.DevExpress.ui.notify({
                            message: 'Erreur lors de la récupération des ressources',
                            position: { my: "top", at: "top" },
                            shading: true,
                            shadingColor: "rgba(0, 0, 0, 0.5)"
                        }, 'error', 3000);
                    }
                });
            }

            // Afficher les pillules
            function displayResourcePills() {
                const pillsGrid = document.getElementById('pillsGrid');
                pillsGrid.innerHTML = '';
                
                allResources.forEach(resource => {
                    const pill = document.createElement('div');
                    pill.className = 'resource-pill ' + (resource.available ? 'available' : 'unavailable');
                    pill.textContent = resource.name;
                    pill.dataset.resourceId = resource.id;
                    
                    pill.addEventListener('click', function() {
                        toggleResourceSelection(parseInt(resource.id));
                    });
                    
                    pillsGrid.appendChild(pill);
                });
            }

            // Toggle sélection d'une ressource
            function toggleResourceSelection(resourceId) {
                if (selectedResources.has(resourceId)) {
                    selectedResources.delete(resourceId);
                } else {
                    selectedResources.add(resourceId);
                }
                
                updatePillsDisplay();
                updateSelectedList();
                updateCounter();
            }

            // Mettre à jour l'affichage des pillules
            function updatePillsDisplay() {
                const pillsGrid = document.getElementById('pillsGrid');
                const pills = pillsGrid.querySelectorAll('.resource-pill');
                
                pills.forEach(pill => {
                    const resourceId = parseInt(pill.dataset.resourceId);
                    
                    if (selectedResources.has(resourceId)) {
                        pill.classList.add('selected');
                    } else {
                        pill.classList.remove('selected');
                    }
                });
            }

            // Mettre à jour la liste des sélectionnées
            function updateSelectedList() {
                const selectedList = document.getElementById('selectedList');
                if (selectedResources.size === 0) {
                    selectedList.innerHTML = '<div class="empty-message">Aucune ressource sélectionnée</div>';
                    return;
                }
                
                selectedList.innerHTML = '';
                selectedResources.forEach(resourceId => {
                    const resource = resourcesData[resourceId];
                    const item = document.createElement('div');
                    item.className = 'selected-item' + (!resource.available ? ' conflict' : '');
                    item.textContent = resource.name;
                    item.dataset.resourceId = resourceId;
                    
                    item.addEventListener('click', function() {
                        toggleResourceSelection(resourceId);
                    });
                    
                    selectedList.appendChild(item);
                });
            }

            // Mettre à jour le compteur
            function updateCounter() {
                const count = selectedResources.size;
                document.getElementById('selectedCounter').textContent = count + ' ressource' + (count > 1 ? 's' : '') + ' sélectionnée' + (count > 1 ? 's' : '');
            }

            // Validation
            function validateSelection() {
                if (selectedResources.size === 0) {
                    window.DevExpress.ui.notify({
                        message: 'Veuillez sélectionner au moins une ressource',
                        position: { my: "top", at: "top" },
                        shading: true,
                        shadingColor: "rgba(0, 0, 0, 0.5)"
                    }, 'warning', 2000);
                    return;
                }

                // Vérifier s'il y a des conflits sélectionnés
                const conflicts = Array.from(selectedResources).filter(id => !resourcesData[id].available);
                
                if (conflicts.length > 0) {
                    // Construire le message de conflit
                    var conflictMessage = "Attention ! " + conflicts.length + 
                                         " ressource(s) ne sont pas disponibles :<br><br>";
                    
                    conflicts.forEach(function(id) {
                        var resource = resourcesData[id];
                        conflictMessage += "• <strong>" + resource.name + "</strong><br>";
                    });
                    
                    // Utiliser DevExpress.ui.dialog.custom comme dans index.php
                    var dialogOptions = {
                        title: "⚠️ Conflit de réservation",
                        messageHtml: conflictMessage + "<br><strong>Voulez-vous forcer la réservation de ces ressources ?</strong>",
                        buttons: [{
                            text: "Annuler",
                            onClick: function() {
                                // Retirer les conflits de la sélection
                                conflicts.forEach(id => selectedResources.delete(id));
                                updatePillsDisplay();
                                updateSelectedList();
                                updateCounter();
                                
                                if (selectedResources.size === 0) {
                                    window.DevExpress.ui.notify({
                                        message: 'Aucune ressource disponible sélectionnée',
                                        position: { my: "top", at: "top" },
                                        shading: true,
                                        shadingColor: "rgba(0, 0, 0, 0.5)"
                                    }, 'warning', 2000);
                                }
                                return { buttonText: "Annuler" };
                            }
                        }, {
                            text: "Forcer la réservation",
                            type: "danger",
                            onClick: function() {
                                // Continuer avec la création des réservations
                                createReservations();
                                return { buttonText: "Forcer" };
                            }
                        }]
                    };
                    
                    window.DevExpress.ui.dialog.custom(dialogOptions).show();
                    return;
                }

                // Pas de conflits, créer directement les réservations
                createReservations();
            }

            // Créer les réservations
            function createReservations() {
                const startDate = startDateBox.option("value");
                const endDate = endDateBox.option("value");
                const user = userBox.option("value");
                const phone = phoneBox.option("value") || '';
                const info = infoBox.option("value") || '';
                
                const data = {
                    resourceIds: Array.from(selectedResources),
                    startDate: startDate.toString(),
                    endDate: endDate.toString(),
                    user: user.trim(),
                    phone: phone.trim(),
                    info: info.trim()
                };

                $.ajax({
                    url: 'MultipleReservations.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(data),
                    success: function(response) {
                        
                        if (response.success) {
                            // Afficher notification de succès
                            window.DevExpress.ui.notify({
                                message: 'Ajout de : ' + response.created + ' réservation(s)',
                                position: { my: "top", at: "top" },
                                shading: true,
                                shadingColor: "rgba(0, 0, 0, 0.5)",
                                animation: {
                                    show: { type: "pop", from: { scale: 0.8 }, to: { scale: 1 } },
                                    hide: { type: "fade", from: 1, to: 0 }
                                }
                            }, 'success', 600);

                            // Fermer la popup
                            $("#popupMultiple").dxPopup("instance").hide();

                            // Rafraîchir le scheduler après 1 seconde (laisse le temps à la BDD)
                            setTimeout(function() {
                                // Récupérer l'instance du scheduler
                                var schedulerInstance = $("#scheduler").dxScheduler("instance");
                                if (schedulerInstance) {
                                    var schedulerStartDate = schedulerInstance.option("currentDate");
                                    var schedulerEndDate = new Date(schedulerStartDate.getFullYear(), schedulerStartDate.getMonth() + 1, schedulerStartDate.getDate());
                                    
                                    // Préparer les dates pour l'appel AJAX (ajouter -2 et +2 mois comme dans getAppointments)
                                    var oneMonthAgoStartDate = new Date(schedulerStartDate);
                                    oneMonthAgoStartDate.setMonth(oneMonthAgoStartDate.getMonth() - 2);
                                    var oneMonthLaterEndDate = new Date(schedulerEndDate);
                                    oneMonthLaterEndDate.setMonth(oneMonthLaterEndDate.getMonth() + 2);
                                    
                                    // Appel AJAX pour recharger les appointments
                                    $.ajax({
                                        url: "Apointement.php",
                                        dataType: "json",
                                        data: {
                                            startDate: oneMonthAgoStartDate,
                                            endDate: oneMonthLaterEndDate
                                        },
                                        success: function(data) {
                                            schedulerInstance.option("dataSource", data);
                                        },
                                        error: function(error) {
                                            console.log("Erreur lors du rechargement des rendez-vous:", error);
                                        }
                                    });
                                }

                                // Mettre à jour les tags de réservation
                                var schedulerInstance = $("#scheduler").dxScheduler("instance");
                                if (schedulerInstance) {
                                    var currentDate = schedulerInstance.option("currentDate");
                                    var currentMonth = currentDate.getMonth() + 1;
                                    var currentYear = currentDate.getFullYear();
                                    
                                    $.ajax({
                                        url: "GetUniqueReservers.php",
                                        dataType: "json",
                                        data: {
                                            month: currentMonth,
                                            year: currentYear
                                        },
                                        success: function(data) {
                                            if (data && data.success && data.reservers) {
                                                var tagsContainer = $("#reservationTags");
                                                tagsContainer.empty();
                                                
                                                data.reservers.forEach(function(reserver) {
                                                    var tag = $("<div class='reservation-tag'>");
                                                    tag.text(reserver);
                                                    tag.css({
                                                        'padding': '6px 6px',
                                                        'background-color': '#f0f0f0',
                                                        'border': 'solid 1px rgb(166, 166, 166)',
                                                        'border-radius': '12px',
                                                        'font-size': '11px',
                                                        'cursor': 'pointer',
                                                        'margin-right': '5px',
                                                        'margin-top': '3px',
                                                        'transition': 'background-color 0.2s',
                                                        'color': 'rgb(98, 94, 94)',
                                                        'font-weight': 'bold'
                                                    });
                                                    
                                                    // Ajouter le hover effect
                                                    tag.hover(
                                                        function() { $(this).css('background-color', '#e0e0e0'); },
                                                        function() { $(this).css('background-color', '#f0f0f0'); }
                                                    );
                                                    
                                                    // Ajouter le click event
                                                    tag.on('click', function() {
                                                        if (window.showReservationsForUser) {
                                                            window.showReservationsForUser(reserver);
                                                        }
                                                    });
                                                    
                                                    tagsContainer.append(tag);
                                                });
                                            }
                                        }
                                    });
                                }
                            }, 1000);
                        } else {
                            window.DevExpress.ui.notify({
                                message: 'Erreur : ' + response.error,
                                position: { my: "top", at: "top" },
                                shading: true,
                                shadingColor: "rgba(0, 0, 0, 0.5)"
                            }, 'error', 3000);
                        }
                    },
                    error: function(xhr, status, error) {
                        window.DevExpress.ui.notify({
                            message: 'Erreur lors de la création des réservations',
                            position: { my: "top", at: "top" },
                            shading: true,
                            shadingColor: "rgba(0, 0, 0, 0.5)"
                        }, 'error', 3000);
                    }
                });
            }

            // Initialisation au chargement
            $(document).ready(function() {
                initializeComponents();
                checkFormValidity();
            });
        })();
    </script>
</body>
</html>
