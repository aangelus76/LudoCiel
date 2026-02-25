<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<title>LudoPresence - Système d'enregistrement</title>
	<link rel="stylesheet" href="css/dx.light.css">
	<script src="js/jquery-3.6.0.min.js"></script>
	<script src="js/jquery-ui.min.js"></script>
	<script src="js/dx.all.js"></script>
	<link rel="stylesheet" href="css/jquery-ui.css">
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/custom.css">
</head>

<body>
	<div class="container">
		<div class="left-panel">
			<div class="date-selector">
				<label style="display:none" for="dateSelector">Sélectionner une date:</label>
				<input type="text" id="dateSelector">
				<span id="SayDay" class="SayDay"></span>
			</div>
			<div class="button-grid">
				<div id="addADULTE" class="btn_panel"></div>
				<div id="addJeune" class="btn_panel"></div>
				<div id="addEnfant" class="btn_panel"></div>
				<div id="openGroupModal" class="btn_panel"></div>
			</div>
			<div class="stats">
				<div class="stat-title">Statistiques du jour : <span style="color:#b85858" class="CountInside">0</span> <span style="color:#7f7e7e; font-size:12px"> en salle</span></div>
				<div class="stat-row">
					<span class="stat-label">PUBLIC</span>
					<span class="stat-value statCount" id="publicCount">0</span>
				</div>
				<div class="stat-row">
					<span class="stat-label statHour" id="publicHours">00:00</span>
				</div>
				<div class="stat-row">
					<span class="stat-label">PARTENAIRE</span>
					<span class="stat-value statCount" id="partenaireCount">0</span>
				</div>
				<div class="stat-row">
					<span class="stat-label statHour" id="partenaireHours">00:00</span>
				</div>
				<div class="stat-row">
					<span class="stat-label">TOTAL DE PRÉSENCES</span>
					<span class="stat-value statCount" id="totalPresences">0</span>
				</div>
				<div class="stat-row">
					<span class="stat-label statHour" id="totalHours">00:00</span>
				</div>
			</div>

			<div class="stats">
				<div class="stat-title">Statistiques de la semaine :</div>
				<div class="stat-row">
					<span class="stat-label">PUBLIC</span>
					<span class="stat-value statCount" id="publicCountW">0</span>
				</div>
				<div class="stat-row">
					<span class="stat-label statHour" id="publicHoursW">00:00</span>
				</div>
				<div class="stat-row">
					<span class="stat-label">PARTENAIRE</span>
					<span class="stat-value statCount" id="partenaireCountW">0</span>
				</div>
				<div class="stat-row">
					<span class="stat-label statHour" id="partenaireHoursW">00:00</span>
				</div>
				<div class="stat-row">
					<span class="stat-label">TOTAL DE PRÉSENCES</span>
					<span class="stat-value statCount" id="totalPresencesW">0</span>
				</div>
				<div class="stat-row">
					<span class="stat-label statHour" id="totalHoursW">00:00</span>
				</div>
			</div>
			
			<div class="partners-pills" id="partnersPills"></div>

		</div>
		<div class="right-panel">
			<div id="associateButton" class="btn_associate"></div>
			<div id="addToGroupButton" class="btn_addToGroup"></div>
			<div id="groupsButton" class="btn_partbaiview"></div>
			<div id="Statistique" class="btn_Stats"></div>
<div id="wsStatusIndicator" style="display: inline-block; margin-left: 10px; vertical-align: middle; position: relative; cursor: pointer;">
    <img id="wsConnecting" src="images/ws-connecting.png" alt="Connexion..." style="width: 30px; height: 30px; display: none;">
    <img id="wsConnected" src="images/ws-connected.png" alt="Connecté" style="width: 30px; height: 30px; display: none;">
    <span id="wsIpBadge" style="display: none;"></span>
</div>
			<div id="TotalDayButton" class="btn_TotalDayview"></div>
<div class="list-container" style="margin-top: 10px;">
    <div class="list">
        <div class="list-header">
    <div class="header-col T_ID">
        <span id="toggleViewIcon" style="cursor: pointer; margin-right: 5px;"></span> #
    </div>
            <div class="header-col T_Ind">Individu</div>
            <div class="header-col T_TCom">Arrivée</div>
            <div class="header-col T_TUse">Présence</div>
            <div class="header-col T_Act">Actions</div>
            <div class="header-col T_IDG">ID Groupe</div>
        </div>
        <div id="participantList">
            <!-- Participant list will be dynamically populated here -->
        </div>
    </div>
</div>
		</div>
	</div>

<!-- Modal pour ajouter des partenaires -->
<div id="partnersModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closePartnersModal">&times;</span>
        <h2>Ajouter un groupe/partenaire</h2>
        <form id="partnerForm">
            <div class="form-row">
                <label for="partnerName">Nom :</label>
                <input type="text" id="partnerName" name="partnerName" required>
            </div>
            <div class="form-row">
                <div class="form-half-row">
                    <label for="numPersons">Nombre de personnes :</label>
                    <input type="number" id="partnerSize" name="numPersons" min="1" required>
                </div>
                <div class="form-half-row">
                    <label for="presenceTime">Temps de présence :</label>
                    <input type="time" id="partnerHours" name="presenceTime" min="01:00" value="01:00" required>
                </div>
            </div>
            <button type="submit" class="PartnerAdd_Btn">Ajouter</button>
        </form>
    </div>
</div>

<!-- Modal pour la liste des partenaires -->
<div id="partnersListModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closePartnersListModal">&times;</span>
        <h2>Liste des Groupes/Partenaires</h2>
        <table id="partnersTable" class="partners-table">
            <tbody id="partnersList">
                <!-- La liste des partenaires sera peuplée dynamiquement ici -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal pour éditer un partenaire -->
<div id="editPartnerModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeEditPartnerModal">&times;</span>
        <h2>Modifier le groupe/partenaire</h2>
        <form id="editPartnerForm">
            <input type="hidden" id="editPartnerId" name="partnerId">
            
            <div class="form-row">
                <label for="editPartnerName">Nom :</label>
                <input type="text" id="editPartnerName" name="partnerName" required>
            </div>
            <div class="form-row">
                <div class="form-half-row">
                    <label for="editPartnerSize">Nombre de personnes :</label>
                    <input type="number" id="editPartnerSize" name="numPersons" min="1" required>
                </div>
                <div class="form-half-row">
                    <label for="editPartnerHours">Temps de présence :</label>
                    <input type="time" id="editPartnerHours" name="presenceTime" min="01:00" value="01:00" required>
                </div>
            </div>
            <button type="submit">Enregistrer</button>
        </form>
    </div>
</div>

	<div id="groupPopup" style="display:none;">
		<div>
			<label for="groupIdInput">ID du groupe (4 caractères max):</label>
			<input type="text" id="groupIdInput" maxlength="4">
		</div>
	</div>

	
	<!-- Alerte et Prompt personnalisés -->
    <div id="customDialog" class="ui-dialog" title="">
        <p id="dialogMessage"></p>
        <input type="text" id="dialogInput" style="display:none;" />
    </div>
    
	<script>
DevExpress.localization.locale("fr");
	</script>
	<!-- WebSocket client (NOUVEAU) -->
	<script src="ws-handlers/WS_Pointage.js"></script>
	
	<!-- Application principale -->
	<script src="js/main.js"></script>
</body>

</html>