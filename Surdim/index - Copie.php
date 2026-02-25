<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Website</title>
  <link rel="stylesheet" href="css/dx.spa.css">
  <link rel="stylesheet" href="css/dx.common.css">
  <link rel="stylesheet" href="css/dx.light.21.2.7.css">
  <link rel="stylesheet" href="css/Custom.21.css">
  <script src="js/jquery-3.6.0.min.js"></script>
  <!--<script src="js/jquery.min.js"></script>-->
  <script src="js/comp.php"></script>
  <script src="js/dx.messages.fr.js"></script>
  <style>

  </style>
</head>
  <body>
  <!--<div id="popup"></div><div id="popupContent"></div>-->
  <div id="ListDay"><div id="datePick"></div><div id="simpleList"></div></div>
  <div id="ListNM"><div id="simpleListNM"></div></div>
    <div class="dx-viewport demo-container"> 
		<div id="tabpanel">
			<div id="tabpanel-container"></div>
		</div>
		<div id="scheduler"></div>
	</div>
<div id="popupMode" style="display: none;"></div>
<script>DevExpress.localization.locale("fr");</script>

<script>

var scheduler;
var selectedCellCoordinates;
var AllAppointement;
$(function() {
	isBtnPress = false;
	var DataNextMonth;
	var date = new Date();
	const month = ["Janvier", "Fevrier", "Mars", "Avril", "Mai", "Juin", "Juillet", "Aout", "Septembre", "Octobre", "Novembre", "Decembre"];
	var dayOfWeekNames = ["Dim", "Lun", "Mar", "Mer", "Jeu", "Ven", "Sam"];
	
	let CurrentMonth = month[date.getMonth()];
	var d = new Date();
	d.setMonth(d.getMonth() + 2);
	
	//let NextTwoMonth = date.setMonth(date.getMonth() + 3);
	let TwoMonth = month[d.getMonth()];
	function showToast(event, value, type) {
		DevExpress.ui.notify({
			message: `${event} "${value}"`,
			position: {
				my: "top",
				at: "top"
			},
			shading: true,
    shadingColor: "rgba(0, 0, 0, 0.5)",
    animation: {
      show: { type: "pop", from: { scale: 0.8 }, to: { scale: 1 } },
      hide: { type: "fade", from: 1, to: 0 }
    }
		}, type, 600);
	}
	function getAppointments(startDate, endDate) {
		var oneMonthAgoStartDate = new Date(startDate);
		oneMonthAgoStartDate.setMonth(oneMonthAgoStartDate.getMonth()-2);
		var oneMonthLaterEndDate = new Date(endDate);
		oneMonthLaterEndDate.setMonth(oneMonthLaterEndDate.getMonth()+2);
		return $.ajax({
			url: "Apointement.php",
			dataType: "json",
			data: {
				startDate: oneMonthAgoStartDate, 
				endDate: oneMonthLaterEndDate
			},
			success: function(data) {
				scheduler.option("dataSource", data); 
			},
			error: function(error) {
				console.log("Erreur lors du chargement des rendez-vous:");
			}
		});
	}
	scheduler = $("#scheduler").dxScheduler({
		dataSource: [],
		views: [{
			name: "Vue du mois",
			type: "timelineMonth",
			groupOrientation: "vertical",
		}],
		crossScrollingEnabled: true,
		currentView: "timelineMonth",
		allowMultiple: true,
		currentDate: new Date(date.getFullYear(), date.getMonth(), 1),
		firstDayOfWeek: 0,
		maxAppointmentsPerCell: 2,
		showCurrentTimeIndicator: true,
		shadeUntilCurrentTime: true,
		groups: ["priority"],
		resources: [{
			fieldExpr: "priority",
			dataSource: priorityData,
			label: "Priority",
		}],
		editing: {
			allowDragging: false
		},		
		customizeDateNavigator: function(e) {
			e.actionOptions.items.unshift({
				text: "Mois en cours",
				onClick: function() {
					e.component.option("currentDate", new Date()); // Définir la date du jour
				}
			});
		},
		
		//Gestion de conflit d'enregistrement (pas de chevauchement de reservation pour 1 item)
		onAppointmentAdding: function(e) {
		
			//Creer une fonction de vérification en AJAX pour controler la disponibilité
			var isItemAvailable = true; // Remplacez par votre vérification réelle
			for (var key in e.appointmentData) {                    
                console.log(key);
            }

			if (!isItemAvailable) {
				showToast('Erreur : ', "Conflit d'enregistrement, il existe déjà une réservation dans la plage de date selectionée !", 'error'); // Affiche un message d'erreur
				e.cancel = true;
			}
		},
		onAppointmentAdded: function(e) {
			    showToast('Ajout de : ', e.appointmentData.text, 'success');
    
    // Utilisez la correspondance pour obtenir l'ID réel
    var priority = e.appointmentData.priority;
    var GameID = priorityToIdMap[priority]; // Obtenez l'ID réel depuis la priorité
    
    StartDate = e.appointmentData.startDate;
    EndDate = e.appointmentData.endDate;
    User = e.appointmentData.text;
    Phone = e.appointmentData.Phone == undefined ? "" : e.appointmentData.Phone;
    Info = e.appointmentData.description == undefined ? "Nan" : e.appointmentData.description;
    Priority = priority; // Ou GameID selon votre logique
    
    addAppointment(StartDate, EndDate, User, Info, GameID, Priority, Phone, "Add").then(function() {
        // Mettre à jour les tags après la suppression d'une réservation
        updateReservationTags();
    });
/* 			showToast('Ajout de : ', e.appointmentData.text, 'success');
			GameID = priorityData[e.appointmentData.priority].gameId;
			//alert(GameID);
			StartDate = e.appointmentData.startDate;
			EndDate = e.appointmentData.endDate;
			User = e.appointmentData.text;
			Phone = e.appointmentData.Phone == undefined ? "" : e.appointmentData.Phone;
			Info = e.appointmentData.description == undefined ? "Nan" : e.appointmentData.description;
			Priority = e.appointmentData.priority;
			addAppointment(StartDate, EndDate, User, Info, GameID, Priority, Phone, "Add"); */
			setTimeout(function() {
				getAppointments(e.appointmentData.startDate, e.appointmentData.endDate).then(function(appointments) {
					e.component.option("dataSource", appointments);
				});
			}, 1000);
		},
		onAppointmentUpdated: function(e) {
			showToast('Modification de : ', e.appointmentData.text, 'info');
			GameID = priorityData[e.appointmentData.priority].gameId;
			///alert(GameID);
			RentID = e.appointmentData.ID;
			StartDate = e.appointmentData.startDate;
			EndDate = e.appointmentData.endDate;
			User = e.appointmentData.text;
			Phone = e.appointmentData.Phone == undefined ? "Nan" : e.appointmentData.Phone;
			Info = e.appointmentData.description == undefined ? "Nan" : e.appointmentData.description;
			Priority = e.appointmentData.priority;
			addAppointment(StartDate, EndDate, User, Info, RentID, GameID, Phone, "Update");
			updateReservationTags();
		},
		onAppointmentDeleted: function(e) { 
			if (isBtnPress) {
				isBtnPress = false; 
				done = e.appointmentData; 
			}
			else {
				done = e;
			}
			showToast('Suppresion de : ', done.appointmentData.text, 'warning');
			RentID = done.appointmentData.ID;
			StartDate = done.appointmentData.startDate;
			EndDate = done.appointmentData.endDate;
			User = done.appointmentData.text;
			Info = done.appointmentData.description == undefined ? "Nan" : done.appointmentData.description;
			addAppointment(StartDate, EndDate, User, Info, RentID, 0, 0, "Delete").then(function() {
        // Mettre à jour les tags après la suppression d'une réservation
        updateReservationTags();
    });
		},
		appointmentTemplate: function(data) {
			return $("<div>" + data.appointmentData.text + "</div>");
		},
/* 		onAppointmentRendered: function(e) {
			StartDate = new Date(e.appointmentData.startDate);
			EndDate = new Date(e.appointmentData.endDate);
			const oneDay = 1000 * 60 * 60 * 24;
			const start = Date.UTC(StartDate.getFullYear(), StartDate.getMonth(), StartDate.getDate());
			const end = Date.UTC(EndDate.getFullYear(), EndDate.getMonth(), EndDate.getDate());
			DayBetween = (end - start) / oneDay + 1;
			e.appointmentElement.height(30);
			e.appointmentElement.css("opacity", "0.7");
			e.appointmentElement.css("color", "#000000");
			e.appointmentElement.css("font-weight", "bold");
			e.appointmentElement.css("font-size", "16px");
		}, */
onAppointmentRendered: function(e) {
    StartDate = new Date(e.appointmentData.startDate);
    EndDate = new Date(e.appointmentData.endDate);
    const oneDay = 1000 * 60 * 60 * 24;
    const start = Date.UTC(StartDate.getFullYear(), StartDate.getMonth(), StartDate.getDate());
    const end = Date.UTC(EndDate.getFullYear(), EndDate.getMonth(), EndDate.getDate());
    DayBetween = (end - start) / oneDay + 1;
    
    // Configuration de base
    e.appointmentElement.height(30);
    e.appointmentElement.css("opacity", "0.7");
    e.appointmentElement.css("color", "#000000");
    e.appointmentElement.css("font-weight", "bold");
    e.appointmentElement.css("font-size", "16px");
    
    // Vérifier le status et appliquer l'hachurage si nécessaire
    console.log("Status pour " + e.appointmentData.text + ": ", e.appointmentData.status); // Debug
    
    if (e.appointmentData.status === 1) {
        e.appointmentElement.css({
            'background-image': 'repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(255,255,255,0.5) 5px, rgba(255,255,255,0.5) 10px)',
            'background-size': '14px 14px'
        });
    }
},

		onAppointmentFormOpening: function(e) {

			var form = e.form;
			var validationGroup = form.getEditor("text")._validationGroup;
			form.option("items", [{
					label: {
						text: "NOM",
					},
					dataField: "text",
					editorType: "dxTextBox",
					validationRules: [{
						type: "required",
						message: "Le nom de l'emprinteur est obligatoire!"
					}],
				},
				{
					label: {
						text: "Commentaire",
					},
					dataField: "description",
					editorType: "dxTextArea",
				},
				{
					label: {
						text: "Date de départ",
					},
					dataField: "startDate",
					editorType: "dxDateBox",
					validationRules: [{
						type: "required",
						message: "Une date de départ est obligatoire!"
					}],
					editorOptions: {
						displayFormat: "dd/MM/yyyy",
					},
				},
				{
					label: {
						text: "Date de retour",
					},
					dataField: "endDate",
					editorType: "dxDateBox",
					validationRules: [{
						type: "required",
						message: "Une date de retour est obligatoire!"
					}],
					onInitialized: function(e) {
						var endDate = new Date(e.appointmentData.endDate);
						endDate.setDate(endDate.getDate() - 1);
						e.appointmentData.endDate = endDate;
						console.log("Init")
					},
					editorOptions: {
						displayFormat: "dd/MM/yyyy",
					},
				}, {
					label: {
						text: "Téléphone",
					},
					dataField: "Phone",
					editorType: "dxTextBox",
				},
			]);
			
			var appointmentData = e.appointmentData;
			if (appointmentData.text == undefined) {
				//console.log("G3:"+ appointmentData.text)
				var setNameValue = SetName.option("value");
				var setPhoneValue = SetPhone.option("value");
				if (setNameValue) {
					e.form.updateData("text", setNameValue);
					e.form.updateData("Phone", setPhoneValue);
				}
				var endDate = new Date(e.appointmentData.endDate);
				endDate.setDate(endDate.getDate() - 1);
				appointmentData.endDate = endDate;
				e.form.updateData("endDate", endDate);
			}

		},
/* 		appointmentTooltipTemplate: function(data) {
			var tooltipContent = $("<div>");
			var StartDate = new Date(data.appointmentData.startDate);
			var EndDate = new Date(data.appointmentData.endDate);
			EndDate.setDate(EndDate.getDate());
			const oneDay = 1000 * 60 * 60 * 24;
			const start = Date.UTC(StartDate.getFullYear(), StartDate.getMonth(), StartDate.getDate());
			const end = Date.UTC(EndDate.getFullYear(), EndDate.getMonth(), EndDate.getDate());
			var DayBetween = (end - start) / oneDay + 1;
			tooltipContent.append("<p style='text-align:start; font-size:18px; font-weight : 800; text-transform: Uppercase; border-bottom: 1.5px solid #21409b;'>" + data.appointmentData.text + "</p><p style='float: right; margin-top: -30px; font-size: x-small; color:#b6b6b6'>"+data.appointmentData.dateSave+"</p><br />");
			tooltipContent.append("<p><strong>Nombre de jour(s) :</strong> " + DayBetween + "</p>");
			if (data.appointmentData.description != undefined && data.appointmentData.description != null && data.appointmentData.description != "") {
				tooltipContent.append("<p style='white-space: normal;'><strong>Commentaire : </strong>" + data.appointmentData.description + "</p>");
			}
			if (data.appointmentData.Phone != undefined && data.appointmentData.Phone != null && data.appointmentData.Phone != "" && data.appointmentData.Phone != "Nan") {
				tooltipContent.append("<p><strong>Téléphone : </strong>" + data.appointmentData.Phone + "</p>");
			}
			const buttonContainer = $('<div class="dx-tooltip-appointment-item-delete-button-container">');
			
			const DataDay = $('<div class="Info-Day">').text("Du " + StartDate.getDate() + "  " + month[StartDate.getMonth()] + " au  " + EndDate.getDate() + "  " + month[EndDate.getMonth()]);
			const buttonDelete = $('<div class="dx-tooltip-appointment-item-delete-button">').dxButton({
				icon: 'trash',
				type: 'danger',
				stylingMode: 'outlined',
				onClick(e) {
					isBtnPress = true;
					scheduler.deleteAppointment(data);
					e.event.stopPropagation();
					scheduler.hideAppointmentTooltip();
					const dataSource = scheduler.option("dataSource");
					const updatedDataSource = dataSource.filter((appointment) => appointment.ID !== data.appointmentData.ID);
					scheduler.option("dataSource", updatedDataSource);
				}
			});
			buttonDelete.css({
				'margin': '0px 5px',
				'float': 'right'
			});
			const buttonEdit = $('<div class="dx-tooltip-appointment-item-edit-button">').dxButton({
				stylingMode: 'outlined',
				icon: 'edit',
				type: 'default',
				onClick(e) {
					scheduler.updateAppointment(data);
					scheduler.hideAppointmentTooltip();
				}
			});
			buttonEdit.css({
				'margin': '0px 5px',
				'float': 'right'
			});
			buttonContainer.append(DataDay);
			buttonContainer.append(buttonEdit);
			buttonContainer.append(buttonDelete);
			tooltipContent.append(buttonContainer);
			return tooltipContent;
		}, */
		appointmentTooltipTemplate: function(data) {
    var tooltipContent = $("<div>");
    var StartDate = new Date(data.appointmentData.startDate);
    var EndDate = new Date(data.appointmentData.endDate);
    EndDate.setDate(EndDate.getDate());
    const oneDay = 1000 * 60 * 60 * 24;
    const start = Date.UTC(StartDate.getFullYear(), StartDate.getMonth(), StartDate.getDate());
    const end = Date.UTC(EndDate.getFullYear(), EndDate.getMonth(), EndDate.getDate());
    var DayBetween = (end - start) / oneDay + 1;
    
    tooltipContent.append("<p style='text-align:start; font-size:18px; font-weight:800; text-transform:Uppercase; border-bottom:1.5px solid #21409b;'>" + data.appointmentData.text + "</p><p style='float:right; margin-top:-30px; font-size:x-small; color:#b6b6b6'>"+data.appointmentData.dateSave+"</p><br />");
    tooltipContent.append("<p><strong>Nombre de jour(s) :</strong> " + DayBetween + "</p>");
    
    if (data.appointmentData.description != undefined && data.appointmentData.description != null && data.appointmentData.description != "") {
        tooltipContent.append("<p style='white-space:normal;'><strong>Commentaire : </strong>" + data.appointmentData.description + "</p>");
    }
    if (data.appointmentData.Phone != undefined && data.appointmentData.Phone != null && data.appointmentData.Phone != "" && data.appointmentData.Phone != "Nan") {
        tooltipContent.append("<p><strong>Téléphone : </strong>" + data.appointmentData.Phone + "</p>");
    }
    
    const buttonContainer = $('<div class="dx-tooltip-appointment-item-delete-button-container">');
    const DataDay = $('<div class="Info-Day">').text("Du " + StartDate.getDate() + "  " + month[StartDate.getMonth()] + " au  " + EndDate.getDate() + "  " + month[EndDate.getMonth()]);
    
    // Bouton de retour - masqué si le jeu est déjà retourné
    if (data.appointmentData.status !== 1) {
        const buttonReturn = $('<div class="dx-tooltip-appointment-item-return-button">').dxButton({
            icon: 'check',
            text: '',
            type: 'success',
            stylingMode: 'outlined',
            onClick(e) {
                const today = new Date();
                today.setDate(today.getDate() - 1); // hier
                
                // Mise à jour de l'appointment avec le statut retourné
                addAppointment(
                    data.appointmentData.startDate,
                    today,
                    data.appointmentData.text,
                    data.appointmentData.description || "Nan",
                    data.appointmentData.ID,
                    data.appointmentData.ListId,
                    data.appointmentData.Phone || "",
                    "Return"
                ).then(() => {
                    // Une fois la mise à jour réussie
                    data.appointmentData.status = 1;
                    data.appointmentData.endDate = today;
                    
                    // Rafraîchir l'affichage
                    scheduler.option("dataSource", scheduler.option("dataSource"));
                    scheduler.repaint();
                    
                    scheduler.hideAppointmentTooltip();
                    showToast('Retour de : ', data.appointmentData.text, 'success');
                });
                
                e.event.stopPropagation();
            }
        });
        buttonReturn.css({
            'margin': '0px 1px',
            'float': 'right'
        });
        buttonContainer.append(buttonReturn);
    }

    const buttonEdit = $('<div class="dx-tooltip-appointment-item-edit-button">').dxButton({
        stylingMode: 'outlined',
        icon: 'edit',
        type: 'default',
        onClick(e) {
            scheduler.updateAppointment(data);
            scheduler.hideAppointmentTooltip();
        }
    });
    buttonEdit.css({
        'margin': '0px 1px',
        'float': 'right'
    });

    // Bouton de suppression
    const buttonDelete = $('<div class="dx-tooltip-appointment-item-delete-button">').dxButton({
        icon: 'trash',
        type: 'danger',
        stylingMode: 'outlined',
        onClick(e) {
            isBtnPress = true;
            scheduler.deleteAppointment(data);
            e.event.stopPropagation();
            scheduler.hideAppointmentTooltip();
            const dataSource = scheduler.option("dataSource");
            const updatedDataSource = dataSource.filter((appointment) => appointment.ID !== data.appointmentData.ID);
            scheduler.option("dataSource", updatedDataSource);
        }
    });
    buttonDelete.css({
        'margin': '0px 1px',
        'float': 'right'
    });
    
    buttonContainer.append(DataDay);
    buttonContainer.append(buttonDelete);
    buttonContainer.append(buttonEdit);
    tooltipContent.append(buttonContainer);
    
    return tooltipContent;
},
		dataCellTemplate: function(data, index, container) {
			var GetPriority = data.groups.priority;
			var currentDate = new Date();
			var lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
			if (data.startDate.getDay() == 1 || data.startDate.getDay() == 0) {
				var cellContent = $("<div>")
					.css({
						'width': '100%',
						'height': '35px',
						'background-color': 'rgba(' + priorityData[GetPriority].colorRVB + ',0.05)',
						'margin-top': '-1px'
					})
					.appendTo(container);
			}
			else {
				$("<div>")
					.css({
						'width': '100%',
						'height': '35px',
						'background-color': 'rgba(' + priorityData[GetPriority].colorRVB + ',0.1)',
						'margin-top': '-1px'
					})
					.appendTo(container);
			}
		},
		resourceCellTemplate: function(cellData, index, container) {
			var resourceTextElement = $("<div>").text(cellData.data.text);
			if (cellData.data.is_titre) {
				resourceTextElement.addClass("titre");
			}
			else {
				resourceTextElement.addClass("sujet");
			}
			resourceTextElement.attr("ShowData-Rent", cellData.data.id);
			resourceTextElement.attr("ShowData-Rent-Titre", cellData.data.Titre);
			resourceTextElement.css({
				'background-color': '#FFFFFF',
				'background-image': 'repeating-linear-gradient(45deg, ' + cellData.data.color + ' 0, ' + cellData.data.color + ' 0.3px, #ffffff 0, #ffffff 50%'
			});
			$(container).empty().append(resourceTextElement);
		},
		dateCellTemplate: function(data, index, element) {
			DateVue = $("<div />");
			DateVue.append("<p style='font-size:14px; font-weight:bold; color:#a4a2a2; padding-top:15px;'>" + dayOfWeekNames[data.date.getDay()] + "</p><p style='font-size:18px; font-weight:bold; color:#4b4a4a; padding-bottom: 15px;'>" + data.date.getDate() + "</p>");
			if(data.date.getDay() == 0 || data.date.getDay() == 1 ){
				element.css({'background-color':'rgba(0, 0, 0, 0.1)'});
			}
			else{
				element.css({'background-color':'rgba(255, 255, 255, 1)'});
			}
			element.append(DateVue);
		},
		onContentReady: function(e) {
			e.component.scrollTo(new Date());
			e.component.element().find(".dx-link-edit").attr("title", "Editer");
			e.component.element().find(".dx-link-delete").attr("title", "Supprimer");
			positionSearchBox();
		}
	}).dxScheduler("instance");
	//positionSearchBox();
	//Test scrollTo
	const searchBox = $('#searchBox').dxAutocomplete({
            dataSource: priorityData,
            valueExpr: 'text',
            placeholder: 'Recherche...',
            onValueChanged: function (e) {
                const selectedResource = e.component.option('value');
                if (selectedResource) {
                    const resource = priorityData.find(resource => resource.text === selectedResource);
                    if (resource) {
                        const resourceId = resource.id;
                        scrollToResource(resourceId);
						console.log("Ressource : "+resourceId)
                    }
                }
            }
        }).dxAutocomplete('instance');
		
		function positionSearchBox() {
    const $schedulerHeaderCell = $('.dx-scheduler-header-panel-empty-cell');

    // Créez le champ de recherche
    const $searchBox = $('<div id="searchBox" style="margin-top:-20px"></div>');

    // Ajoutez le champ de recherche à la div avec la classe "dx-scheduler-header-panel-empty-cell"
    $searchBox.appendTo($schedulerHeaderCell);
}

function scrollToResource(resourceId) {
    $(document).ready(function () {
        const $scheduler = $('#scheduler');
        const $eventToScroll = $scheduler.find(`[ShowData-Rent="${resourceId}"]`);
		console.log($eventToScroll);
        if ($eventToScroll.length > 0) {
            // Faites défiler jusqu'à l'élément avec l'attribut correspondant
            const scrollTop = $eventToScroll.offset().top - $scheduler.offset().top - 90;
            $('html, body').animate({ scrollTop: scrollTop }, 'slow');
			
        }
    });
}
	
	
	//Fin Test scrollTo
	scheduler.option("onOptionChanged", function(e) {
		if (e.name === "currentDate") {
			var startDate = e.value;
			var endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 1, startDate.getDate()); 
			getAppointments(startDate, endDate);
		}
	});
	
	var startDate = scheduler.option("currentDate");
	var endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 1, startDate.getDate()); 
	getAppointments(startDate, endDate);

	function addAppointment(StartDate, EndDate, User, Info, GameID, Priority, Phone, ControlType) {
		return $.ajax({
			url: "AppointmentControl.php",
			type: "GET",
			data: {
				StartDate: StartDate,
				EndDate: EndDate,
				User: User,
				Info: Info,
				ControlType: ControlType,
				Priority: Priority,
				Phone: Phone,
				GameID: GameID
			},
			success: function(response) {
				console.log("Rendez-vous ajouté avec succès !" + " " + StartDate + " " + EndDate);
			},
			error: function(error) {
				console.log("Erreur lors de l'ajout du rendez-vous:", error);
			}
		});
	}

