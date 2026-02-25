$(function() {
	/////// Selecteur de date /////////
	$.datepicker.regional['fr'] = {
		closeText: 'Fermer',
		prevText: '&#x3c;Préc',
		nextText: 'Suiv&#x3e;',
		currentText: 'Aujourd\'hui',
		monthNames: ['Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Decembre'],
		monthNamesShort: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'],
		dayNames: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
		dayNamesShort: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
		dayNamesMin: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'],
		weekHeader: ' ',
		dateFormat: 'dd/mm/yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: '',
		maxDate: '+0D',
		numberOfMonths: 1,
		showButtonPanel: false,
		showWeek: true,
		showAnim: "drop",
		showButtonPanel: true
	};
	$.datepicker.setDefaults($.datepicker.regional['fr']);

	$("#dateSelector").datepicker({
		onSelect: function(date, datepicker) {
			if (date != "") {
				updateParticipantList(formatDate(date));
				updateStats();
				var selectedDate = $(this).datepicker("getDate");
				var weekNumber = $.datepicker.iso8601Week(selectedDate); // Calculer le numéro de la semaine
				var formattedDate = $.datepicker.formatDate("DD d MM yy", selectedDate);
				$("#SayDay").html("<span id='SDate'>" + formattedDate + "</span> - <span id='SWeek'>[S " + weekNumber + "]</span>");
			}
		}
	});

	// Définir la date initiale après l'initialisation pour assurer la traduction correcte
	$("#dateSelector").datepicker("setDate", +1);
	var todayDate = $("#dateSelector").datepicker("getDate");
	var weekNumber = $.datepicker.iso8601Week(todayDate);
	var formattedDate = $.datepicker.formatDate("DD d MM yy", todayDate);
	$("#SayDay").html("<span id='SDate'>" + formattedDate + "</span> - <span id='SWeek'>[S " + weekNumber + "]</span>");


	function initializeButton(selector, text, onClickHandler) {
		$(selector).dxButton({
			text: text,
			onClick: function(e) {
				onClickHandler(e);
				$(e.element).blur(); // Retire le focus du bouton
			}
		});
	}
	// Initialisation des boutons avec la fonction générique
	initializeButton("#addADULTE", "ADULTE", function() {
		addParticipant('ADULTE', formatDate($("#dateSelector").val()));
	});
	initializeButton("#addEnfant", "ENFANT", function() {
		addParticipant('ENFANT', formatDate($("#dateSelector").val()));
	});
	initializeButton("#addJeune", "JEUNE", function() {
		addParticipant('JEUNE', formatDate($("#dateSelector").val()));
	});
	initializeButton("#openGroupModal", "AUTRE", function() {
		$('#partnersModal').show();
	});
	initializeButton("#associateButton", "ASSOCIER", associateParticipants);
	initializeButton("#groupsButton", "LISTE DE GROUPES/PARTENAIRES", showPartnersModal);
	initializeButton("#addToGroupButton", "GROUPER QUELQU'UN", addParticipantToGroup);
	

let Allpartner = [];

function showPartnersModal() {
    const partnersList = $('#partnersList');
    partnersList.html(''); // Vider la liste actuelle
    const date = formatDate($("#dateSelector").val());

    // Appeler performAction pour récupérer les partenaires de la semaine avec la date sélectionnée
    performAction('getPartners', { date: date }, function(response) {
        if (response && Array.isArray(response)) {
            Allpartner = response; // Stocker globalement les partenaires récupérés
            
            // Organiser les partenaires par jour de la semaine
            const partnersByDay = {};
            Allpartner.forEach(function(partner) {
                const day = new Date(partner.created_at).toLocaleDateString('fr-FR', { weekday: 'long' }).toUpperCase();
                if (!partnersByDay[day]) {
                    partnersByDay[day] = [];
                }
                partnersByDay[day].push(partner);
            });

            // Liste des jours de la semaine en français
            const daysOfWeek = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE'];

            // Créer une table avec un seul thead
            const table = $('<table>').addClass('partners-table');
            const thead = $('<thead>').append(`
                <tr>
                    <th>Nom du groupe/partenaire</th>
                    <th style="text-align: center;">Personne(s)</th>
                    <th style="text-align: center;">Présence</th>
                    <th style="text-align: center;">Durée totale</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            `);
            table.append(thead);

            // Ajouter les partenaires dans tbody pour chaque jour
            daysOfWeek.forEach(function(day) {
                if (partnersByDay[day]) {
                    const daySection = $('<tbody>').addClass('day-section');
                    const dayHeaderRow = $('<tr>').addClass('day-header');
                    const dayHeaderCell = $('<td colspan="5">').text(day).css({
                        'background-color': '#cccccc',
                        'font-weight': 'bold',
                        'text-align': 'left',
                        'padding': '8px'
                    });
                    dayHeaderRow.append(dayHeaderCell);
                    daySection.append(dayHeaderRow);

                    partnersByDay[day].forEach(function(partner) {
                        const partnerRow = $('<tr>').addClass('partner-row');
                        partnerRow.append($('<td style="font-size:18px; text-align: left; font-weight:bolder">').text(partner.name));
                        partnerRow.append($('<td style="width: 50px; font-size:16px; text-align: center;">').text(partner.size));
                        partnerRow.append($('<td style="width: 50px; font-size:16px; text-align: center;">').text(partner.input_duration));
                        partnerRow.append($('<td style="width: 50px; font-size:16px; text-align: center;">').text(partner.total_duration));

                        const actionCell = $('<td style="text-align: center;  font-size:16px;">');
                        const editButton = $('<button>').on('click', function() {
                            editPartner(partner.id);
                        }).append($('<i>').addClass('fa fa-pencil-square-o'));
                        const deleteButton = $('<button>').on('click', function() {
                            deletePartner(partner.id);
                        }).append($('<i>').addClass('fa fa-trash'));

                        actionCell.append(editButton).append(deleteButton);
                        partnerRow.append(actionCell);

                        daySection.append(partnerRow);
                    });

                    table.append(daySection);
                }
            });

            partnersList.append(table);
        } else {
            alert('Erreur lors de la récupération des partenaires');
        }
    });

    $('#partnersListModal').show(/* {direction: 'up', effect: 'drop',duration: 100} */);
}





function handlePartnerFormSubmit(formSelector, modalSelector, isEditMode, additionalHandler) {
    $(formSelector).on('submit', function(e) {
        e.preventDefault();

        const partnerId = isEditMode ? $('#editPartnerId').val() : generateShortPartnerId();
        const partnerName = isEditMode ? $('#editPartnerName').val() : $('#partnerName').val();
        const partnerSize = parseInt(isEditMode ? $('#editPartnerSize').val() : $('#partnerSize').val());
        const inputDuration = isEditMode ? $('#editPartnerHours').val() : $('#partnerHours').val();
        const date = formatDate($("#dateSelector").val());

        if (isEditMode) {
            console.log('Calling performAction for updatePartner');
            performAction('updatePartner', {
				name: partnerName,
                id: partnerId,
                size: partnerSize,
                input_duration: inputDuration,
                date: date
            }, function(response) {
                console.log('Response from updatePartner:', response);
                if (response.success) {
                   //alert('Partenaire mis à jour avec succès');
                    const partnerIndex = Allpartner.findIndex(p => p.id === partnerId);
                    if (partnerIndex !== -1) {
                        Allpartner[partnerIndex].name = partnerName;
                        Allpartner[partnerIndex].size = partnerSize;
                        Allpartner[partnerIndex].input_duration = inputDuration;
                    }
                    if (additionalHandler) {
                        additionalHandler();
                    }
                } else {
                    alert('Erreur: ' + response.error);
                }
            });
        } else {
            performAction('addPartner', {
                name: partnerName,
                size: partnerSize,
                input_duration: inputDuration,
                date: date
            }, function(response) {
                if (response.success) {
                    //alert('Partenaire ajouté avec succès');
                    /* Allpartner.push({
                        id: partnerId,
                        name: partnerName,
                        size: partnerSize,
                        input_duration: inputDuration
                    }); */
					$("#partnersModal").hide();
					showPartnersModal();
                    /* if (additionalHandler) {
                        additionalHandler();
                    } */
                } else {
                    alert('Erreur: ' + response.error);
                }
            });
        }

        $(modalSelector).hide();
    });
}


function editPartner(partnerId) {
    console.log('Editing partner with ID:', partnerId);
    const partner = Allpartner.find(p => p.id === partnerId);
    console.log('Found partner:', partner);

    if (partner) {
        $('#editPartnerId').val(partner.id);
        $('#editPartnerName').val(partner.name);
        $('#editPartnerSize').val(partner.size);
        $('#editPartnerHours').val(partner.input_duration);
        $('#editPartnerModal').show();

    } else {
        console.error('Partner not found with ID:', partnerId);
        alert('Partenaire non trouvé');
    }
}





// Fonction pour supprimer un partenaire
function deletePartner(partnerId) {
    performAction('deletePartner', { id: partnerId }, function(response) {
        if (response.success) {
            // Supprimer le partenaire de la liste globale des partenaires
            Allpartner = Allpartner.filter(p => p.id !== partnerId);
            
            // Mettre à jour l'affichage de la liste des partenaires
            showPartnersModal(); // Appeler la fonction pour rafraîchir la liste des partenaires
        } else {
            alert('Erreur: ' + response.error);
        }
    });
}


// Exemples d'utilisation
handlePartnerFormSubmit('#partnerForm', '#partnerModal', false, function() {
    // Refresh the participant list or any other action needed after submission
    updateParticipantList($("#dateSelector").datepicker("getDate"));
});

// Attacher l'événement de suppression au bouton de suppression
$(document).on('click', '.deletePartnerBtn', function() {
    const partnerId = $(this).data('id');
    if (confirm('Voulez-vous vraiment supprimer ce partenaire ?')) {
        deletePartner(partnerId);
    }
});

	handlePartnerFormSubmit('#partnersForm', '#partnersModal', false);
	handlePartnerFormSubmit('#editPartnerForm', '#editPartnerModal', true, showPartnersModal);

	function initializeCloseButton(buttonSelector, modalSelector) {
		$(buttonSelector).on('click', function() {
			$(modalSelector).hide();
		});
	}

	// Initialisation des boutons de fermeture avec la fonction générique
	initializeCloseButton('#closePartnersModal', '#partnersModal');
	initializeCloseButton('#closePartnersListModal', '#partnersListModal');
	initializeCloseButton('#closeEditPartnerModal', '#editPartnerModal');

});

