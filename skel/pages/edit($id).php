<?php echo '<?php' ?> 
<?php foreach ($belongsTo as $relation): ?>
$<?php echo $camelize($relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']); ?> = DB::selectPairs('SELECT `<?php echo $relation['KEY_COLUMN_USAGE']['REFERENCED_COLUMN_NAME']; ?>`,`<?php echo $findDisplayField($relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']); ?>` from `<?php echo $relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']; ?>`');
<?php endforeach; ?>
if ($_SERVER['REQUEST_METHOD']=='POST') {
	$data = $_POST;
	$errors = [];
<?php foreach ($belongsTo as $relation): ?>
	if (!isset($<?php echo $camelize($relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']); ?>[$data['<?php echo $table; ?>']['<?php echo $relation['KEY_COLUMN_USAGE']['COLUMN_NAME']; ?>']])) {
		$errors['<?php echo $table; ?>[<?php echo $relation['KEY_COLUMN_USAGE']['COLUMN_NAME']; ?>]'] = '<?php echo ucfirst($singularize($humanize($relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']))); ?> not found';
	}
<?php endforeach; ?>
	if (!$errors) {
		$rowsAffected = DB::update('UPDATE `<?php echo $table; ?>` SET <?php echo implode(', ',array_map(function($field){ return '`'.$field['COLUMNS']['COLUMN_NAME'].'`=?'; }, $fields)); ?> WHERE `id`=?', <?php echo implode(', ',array_map(function($field) use ($table) { return "\$data['$table']['".$field['COLUMNS']['COLUMN_NAME']."']"; }, $fields)); ?>, $id);
		if ($rowsAffected!==false) {
			Router::redirect('<?php echo $path; ?>/<?php echo $table; ?>/view/'.$id);
		}
	}
	$errors['db'] = '<?php echo ucfirst($singularize($humanize($table))); ?> not saved';
} else {
	$data = DB::selectOne('SELECT * from `<?php echo $table; ?>` where `id` = ?', $id);
}