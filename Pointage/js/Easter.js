const EasterEggs = {
    init: function() {
        this.rapidClickDetector();
        this.timeRelativityDetector();
        this.massExodusDetector();
        this.mysteryDetector();
        this.fourthPartnersDetector();
        this.massArrivalDetector();
		this.fullHouseDetector();
		this.luckyNumberDetector();
		this.groupSplitDetector();
    },
    //Afficheur des badges
    showImageMessage: function(imagePath) {
        const message = $('<div>', {
            class: 'easter-egg-image',
            html: `<img src="${imagePath}" alt="Easter Egg">`
        }).appendTo('body');
        message.fadeIn(300)
            .delay(5000)
            .fadeOut(500, function() {
                $(this).remove();
            });
    },
    //Detection de clique trop rapide sur les boutons individus
    rapidClickDetector: function() {
        let clicks = [];
        const CLICK_THRESHOLD = 100;
        const CLICKS_NEEDED = 2;
        $('.btn_panel').on('click', function() {
            const now = Date.now();
            clicks.push(now);
            clicks = clicks.filter(click => now - click < CLICK_THRESHOLD * CLICKS_NEEDED);
            if (clicks.length >= CLICKS_NEEDED) {
                EasterEggs.showImageMessage('images/rafale.png');
                clicks = [];
            }
        });
    },
    //Detection d"ajout de temps rapidement
    timeRelativityDetector: function() {
        let plusClicks = [];
        const CLICK_THRESHOLD = 90;
        const CLICKS_NEEDED = 2;
        $('#participantList').on('click', 'button[data-action="add"]', function(e) {
            const now = Date.now();
            plusClicks.push(now);
            plusClicks = plusClicks.filter(click => now - click < CLICK_THRESHOLD * CLICKS_NEEDED);
            if (plusClicks.length >= CLICKS_NEEDED) {
                EasterEggs.showImageMessage('images/relativite.png');
                plusClicks = [];
            }
        });
    },
    // Detection de départ en masse	
    massExodusDetector: function() {
        let exodusClicks = [];
        const EXODUS_THRESHOLD = 6000;
        const CLICKS_NEEDED = 8;
        document.addEventListener('click', function(e) {
            let element = e.target;
            while (element && element !== document) {
                if (element.classList &&
                    (element.classList.contains('fa-sign-out') ||
                        element.classList.contains('fa-sign-in'))) {
                    const now = Date.now();
                    exodusClicks = exodusClicks.filter(click => now - click < EXODUS_THRESHOLD);
                    exodusClicks.push(now);
                    if (exodusClicks.length >= CLICKS_NEEDED) {
                        EasterEggs.showImageMessage('images/alerte.png');
                        exodusClicks = [];
                    }
                    break;
                }
                element = element.parentElement;
            }
        }, true);
    },
    // Detection d'attente sur l'icone prénom	
    mysteryDetector: function() {
        let hoverTimer;
        document.addEventListener('mouseover', function(e) {
            let element = e.target;
            if (element.classList && element.classList.contains('fa-user-circle')) {
                const isNameSet = getComputedStyle(element).color === 'rgb(76, 175, 80)';
                hoverTimer = setTimeout(() => {
                    if (isNameSet) {
                        EasterEggs.showImageMessage('images/relecture.png');
                    } else {
                        EasterEggs.showImageMessage('images/inspiration.png');
                    }
                }, 6000);
            }
        }, true);
        document.addEventListener('mouseout', function(e) {
            if (hoverTimer) {
                clearTimeout(hoverTimer);
            }
        }, true);
    },
    // Détection du 4ème partenaire
    fourthPartnersDetector: function() {
        document.addEventListener('click', function(e) {
            let element = e.target;
            if (element.closest('#partnerForm button[type="submit"]')) {
                const pillsContainer = document.getElementById('partnersPills');
                if (pillsContainer && pillsContainer.children.length === 3) {
                    setTimeout(() => {
                        EasterEggs.showImageMessage('images/fourth.png');
                    }, 100);
                }
            }
        }, true);
    },
    // Détection d'arrivée en masse
    massArrivalDetector: function() {
        let addClicks = [];
        const THRESHOLD = 10000;
        const CLICKS_NEEDED = 10;
        document.addEventListener('click', function(e) {
            let element = e.target;
            if (element.closest('#addADULTE') ||
                element.closest('#addJeune') ||
                element.closest('#addEnfant')) {
                const now = Date.now();
                addClicks = addClicks.filter(click => now - click < THRESHOLD);
                addClicks.push(now);
                if (addClicks.length >= CLICKS_NEEDED) {
                    EasterEggs.showImageMessage('images/invasion.png');
                    console.log("Invasion");
                    addClicks = [];
                }
            }
        }, true);
    },
	//plus de 45 perssones dans la salle
	fullHouseDetector: function() {
		$('#addADULTE, #addJeune, #addEnfant').on('click', function() {        
			setTimeout(() => {
				const presentCount = parseInt($('#publicCount').text());            
				if (presentCount >= 45) {
					const today = new Date().toDateString();
					const lastTriggered = sessionStorage.getItem('fullHouseTriggered');
					
					if (lastTriggered !== today) {
						EasterEggs.showImageMessage('images/fullhouse.png');
						sessionStorage.setItem('fullHouseTriggered', today);
					}
				}
			}, 200);
		});
	},
	//Detection d'association de 7 perssones
	luckyNumberDetector: function() {
		document.addEventListener('click', function(e) {
			let element = e.target;
			
			// Cas 1: Validation d'ajout à un groupe existant
			if (element.textContent === "Ajouter" && 
				element.closest('.ui-dialog-buttonset')) {            
				setTimeout(() => {
					// Récupérer l'ID du groupe depuis le champ de saisie
					const groupId = $('#dialogInput').val().toUpperCase();
					checkGroupSize(groupId);
				}, 200);
			}       
			// Cas 2: Création d'un nouveau groupe via le bouton ASSOCIER
			if (element.textContent === "ASSOCIER" || 
				element.closest('#associateButton')) {          
				setTimeout(() => {
					// Trouver le dernier groupe créé (la première entrée avec un group_id)
					const lastGroup = $('.list-item').first().data('group');
					checkGroupSize(lastGroup);
				}, 200);
			}
		}, true);   
		// Fonction de vérification d'un groupe spécifique
		function checkGroupSize(groupId) {
			if (groupId) {
				const groupSize = $(`.list-item[data-group="${groupId}"]`).length;
				if (groupSize === 7) {
					EasterEggs.showImageMessage('images/jackpot.png');
				}
			}
		}
	},
	//Détecte si un groupe de 2 perssone à été split
groupSplitDetector: function() {
    document.addEventListener('click', function(e) {
        let element = e.target;
        if (element.classList.contains('fa-chain-broken')) {
            const listItem = element.closest('.list-item');
            const groupId = listItem.dataset.group;
            const membersCount = document.querySelectorAll(`.list-item[data-group="${groupId}"]`).length;         
            if (membersCount === 2) {
                console.log("Séparation d'un duo ! Déclenchement de groupSplit!");
                EasterEggs.showImageMessage('images/split.png');
            }
        }
    }, true);
}
};
// Initialisation des easter eggs
$(document).ready(function() {
    EasterEggs.init();
});