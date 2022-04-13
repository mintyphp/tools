<?php echo '<?php'."\n" ?>

use MintyPHP\DB;
use MintyPHP\Router;

if (!empty($_POST)) {
    DB::delete("DELETE FROM `<?php echo $table; ?>` WHERE `<?php echo $primaryKey; ?>` = ?", $id);
    Router::redirect('<?php echo $path; ?>/<?php echo $table; ?>/index');
}