var testDate = new Date();
var DatePick = $("#datePick").dxDateBox({
    type: "date",
    value: testDate,
    displayFormat: "dd/MM/yyyy",
    onValueChanged: function(e) {
        var list = $("#simpleList").dxList("instance");  
        getList("GetList", new Date(e.value), $("#popupMode").text()); // Utilisez la valeur stockée dans la balise invisible
        list.repaint();  
    }
}).dxDateBox("instance");
  

//Liste des pret du jour
	var listInstance = $("#simpleList").dxList({
		dataSource: DataNextMonth,
		height: '100%',
		grouped: true,
		collapsibleGroups: true,
		noDataText: 'Pas de prêt !',
		groupTemplate(data) {
			return $('<div id="MyListRent">Emprunt de : '+data.key+'</div>');
		}
	}).dxList("instance");
//Liste des pret de l'item sur le prochain mois
	var listInstanceNM = $("#simpleListNM").dxList({
		dataSource: DataNextMonth,
		height: '100%',
		grouped: true,
		collapsibleGroups: true,
		noDataText: 'Pas de réservation !',
		groupTemplate(data) {
			return $('<div id="MyListRent">Réservé par : '+data.key+'</div>');
		},
	}).dxList("instance");

//Evenement au click sur un item dans la liste Ressources
	$('[ShowData-Rent]').click(function() {
		valueItemSelected = $(this).attr('ShowData-Rent');
		var Titre = $(this).attr('ShowData-Rent-Titre');
		popupListItem.show();
	}); 

