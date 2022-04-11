<?php
// Use default autoload implementation
require 'vendor/mintyphp/core/src/Loader.php';
// Load the config parameters
require 'config/config.php';

use MintyPHP\DB;

$directory = $_POST['directory'] ?? false;
$table = $_POST['table'] ?? false;
$template = $_POST['template'] ?? false;
$singular = $_POST['singular'] ?? false;
$plural = $_POST['plural'] ?? false;
$fieldNames = $_POST['fieldNames'] ?? [];

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

if (!$directory) {
    echo '<form method="post">';
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
    echo '<form method="post">';
    echo '<label>Directory</label><br>';
    echo '<div style="padding: 4px 1px;">' . $directory . '</div>';
    echo '<input type="hidden" name="directory" value="' . $directory . '">';
    echo '<label>Table</label><br>';
    echo '<select name="table">';
    if ($directory) {
        $entities = DB::select('SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` WHERE `TABLE_SCHEMA` = ?;', DB::$database);
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
    echo '<div style="padding: 4px 1px;">' . $directory . '</div>';
    echo '<input type="hidden" name="directory" value="' . $directory . '">';
    echo '<label>Table</label><br>';
    echo '<div style="padding: 4px 1px;">' . $table . '</div>';
    echo '<input type="hidden" name="table" value="' . $table . '">';
    echo '<label>Template</label><br>';
    $templates = glob("templates/*.phtml");
    echo '<select name="template">';
    foreach ($templates as $template) {
        $begin = strpos($template, '/') + 1;
        $template = substr($template, $begin, strrpos($template, '.') - $begin);
        $selected = in_array($template, explode('/', $directory));
        echo '<option value="' . $template . '" ' . ($selected ? 'selected' : '') . '>' . $template . '</option>';
    }
    echo '</select><br>';
    echo '<label>Table name singular</label><br>';
    echo '<input type="text" name="singular" value="' . $singular . '"><br>';
    echo '<label>Table name plular</label><br>';
    echo '<input type="text" name="plural" value="' . $plural . '"><br>';
    echo '<label>Table fields</label><br>';
    $fieldNames = DB::selectValues("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE table_schema=DATABASE() and extra != 'auto_increment' and table_name = ?", $table);
    foreach ($fieldNames as $fieldName) {
        echo '<input type="text" name="fieldNames[' . $fieldName . ']" value="' . preg_replace('/_id$/', '', $fieldName) . '"><br>';
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

    $fields = DB::select("SELECT * FROM information_schema.COLUMNS WHERE table_schema=DATABASE() and extra != 'auto_increment' and table_name = ?", $table);
    $belongsTo = DB::select("select * from information_schema.KEY_COLUMN_USAGE where referenced_table_name is not null and table_schema=DATABASE() AND table_name = ?", $table);
    $hasMany = DB::select("select * from information_schema.KEY_COLUMN_USAGE where referenced_table_name is not null and table_schema=DATABASE() AND referenced_table_name = ?", $table);
    $hasAndBelongsToMany = DB::select("select * from information_schema.KEY_COLUMN_USAGE a, information_schema.KEY_COLUMN_USAGE b where a.referenced_table_name is not null and b.referenced_table_name is not null and a.table_schema=DATABASE() and b.table_schema=DATABASE() and a.table_name = b.table_name and a.CONSTRAINT_NAME != b.CONSTRAINT_NAME and a.referenced_table_name = ?", $table);

    $findDisplayField = function ($table) {
        $field = DB::selectValue("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE table_schema=DATABASE() and extra != 'auto_increment' and table_name = ? and COLUMN_NAME = 'name' limit 1", $table);
        if ($field) {
            return $field;
        }

        $field = DB::selectValue("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE table_schema=DATABASE() and extra != 'auto_increment' and table_name = ? and COLUMN_KEY = 'UNI' limit 1 ", $table);
        if ($field) {
            return $field;
        }

        $field = DB::selectValue("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE table_schema=DATABASE() and extra != 'auto_increment' and table_name = ? limit 1", $table);
        return $field;
    };
    $findBelongsTo = function ($name) use ($belongsTo) {
        foreach ($belongsTo as $relation) {
            if ($relation['KEY_COLUMN_USAGE']['COLUMN_NAME'] == $name) {
                return $relation;
            }
        }
        return false;
    };

    //echo $table . '<br/><pre>';
    //var_dump($fields[0], $belongsTo, $hasMany, $hasAndBelongsToMany);
    //echo '</pre>';

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
