<?php echo '<?php'."\n" ?>

/**
 * @var string|null $id
 */
 
use MintyPHP\DB;
use MintyPHP\Router;

<?php foreach ($references as $column => $referencedTable): $referencedColumn = $primaryKeys[$referencedTable]; ?>
$<?php echo $camelize($referencedTable); ?> = DB::selectPairs("SELECT `<?php echo $referencedColumn; ?>`, `<?php echo $displayFields[$referencedTable]; ?>` FROM `<?php echo $referencedTable; ?>`");
<?php endforeach;?>

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$data = $_POST;

<?php foreach ($references as $column => $referencedTable): ?>
	if (!isset($<?php echo $camelize($referencedTable); ?>[$data['<?php echo $table; ?>']['<?php echo $column; ?>']])) {
		$errors['<?php echo $table; ?>[<?php echo $column; ?>]'] = '<?php echo ucfirst($fieldNames[$column]); ?> not found';
	}
<?php endforeach;?>
<?php foreach ($fields as $field): if ($field['IS_NULLABLE']=="NO"): $column = $field['COLUMN_NAME']; ?>
	if (!$data['<?php echo $table; ?>']['<?php echo $column; ?>']) {
		$errors['<?php echo $table; ?>[<?php echo $column; ?>]'] = '<?php echo ucfirst($fieldNames[$column]); ?> is required';
	}
<?php endif; endforeach;?>
<?php foreach ($fields as $field): if ($field['IS_NULLABLE']=="YES"): $column = $field['COLUMN_NAME']; ?>
	if (!$data['<?php echo $table; ?>']['<?php echo $column; ?>']) {
		$data['<?php echo $table; ?>']['<?php echo $column; ?>'] = null;
	}
<?php endif; endforeach;?>
	if (!isset($errors)) {
		DB::update("UPDATE `<?php echo $table; ?>` SET <?php echo implode(', ', array_map(function ($field) { return '`' . $field['COLUMN_NAME'] . '` = ?';}, $fields)); ?> WHERE `<?php echo $primaryKey; ?>` = ?", <?php echo implode(', ', array_map(function ($field) use ($table) { return "\$data['$table']['" . $field['COLUMN_NAME'] . "']";}, $fields)); ?>, $id);
		Router::redirect("<?php echo $path; ?>/<?php echo $table; ?>/view/$id");
	}
} else {
	$data = DB::selectOne('SELECT * FROM `<?php echo $table; ?>` WHERE `<?php echo $primaryKey; ?>` = ?', $id);
}
