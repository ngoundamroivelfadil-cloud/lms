<?php
require_once '../includes/session.php';
verifierRole('etudiant');
require_once '../config/database.php';
require_once '../includes/sidebar.php';
require_once '../includes/topbar.php';

$id_etudiant=$_SESSION['user_id'];
$id_cours=intval($_GET['id']??0);
$id_lecon=intval($_GET['lecon']??0);

// Vérifier inscription
$ins=$conn->prepare("SELECT * FROM inscriptions WHERE id_etudiant=? AND id_cours=?");
$ins->bind_param("ii",$id_etudiant,$id_cours);$ins->execute();
if(!$ins->get_result()->fetch_assoc()){header("Location: catalogue.php");exit();}

$s=$conn->prepare("SELECT c.*,u.nom enseignant_nom,m.titre module_titre FROM cours c LEFT JOIN utilisateurs u ON c.id_enseignant=u.id LEFT JOIN modules m ON c.id_module=m.id WHERE c.id=?");
$s->bind_param("i",$id_cours);$s->execute();
$cours=$s->get_result()->fetch_assoc();

$lecons_r=$conn->query("SELECT l.*,(SELECT id FROM evaluations WHERE id_lecon=l.id LIMIT 1) id_eval, (SELECT COUNT(*) FROM lecons_vues WHERE id_lecon=l.id AND id_etudiant=$id_etudiant) vue FROM lecons l WHERE l.id_cours=$id_cours ORDER BY l.ordre ASC");
$lecons=[];while($l=$lecons_r->fetch_assoc())$lecons[]=$l;

// Leçon active
$lecon=null;
if($id_lecon){foreach($lecons as $l){if($l['id']==$id_lecon){$lecon=$l;break;}}}
if(!$lecon && !empty($lecons))$lecon=$lecons[0];

// Marquer comme vue (AJAX)
if(isset($_GET['mark_vue']) && $lecon){
    $conn->query("INSERT IGNORE INTO lecons_vues(id_etudiant,id_lecon) VALUES($id_etudiant,".$lecon['id'].")");
    echo "ok"; exit();
}

// Ajouter un commentaire
if(isset($_POST['ajouter_commentaire']) && $lecon){
    verifier_csrf();
    $contenu = trim($_POST['contenu_comm'] ?? '');
    if(!empty($contenu)){
        $s_comm = $conn->prepare("INSERT INTO commentaires (id_lecon, id_auteur, contenu) VALUES (?,?,?)");
        $s_comm->bind_param("iis", $lecon['id'], $id_etudiant, $contenu);
        $s_comm->execute();
        header("Location: ?id=$id_cours&lecon=".$lecon['id']); exit();
    }
}

// Convertir URL YouTube en embed
if(!function_exists('youtubeEmbed')){
    function youtubeEmbed($url){
        preg_match('/(?:v=|youtu\.be\/)([^&\s]+)/',$url,$m);
        return isset($m[1])?"https://www.youtube.com/embed/{$m[1]}":$url;
    }
}

