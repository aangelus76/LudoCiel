<?php
// Chargement du contenu Markdown depuis un fichier externe
$markdownFile = 'versions.md'; // Nom de votre fichier Markdown

// Vérification de l'existence du fichier
if (file_exists($markdownFile)) {
    $markdownContent = file_get_contents($markdownFile);
} else {
    // Message d'erreur si le fichier n'existe pas
    $markdownContent = "# Erreur\n\nLe fichier '$markdownFile' est introuvable.\n\nVeuillez vérifier que le fichier existe dans le même répertoire que ce script PHP.";
}

// Fonction pour convertir le format de version en date
function versionToDate($version) {
    // Format: 44.E1.25.81
    // 44 = Semaine, E = Jour (A-G), 1 = Nième modif, 25 = Année, 81 = Numéro séquentiel
    
    $parts = explode('.', $version);
    if (count($parts) < 3) return '';
    
    $week = intval($parts[0]);
    $dayLetter = substr($parts[1], 0, 1);
    $year = 2000 + intval($parts[2]);
    
    // Conversion lettre -> jour de la semaine (A=Lundi=1, G=Dimanche=7)
    $dayMap = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7];
    $dayOfWeek = isset($dayMap[$dayLetter]) ? $dayMap[$dayLetter] : 1;
    
    try {
        // Calculer la date à partir du numéro de semaine
        $date = new DateTime();
        $date->setISODate($year, $week, $dayOfWeek);
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return '';
    }
}

// Fonction simple pour convertir le Markdown en HTML
function parseMarkdown($text) {
    // Titres avec conversion de version en date pour les h3
    $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
    
    // Pour les h3 (numéros de version), ajouter la date
    $text = preg_replace_callback('/^### (.+)$/m', function($matches) {
        $version = trim($matches[1]);
        $date = versionToDate($version);
        if ($date) {
            return '<h3>' . $version . ' <span class="version-date">(' . $date . ')</span></h3>';
        }
        return '<h3>' . $version . '</h3>';
    }, $text);
    
    // Gras
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    
    // Italique
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    
    // Code inline
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
    
    // Listes
    $text = preg_replace('/^- (.+)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);
    
    // Lignes horizontales
    $text = preg_replace('/^---$/m', '<hr>', $text);
    
    // Retours à la ligne
    $text = nl2br($text);
    
    return $text;
}

$htmlContent = parseMarkdown($markdownContent);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changelog - Journal des modifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .content {
            padding: 30px;
            line-height: 1.6;
            color: #333;
        }
        
        h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        h2 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            font-size: 1.8em;
        }
        
        h3 {
            color: #34495e;
            margin: 25px 0 15px 0;
            font-size: 1.2em;
            padding: 10px;
            background: #ecf0f1;
            border-left: 4px solid #3498db;
            border-radius: 4px;
            margin-bottom: -25px;
        }
        
        /* Style pour la date dans les versions */
        .version-date {
            color: #7f8c8d;
            font-size: 0.85em;
            font-weight: normal;
            margin-left: 10px;
        }
        
        ul {
            margin: 15px 0;
            padding-left: 20px;
        }
        
        li {
            margin: 8px 0;
            list-style-type: none;
            position: relative;
            padding-left: 20px;
        }
        
        li:before {
            content: "→";
            color: #3498db;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        
        strong {
            color: #2c3e50;
        }
        
        code {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 2px 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #e74c3c;
        }
        
        em {
            color: #7f8c8d;
            font-style: italic;
        }
        
        hr {
            border: none;
            height: 3px;
            background: linear-gradient(135deg, #3498db, #9b59b6);
            margin: 30px 0;
            border-radius: 2px;
        }
        
        /* Style spécifique pour les types de modifications */
        strong:contains("[Fixé]") {
            color: #e74c3c;
        }
        
        strong:contains("[Ajout]") {
            color: #27ae60;
        }
        
        strong:contains("[Modif]") {
            color: #f39c12;
        }
        
        strong:contains("[Correction]") {
            color: #3498db;
        }
        
        /* Animation d'entrée */
        .content {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .header, .content {
                padding: 20px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            h2 {
                font-size: 1.5em;
            }
        }
        
        /* Style pour les versions */
        .version-badge {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-bottom: 20px;
        }
        
        /* Scroll smooth */
        html {
            scroll-behavior: smooth;
        }
        
        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Journal des Modifications</h1>
            <p>Suivi des évolutions et corrections</p>
        </div>
        
        <div class="content">
            <?php echo $htmlContent; ?>
        </div>
        
        <div class="footer">
            <p>© Colombel Anthony 2024, pour S.E.R</p>
        </div>
    </div>
    
    <script>
        // Ajout d'un effet de survol sur les éléments h3
        document.querySelectorAll('h3').forEach(element => {
            element.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(10px)';
                this.style.transition = 'transform 0.3s ease';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
        
        // Coloration dynamique des types de modifications
        document.querySelectorAll('strong').forEach(element => {
            const text = element.textContent;
            if (text.includes('[Fixé]')) {
                element.style.color = '#e74c3c';
            } else if (text.includes('[Ajout]')) {
                element.style.color = '#27ae60';
            } else if (text.includes('[Modif]')) {
                element.style.color = '#f39c12';
            } else if (text.includes('[Correction]')) {
                element.style.color = '#3498db';
            }
        });
    </script>
</body>
</html>