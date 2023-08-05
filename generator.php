<?php
// Use default autoload implementation
require 'vendor/mintyphp/core/src/Loader.php';
// Load the config parameters
require 'config/config.php';

use MintyPHP\DB;

$data = $_POST;
if (isset($data['listFields'])) {
    $data['listFields'] = array_keys($data['listFields']);
}
$steps = 10;
$step = $data['step'] ?? 1;
unset($data['step']);
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
$listFields = $data['listFields'] ?? ($previous['listFields'] ?? []);
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
    return preg_replace_callback('/_([a-z])/i', function ($matches) {
        return strtoupper($matches[1]);
    }, $word);
};

echo '<h1>Generator</h1>';

if ($step < $steps) {
    echo '<form method="post">';
    if ($step == 1) {
        echo '<label>Table</label><br>';
        echo '<select name="table">';
        $options = DB::selectValues('SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` WHERE `TABLE_SCHEMA` = ? ORDER BY `TABLE_NAME`;', DB::$database);
        foreach ($options as $option) {
            $exists = file_exists("skel/config/$option.json") ? '*' : '';
            echo '<option value="' . $option . '">' . $exists .  $option  . '</option>';
        }
        echo '</select><br>';
    } elseif ($step > 1) {
        echo '<label>Table</label><br>';
        echo '<div style="padding: 4px 1px;">' . $table . '</div>';
        echo '<input type="hidden" name="table" value="' . $table . '">';
    }
    if ($step == 2) {
        echo '<label>Directory</label><br>';
        $options = readdirs('pages', ['pages']);
        sort($options);
        echo '<select name="directory">';
        foreach ($options as $option) {
            $selected = $option == $directory ? 'selected' : '';
            echo '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }
        echo '</select><br>';
    } elseif ($step > 2) {
        echo '<label>Directory</label><br>';
        echo '<div style="padding: 4px 1px;">' . $directory . '</div>';
        echo '<input type="hidden" name="directory" value="' . $directory . '">';
    }
    if ($step == 3) {
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
    } elseif ($step > 3) {
        echo '<label>Skeleton</label><br>';
        echo '<div style="padding: 4px 1px;">' . $skeleton . '</div>';
        echo '<input type="hidden" name="skeleton" value="' . $skeleton . '">';
    }
    if ($step == 4) {
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
    } elseif ($step > 4) {
        echo '<label>Template</label><br>';
        echo '<div style="padding: 4px 1px;">' . $template . '</div>';
        echo '<input type="hidden" name="template" value="' . $template . '">';
    }
    if ($step == 5) {
        $singular = $singular ?: $singularize($humanize($table));
        echo '<label>Table name singular for "' . $table . '"</label><br>';
        echo '<input type="text" name="singular" value="' . $singular . '"><br>';
        $plural = $plural ?: $pluralize($humanize($table));
        echo '<label>Table name plular for "' . $table . '"</label><br>';
        echo '<input type="text" name="plural" value="' . $plural . '"><br>';
    } elseif ($step > 5) {
        echo '<label>Table name (singular/plural) for "' . $table . '"</label><br>';
        echo '<div style="padding: 4px 1px;">' . $singular . ' / ' . $plural . '</div>';
        echo '<input type="hidden" name="singular" value="' . $singular . '">';
        echo '<input type="hidden" name="plural" value="' . $plural . '">';
    }
    if ($step == 6) {
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
    } elseif ($step > 6) {
        echo '<label>Column names for "' . $singular . '"</label><br>';
        echo '<div style="padding: 4px 1px;">' . implode(', ', $fieldNames) . '</div>';
        foreach ($fieldNames as $columnName => $fieldName) {
            echo '<input type="hidden" name="fieldNames[' . $columnName . ']" value="' . $fieldName . '">';
        }
    }
    if ($step == 7) {
        echo '<label>List columns for "' . $singular . '"</label><br>';
        $columnNames = DB::selectValues("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and EXTRA != 'auto_increment' and TABLE_NAME = ?", $table);
        if (!$listFields) {
            foreach ($columnNames as $i => $columnName) {
                $listFields[$columnName] = $i < 4 ? 1 : 0;
            }
        }
        foreach ($columnNames as $columnName) {
            $checked = in_array($columnName, $listFields) ? ' checked' : '';
            echo '<input type="checkbox" name="listFields[' . $columnName . ']"' . $checked . '>' . $fieldNames[$columnName] . '<br>';
        }
    } elseif ($step > 7) {
        echo '<label>List columns for "' . $singular . '"</label><br>';
        $listFieldNames = array_map(function ($v) use ($fieldNames) {
            return $fieldNames[$v];
        }, $listFields);
        echo '<div style="padding: 4px 1px;">' . implode(', ', $listFieldNames) . '</div>';
        foreach ($listFields as $columnName) {
            echo '<input type="hidden" name="listFields[' . $columnName . ']" value="1">';
        }
    }
    if ($step == 8) {
        $columnNames = DB::selectValues("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() and EXTRA != 'auto_increment' and TABLE_NAME = ?", $table);
        $references = DB::selectPairs("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME from INFORMATION_SCHEMA.KEY_COLUMN_USAGE where REFERENCED_TABLE_NAME is not null and TABLE_SCHEMA=DATABASE() AND TABLE_NAME = ?", $table);
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
            echo '<label>Display field for related table "' . $otherTable . '"</label><br>';
            echo '<select name="displayFields[' . $otherTable . ']">';
            $foundDisplayField = $findDisplayField($otherTable);
            foreach ($columnNames as $option) {
                if ($displayFields[$otherTable] ?? false) {
                    $selected = $option == $displayFields[$otherTable] ? 'selected' : '';
                } else {
                    $selected = $option == $foundDisplayField ? 'selected' : '';
                }
                echo '<option value="' . $option . '" ' . $selected . '>' . $option . '</option>';
            }
            echo '</select><br>';
        }
    } elseif ($step > 8) {
        $references = DB::selectPairs("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME from INFORMATION_SCHEMA.KEY_COLUMN_USAGE where REFERENCED_TABLE_NAME is not null and TABLE_SCHEMA=DATABASE() AND TABLE_NAME = ?", $table);
        echo '<label>Display fields for related tables "' . implode(', ', array_unique($references)) . '"</label><br>';
        echo '<div style="padding: 4px 1px;">' . implode(', ', array_filter($displayFields)) . '</div>';
        foreach ($displayFields as $columnName => $displayField) {
            echo '<input type="hidden" name="displayFields[' . $columnName . ']" value="' . $displayField . '">';
        }
    }
    echo '<p>Step ' . $step . '/' . $steps . '</p>';
    echo '<input type="button" value="Back" onclick="var form=document.forms[0]; form.elements[\'step\'].value=' . ($step - 1) . '; form.submit();">';
    echo '<input type="submit" value="Next">';
    echo '<input type="hidden" name="step" value="' . ($step + 1)  . '">';
    echo '</form>';
} else {
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

    $path = substr($directory, strlen('pages/')) ?: '.';
    $dir = $directory . '/' . $table;
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    $filenames = glob("skel/$skeleton/*");
    echo '<p>Files written:</p>';
    echo '<ul>';
    foreach ($filenames as $filename) {
        ob_start();
        include $filename;
        $filename = $dir . '/' . str_replace('(admin).phtml', "($template).phtml", basename($filename));
        file_put_contents($filename, ob_get_clean());
        echo "<li>$filename</li>";
    }
    echo '</ul>';
    echo '<form method="get">';
    echo '<input type="submit" value="Done">';
    echo '</form>';
}
