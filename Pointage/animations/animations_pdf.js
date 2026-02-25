// animations_pdf.js - Export PDF des inscriptions
// Compatible Chrome 57 avec jsPDF 1.5.3

function exportInscriptionsPDF() {
    if (!currentAnimationData) {
        customAlert('Erreur: données animation non disponibles', 'Erreur');
        return;
    }
    
    // Récupérer les inscriptions depuis le DOM (seulement les inscrits)
    var inscriptions = [];
    $('#inscriptionsList tr').each(function() {
        var cols = $(this).find('td');
        if (cols.length >= 3) {
            var identite = cols.eq(0).text().trim();
            var telephone = cols.eq(1).text().trim();
            var places = cols.eq(2).text().trim();
            var statut = cols.eq(3).text().trim();
            
            // Détecter si c'est un invité
            var isInvite = identite.indexOf('↪') === 0 || identite.indexOf('-->') === 0;
            
            // Ignorer les lignes vides, d'en-tête et les personnes en attente
            if (identite && identite !== 'Identité' && statut === 'Inscrit') {
                inscriptions.push({
                    identite: identite,
                    telephone: telephone,
                    places: places,
                    isInvite: isInvite
                });
            }
        }
    });
    
    if (inscriptions.length === 0) {
        customAlert('Aucune inscription à exporter', 'Information');
        return;
    }
    
    // Recalculer nb_personnes pour chaque parent (compter invités)
    for (var i = 0; i < inscriptions.length; i++) {
        if (!inscriptions[i].isInvite) {
            var count = 1; // Le parent lui-même
            // Compter les invités qui suivent
            for (var j = i + 1; j < inscriptions.length && inscriptions[j].isInvite; j++) {
                count++;
            }
            inscriptions[i].places = count.toString();
        }
    }
    
    // Créer le PDF
    var doc = new jsPDF('p', 'mm', 'a4');
    
    // Titre à 25px du haut
    var titre = 'Inscriptions - ' + currentAnimationData.nom.toUpperCase();
    doc.setFontSize(16);
    doc.setFontType('bold');
    doc.text(titre, 105, 10, 'center');
    
    // En-têtes du tableau (20px après le titre = 45px du haut)
    var startY = 20;
    var maxY = 280;
    var availableHeight = maxY - startY - 9;
    var calculatedRowHeight = availableHeight / inscriptions.length;
    var rowHeight = Math.min(calculatedRowHeight, 9);
    var colWidths = [10, 90, 50, 30, 10]; // #, Identité, Téléphone, Place(s), X
    var startX = 10;
    
    doc.setFontSize(10);
    doc.setFontType('bold');
    
    // Dessiner les en-têtes
    var x = startX;
    doc.rect(x, startY, colWidths[0], rowHeight);
    doc.text('#', x + 5, startY + 6.5, 'center');
    x += colWidths[0];
    
    doc.rect(x, startY, colWidths[1], rowHeight);
    doc.text('Identité', x + 3, startY + 6.5);
    x += colWidths[1];
    
    doc.rect(x, startY, colWidths[2], rowHeight);
    doc.text('Téléphone', x + 3, startY + 6.5);
    x += colWidths[2];
    
    doc.rect(x, startY, colWidths[3], rowHeight);
    doc.text('Place(s)', x + 10, startY + 6.5, 'center');
    x += colWidths[3];
    
    doc.rect(x, startY, colWidths[4], rowHeight);
    doc.text('X', x + 5, startY + 6.5, 'center');
    
    // Données
    doc.setFontType('normal');
    var y = startY + rowHeight;
    
    for (var i = 0; i < inscriptions.length; i++) {
        var insc = inscriptions[i];
        x = startX;
        
        // Fond gris pour les invités
        if (insc.isInvite) {
            doc.setFillColor(240, 240, 240);
            doc.rect(startX, y, colWidths[0] + colWidths[1] + colWidths[2] + colWidths[3] + colWidths[4], rowHeight, 'F');
        }
        
        // Numéro
        doc.rect(x, y, colWidths[0], rowHeight);
        doc.text((i + 1).toString(), x + 5, y + 6.5, 'center');
        x += colWidths[0];
        
        // Identité - style différent parent/invité
        var identite = insc.identite.replace('↪', '  --> ');
        if (identite.length > 45) {
            identite = identite.substring(0, 42) + '...';
        }
        
        doc.rect(x, y, colWidths[1], rowHeight);
        
        // Parents : gras + taille 11
        if (!insc.isInvite) {
            doc.setFontSize(11);
            doc.setFontType('bold');
            doc.text(identite, x + 2, y + 6.5);
            doc.setFontSize(10);
            doc.setFontType('normal');
        } else {
            doc.text(identite, x + 2, y + 6.5);
        }
        x += colWidths[1];
        
        // Téléphone
        doc.rect(x, y, colWidths[2], rowHeight);
        doc.text(insc.telephone, x + 2, y + 6.5);
        x += colWidths[2];
        
        // Places
        doc.rect(x, y, colWidths[3], rowHeight);
        doc.text(insc.places, x + 13, y + 6.5, 'center');
        x += colWidths[3];
        
        // Case X (vide pour cocher)
        doc.rect(x, y, colWidths[4], rowHeight);
        
        y += rowHeight;
    }
    
    
    // Ouvrir dans nouvelle fenêtre
    var pdfData = doc.output('dataurlstring');
    var win = window.open('', '_blank', 'width=1000,height=1000,toolbar=no,menubar=no,location=no');
    win.document.write('<iframe src="' + pdfData + '" frameborder="0" style="border:0; top:0px; left:0px; bottom:0px; right:0px; width:100%; height:100%;" allowfullscreen></iframe>');
}