//Afficheur Popup pour liste des prêt pour l'item au prochain mois
var ContentListNM = '';	
	$("#ListNM").dxPopup({
		contentTemplate: () => {
			const content = $("<div />");
			
			content.append(
				$(ContentListNM),listInstanceNM.$element()
			); 
			content.dxScrollView({
				width: '100%',
				height: '100%',
			});
			return content;
		},
		onShowing: function () {
			getList("GetItem", parseInt(valueItemSelected));
		}, 
		title: "Réservation(s) de "+CurrentMonth+" à "+TwoMonth,
		width: 500,
		height: 600,
		closeOnOutsideClick: true,
	}).dxPopup("instance");
	const popupListItem = $("#ListNM").dxPopup("instance");

//Afficheur Popup pour liste des prêt du jour
var ContentListDay = '';
var SetTitle = '';
var popupListDay;

$("#ListDay").dxPopup({
    contentTemplate: () => {
        const content = $("<div />");

        // Ajout de la balise invisible pour stocker la valeur "In" ou "Out"
        //const hiddenMode = $("<div id='popupMode' style='display: none;' />");
        content.append(
            $(ContentListDay), DatePick.$element(), listInstance.$element()
        );
        content.dxScrollView({
            width: '100%',
            height: '100%',
        });
        return content;
    },
    onShowing: function () {
        DatePick.option("value", new Date());
		lastClickedButton = $("#popupMode").text()
        if (lastClickedButton == "Out") {
            SetTitle = "Liste des sorties";
            $("#popupMode").text("Out"); // Stockez la valeur "Out" dans la balise invisible
        } else {
            SetTitle = "Liste des retours";
            $("#popupMode").text("In"); // Stockez la valeur "In" dans la balise invisible
        }
        popupListDay.option("title", SetTitle);
    },
    title: "",
    width: 500,
    height: 600,
    closeOnOutsideClick: true,
});

