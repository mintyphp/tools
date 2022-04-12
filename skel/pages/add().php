<?php echo '<?php'."\n" ?>

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
<?php foreach ($fields as $field): if (!$field['IS_NULLABLE']): $column = $field['COLUMN_NAME']; ?>
    if (!$data['<?php echo $table; ?>']['<?php echo $column; ?>']) {
        $errors['<?php echo $table; ?>[<?php echo $column; ?>]'] = '<?php echo ucfirst($fieldNames[$column]); ?> is required';
    }
<?php endif; endforeach;?>
<?php foreach ($fields as $field): if ($field['IS_NULLABLE']): $column = $field['COLUMN_NAME']; ?>
    if (!$data['<?php echo $table; ?>']['<?php echo $column; ?>']) {
        $data['<?php echo $table; ?>']['<?php echo $column; ?>'] = null;
    }
<?php endif; endforeach;?>
    if (!isset($errors)) {
        DB::insert("INSERT INTO `<?php echo $table; ?>` (<?php echo implode(', ', array_map(function ($field) {return '`' . $field['COLUMN_NAME'] . '`';}, $fields)); ?>) VALUES (<?php echo implode(', ', array_map(function () {return '?';}, $fields)); ?>)", <?php echo implode(', ', array_map(function ($field) use ($table) {return "\$data['$table']['" . $field['COLUMN_NAME'] . "']";}, $fields)); ?>);
        Router::redirect('<?php echo $path; ?>/<?php echo $table; ?>/index');
    }
} else {
    $data = ['<?php echo $table; ?>' => [
<?php foreach ($fields as $field):?>
        <?php $default = var_export($field['COLUMN_DEFAULT'],true); echo "'" . $field['COLUMN_NAME'] . "'" . ' => ' . ($default=='NULL'?'null':$default);?>,
<?php endforeach;?>
    ]];
}