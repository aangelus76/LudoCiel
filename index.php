<?php
// ============================================================================
// CONFIGURATION CENTRALISÉE DU MENU
// ============================================================================
// Pour ajouter un nouveau projet, ajoutez simplement une ligne dans ce tableau
// Pour supprimer un projet, supprimez la ligne correspondante
// 'fichier' : chemin vers le dossier/ ou vers un fichier.html spécifique

$menu_config = [
    [
        'id' => 'Pointage',
        'nom' => 'Fréquentation',
        'icone' => 'fa-users',
        'position' => 'gauche',
        'fichier' => 'Pointage/'
    ],
    [
        'id' => 'Surdim', 
        'nom' => 'Réservation de surdim',
        'icone' => 'fa-calendar',
        'position' => 'gauche',
        'fichier' => 'Surdim/'
    ],
    [
        'id' => 'Animation', 
        'nom' => 'Animation',
        'icone' => 'fa-paint-brush',
        'position' => 'gauche',
        'fichier' => 'Pointage/animations/animations.html'
    ],
    [
        'id' => 'Stat', 
        'nom' => 'Statistiques',
        'icone' => 'fa-bar-chart',
        'position' => 'gauche',
        'fichier' => 'Pointage/statistiques/Stat.html'
    ],
    [
        'id' => 'version',
        'nom' => 'Version', 
        'icone' => 'fa-tasks',
        'position' => 'droite',
        'fichier' => 'version/'
    ]
];

// ============================================================================
// GÉNÉRATION AUTOMATIQUE DES PROJETS AUTORISÉS
// ============================================================================
$allowed_projects = array_column($menu_config, 'id');
$projet_defaut = $menu_config[0]['id']; // Premier projet par défaut

// Récupérer le projet depuis l'URL
$projet = isset($_GET['projet']) ? $_GET['projet'] : $projet_defaut;

// Récupérer le fichier correspondant au projet
$fichier_defaut = '';
foreach($menu_config as $item) {
    if($item['id'] === $projet) {
        $fichier_defaut = $item['fichier'];
        break;
    }
}

// Séparer les liens gauche et droite
$liens_gauche = array_filter($menu_config, function($item) {
    return $item['position'] === 'gauche';
});