popupListDay = $("#ListDay").dxPopup("instance");

//Récupération des données ListJours/Item pour les list (List du jour)/(Pret de l'item prochain mois)
// -Mode:[GetItem, GetList], Select: [Item(Number), DateDay]
function getList() {
	let Mode;
	let Select;
	let Step;
	
	for (const arg of arguments) {
		if (arg === "GetItem" || arg === "GetList") {
		  Mode = arg;
		}
		else if (typeof arg === "number" || arg instanceof Date) {
		  Select = arg;
		}
		else if (arg === "Out") {
            Step = "Out";
        }
		else if (arg === "In") {
            Step = "In";
        }
	}
	return $.ajax({
		url: "NextMonth.php",
		dataType: "json",
		data: {
			Mode: Mode, 
			Select: Select,
			Step  : Step
		},
		success: function(data) {
			if(Mode == "GetList"){
				listInstance.option("dataSource", data);
			}
			else{
				listInstanceNM.option("dataSource", data);
			}
		},
		error: function(error) {
			DataNextMonth = null;
			listInstance.option("dataSource", DataNextMonth);
			listInstanceNM.option("dataSource", DataNextMonth);
		}
	}); 
}	

//Manipulation du menu au dessus du scheduler
//Ajout de deux boutons (1 - Liste du jour; 2 - Admin)
var dateNavigatorContainer = $(".dx-scheduler-navigator");
	var customButton0 = $("<div id='ToNow'>")
		customButton0.css({'margin-top':'9px'});
		customButton0.css({'margin-right':'-5px'});
		customButton0.css({'padding-top':'-5px'});
		customButton0.css({'background-color':'#FFF'});
		customButton0.css({'order':'-1'});
		customButton0.appendTo(dateNavigatorContainer);
	var customButton1 = $("<div id='ViewList'>")
		customButton1.css({'margin-top':'9px'});
		customButton1.css({'margin-right':'10px'});
		customButton1.css({'padding-top':'-5px'});
		customButton1.css({'background-color':'#FFF'});
		customButton1.appendTo(dateNavigatorContainer);
	var customButton3 = $("<div id='ViewListR'>")
		customButton3.css({'margin-top':'9px'});
		customButton3.css({'margin-right':'10px'});
		customButton3.css({'padding-top':'-5px'});
		customButton3.css({'background-color':'#FFF'});
		customButton3.appendTo(dateNavigatorContainer);
	var customButton2 = $("<div id='Admin'>")
		customButton2.css({'margin-top':'9px'});
		customButton2.css({'margin-right':'10px'});
		customButton2.css({'padding-top':'-5px'});
		customButton2.css({'background-color':'#FFF'});
		customButton2.appendTo(dateNavigatorContainer);
		
		
	var customLegend = $("<div id='FutureReservationsLegend'>");