function formatDate(date) {
	let parts = date.split('/');
	let formattedDate = parts[2] + '-' + parts[1] + '-' + parts[0];
	return formattedDate;
}

let partners = [];

function generateShortPartnerId() {
	const validChars = "ACDEFHJKLMNPQRTUVWXY123479"; // Caractères valides sans 8, B, 5, S, 2, Z, 1, I, 0, O
	let result = '';
	for (let i = 0; i < 4; i++) {
		const randomIndex = Math.floor(Math.random() * validChars.length);
		result += validChars[randomIndex];
	}
	return result;
}

function addParticipant(type, datetime) {
	performAction('addParticipant', {
		type: type,
		datetime: datetime // Passer la date et l'heure d'enregistrement
	}, function(data) {
		if (data.success) {
			updateParticipantList(formatDate($("#dateSelector").val()));
			updateStats();
		} else {
			console.error('Erreur lors de l\'ajout du participant:', data);
		}
	});
}

$('#participantList').on('click', 'button', function() {
	const action = $(this).data('action');
	const participantId = $(this).data('id');

	if (action === 'add') {
		performAction('updateParticipant', {
			id: participantId,
			duration_action: 'up'
		}, function(data) {
			if (data.success) {
				updateParticipantList(formatDate($("#dateSelector").val()));
				updateStats();
			} else {
				console.error('Erreur lors de l\'augmentation de la durée:', data.error);
			}
		});
	} else if (action === 'remove') {
		// Vérifier la durée actuelle avant de diminuer
		const currentDuration = $(this).closest('tr').find('td:nth-child(4)').text(); // Supposant que la durée est dans la 4ème cellule
		const [hours, minutes] = currentDuration.split(':').map(Number);

		if (hours === 1 && minutes === 0) {
			showToast('La durée ne peut pas être inférieure à 01:00.', 5000);
			//alert('La durée ne peut pas être inférieure à 01:00.');
		} else {
			performAction('updateParticipant', {
				id: participantId,
				duration_action: 'down'
			}, function(data) {
				if (data.success) {
					updateParticipantList(formatDate($("#dateSelector").val()));
					updateStats();
				} else {
					console.error('Erreur lors de la diminution de la durée:', data.error);
				}
			});
		}
	} else if (action === 'delete') {
		performAction('deleteParticipant', {
			id: participantId
		}, function(data) {
			if (data.success) {
				$(this).closest('tr').remove(); // Supprimer la ligne du tableau
				updateParticipantList(formatDate($("#dateSelector").val()));
				updateStats();
			} else {
				console.error('Erreur lors de la suppression du participant:', data.error);
			}
		});
	}
});