$liens_droite = array_filter($menu_config, function($item) {
    return $item['position'] === 'droite';
});
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projets Web</title>
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="css/jquery-ui.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/custom.css">
    <style>
        /* Styles pour les boutons et l'iframe */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #f5f5f5;
        }

        #content {
            margin-top: 10px;
            padding: 0;
            border-top: 2px solid #ccc;
            height: calc(98% - 50px);
            width: 100%;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background-color: #f5f5f5;
        }
        
        * {
            margin: 0;
            padding: 0;
        }
        i {
            margin-right: 10px;
        }
        
        /* Menu navbar */
        #navbar-animmenu {
            background: #99999b;
            float: left;
            overflow: hidden;
            position: relative;
            padding: 10px 0px;
            width: 100%;
        }
        #navbar-animmenu ul{
            padding: 0px;
            margin: 0px;
        }

        #navbar-animmenu ul li a i{
            margin-right: 10px;
        }

        #navbar-animmenu li {
            list-style-type: none;
            float: left;
        }

        /* Liens à droite générés automatiquement */
        <?php foreach($liens_droite as $lien): ?>
        #navbar-animmenu li[data-projet="<?php echo $lien['id']; ?>"] {
            float: right;
        }
        <?php endforeach; ?>

        #navbar-animmenu ul li a{
            color: black;
            text-decoration: none;
            font-size: 20px;
            line-height: 45px;
            display: block;
            padding: 0px 20px;
            transition-duration:0.6s;
            transition-timing-function: cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
        }

        #navbar-animmenu>ul>li.active>a{
            background-color: transparent;
            transition: all 0.7s;
            color: #666668;
            text-shadow: 0px 2px 8px #6E6E6F;
        }

        #navbar-animmenu a:not(:only-child):after {
            content: "\f105";
            position: absolute;
            right: 20px;
            top: 10%;
            font-size: 14px;
            font-family: "Font Awesome 5 Free";
            display: inline-block;
            padding-right: 3px;
            vertical-align: middle;
            font-weight: 900;
            transition: 0.5s;
        }

        #navbar-animmenu .active>a:not(:only-child):after {
            transform: rotate(90deg);
        }
        #navbar-animmenu ul li:not(.active) a:hover {
            color: white;
            text-shadow: 0px 2px 8px #6E6E6F;
        }
        .hori-selector{
            display:inline-block;
            position:absolute;
            height: 100%;
            top: 10px;
            left: 0px;
            transition-duration:0.6s;
            transition-timing-function: cubic-bezier(0.68, -0.55, 0.265, 1.55);
            background-color: #f5f5f5;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .hori-selector .right,
        .hori-selector .left{
            position: absolute;
            width: 25px;
            height: 25px;
            background-color: #f5f5f5;
            bottom: 10px;
        }
        .hori-selector .right{
            right: -25px;
        }
        .hori-selector .left{
            left: -25px;
        }
        .hori-selector .right:before,
        .hori-selector .left:before{
            content: '';
            position: absolute;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #99999b;
        }
        .hori-selector .right:before{
            bottom: 0;
            right: -25px;
        }
        .hori-selector .left:before{
            bottom: 0;
            left: -25px;
        }
    </style>
<script>
$(document).ready(function() {
    // Lire le paramètre GET
    const urlParams = new URLSearchParams(window.location.search);
    const projet = urlParams.get('projet') || '<?php echo $projet_defaut; ?>';

    // Sélectionner l'élément avec l'attribut data-projet correspondant
    var tabsNewAnim = $('#navbar-animmenu');
    var activeItemNewAnim = tabsNewAnim.find('li[data-projet="' + projet + '"]');
    activeItemNewAnim.addClass('active');
    
    function setHoriSelectorPosition() {
        var activeWidthNewAnimWidth = activeItemNewAnim.innerWidth();
        var itemPosNewAnimLeft = activeItemNewAnim.position();
        $(".hori-selector").css({
            "left": itemPosNewAnimLeft.left + "px",
            "width": activeWidthNewAnimWidth + "px"
        });
    }

    // Écouter la fin de la transition CSS
    $(".hori-selector").on('transitionend webkitTransitionEnd oTransitionEnd', function() {
        const fichier = activeItemNewAnim.data('fichier');
        $('iframe').attr('src', fichier);
    });

    setHoriSelectorPosition();
    
    $("#navbar-animmenu").on("click", "li", function(e) {
        e.preventDefault();
        $('#navbar-animmenu ul li').removeClass("active");
        $(this).addClass('active');
        activeItemNewAnim = $(this);
        setHoriSelectorPosition();
    });

    $(window).on('resize', function() {
        setHoriSelectorPosition();
    });

    // Charger initialement l'iframe avec le fichier correspondant
    if (projet) {
        const fichier = activeItemNewAnim.data('fichier');
        $('iframe').attr('src', fichier);
    }
});
</script>
</head>
<body>

<div id="navbar-animmenu">
    <ul class="show-dropdown main-navbar">
        <div class="hori-selector"><div class="left"></div><div class="right"></div></div>
        
        <?php 
        // Génération automatique des liens à partir de la configuration
        foreach($menu_config as $lien): 
        ?>
        <li data-projet="<?php echo $lien['id']; ?>" data-fichier="<?php echo $lien['fichier']; ?>">
            <a href="?projet=<?php echo $lien['id']; ?>">
                <i class="fa <?php echo $lien['icone']; ?>"></i><?php echo $lien['nom']; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<div id="content">
    <?php
    if (in_array($projet, $allowed_projects)) {
        // Afficher le projet dans une iframe avec le bon fichier
        echo '<iframe src="' . htmlspecialchars($fichier_defaut) . '"></iframe>';
    } else {
        echo '<p>Projet invalide sélectionné.</p>';
    }
    ?>
</div>

</body>
</html>