customLegend.css({
    'margin-top': '2px',
    'margin-right': '10px',
    'padding': '3px 4px',
    
    'border-radius': '4px',
    'display': 'flex',
    'align-items': 'center',
    'font-size': '12px'
});

// Créer l'indicateur bleu
var blueIndicator = $("<span>");
blueIndicator.css({
    'display': 'inline-block',
    'width': '15px',
    'height': '3px',
    'background-color': '#3498db',
    'margin-right': '5px',
    'vertical-align': 'middle'
});

// Texte pour l'indicateur bleu
var blueText = $("<span>+1 mois</span>");
blueText.css({
    'margin-right': '10px'
});

// Créer l'indicateur vert
var greenIndicator = $("<span>");
greenIndicator.css({
    'display': 'inline-block',
    'width': '15px',
    'height': '3px',
    'background-color': '#2ecc71',
    'margin-right': '5px',
    'vertical-align': 'middle'
});

// Texte pour l'indicateur vert
var greenText = $("<span>+2 mois</span>");

// Assembler la légende
customLegend.append(blueIndicator);
customLegend.append(blueText);
customLegend.append(greenIndicator);
customLegend.append(greenText);

// Ajouter la légende après le bouton Admin
customLegend.insertAfter(customButton2);
//Ajout de 2 champs à droite pour Nom et Phone permanent		
var dateNavigatorContainerR = $(".dx-toolbar-after");
	var customSetName = $("<div id='SetName'>")
		customSetName.css({'margin-top':'9px'});
		customSetName.css({'margin-right':'10px'});
		customSetName.css({'padding-top':'-5px'});
		customSetName.css({'background-color':'#FFF'});
		customSetName.appendTo(dateNavigatorContainerR);
	var customSetPhone = $("<div id='SetPhone'>")
		customSetPhone.css({'margin-top':'9px'});
		customSetPhone.css({'margin-right':'10px'});
		customSetPhone.css({'padding-top':'-5px'});
		customSetPhone.css({'background-color':'#FFF'});
		customSetPhone.appendTo(dateNavigatorContainerR);
//Création des éléments a afficher dans le menu au dessus du scheduler
//Nom Permanent
	SetName = $('#SetName').dxTextBox({
		height:32,
		showClearButton: true,
		placeholder: 'Nom permanent',
	  }).dxTextBox("instance");
//Phone permanent
	SetPhone = $('#SetPhone').dxTextBox({
		height:32,
		showClearButton: true,
		placeholder: 'Téléphone',
	  }).dxTextBox("instance");
//Bouton Liste Départ
function SetStepGetList(Step){
	if(Step == "In"){
		$("#popupMode").text("In");
	}
	else{
		$("#popupMode").text("Out");
	}
}

	$("#ViewList").dxButton({
		text: "Départ",
		icon: "event",
		height: "32px",
		stylingMode: "outlined",
		type: "success",
		onClick: () => {
			SetStepGetList("Out");
			popupListDay.show();
		}
	});	
//Bouton Liste Retour	
	$("#ViewListR").dxButton({
		text: "Retour",
		icon: "event",
		height: "32px",
		stylingMode: "outlined",
		type: "success",
		onClick: () => {
			SetStepGetList("In");
			popupListDay.show();
		}
	});
//Bouton Ajourd'hui	
	TodayButton = $("#ToNow").dxButton({
		text: "Ce mois",
		icon: "undo",
		height: "32px",
		stylingMode: "outlined",
		type: "back",
		disabled: false,
		onClick: () => {
			var SetDateNow = new Date();
			SetDateNow.setDate(1);
			$("#scheduler").dxScheduler("instance").option("currentDate", SetDateNow);
		}
	}).dxButton("instance");
//Bouton Admin	
	AdminButton = $("#Admin").dxButton({
		text: "Admin",
		icon: "preferences",
		height: "32px",
		stylingMode: "outlined",
		type: "danger",
		disabled: true,
		onClick: () => {
			popupListDay.show();
		}
	}).dxButton("instance");
//Ecouteur de touche, si F1 activé, on active le bouton admin	
	$(document).on("keydown", function (event) {
		if (event.key === "F1") {
			event.preventDefault();
			AdminButton.option("disabled", false);
		}
	});
	
	
	
	///Module pour affichage des reservation futur
	
	// Objet pour stocker les réservations futures par ressource et par mois
var futureReservations = {
    nextMonth: {}, // resourceId -> [jour1, jour2, ...]
    afterNextMonth: {} // resourceId -> [jour1, jour2, ...]
};

// Fonction pour charger les réservations futures une seule fois au démarrage
function loadFutureReservations() {
    // Obtenir le mois actuel
    var currentDate = scheduler.option("currentDate");
    var currentMonth = currentDate.getMonth();
    var currentYear = currentDate.getFullYear();
    
    // Construire les dates pour les 2 prochains mois
    var nextMonthDate = new Date(currentYear, currentMonth + 1, 1);
    var afterNextMonthDate = new Date(currentYear, currentMonth + 2, 1);
    
    // Faire une seule requête AJAX pour tous les mois futurs
    $.ajax({
        url: "FutureReservations.php", // Nouveau script PHP spécifique
        dataType: "json",
        data: {
            nextMonthStart: nextMonthDate.toISOString(),
            afterNextMonthStart: afterNextMonthDate.toISOString()
        },
        success: function(data) {
            if (data && data.nextMonth) {
                futureReservations.nextMonth = data.nextMonth;
            }
            if (data && data.afterNextMonth) {
                futureReservations.afterNextMonth = data.afterNextMonth;
            }
            
            // Ajouter les lignes d'indicateurs après réception des données
            //drawFutureReservationIndicators();
			addSimpleIndicators();
        }
    });
}

