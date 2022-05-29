<?php echo '<?php' ?> 
$data = DB::selectOne('SELECT * FROM `<?php echo $table; ?>` WHERE `id` = ?', $id);

if (!empty($_POST)) {
	$errors = [];
	$rows = DB::delete('DELETE FROM `<?php echo $table; ?>` WHERE `id` = ?', $id);
	if ($rows) {
		Router::redirect('<?php echo $path; ?>/<?php echo $table; ?>/index');
	}
	$errors['db'] = '<?php echo ucfirst($singularize($humanize($table))); ?> not deleted';
}