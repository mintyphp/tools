<?php echo '<?php' ?> 
$data = DB::selectOne('select * from `<?php echo $table; ?>` where `id`=?',$id);
<?php foreach ($belongsTo as $relation): $referencedTable = $relation['KEY_COLUMN_USAGE']['REFERENCED_TABLE_NAME']; $referencedColumn = $relation['KEY_COLUMN_USAGE']['REFERENCED_COLUMN_NAME']; ?>
$<?php echo $referencedTable; ?> = DB::selectPairs('select `<?php echo $referencedColumn; ?>`,`<?php echo $findDisplayField($referencedTable); ?>` from `<?php echo $referencedTable; ?>`');
<?php endforeach; ?>