function addSimpleIndicators() {
    // Supprimer les indicateurs existants
    $(".future-reservation-indicator").remove();
    
    // Ajouter un conteneur d'indicateurs à chaque cellule
    $(".dx-scheduler-date-table-cell").each(function(index) {
        var $cell = $(this);
        
        // Créer un conteneur pour les indicateurs
        var $indicatorContainer = $("<div>")
            .addClass("indicator-container")
            .css({
                'position': 'absolute',
                'top': '0',
                'left': '0',
                'right': '0',
                'height': '10px',
                'z-index': '100',
                'pointer-events': 'none'
            });
        
        // Ajouter le conteneur à la cellule
        $cell.css('position', 'relative');
        $cell.append($indicatorContainer);
        
        // Ajouter un espace réservé pour la ligne bleue (invisible)
        // Ceci garantit que les lignes vertes seront toujours positionnées en dessous
        $("<div>")
            .addClass("blue-indicator-placeholder")
            .css({
                'position': 'absolute',
                'left': '0',
                'right': '0',
                'top': '2px',
                'height': '2px',
                'background-color': 'transparent' // Invisible
            })
            .appendTo($indicatorContainer);
    });
    
    // Récupérer les réservations complètes pour le mois prochain et le suivant
    $.ajax({
        url: "FutureReservations.php",
        dataType: "json",
        data: {
            currentMonth: scheduler.option("currentDate").getMonth(),
            currentYear: scheduler.option("currentDate").getFullYear()
        },
        success: function(data) {
            if (data && data.success) {
                // D'abord, ajouter tous les indicateurs verts (mois après le suivant)
                // Ils seront positionnés EN DESSOUS des lignes bleues grâce à l'espace réservé
                if (data.afterNextMonth) {
                    data.afterNextMonth.forEach(function(reservation) {
                        var resourceIndex = reservation.resourceIndex;
                        var startDay = reservation.startDay;
                        var endDay = reservation.endDay;
                        
                        for (var day = startDay; day <= endDay; day++) {
                            var rowStartIndex = resourceIndex * 31;
                            var cellIndex = rowStartIndex + (day - 1);
                            
                            var $cells = $(".dx-scheduler-date-table-cell");
                            if (cellIndex < $cells.length) {
                                var $cell = $cells.eq(cellIndex);
                                var $indicatorContainer = $cell.find(".indicator-container");
                                
                                // Ajouter l'indicateur vert (position basse fixe)
                                $("<div>")
                                    .addClass("future-reservation-indicator green-indicator")
                                    .css({
                                        'position': 'absolute',
                                        'left': '0',
                                        'right': '0',
                                        'top': '6px', // Position fixe, toujours sous la ligne bleue
                                        'height': '2px',
                                        'background-color': '#2ecc71'
                                    })
                                    .appendTo($indicatorContainer);
                            }
                        }
                    });
                }
                
                // Ensuite, ajouter les indicateurs bleus (mois suivant)
                // Ils seront positionnés AU-DESSUS des lignes vertes
                if (data.nextMonth) {
                    data.nextMonth.forEach(function(reservation) {
                        var resourceIndex = reservation.resourceIndex;
                        var startDay = reservation.startDay;
                        var endDay = reservation.endDay;
                        
                        for (var day = startDay; day <= endDay; day++) {
                            var rowStartIndex = resourceIndex * 31;
                            var cellIndex = rowStartIndex + (day - 1);
                            
                            var $cells = $(".dx-scheduler-date-table-cell");
                            if (cellIndex < $cells.length) {
                                var $cell = $cells.eq(cellIndex);
                                var $indicatorContainer = $cell.find(".indicator-container");
                                
                                // Remplacer l'espace réservé par un indicateur bleu réel
                                $indicatorContainer.find(".blue-indicator-placeholder").remove();
                                
                                // Ajouter l'indicateur bleu (position haute fixe)
                                $("<div>")
                                    .addClass("future-reservation-indicator blue-indicator")
                                    .css({
                                        'position': 'absolute',
                                        'left': '0',
                                        'right': '0',
                                        'top': '2px', // Position fixe, toujours au-dessus
                                        'height': '2px',
                                        'background-color': '#3498db'
                                    })
                                    .appendTo($indicatorContainer);
                            }
                        }
                    });
                }
            }
        }
    });
}

function drawFutureReservationIndicators() {
    // Supprimer d'abord tous les indicateurs existants
    $(".future-reservation-indicator").remove();
    
    // Obtenir les informations sur le mois actuel
    var currentDate = scheduler.option("currentDate");
    var daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();
    
    // Pour chaque ressource
    priorityData.forEach(function(resource, priorityIndex) {
        var resourceId = resource.gameId;
        
        // Indicateurs pour le mois suivant (bleu)
        if (futureReservations.nextMonth[resourceId]) {
            futureReservations.nextMonth[resourceId].forEach(function(day) {
                addIndicator(day, priorityIndex, "#3498db", 5); // bleu, position top 5px
            });
        }
        
        // Indicateurs pour le mois d'après (vert)
        if (futureReservations.afterNextMonth[resourceId]) {
            futureReservations.afterNextMonth[resourceId].forEach(function(day) {
                // Si le même jour est déjà réservé le mois suivant, décaler l'indicateur
                var hasNextMonth = futureReservations.nextMonth[resourceId] && 
                                  futureReservations.nextMonth[resourceId].includes(day);
                addIndicator(day, priorityIndex, "#2ecc71", hasNextMonth ? 8 : 5); // vert
            });
        }
    });
}

// Fonction helper pour ajouter un indicateur sur une cellule spécifique
function addIndicator(day, priorityIndex, color, topPosition) {
    // Un peu de débogage
    console.log("Ajout d'indicateur:", day, priorityIndex, color);
    
    try {
        // Sélectionner toutes les cellules du jour donné
        var dayColumnSelector = ".dx-scheduler-date-table-cell[data-date*='" + day + "']";
        var $allCells = $(dayColumnSelector);
        console.log("Cellules trouvées:", $allCells.length);
        
        // Trouver la cellule pour la ressource spécifique (il y a généralement une cellule par ressource par jour)
        var $cell = $allCells.eq(priorityIndex);
        
        if ($cell.length) {
            console.log("Cellule trouvée pour l'indicateur");
            // Ajouter l'indicateur
            $("<div>")
                .addClass("future-reservation-indicator")
                .css({
                    'position': 'absolute',
                    'left': '0',
                    'right': '0',
                    'top': topPosition + 'px',
                    'height': '2px',
                    'background-color': color,
                    'z-index': '10'
                })
                .appendTo($cell);
        } else {
            console.log("Aucune cellule trouvée pour l'indicateur");
        }
    } catch (error) {
        console.error("Erreur lors de l'ajout de l'indicateur:", error);
    }
}

loadFutureReservations();
    
    // Mettre à jour les indicateurs lors du changement de mois
    scheduler.option("onOptionChanged", function(e) {
        if (e.name === "currentDate") {
            var startDate = e.value;
            var endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 1, startDate.getDate()); 
            getAppointments(startDate, endDate);
            
            // Recharger les réservations futures pour le nouveau mois
            loadFutureReservations();
        }
    });
    
    // Redessiner les indicateurs après le rendu complet du scheduler
    scheduler.option("onContentReady", function(e) {
        e.component.scrollTo(new Date());
        e.component.element().find(".dx-link-edit").attr("title", "Editer");
        e.component.element().find(".dx-link-delete").attr("title", "Supprimer");
        positionSearchBox();
        
        // Dessiner les indicateurs après le rendu complet
        setTimeout(function() {
            //drawFutureReservationIndicators();
        }, 200);
    });

	
	
	//Fin module reservation futur
	///Module de tags
	// Créer un conteneur pour les tags après la légende
var tagsContainer = $("<div id='reservationTags'>");
tagsContainer.css({
    'margin-top': '5px',
    'margin-right': '10px',
    'display': 'flex',
    'flex-wrap': 'wrap',
    'gap': '8px',
    'max-width': '700px',
    'height': '55px', // Hauteur optimisée pour 2 lignes (tags de 24px + margins)
    'overflow': 'hidden',
    'position': 'relative',
    'transition': 'all 0.3s ease' // Animation fluide
});



// Insérer les conteneurs après la légende
tagsContainer.insertAfter(customLegend);

var currentTagPage = 0;
var tagsPerPage = 0; // Calculé dynamiquement
var totalTagPages = 0;
var allTags = []; // Stocker tous les tags

