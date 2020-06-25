<?php echo '<?php' ?>
<?php foreach ($belongsTo as $relation): $referencedTable = $relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']; $referencedColumn = $relation['KEY_COLUMN_USAGE']['REFERENCED_COLUMN_NAME']; ?>
$<?php echo $referencedTable; ?> = DB::selectPairs('select `<?php echo $referencedColumn; ?>`,`<?php echo $findDisplayField($referencedTable); ?>` from `<?php echo $referencedTable; ?>`');
<?php endforeach;?>
if ($_SERVER['REQUEST_METHOD']=='POST') {
    $data = $_POST;
<?php foreach ($belongsTo as $relation): $referencedTable = $relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']; $column = $relation['KEY_COLUMN_USAGE']['COLUMN_NAME']; ?>
    if (!isset($<?php echo $referencedTable; ?>[$data['<?php echo $table; ?>']['<?php echo $column; ?>']])) $errors['<?php echo $table; ?>[<?php echo $column; ?>]']='<?php echo ucfirst($singularize($humanize($referencedTable))); ?> not found';
<?php endforeach;?>
<?php foreach ($fields as $field): if (!$field['COLUMNS']['IS_NULLABLE']): $column = $field['COLUMNS']['COLUMN_NAME']; ?>
    if (!$data['<?php echo $table; ?>']['<?php echo $column; ?>']) $errors['<?php echo $table; ?>[<?php echo $column; ?>]']='<?php echo ucfirst($humanize($column)); ?> must be filled';
<?php endif; endforeach;?>
<?php foreach ($fields as $field): if ($field['COLUMNS']['IS_NULLABLE']): $column = $field['COLUMNS']['COLUMN_NAME']; ?>
    if (!$data['<?php echo $table; ?>']['<?php echo $column; ?>']) $data['<?php echo $table; ?>']['<?php echo $column; ?>']=null;
<?php endif; endforeach;?>
    if (!isset($errors)) {
        $rowsAffected = DB::update('UPDATE `<?php echo $table; ?>` SET <?php echo implode(', ', array_map(function ($field) {return '`' . $field['COLUMNS']['COLUMN_NAME'] . '`=?';}, $fields)); ?> WHERE `id`=?', <?php echo implode(', ', array_map(function ($field) use ($table) {return "\$data['$table']['" . $field['COLUMNS']['COLUMN_NAME'] . "']";}, $fields)); ?>, $id);
        if ($rowsAffected!==false) {
            Flash::set('success','<?php echo ucfirst($singularize($humanize($table))); ?> saved');
            Router::redirect('<?php echo $path; ?>/<?php echo $table; ?>/view/'.$id);
        }
    }
    Flash::set('danger','<?php echo ucfirst($singularize($humanize($table))); ?> not saved');
} else {
    $data = DB::selectOne('SELECT * from `<?php echo $table; ?>` where `id` = ?', $id);
}