function associateParticipants() {
	const checkedItems = $('input[type="checkbox"]:checked');
	if (checkedItems.length < 2) {
		alert("Vous devez sélectionner au moins deux individus pour les associer.");
		return;
	}

	// Vérifier si au moins un des individus sélectionnés est déjà dans un groupe
	const inGroupItems = checkedItems.filter(function() {
		return $(this).closest('.list-item').attr('data-group');
	});

	if (inGroupItems.length > 0) {
		alert("Vous ne pouvez pas associer des individus déjà membres d'un groupe.");
		return;
	}

	const groupId = generateShortPartnerId(); // Générer un ID de groupe court

	// Créer le groupe via performAction avant de mettre à jour les participants
	date = formatDate($("#dateSelector").val());
	performAction('createGroup', {
		group_id: groupId,
		date: date
	}, function(data) {
		if (data.success) {
			const groupColor = data.color; // Couleur attribuée au groupe

			// Mettre à jour chaque participant sélectionné avec le group_id
			checkedItems.each(function() {
				const participantId = $(this).closest('.list-item').data('id');

				performAction('updateParticipant', {
					id: participantId,
					group_id: groupId
				}, function(data) {
					if (data.success) {
						updateParticipantList(formatDate($("#dateSelector").val()));
						updateStats();
					} else {
						console.error('Erreur lors de la mise à jour du participant:', data.error);
					}
				}.bind(this)); // Utilisation de .bind(this) pour garder le contexte de l'élément courant
			});

			// Décocher toutes les cases à cocher après l'association
			checkedItems.prop('checked', false);

		} else {
			console.error('Erreur lors de la création du groupe:', data.error);
		}
	});
}