// Fonction pour mettre à jour les tags
function updateReservationTags() {
    // Vider les conteneurs
    tagsContainer.empty();
    
    
    // Reset des variables
    currentTagPage = 0;
    allTags = [];
    
    // Récupérer le mois et l'année actuels
    var currentDate = scheduler.option("currentDate");
    var currentMonth = currentDate.getMonth() + 1;
    var currentYear = currentDate.getFullYear();
    
    // Requête AJAX pour obtenir les réservataires uniques du mois en cours
    $.ajax({
        url: "GetUniqueReservers.php",
        dataType: "json",
        data: {
            month: currentMonth,
            year: currentYear
        },
        success: function(data) {
			if (data && data.success && data.reservers) {
				data.reservers.forEach(function(reserver) {
					var tag = createTag(reserver);
					allTags.push(tag);
				});
				
				calculatePaginationAndDisplay();
				attachTagEvents(); // AJOUTER CETTE LIGNE
			}
		}
    });
}


function createTag(reserver) {
    var tag = $("<div class='reservation-tag'>");
    tag.text(reserver);
    tag.attr('data-reserver', reserver); // AJOUTER CETTE LIGNE - c'était manquant !
    tag.css({
        'padding': '4px 6px',
        'background-color': '#f0f0f0',
        'border': 'solid 1px rgb(166, 166, 166)',
        'border-radius': '12px',
        'font-size': '11px',
        'cursor': 'pointer',
        'margin-right': '5px',
        'margin-top': '3px',
        'margin-bottom': '2px',
        'transition': 'background-color 0.2s',
        'color': 'rgb(98, 94, 94)',
        'font-weight': 'bold',
        'white-space': 'nowrap',
        'height': '20px',
        'display': 'inline-flex',
        'align-items': 'center',
        'box-sizing': 'border-box'
    });
    
    // Hover effect cohérent avec votre style
    tag.hover(
        function() { $(this).css('background-color', '#e0e0e0'); },
        function() { $(this).css('background-color', '#f0f0f0'); }
    );
    
    return tag;
}

function attachTagEvents() {
    // Supprimer les anciens événements pour éviter les doublons
    tagsContainer.off('click', '.reservation-tag');
    
    // Attacher l'événement avec délégation
    tagsContainer.on('click', '.reservation-tag', function() {
        var reserver = $(this).attr('data-reserver');
        if (reserver) {
            showReservationsForUser(reserver);
        }
    });
}

function calculatePaginationAndDisplay() {
    if (allTags.length === 0) return;
    
    // Mesurer combien de tags rentrent sur 2 lignes
    var tempContainer = $("<div>").css({
        'position': 'absolute',
        'visibility': 'hidden',
        'top': '-1000px',
        'display': 'flex',
        'flex-wrap': 'wrap',
        'gap': '8px',
        'max-width': '700px',
        'width': '700px'
    });
    
    allTags.forEach(function(tag) {
        tempContainer.append(tag.clone());
    });
    
    $('body').append(tempContainer);
    
    var tagsInTwoLines = 0;
    var maxHeight = 55;
    
    tempContainer.find('.reservation-tag').each(function(index) {
        var tagPosition = $(this).position();
        var tagBottom = tagPosition.top + $(this).outerHeight(true);
        
        if (tagBottom <= maxHeight) {
            tagsInTwoLines++;
        } else {
            return false;
        }
    });
    
    tempContainer.remove();
    
    // CORRECTION: Ajuster le nombre de tags par page pour laisser de la place aux boutons
    var maxTagsPerPage = Math.max(1, tagsInTwoLines);
    
    // Si on a plus de tags que ce qui peut s'afficher, on réserve de l'espace pour les boutons
    if (allTags.length > maxTagsPerPage) {
    tagsPerPage = Math.max(1, maxTagsPerPage - 4); // Plus conservateur : -4 au lieu de -2
} else {
    tagsPerPage = maxTagsPerPage; // Pas besoin de boutons
}
    
    // S'assurer qu'on a au moins 1 tag normal par page
    tagsPerPage = Math.max(1, tagsPerPage);
    
    totalTagPages = Math.ceil(allTags.length / tagsPerPage);
    
    console.log('Tags total:', allTags.length, 'Tags par page (ajusté):', tagsPerPage, 'Pages:', totalTagPages);
    
    displayTagPage(0);
}

function displayTagPage(pageIndex) {
    tagsContainer.empty();
    
    var tagsToShow = [];
    var startIndex = pageIndex * tagsPerPage;
    var endIndex = Math.min(startIndex + tagsPerPage, allTags.length);
    
    // Ajouter tag "Retour" si pas au début
    if (pageIndex > 0) {
        var backTag = createNavigationTag("◀ Retour", function() {
            displayTagPage(currentTagPage - 1);
        });
        tagsToShow.push(backTag);
    }
    
    // Ajouter les tags normaux de cette page
    for (var i = startIndex; i < endIndex; i++) {
        if (allTags[i]) {
            tagsToShow.push(allTags[i]);
        }
    }
    
    // Ajouter tag "Suivant" si pas à la fin
    if (pageIndex < totalTagPages - 1) {
        var nextTag = createNavigationTag("Suivant ▶", function() {
            displayTagPage(currentTagPage + 1);
        });
        tagsToShow.push(nextTag);
    }
    
    // Afficher tous les tags
    tagsToShow.forEach(function(tag) {
        tagsContainer.append(tag);
    });
    
    currentTagPage = pageIndex;
    
    // AJOUTER CETTE LIGNE
    attachTagEvents();
}

function createNavigationTag(text, clickHandler) {
    var tag = $("<div class='navigation-tag'>");
    tag.text(text);
    tag.css({
        'padding': '4px 6px',
        'background-color': '#337ab7', // Bleu
        'color': '#ffffff', // Texte blanc
        'border': 'solid 1px #2e6da4',
        'border-radius': '12px',
        'font-size': '11px',
        'cursor': 'pointer',
        'margin-right': '5px',
        'margin-top': '3px',
        'margin-bottom': '2px',
        'transition': 'background-color 0.2s',
        'font-weight': 'bold',
        'white-space': 'nowrap',
        'height': '20px',
        'display': 'inline-flex',
        'align-items': 'center',
        'box-sizing': 'border-box'
    });
    
    // Hover effect
    tag.hover(
        function() { $(this).css('background-color', '#286090'); },
        function() { $(this).css('background-color', '#337ab7'); }
    );
    
    // Click event
    tag.on('click', clickHandler);
    
    return tag;
}



// Appeler la fonction au chargement initial
updateReservationTags();

// Mettre à jour les tags lors du changement de mois
scheduler.option("onOptionChanged", function(e) {
    if (e.name === "currentDate") {
        var startDate = e.value;
        var endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 1, startDate.getDate()); 
        getAppointments(startDate, endDate);
        
        // Recharger les réservations futures pour le nouveau mois
        loadFutureReservations();
        
        // Mettre à jour les tags
        updateReservationTags();
    }
});

