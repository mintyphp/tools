<?php
// Use default autoload implementation
require 'vendor/mintyphp/core/src/Loader.php';
// Load the config parameters
require 'config/config.php';

use MintyPHP\DB;

$data = $_GET + $_POST;
$steps = 4;
$step = $data['step'] ?? 1;
$table = $data['table'] ?? '';

if ($step > 1 && file_exists("skel/config/$table.json")) {
    $previous = json_decode(file_get_contents("skel/config/$table.json"), true);
} else {
    $previous = [];
}

$directory = $data['directory'] ?? ($previous['directory'] ?? '');
$skeleton = $data['skeleton'] ?? ($previous['skeleton'] ?? '');
$template = $data['template'] ?? ($previous['template'] ?? '');
$singular = $data['singular'] ?? ($previous['singular'] ?? '');
$plural = $data['plural'] ?? ($previous['plural'] ?? '');
$fieldNames = $data['fieldNames'] ?? ($previous['fieldNames'] ?? []);
$displayFields = $data['displayFields'] ?? ($previous['displayFields'] ?? []);


function readdirs($directory, $entries_array = array())
{
    if (is_dir($directory)) {
        $handle = opendir($directory);
        while (FALSE !== ($entry = readdir($handle))) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            $newEntry = $directory . '/' . $entry;
            if (is_dir($newEntry)) {
                $entries_array[] = $newEntry;
                $entries_array = readdirs($newEntry, $entries_array);
            }
        }
        closedir($handle);
    }
    return $entries_array;
}

$humanize = function ($v) {
    return str_replace('_', ' ', $v);
};
$singularize = function ($v) {
    return rtrim($v, 's');
};
$pluralize = function ($v) {
    return rtrim($v, 's') . 's';
};
$camelize = function ($word) {
    return preg_replace_callback('/_[a-z]/', function ($matches) {
        return strtolower($matches[0]);
    }, $word);
};

echo '<h1>Generator</h1>';
echo '<p>Step ' . $step . '/' . $steps . '</p>';

