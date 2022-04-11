<?php echo '<?php'."\n" ?>
<?php foreach ($belongsTo as $relation): $referencedTable = $relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']; $referencedColumn = $relation['KEY_COLUMN_USAGE']['REFERENCED_COLUMN_NAME']; ?>
$<?php echo $referencedTable; ?> = DB::selectPairs('select `<?php echo $referencedColumn; ?>`,`<?php echo $findDisplayField($referencedTable); ?>` from `<?php echo $referencedTable; ?>`');
<?php endforeach;?>
if ($_SERVER['REQUEST_METHOD']=='POST') {
    $data = $_POST;
<?php foreach ($belongsTo as $relation): $referencedTable = $relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']; $column = $relation['KEY_COLUMN_USAGE']['COLUMN_NAME']; ?>
    if (!isset($<?php echo $referencedTable; ?>[$data['<?php echo $table; ?>']['<?php echo $column; ?>']])) $errors['<?php echo $table; ?>[<?php echo $column; ?>]']='Option not found';
<?php endforeach;?>
<?php foreach ($fields as $field): if (!$field['COLUMNS']['IS_NULLABLE']): $column = $field['COLUMNS']['COLUMN_NAME']; ?>
    if (!$data['<?php echo $table; ?>']['<?php echo $column; ?>']) $errors['<?php echo $table; ?>[<?php echo $column; ?>]']='Field must be filled';
<?php endif; endforeach;?>
<?php foreach ($fields as $field): if ($field['COLUMNS']['IS_NULLABLE']): $column = $field['COLUMNS']['COLUMN_NAME']; ?>
    if (!$data['<?php echo $table; ?>']['<?php echo $column; ?>']) $data['<?php echo $table; ?>']['<?php echo $column; ?>']=null;
<?php endif; endforeach;?>
    if (!isset($errors)) {
        $id = DB::insert('INSERT INTO `<?php echo $table; ?>` (<?php echo implode(', ', array_map(function ($field) {return '`' . $field['COLUMNS']['COLUMN_NAME'] . '`';}, $fields)); ?>) VALUES (<?php echo implode(', ', array_map(function () {return '?';}, $fields)); ?>)', <?php echo implode(', ', array_map(function ($field) use ($table) {return "\$data['$table']['" . $field['COLUMNS']['COLUMN_NAME'] . "']";}, $fields)); ?>);
        if ($id) {
            Flash::set('success','<?php echo ucfirst($singularize($humanize($table))); ?> saved');
            Router::redirect('<?php echo $path; ?>/<?php echo $table; ?>/index');
        }
    }
    Flash::set('danger','<?php echo ucfirst($singularize($humanize($table))); ?> not saved');
} else {
    $data = array('<?php echo $table; ?>'=>array(<?php echo implode(', ', array_map(function ($field) {return "'" . $field['COLUMNS']['COLUMN_NAME'] . "'" . '=>' . var_export($field['COLUMNS']['COLUMN_DEFAULT'], true);}, $fields)); ?>));
}