function addParticipantToGroup() {
	const checkedItems = $('input[type="checkbox"]:checked');
	if (checkedItems.length !== 1) {
		showDialog("Vous devez sélectionner un seul individu à ajouter à un groupe.", "Erreur");
		return;
	}

	const selectedItem = checkedItems.closest('.list-item');
	const currentGroup = selectedItem.attr('data-group');

	if (currentGroup) {
		showDialog("Cet individu fait déjà partie d'un groupe.", "Erreur");
		return;
	}

	showPrompt("Entrez l'ID du groupe pour ajouter cet individu (4 caractères max):", "ID du Groupe")
		.then(groupToJoin => {
			// Si l'utilisateur a cliqué sur "Cancel", ne rien faire
			if (groupToJoin === null || groupToJoin === undefined) {
				return;
			}

			// Vérification unique : ID vide, trop long ou inexistant
			if (groupToJoin.trim() === "" || groupToJoin.length > 4 || $('.list-item[data-group="' + groupToJoin.toUpperCase() + '"]').length === 0) {
				showDialog("ID incorrecte.", "Erreur");
				return;
			}

			// Si l'ID est valide, effectuer l'action
			groupToJoin = groupToJoin.toUpperCase();
			participantId = selectedItem.data('id');
			performAction('updateParticipant', {
				id: participantId,
				group_id: groupToJoin
			}, function(data) {
				if (data.success) {
					checkedItems.prop('checked', false);
					updateParticipantList(formatDate($("#dateSelector").val()));
					updateStats();
				} else {
					console.error('Erreur lors de la dissociation du participant dans un groupe :', data.error);
				}
			});
		});
}