// MODIFIER la fonction showReservationsForUser()
function showReservationsForUser(username) {
    console.log("Affichage des réservations pour : " + username);
    
    var currentDate = scheduler.option("currentDate");
    var currentMonth = currentDate.getMonth() + 1;
    var currentYear = currentDate.getFullYear();
    
    $.ajax({
        url: "GetUserReservations.php",
        dataType: "json",
        data: {
            user: username,
            month: currentMonth,
            year: currentYear
        },
        success: function(data) {
            console.log("Réponse reçue :", data);
            
            if (data && data.success && data.reservations && data.reservations.length > 0) {
                // Regrouper les réservations par période de dates
                var groupedReservations = {};
                
                data.reservations.forEach(function(reservation) {
                    var startDate = new Date(reservation.startDate);
                    var endDate = new Date(reservation.endDate);
                    var dateKey = "Du " + startDate.getDate() + " " + month[startDate.getMonth()] + 
                                 " au " + endDate.getDate() + " " + month[endDate.getMonth()];
                    
                    if (!groupedReservations[dateKey]) {
                        groupedReservations[dateKey] = [];
                    }
                    
                    groupedReservations[dateKey].push({
                        resourceName: reservation.resourceName,
                        info: reservation.info || '',
                        startDate: startDate,
                        endDate: endDate
                    });
                });
                
                // Trier les groupes par date de début
                var sortedGroups = Object.keys(groupedReservations).sort(function(a, b) {
                    var dateA = groupedReservations[a][0].startDate;
                    var dateB = groupedReservations[b][0].startDate;
                    return dateA - dateB;
                });
                
                // Créer le popup
                var $popup = $("<div>").appendTo("body");
                
                var popup = $popup.dxPopup({
                    visible: true,
                    title: "Réservations de " + username,
                    width: 500,
                    height: 600,
                    maxHeight: 600,
                    showTitle: true,
                    showCloseButton: true,
                    onContentReady: function(e) {
                        var $content = $("<div>").appendTo(e.component.content());
                        
                        // Créer le contenu groupé
                        sortedGroups.forEach(function(dateKey) {
                            var reservations = groupedReservations[dateKey];
                            
                            // En-tête de groupe (période)
                            var $groupHeader = $("<div>").css({
                                'background-color': '#f0f8ff',
                                'border': '1px solid #337ab7',
                                'border-radius': '6px',
                                'padding': '8px 12px',
                                'margin': '10px 0 5px 0',
                                'font-weight': 'bold',
                                'color': '#337ab7',
                                'font-size': '16px'
                            }).text(dateKey);
                            
                            $content.append($groupHeader);
                            
                            // Liste des objets pour cette période
                            var $itemsList = $("<div>").css({
                                'margin-left': '15px',
                                'margin-bottom': '10px',
								'font-size' : '16px'
                            });
                            
                            reservations.forEach(function(item) {
                                var $item = $("<div>").css({
                                    'padding': '6px 10px',
                                    'margin': '3px 0',
                                    'background-color': '#f9f9f9',
                                    'border-left': '3px solid #337ab7',
                                    'border-radius': '0 4px 4px 0',
                                    'font-size': '16px'
                                });
                                
                                // Nom de l'objet
                                var $resourceName = $("<div>").css({
                                    'font-weight': 'bold',
                                    'color': '#333',
                                    'margin-bottom': '2px'
                                }).text(item.resourceName);
                                
                                $item.append($resourceName);
                                
                                // Commentaire si présent
                                if (item.info && item.info.trim() !== '') {
                                    var $info = $("<div>").css({
                                        'font-style': 'italic',
                                        'color': '#666',
                                        'font-size': '12px',
                                        'margin-top': '4px'
                                    }).html('<i class="dx-icon dx-icon-comment"></i> ' + item.info);
                                    
                                    $item.append($info);
                                }
                                
                                $itemsList.append($item);
                            });
                            
                            $content.append($itemsList);
                        });
                        
                        // Ajouter un scroll si nécessaire
                        /* $content.css({
                            'max-height': '500px',
                            'overflow-y': 'auto',
                            'padding': '10px'
                        }); */
						 $content.css({
            'height': '100%',
            'overflow-y': 'auto',
            'padding': '10px',
            'box-sizing': 'border-box'
        });
                    },
                    onHidden: function() {
                        $popup.remove();
                    }
                }).dxPopup("instance");
            } else {
                // Message si pas de réservations
                DevExpress.ui.notify({
                    message: "Aucune réservation trouvée pour " + username,
                    position: { my: "top", at: "top" },
                    width: 300,
                    shading: true,
                    shadingColor: "rgba(0, 0, 0, 0.5)"
                }, "warning", 2000);
            }
        },
        error: function(xhr, status, error) {
            console.error("Erreur lors de la récupération des réservations :", error);
            
            DevExpress.ui.notify({
                message: "Erreur lors de la récupération des réservations",
                position: { my: "top", at: "top" },
                width: 300,
                shading: true,
                shadingColor: "rgba(0, 0, 0, 0.5)"
            }, "error", 2000);
        }
    });
}
	
	//Fin module tag
});
</script>

<?php
include "_pdo.php";
$db_file = "Surdim.db";
PDO_Connect("sqlite:$db_file");

// Récupérer les ressources visibles et réattribuer les priorités dynamiquement
$data = PDO_FetchAll("
    SELECT * 
    FROM surdim_List 
    WHERE Visible = 1 
    ORDER BY Name ASC, Is_Titre DESC, Id ASC
");

$CurrentId = 0;
$i = 0;

$ArrayColor = array("#addaa0", "#e9d2aa", "#abc9e4", "#e3acb7");
$ArrayColorRVB = array("120, 179, 102", "223, 179, 105", "105, 166, 223", "209, 135, 151");
$assignedColors = array();
$assignedColorsRVB = array();
$priorityMap = array();

echo "<script>";

//Patch pour résourdre les probleme d'affichage des ressources sur la bonne ligne
echo "var idToPriorityMap = {";
foreach($data as $index => $row) {
    echo '"'.$row['Id'].'": '.$index;
    if($index < count($data) - 1) echo ", ";
}
echo "};";

// Et l'inverse
echo "var priorityToIdMap = {";
foreach($data as $index => $row) {
    echo '"'.$index.'": '.$row['Id'];
    if($index < count($data) - 1) echo ", ";
}
echo "};";
// Fin de patch
echo 'var priorityData = [';

foreach($data as $row) {
    // Réattribuer la priorité dynamiquement
    $newPriority = $CurrentId;
    $priorityMap[$row['Id']] = $newPriority;

    echo "{";
    echo 'text: "'.$row['Name_Vue'].'", ';
    echo 'Titre: "'.$row['Name'].'", ';
    echo 'gameId: "'.$row['Id'].'", ';
    echo 'priority: '.$newPriority.', ';
    echo 'id: '.$i.', ';
    
    $AppId = $row['Name'];
    if (isset($assignedColors[$AppId])) {
        echo 'color: "' . $assignedColors[$AppId] . '", ';
        echo 'colorRVB: "' . $assignedColorsRVB[$AppId] . '" ';
    } else {
        $colorIndex = count($assignedColors) % count($ArrayColor);
        $colorIndexRVB = count($assignedColorsRVB) % count($ArrayColorRVB);
        $color = $ArrayColor[$colorIndex];
        $colorRVB = $ArrayColorRVB[$colorIndexRVB];
        $assignedColors[$AppId] = $color;
        echo 'color: "' . $color . '", ';
        $assignedColorsRVB[$AppId] = $colorRVB;
        echo 'colorRVB: "' . $colorRVB . '" ';
    }

    if($row['Is_Titre'] == "true"){
        echo ', is_titre: '.$row['Is_Titre'];
    }
    if($CurrentId >= Count($data)){
        echo "}";
    }
    else{
        echo "},";
    }
    $CurrentId++;
    $i++;
}

echo "]";
echo "</script>";
?>


  </body>
</html>