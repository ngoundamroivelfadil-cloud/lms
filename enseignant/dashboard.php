<?php
require_once '../includes/session.php';
verifierRole('enseignant');
require_once '../config/database.php';
require_once '../includes/sidebar.php';

// Definition directe de topbar au cas où l'inclusion échoue sur le serveur
if (!function_exists('topbar')) {
    function topbar($title = '', $subtitle = '', $base = '../') {
        global $conn;
        $id_user = $_SESSION['user_id'];
        $nb_msg = 0;
        if (function_exists('nbMessagesNonLus')) {
            $nb_msg = nbMessagesNonLus($conn, $id_user);
        }
        ?>
        <div class="topbar">
            <div class="topbar-left">
                <div class="topbar-title"><?= sanitize($title) ?></div>
                <?php if($subtitle): ?><div class="topbar-sub"><?= sanitize($subtitle) ?></div><?php endif; ?>
            </div>
            <div class="topbar-right">
                <div class="topbar-actions">
                    <div class="theme-toggle" onclick="toggleTheme()"><i class="fa-solid fa-moon dark-only"></i><i class="fa-solid fa-sun light-only"></i></div>
                    <div class="notif-bell" onclick="window.location.href='<?= $base ?>messagerie.php'">
                        <i class="fa-solid fa-envelope"></i>
                        <?php if($nb_msg > 0): ?><span class="notif-dot"></span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
} else {
    require_once '../includes/topbar.php';
}

$id = $_SESSION['user_id'];
$nb_cours    = $conn->query("SELECT COUNT(*) n FROM cours WHERE id_enseignant=$id")->fetch_assoc()['n'];
$nb_lecons   = $conn->query("SELECT COUNT(*) n FROM lecons l JOIN cours c ON l.id_cours=c.id WHERE c.id_enseignant=$id")->fetch_assoc()['n'];
$nb_etudiants= $conn->query("SELECT COUNT(DISTINCT i.id_etudiant) n FROM inscriptions i JOIN cours c ON i.id_cours=c.id WHERE c.id_enseignant=$id")->fetch_assoc()['n'];
$nb_evals    = $conn->query("SELECT COUNT(*) n FROM evaluations ev JOIN lecons l ON ev.id_lecon=l.id JOIN cours c ON l.id_cours=c.id WHERE c.id_enseignant=$id")->fetch_assoc()['n'];

$mes_cours=$conn->query("
    SELECT c.*, m.titre module_titre, COUNT(DISTINCT l.id) nb_lecons, COUNT(DISTINCT i.id) nb_inscrits
    FROM cours c LEFT JOIN modules m ON c.id_module=m.id
    LEFT JOIN lecons l ON l.id_cours=c.id
    LEFT JOIN inscriptions i ON i.id_cours=c.id
    WHERE c.id_enseignant=$id GROUP BY c.id ORDER BY c.date_creation DESC LIMIT 4
");
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Enseignant — Tableau de bord</title>
<link rel="icon" type="image/svg+xml" href="../img/logo.svg">
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></head>
<body>
<div class="layout">
<?php sidebar('enseignant','dashboard'); ?>
<main class="main-content">
    <?php topbar("Tableau de bord", "Bonjour, " . sanitize($_SESSION['nom'])); ?>

    <div class="stats-grid">
        <div class="stat-card blue"><div class="stat-icon blue"></div><div class="stat-info"><h3><?=$nb_cours?></h3><p>Mes cours</p></div></div>
        <div class="stat-card green"><div class="stat-icon green"></div><div class="stat-info"><h3><?=$nb_lecons?></h3><p>Leçons créées</p></div></div>
        <div class="stat-card purple"><div class="stat-icon purple"></div><div class="stat-info"><h3><?=$nb_etudiants?></h3><p>Étudiants</p></div></div>
        <div class="stat-card orange"><div class="stat-icon orange"></div><div class="stat-info"><h3><?=$nb_evals?></h3><p>Évaluations</p></div></div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Mes cours récents</h2><a href="mes_cours.php" class="btn btn-primary btn-sm">Voir tous</a></div>
        <div class="cours-grid" style="padding:20px;">
        <?php
        $colors=['c1','c2','c3','c4','c5'];$i=0;
        while($c=$mes_cours->fetch_assoc()):$ci=$colors[$i%5];$i++;?>
        <div class="cours-card">
            <div class="cours-card-banner <?=$ci?>"></div>
            <div class="cours-card-body">
                <h3><?=sanitize($c['titre'])?></h3>
                <p><?=sanitize($c['description']??'')?></p>
                <div class="cours-card-meta">
                    <span class="cours-meta-item">Leçons : <?=$c['nb_lecons']?></span>
                    <span class="cours-meta-item">Étudiants : <?=$c['nb_inscrits']?></span>
                </div>
                <?php if($c['module_titre']): ?><div style="margin-top:8px;"><span class="badge badge-enseignant">Module : <?=sanitize($c['module_titre'])?></span></div><?php endif; ?>
            </div>
            <div class="cours-card-footer">
                <span></span>
                <a href="gerer_cours.php?id=<?=$c['id']?>" class="btn btn-primary btn-sm">Gérer</a>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
</main>
</div>
<?php require_once '../includes/footer.php'; pageFooter(); ?>
<script src="../js/app.js"></script>
</body></html>
