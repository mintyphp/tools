<?php
// Use default autoload implementation
require 'vendor/mintyphp/core/src/Loader.php';
// Load the config parameters
require 'config/config.php';

use MintyPHP\DB;

$template = isset($_POST['template']) ? $_POST['template'] : false;
$directory = isset($_POST['directory']) ? $_POST['directory'] : false;
$table = isset($_POST['table']) ? $_POST['table'] : false;
$field = isset($_POST['field']) ? $_POST['field'] : false;

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

if (!$template) {
    echo '<form method="post">';
    echo '<label>Template</label><br>';
    $templates = glob("templates/*.phtml");
    echo '<select name="template">';
    foreach ($templates as $template) {
        $begin = strpos($template, '/') + 1;
        $template = substr($template, $begin, strrpos($template, '.') - $begin);
        echo '<option value="' . $template . '">' . $template . '</option>';
    }
    echo '</select><br>';
    echo '<input type="submit" value="Next">';
    echo '</form>';
} elseif (!$directory) {
    echo '<form method="post">';
    echo '<label>Template</label><br>';
    echo '<div style="padding: 4px 1px;">' . $template . '</div>';
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
    echo '<label>Template</label><br>';
    echo '<div style="padding: 4px 1px;">' . $template . '</div>';
    echo '<input type="hidden" name="template" value="' . $template . '">';
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
    $humanize = function ($v) {
        return str_replace('_', ' ', $v);
    };
    $singularize = function ($v) {
        return rtrim($v, 's');
    };

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
        file_put_contents("$dir/$page", ob_get_clean());
    }
}
