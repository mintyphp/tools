<?php echo '<?php' . "\n" ?>

use MintyPHP\DB;

$data = DB::select("SELECT * FROM `<?php echo $table; ?>`");

<?php foreach ($references as $column => $referencedTable) : $referencedColumn = $primaryKeys[$referencedTable]; ?>
$<?php echo $camelize($referencedTable); ?> = DB::selectPairs("SELECT `<?php echo $referencedColumn; ?>`, `<?php echo $displayFields[$referencedTable]; ?>` FROM `<?php echo $referencedTable; ?>`");
<?php endforeach; ?>