function updateStats() {

	performAction('getStatsDay', {
		date: formatDate($("#dateSelector").val())
	}, function(data) {
		if (data.error) {
			console.error('Erreur lors de la récupération des statistiques journalières:', data.error);
		} else {
			// Traitez les données reçues (affichage, mise à jour du DOM, etc.)
			$('#publicCount').text(data.individuals_count);
			$('#publicHours').text(data.individuals_hours);
			$('#partenaireCount').text(data.partners_count);
			$('#partenaireHours').text(data.partners_hours);
			$('#totalPresences').text(data.total_count);
			$('#totalHours').text(data.total_hours);
		}
	});
	performAction('getStatsWeek', {
		date: formatDate($("#dateSelector").val())
	}, function(data) {
		if (data.error) {
			console.error('Erreur lors de la récupération des statistiques hebdomadaires:', data.error);
		} else {
			// Traitez les données reçues (affichage, mise à jour du DOM, etc.)
			$('#publicCountW').text(data.individuals_count);
			$('#publicHoursW').text(data.individuals_hours);
			$('#partenaireCountW').text(data.partners_count);
			$('#partenaireHoursW').text(data.partners_hours);
			$('#totalPresencesW').text(data.total_count);
			$('#totalHoursW').text(data.total_hours);
		}
	});
}

//************
function performAction(action, params, onSuccess) {
	// Construire l'URL avec les paramètres
	let url = "http://127.0.0.1:99/Request.php?action=" + action;

	// Ajouter les paramètres à l'URL
	for (let key in params) {
		url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
	}

	// Effectuer l'appel AJAX
	fetch(url)
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			// Appeler onSuccess quel que soit le résultat
			if (onSuccess) {
				onSuccess(data);
			}
		})
		.catch(function(error) {
			console.error('Erreur:', error);
		});
}

