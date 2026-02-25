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
		//height: (window.outerHeight - 5),
		//height: "auto",
		
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
			GameID = priorityData[e.appointmentData.priority].gameId;
			StartDate = e.appointmentData.startDate;
			EndDate = e.appointmentData.endDate;
			User = e.appointmentData.text;
			Phone = e.appointmentData.Phone == undefined ? "" : e.appointmentData.Phone;
			Info = e.appointmentData.description == undefined ? "Nan" : e.appointmentData.description;
			Priority = e.appointmentData.priority;
			addAppointment(StartDate, EndDate, User, Info, GameID, Priority, Phone, "Add");
			setTimeout(function() {
				getAppointments(e.appointmentData.startDate, e.appointmentData.endDate).then(function(appointments) {
					e.component.option("dataSource", appointments);
				});
			}, 1000);
		},
		onAppointmentUpdated: function(e) {
			showToast('Modification de : ', e.appointmentData.text, 'info');
			RentID = e.appointmentData.ID;
			StartDate = e.appointmentData.startDate;
			EndDate = e.appointmentData.endDate;
			User = e.appointmentData.text;
			Phone = e.appointmentData.Phone == undefined ? "Nan" : e.appointmentData.Phone;
			Info = e.appointmentData.description == undefined ? "Nan" : e.appointmentData.description;
			Priority = e.appointmentData.priority;
			addAppointment(StartDate, EndDate, User, Info, RentID, Priority, Phone, "Update");
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
			addAppointment(StartDate, EndDate, User, Info, RentID, 0, 0, "Delete");
		},
		appointmentTemplate: function(data) {
			return $("<div>" + data.appointmentData.text + "</div>");
		},
		onAppointmentRendered: function(e) {
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
		appointmentTooltipTemplate: function(data) {
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
            const scrollTop = $eventToScroll.offset().top - $scheduler.offset().top;
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
        const hiddenMode = $("<div id='popupMode' style='display: none;' />");
        content.append(
            $(ContentListDay), DatePick.$element(), listInstance.$element(), hiddenMode
        );
        content.dxScrollView({
            width: '100%',
            height: '100%',
        });
        return content;
    },
    onShowing: function () {
        DatePick.option("value", new Date());
        if (lastClickedButton == "RentOut") {
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
		height:36,
		showClearButton: true,
		placeholder: 'Nom permanent',
	  }).dxTextBox("instance");
//Phone permanent
	SetPhone = $('#SetPhone').dxTextBox({
		height:36,
		showClearButton: true,
		placeholder: 'Téléphone',
	  }).dxTextBox("instance");
//Bouton Liste Départ	
	$("#ViewList").dxButton({
		text: "Départ",
		icon: "event",
		height: "36px",
		stylingMode: "outlined",
		type: "success",
		onClick: () => {
			lastClickedButton = 'RentOut';
			popupListDay.show();
		}
	});	
//Bouton Liste Retour	
	$("#ViewListR").dxButton({
		text: "Retour",
		icon: "event",
		height: "36px",
		stylingMode: "outlined",
		type: "success",
		onClick: () => {
			lastClickedButton = 'RentIn';
			popupListDay.show();
		}
	});
//Bouton Ajourd'hui	
	TodayButton = $("#ToNow").dxButton({
		text: "Ce mois",
		icon: "undo",
		height: "36px",
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
		height: "36px",
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
	
});
</script>

<?php
include "_pdo.php";
$db_file = "Surdim.db";
PDO_Connect("sqlite:$db_file");
$data = PDO_FetchAll("SELECT * FROM surdim_List ORDER BY Name, Priority ASC");

$CurrentId = 1;
$i = 0;

$ArrayColor = array("#addaa0", "#e9d2aa", "#abc9e4", "#e3acb7");
$ArrayColorRVB = array("120, 179, 102", "223, 179, 105", "105, 166, 223", "209, 135, 151");
$assignedColors = array();
$assignedColorsRVB = array();

echo "<script>";
echo 'var priorityData = [';
foreach($data as $row) {
    echo "{";
    if($row['Is_Titre'] == "true"){
        echo 'text: "'.$row['Name_Vue'].'", ';
    }
    else{
        echo 'text: "'.$row['Name_Vue'].'", ';
    }
    echo 'Titre : "'.$row['Name'].'", ';
    echo 'gameId : "'.$row['Id'].'", ';
    echo 'id : '.$i.', ';
    $AppId = $row['Name'];
    if (isset($assignedColors[$AppId])) {
        echo 'color : "' . $assignedColors[$AppId] . '", ';
        echo 'colorRVB : "' . $assignedColorsRVB[$AppId] . '" ';
    } else {
        $colorIndex = count($assignedColors) % count($ArrayColor);
        $colorIndexRVB = count($assignedColorsRVB) % count($ArrayColorRVB);
        $color = $ArrayColor[$colorIndex];
        $colorRVB = $ArrayColorRVB[$colorIndexRVB];
        $assignedColors[$AppId] = $color;
        echo 'color : "' . $color . '", ';
        $assignedColorsRVB[$AppId] = $colorRVB;
        echo 'colorRVB : "' . $colorRVB . '" ';
    }
    
    if($row['Is_Titre'] == "true"){
        echo ', is_titre : '.$row['Is_Titre'];
    }
    if($CurrentId >= Count($data)){
        echo "}";
    }
    else{
        echo "},";
    }
    $CurrentId = $CurrentId + 1;
    $i++;
}
echo "]";
echo "</script>";
?>

  </body>
</html>