// Index leçon active
$idx_active=0;
if($lecon){foreach($lecons as $k=>$l){if($l['id']==$lecon['id']){$idx_active=$k;break;}}}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?=sanitize($lecon['titre']??'Cours')?> - EduLearn</title>
    <link rel="icon" type="image/svg+xml" href="../img/logo.svg">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="layout">
    <?php sidebar('etudiant', 'cours'); ?>
    <main class="main-content">
        <?php topbar(sanitize($cours['titre']), sanitize($cours['enseignant_nom'])); ?>

        <div class="lecon-layout">
            <!-- Liste des leçons (Sommaire) -->
            <div class="lecon-sidebar-list">
                <div class="lecon-sidebar-header">Sommaire</div>
                <?php foreach($lecons as $k=>$l): ?>
                <a href="?id=<?=$id_cours?>&lecon=<?=$l['id']?>" class="lecon-item-link <?=$lecon&&$l['id']==$lecon['id']?'active':''?> <?=$l['vue']?'done':''?>">
                    <div class="lecon-num"><?=$l['vue']?'<i class="fa-solid fa-check"></i>':($k+1)?></div>
                    <div class="lecon-item-info">
                        <div class="lecon-item-title"><?=sanitize($l['titre'])?></div>
                        <div class="lecon-item-type"><?=strtoupper($l['type_contenu'])?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ZONE MATERIEL (Style Google Classroom) -->
            <div class="classroom-view">
                <?php if($lecon): ?>
                <div class="classroom-material">
                    <div class="material-header">
                        <div class="material-icon-circle"><i class="fa-solid fa-file-lines"></i></div>
                        <div class="material-titles">
                            <h1><?=sanitize($lecon['titre'])?></h1>
                            <div class="material-meta"><?=sanitize($cours['enseignant_nom'])?> • <?=date('d M', strtotime($lecon['date_creation']))?></div>
                        </div>
                    </div>

                    <div class="material-body">
                        <div class="material-description">
                            <?=nl2br(sanitize($lecon['description']??''))?>
                        </div>

                        <!-- LECTEUR INTÉGRÉ (Pour rester sur la plateforme) -->
                        <?php if($lecon['type_contenu']==='pdf' && $lecon['fichier_pdf']): ?>
                        <div class="material-preview">
                            <iframe src="<?=sanitize($lecon['fichier_pdf'])?>#toolbar=0" allow="autoplay"></iframe>
                        </div>
                        <?php elseif($lecon['type_contenu']==='video_url' && $lecon['video_url']): ?>
                        <div class="material-preview">
                            <iframe src="<?=youtubeEmbed(sanitize($lecon['video_url']))?>" allowfullscreen></iframe>
                        </div>
                        <?php elseif($lecon['type_contenu']==='video_fichier' && $lecon['video_fichier']): ?>
                        <div class="material-preview" style="background:#000;">
                            <video controls style="width:100%; height:100%;">
                                <source src="<?=sanitize($lecon['video_fichier'])?>" type="video/mp4">
                                Votre navigateur ne supporte pas la lecture de vidéos.
                            </video>
                        </div>
                        <?php endif; ?>

                        <div class="material-attachments" style="margin-top:30px;">
                            <h3>Téléchargements et liens</h3>
                            <div class="attachment-grid">
                                <?php if($lecon['type_contenu']==='pdf' && $lecon['fichier_pdf']): ?>
                                <a href="<?=sanitize($lecon['fichier_pdf'])?>" target="_blank" class="attachment-card">
                                    <div class="attachment-thumb pdf-thumb"><i class="fa-solid fa-file-pdf"></i></div>
                                    <div class="attachment-info">
                                        <strong>Support de cours (PDF)</strong>
                                        <span>Document PDF</span>
                                    </div>
                                </a>
                                <?php elseif($lecon['type_contenu']==='video_url' && $lecon['video_url']): ?>
                                <a href="<?=sanitize($lecon['video_url'])?>" target="_blank" class="attachment-card">
                                    <div class="attachment-thumb video-thumb"><i class="fa-solid fa-play"></i></div>
                                    <div class="attachment-info">
                                        <strong>Lien vidéo externe</strong>
                                        <span>Vidéo</span>
                                    </div>
                                </a>
                                <?php elseif($lecon['type_contenu']==='video_fichier' && $lecon['video_fichier']): ?>
                                <a href="<?=sanitize($lecon['video_fichier'])?>" target="_blank" class="attachment-card">
                                    <div class="attachment-thumb video-thumb"><i class="fa-solid fa-circle-play"></i></div>
                                    <div class="attachment-info">
                                        <strong>Support vidéo MP4</strong>
                                        <span>Fichier Vidéo</span>
                                    </div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="material-footer">
                        <div style="display:flex; gap:10px;">
                            <?php if($idx_active>0): $prev=$lecons[$idx_active-1]; ?>
                            <a href="?id=<?=$id_cours?>&lecon=<?=$prev['id']?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Précédent</a>
                            <?php endif; ?>
                            <?php if($idx_active<count($lecons)-1): $next=$lecons[$idx_active+1]; ?>
                            <a href="?id=<?=$id_cours?>&lecon=<?=$next['id']?>" class="btn btn-secondary btn-sm">Suivant <i class="fa-solid fa-arrow-right"></i></a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(!$lecon['vue']): ?>
                        <button onclick="markVue(this)" class="btn btn-primary"><i class="fa-solid fa-check"></i> Marquer comme terminé</button>
                        <?php else: ?>
                        <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Terminé</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- COMMENTAIRES (Style Stream) -->
                <div class="classroom-material">
                    <div style="padding:15px 24px; border-bottom:1px solid var(--border); font-weight:600;">
                        <i class="fa-solid fa-comments"></i> Commentaires sur le cours
                    </div>
                    <div class="material-body">
                        <div class="comment-list">
                            <?php
                            $comms = $conn->query("SELECT c.*, u.nom as auteur_nom, u.avatar as auteur_avatar FROM commentaires c JOIN utilisateurs u ON c.id_auteur = u.id WHERE c.id_lecon = ".$lecon['id']." ORDER BY c.date_commentaire DESC");
                            while($comm = $comms->fetch_assoc()):
                            ?>
                            <div class="comment-item">
                                <div class="comment-av"><?=initiale($comm['auteur_nom'])?></div>
                                <div class="comment-content">
                                    <div class="comment-author"><?=sanitize($comm['auteur_nom'])?> <span class="comment-date"><?=date('d M à H:i', strtotime($comm['date_commentaire']))?></span></div>
                                    <div class="comment-text"><?=sanitize($comm['contenu'])?></div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>

                        <form method="POST" style="margin-top:20px; display:flex; gap:10px;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="ajouter_commentaire" value="1">
                            <input type="text" name="contenu_comm" class="form-control" placeholder="Ajouter un commentaire..." required style="border-radius:20px; background:#f1f3f4; border:none; padding:10px 20px;">
                            <button type="submit" class="btn btn-primary" style="border-radius:50%; width:40px; height:40px; padding:0; justify-content:center;"><i class="fa-solid fa-paper-plane"></i></button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state"><h3>Sélectionnez une leçon pour commencer</h3></div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function markVue(btn){
    fetch('?id=<?=$id_cours?>&lecon=<?=$lecon['id']??0?>&mark_vue=1')
    .then(r => r.text())
    .then(t => {
        if(t==='ok'){
            if(btn) btn.outerHTML = '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Terminé</span>';
            // Update sidebar link
            location.reload(); 
        }
    });
}
// Signature Footer
</script>
<?php require_once '../includes/footer.php'; pageFooter(); ?>
</body>
</html>