function updateParticipantList(date) {
	// Étape 1 : Sauvegarder l'état des sélections actuelles
	const selectedParticipants = [];
	$('#participantList input[type="checkbox"]:checked').each(function() {
		selectedParticipants.push($(this).closest('tr').attr('data-id'));
	});

	performAction('getParticipants', {
		date: date
	}, function(data) {
		const tbody = $('#participantList');
		tbody.empty(); // Effacer l'ancienne liste

		data.forEach(function(participant) {
			const row = $('<tr>', {
				class: 'list-item',
				'data-id': participant.id,
				'data-group': participant.group_id || '',
				css: {
					'background-color': participant.color || '#ffffff'
				} // Appliquer la couleur si présente
			});

			const checkboxCell = $('<td>').append(
				$('<input>', {
					type: 'checkbox'
				})
			);
			const typeCell = $('<td>').text(participant.type);
			const timeCell = $('<td>').text(participant.arrival_time.split(' ')[1].substring(0, 5)); // Extraire l'heure de arrival_time
			const durationCell = $('<td>').text(participant.duration ? participant.duration : '01:00'); // Utiliser la durée réelle si présente

			const actionsCell = $('<td>').append(
				$('<button>', {
					class: 'dx-button add-button',
					'data-action': 'add',
					'data-id': participant.id
				}).append($('<i>', {
					class: 'fa fa-plus'
				})), // Icône pour l'ajout

				$('<button>', {
					class: 'dx-button remove-button',
					'data-action': 'remove',
					'data-id': participant.id
				}).append($('<i>', {
					class: 'fa fa-minus'
				})), // Icône pour la suppression

				$('<button>', {
					class: 'dx-button delete-button',
					'data-action': 'delete',
					'data-id': participant.id
				}).append($('<i>', {
					class: 'fa fa-trash'
				})) // Icône pour la suppression totale
			);

			// Créer la cellule de group_id avec l'icône de dissociation
			const groupIdCell = $('<td>', {
				class: 'group-id'
			});

			if (participant.group_id) {
				const dissociateIcon = $('<i>', {
					class: 'fa fa-chain-broken',
					css: {
						cursor: 'pointer', // Indiquer que l'icône est cliquable
						marginRight: '5px' // Espace entre l'icône et l'ID du groupe
					}
				}).on('click', function() {
					// Appeler une fonction pour dissocier l'individu du groupe
					dissociateParticipant(participant.id);
				});

				groupIdCell.append(dissociateIcon).append(participant.group_id);
			} else {
				groupIdCell.text('');
			}

			row.append(checkboxCell, typeCell, timeCell, durationCell, actionsCell, groupIdCell);

			// Étape 2 : Restaurer l'état des sélections après la mise à jour
			if (selectedParticipants.includes(participant.id.toString())) {
				checkboxCell.find('input[type="checkbox"]').prop('checked', true);
			}

			tbody.append(row);
		});
	});
}

// Fonction pour dissocier un participant d'un groupe
function dissociateParticipant(participantId) {
	// Envoyer une requête AJAX pour mettre à jour le participant en supprimant son group_id
	performAction('updateParticipant', {
		id: participantId,
		group_id: '' // Supprimer le group_id
	}, function(data) {
		if (data.success) {
			updateParticipantList(formatDate($("#dateSelector").val()));
			updateStats();
		} else {
			console.error('Erreur lors de la dissociation du participant:', data.error);
		}
	});
}

// Fonction pour afficher un toast centré
function showToast(message, duration = 3000) {
	var $toast = $('<div class="toast"></div>').text(message);
	$('#toast-container').append($toast);
	$toast.fadeIn(400).delay(duration).fadeOut(400, function() {
		$(this).remove();
	});
}


// Fonction pour afficher un prompt personnalisé avec une promesse
function showPrompt(message, title = "Prompt") {
	return new Promise((resolve) => {
		showDialog(message, title, true, resolve, "Associer");
	});
}
// Fonction générique pour afficher une boîte de dialogue
function showDialog(message, title, isPrompt = false, callback = null, buttonText = 'OK') {
	$("#dialogMessage").text(message);
	$("#dialogInput").toggle(isPrompt).val(''); // Afficher ou masquer l'input selon le besoin
	let dialogButtons = {};
	dialogButtons[buttonText] = function() { // Utiliser le texte du bouton personnalisé
		var userInput = isPrompt ? $("#dialogInput").val() : null;
		$(this).dialog("close");
		if (callback) callback(userInput);
	};
	
	// Ajouter le bouton "Annuler" uniquement si c'est un prompt
	if (isPrompt) {
		dialogButtons["Annuler"] = function() {
			$(this).dialog("close");
			if (callback) callback(null);
		};
	}
	
	$("#customDialog").dialog({
		modal: true,
		title: title,
		buttons: dialogButtons,
		width: 500,
		draggable: false,
		hide: {
			effect: 'fade',
			duration: 100
		},
		show: {
			effect: 'clip',
			duration: 100
		}
	});
}


//***************  POOLING ********************///
const eventSource = new EventSource('check_file.php');
// Écouter l'événement standard "message"
eventSource.onmessage = (event) => {};
// Écouter l'événement personnalisé "fileModified"
eventSource.addEventListener('fileModified', (event) => {
	updateParticipantList(formatDate($("#dateSelector").val()));
	updateStats();
});
eventSource.onerror = (error) => {};