if ($step == 1) {
    echo '<form method="get">';
    echo '<label>Table</label><br>';
    echo '<select name="table">';
    $options = DB::selectValues('SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` WHERE `TABLE_SCHEMA` = ? ORDER BY `TABLE_NAME`;', DB::$database);
    foreach ($options as $option) {
        $exists = file_exists("skel/config/$option.json") ? '*' : '';
        echo '<option value="' . $option . '">' . $exists .  $option  . '</option>';
    }
    echo '</select><br>';
    echo '<input type="hidden" name="step" value="' . ($step + 1) . '"><br>';
    echo '<input type="submit" value="Next">';
    echo '</form>';
} elseif ($step == 2) {
    echo '<form method="get">';
    echo '<label>Table</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?step=1">' . $table . '</a></div>';
    echo '<input type="hidden" name="table" value="' . $table . '">';
    echo '<label>Directory</label><br>';
    $options = readdirs('pages', ['pages']);
    sort($options);
    echo '<select name="directory">';
    foreach ($options as $option) {
        $selected = $option == $directory ? 'selected' : '';
        echo '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
    }
    echo '</select><br>';
    echo '<input type="hidden" name="step" value="' . ($step + 1) . '"><br>';
    echo '<input type="submit" value="Next">';
    echo '</form>';
} elseif ($step == 3) {
    echo '<form method="get">';
    echo '<label>Table</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?step=1">' . $table . '</a></div>';
    echo '<input type="hidden" name="table" value="' . $table . '">';
    echo '<label>Directory</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?table=' . urlencode($table) . '&step=2">' . $directory . '</a></div>';
    echo '<input type="hidden" name="directory" value="' . $directory . '">';
    echo '<label>Skeleton</label><br>';
    if (!file_exists("skel/default")) {
        mkdir("skel/default", 0755, true);
        $filenames = glob(__DIR__ . "/skel/pages/*");
        foreach ($filenames as $filename) {
            $basename = basename($filename);
            copy($filename, "skel/default/$basename");
        }
    }
    $filenames = glob("skel/*");
    echo '<select name="skeleton">';
    foreach ($filenames as $filename) {
        if ($filename == 'skel/config') {
            continue;
        }
        $option = substr($filename, strpos($filename, '/') + 1);
        $selected = $option == $skeleton ? 'selected' : '';
        echo '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
    }
    echo '</select><br>';
    echo '<input type="hidden" name="step" value="' . ($step + 1) . '"><br>';
    echo '<input type="submit" value="Next">';
    echo '</form>';
} elseif ($step == 4) {
    $singular = $singular ?: $singularize($humanize($table));
    $plural = $plural ?: $pluralize($humanize($table));
    echo '<form method="post">';
    echo '<label>Table</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?step=1">' . $table . '</a></div>';
    echo '<input type="hidden" name="table" value="' . $table . '">';
    echo '<label>Directory</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?table=' . urlencode($table) . '&step=2">' . $directory . '</a></div>';
    echo '<input type="hidden" name="directory" value="' . $directory . '">';
    echo '<label>Skeleton</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?table=' . urlencode($table) . '&directory=' . urlencode($directory) . '&step=3">' . $skeleton . '</a></div>';
    echo '<input type="hidden" name="skeleton" value="' . $skeleton . '">';
    echo '<label>Template</label><br>';
    $filenames = glob("templates/*.phtml");
    echo '<select name="template">';
    foreach ($filenames as $filename) {
        $begin = strpos($filename, '/') + 1;
        $option = substr($filename, $begin, strrpos($filename, '.') - $begin);
        if ($template) {
            $selected = $option == $template;
        } else {
            $selected = in_array($option, explode('/', $directory)) ? 'selected' : '';
        }
        echo '<option value="' . $option . '" ' . $selected . '>' . $option . '</option>';
    }
    echo '</select><br>';
    echo '<label>Table name singular for "' . $table . '"</label><br>';
    echo '<input type="text" name="singular" value="' . $singular . '"><br>';
    echo '<label>Table name plular for "' . $table . '"</label><br>';
    echo '<input type="text" name="plural" value="' . $plural . '"><br>';
    $columnNames = DB::selectValues("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and EXTRA != 'auto_increment' and TABLE_NAME = ?", $table);
    $references = DB::selectPairs("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME from INFORMATION_SCHEMA.KEY_COLUMN_USAGE where REFERENCED_TABLE_NAME is not null and TABLE_SCHEMA=DATABASE() AND TABLE_NAME = ?", $table);
    foreach ($columnNames as $columnName) {
        $reference = $references[$columnName] ?? '';
        echo '<label>Column name for "' . $columnName . '"' . ($reference ? " ($reference)" : '') . '</label><br>';
        if ($fieldNames[$columnName] ?? false) {
            $value = $fieldNames[$columnName];
        } else {
            $value = $reference ? preg_replace('/_id$/', '', $columnName) : $columnName;
        }
        echo '<input type="text" name="fieldNames[' . $columnName . ']" value="' . $value . '"><br>';
    }
    $findDisplayField = function ($table) {
        $field = DB::selectValue("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and EXTRA != 'auto_increment' and TABLE_NAME = ? and COLUMN_NAME IN ('name','title') limit 1", $table);
        if ($field) {
            return $field;
        }
        $field = DB::selectValue("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and EXTRA != 'auto_increment' and TABLE_NAME = ? and COLUMN_KEY = 'UNI' limit 1 ", $table);
        if ($field) {
            return $field;
        }
        $field = DB::selectValue("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and EXTRA != 'auto_increment' and TABLE_NAME = ? limit 1", $table);
        return $field;
    };
    foreach (array_unique($references) as $otherTable) {
        $options = DB::selectValues("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and table_name = ?", $otherTable);
        echo '<label>Display field for table "' . $otherTable . '"</label><br>';
        echo '<select name="displayFields[' . $otherTable . ']">';
        $foundDisplayField = $findDisplayField($otherTable);
        foreach ($options as $option) {
            if ($displayFields[$otherTable] ?? false) {
                $selected = $option == $displayFields[$otherTable] ? 'selected' : '';
            } else {
                $selected = $option == $foundDisplayField ? 'selected' : '';
            }
            echo '<option value="' . $option . '" ' . $selected . '>' . $option . '</option>';
        }
        echo '</select><br>';
    }
    echo '<input type="hidden" name="step" value="' . ($step + 1) . '"><br>';
    echo '<input type="submit" value="Next">';
    echo '</form>';
} elseif ($step == 5) {
    if (!file_exists("skel/config")) {
        mkdir("skel/config", 0755, true);
    }
    file_put_contents("skel/config/$table.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $fields = DB::select("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and EXTRA != 'auto_increment' and TABLE_NAME = ?", $table);
    $fields = array_map(function ($v) {
        return $v['COLUMNS'];
    }, $fields);
    $fields = array_combine(array_column($fields, 'COLUMN_NAME'), array_values($fields));
    $references = DB::selectPairs("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME from INFORMATION_SCHEMA.KEY_COLUMN_USAGE where REFERENCED_TABLE_NAME is not null and TABLE_SCHEMA=DATABASE() AND TABLE_NAME = ?", $table);
    $primaryKey = DB::selectValue("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE CONSTRAINT_NAME = 'PRIMARY' AND TABLE_NAME = ? AND TABLE_SCHEMA=DATABASE()", $table);
    $primaryKeys = DB::selectPairs("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE CONSTRAINT_NAME = 'PRIMARY' AND TABLE_NAME IN (???) AND TABLE_SCHEMA=DATABASE()", $references);

    //echo $table . '<br/><pre>';
    //var_dump($fields[0], $belongsTo, $hasMany, $hasAndBelongsToMany);
    //echo '</pre>';

    //$dirs = readdirs('pages', ['pages']);
    //foreach ($dirs as $dir) {
    //    $dirParts = explode('/', $dir);
    //    $lastDirPart = array_pop($dirParts);
    //    if (isset($tablePaths[$lastDirPart])) {
    //        $tablePaths[$lastDirPart][] = $dir;
    //    }
    //}
    //var_dump($tablePaths);

    $path = substr($directory, strlen('pages/'));
    $dir = $directory . '/' . $table;
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    $filenames = glob("skel/$skeleton/*");
    foreach ($filenames as $filename) {
        ob_start();
        include $filename;
        $filename = $dir . '/' . str_replace('(admin).phtml', "($template).phtml", basename($filename));
        file_put_contents($filename, ob_get_clean());
    }
}
