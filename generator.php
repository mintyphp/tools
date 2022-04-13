<?php
// Use default autoload implementation
require 'vendor/mintyphp/core/src/Loader.php';
// Load the config parameters
require 'config/config.php';

use MintyPHP\DB;

$directory = $_GET['directory'] ?? false;
$table = $_GET['table'] ?? false;
$template = $_POST['template'] ?? false;
$singular = $_POST['singular'] ?? false;
$plural = $_POST['plural'] ?? false;
$fieldNames = $_POST['fieldNames'] ?? [];
$displayFields = $_POST['displayFields'] ?? [];

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

if (!$directory) {
    echo '<form method="get">';
    echo '<input type="hidden" name="template" value="' . $template . '">';
    echo '<label>Directory</label><br>';
    $dirs = readdirs('pages', ['pages']);
    sort($dirs);
    echo '<select name="directory">';
    foreach ($dirs as $dir) {
        echo '<option value="' . $dir . '">' . $dir . '</option>';
    }
    echo '</select><br>';
    echo '<input type="submit" value="Next">';
    echo '</form>';
} elseif (!$table) {
    echo '<form method="get">';
    echo '<label>Directory</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?">' . $directory . '</a></div>';
    echo '<input type="hidden" name="directory" value="' . $directory . '">';
    echo '<label>Table</label><br>';
    echo '<select name="table">';
    if ($directory) {
        $entities = DB::select('SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` WHERE `TABLE_SCHEMA` = ? ORDER BY `TABLE_NAME`;', DB::$database);
        foreach ($entities as $entity) {
            $table = $entity['TABLES']['TABLE_NAME'];
            if (!file_exists($directory . '/' . $table)) {
                echo '<option value="' . $table . '">' . $table . '</option>';
            }
        }
    }
    echo '</select><br>';
    echo '<input type="submit" value="Next">';
    echo '</form>';
} elseif (!$template) {
    $singular = $singular ?: $singularize($humanize($table));
    $plural = $plural ?: $pluralize($humanize($table));
    echo '<form method="post">';
    echo '<label>Directory</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?">' . $directory . '</a></div>';
    echo '<input type="hidden" name="directory" value="' . $directory . '">';
    echo '<label>Table</label><br>';
    echo '<div style="padding: 4px 1px;"><a href="?directory=' . urlencode($directory) . '">' . $table . '</a></div>';
    echo '<input type="hidden" name="table" value="' . $table . '">';
    echo '<label>Template</label><br>';
    $templates = glob("templates/*.phtml");
    echo '<select name="template">';
    foreach ($templates as $template) {
        $begin = strpos($template, '/') + 1;
        $template = substr($template, $begin, strrpos($template, '.') - $begin);
        $selected = in_array($template, explode('/', $directory)) ? 'selected' : '';
        echo '<option value="' . $template . '" ' . $selected . '>' . $template . '</option>';
    }
    echo '</select><br>';
    echo '<label>Table name singular for "' . $table . '"</label><br>';
    echo '<input type="text" name="singular" value="' . $singular . '"><br>';
    echo '<label>Table name plular for "' . $table . '"</label><br>';
    echo '<input type="text" name="plural" value="' . $plural . '"><br>';
    $fieldNames = DB::selectValues("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and EXTRA != 'auto_increment' and TABLE_NAME = ?", $table);
    $references = DB::selectPairs("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME from INFORMATION_SCHEMA.KEY_COLUMN_USAGE where REFERENCED_TABLE_NAME is not null and TABLE_SCHEMA=DATABASE() AND TABLE_NAME = ?", $table);
    foreach ($fieldNames as $fieldName) {
        $reference = $references[$fieldName] ?? '';
        echo '<label>Column name for "' . $fieldName . '"' . ($reference ? " ($reference)" : '') . '</label><br>';
        echo '<input type="text" name="fieldNames[' . $fieldName . ']" value="' . ($reference ? preg_replace('/_id$/', '', $fieldName) : $fieldName) . '"><br>';
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
        $fieldNames = DB::selectValues("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and table_name = ?", $otherTable);
        echo '<label>Display field for table "' . $otherTable . '"</label><br>';
        echo '<select name="displayFields[' . $otherTable . ']">';
        foreach ($fieldNames as $fieldName) {
            $selected = $fieldName == $findDisplayField($otherTable) ? 'selected' : '';
            echo '<option value="' . $fieldName . '" ' . $selected . '>' . $fieldName . '</option>';
        }
        echo '</select><br>';
    }
    echo '<input type="submit" value="Next">';
    echo '</form>';
} else {
    $pages = array(
        'index().php',
        'index(admin).phtml',
        'add().php',
        'add(admin).phtml',
        'edit($id).php',
        'edit(admin).phtml',
        'delete($id).php',
        'delete(admin).phtml',
        'view($id).php',
        'view(admin).phtml',
    );

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

    foreach ($pages as $page) {
        ob_start();
        include "skel/pages/$page";
        $filename = $dir . '/' . str_replace('(admin).phtml', "($template).phtml", $page);
        file_put_contents($filename, ob_get_clean());
    }
}
