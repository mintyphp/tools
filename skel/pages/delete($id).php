<?php echo '<?php'."\n" ?>

/**
 * @var string|null $id
 */

use MintyPHP\DB;
use MintyPHP\Router;

$data = DB::selectOne('SELECT * FROM `<?php echo $table; ?>` WHERE `id` = ?', $id);

if (!empty($_POST)) {
	$errors = [];
	$rows = DB::delete('DELETE FROM `<?php echo $table; ?>` WHERE `id` = ?', $id);
	if ($rows) {
		Router::redirect('<?php echo $path; ?>/<?php echo $table; ?>/index');
	}
}