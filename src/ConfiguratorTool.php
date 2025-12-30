<?php

namespace MintyPHP\Tools;

use MintyPHP\Form\Elements as E;
use MintyPHP\Form\Form;

class ConfiguratorTool
{
    private string $filename;

    /**
     * Constructor with optional filename parameter
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * Static method to run the configurator tool.
     */
    public static function run(): void
    {
        (new self('config/config.php'))->execute();
    }

    public function getHtml(): string
    {
        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html>';
        $html[] = '<head>';
        $html[] = '    <title>MintyPHP Configurator</title>';
        $html[] = '    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $html[] = '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html[] = '    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/1.0.3/css/bulma.min.css">';
        $html[] = '</head>';
        $html[] = '<body class="container p-5">';
        $html[] = '    <div>';
        $html[] = '        <h1 class="title">';
        $html[] = '            MintyPHP Configurator';
        $html[] = '        </h1>';
        $html[] = $_SERVER['REQUEST_METHOD'] == 'POST' ? $this->getPostHtml() : $this->getFormHtml();
        $html[] = '    </div>';
        $html[] = '</body>';
        $html[] = '';
        $html[] = '</html>';
        return implode("\n", $html);
    }

    /**
     * Execute the configurator tool logic.
     */
    public function execute(): void
    {
        E::$style = 'bulma';
        echo $this->getHtml();
    }

    private function getPostHtml(): string
    {
        $code = $this->loadCode();
        $config = $this->parseConfig($code);
        $config = $this->mergePost($config, $_POST);

        $html = [];
        $html[] = '<pre>';

        $success = false;
        $messages = $this->captureTestConfig($config);

        if ($messages['success']) {
            $code = $this->generateCode($config);
            $this->writeCode($code);
            $html[] = $messages['output'];
            $html[] = '\nConfig written';
            $success = true;
        } else {
            $html[] = $messages['output'];
            $html[] = '';
            $html[] = 'Config not written (invalid)';
        }

        $html[] = '</pre>';

        if ($success) {
            $html[] = '<input type="button" value="OK" onClick="window.location.href=\'/\'">';
        } else {
            $html[] = '<input type="button" value="Back" onClick="history.go(-1)">';
        }

        return implode("\n", $html);
    }

    private function getFormHtml(): string
    {
        $code = $this->loadCode();
        $config = $this->parseConfig($code);
        $form = $this->buildForm($config);
        $form->fill($this->flattenConfig($config));

        return $form->toString();
    }

    public function loadCode(): string
    {
        $filename = $this->filename;

        if (!file_exists($filename)) {
            $filename .= '.template';
        }

        if (!file_exists($filename)) {
            throw new \Exception("Could not read: $filename");
        }

        return file_get_contents($filename);
    }

    public function writeCode(string $code): void
    {
        file_put_contents($this->filename, $code);

        if (!file_exists($this->filename)) {
            throw new \Exception("Could not write: {$this->filename}");
        }
    }

    /**
     * Merge POST data into the configuration array
     * @param array $config Original configuration array
     * @param array $post POST data from the form
     * @return array Merged configuration array
     */
    public function mergePost(array $config, array $post): array
    {
        $store = function (string $class, string $name, $value) use (&$config) {
            foreach ($config[$class] as $i => $v) {
                if ($name == $v['name']) {
                    if (gettype($v['value']) == 'boolean') {
                        $config[$class][$i]['value'] = $value == 'true';
                    } else if (gettype($v['value']) == 'integer') {
                        $config[$class][$i]['value'] = (int) $value;
                    } else if (gettype($v['value']) == 'double') {
                        $config[$class][$i]['value'] = (float) $value;
                    } else {
                        $config[$class][$i]['value'] = $value;
                    }
                }
            }
        };

        foreach ($config as $class => $variables) {
            foreach ($variables as $v) {
                $name = $v['name'];
                if (isset($post[$class][$name])) {
                    $store($class, $name, $post[$class][$name]);
                }
            }
        }

        return $config;
    }

    private function buildForm(array $config): Form
    {
        E::$style = 'bulma';
        $form = E::form()->method('POST');

        foreach ($config as $class => $variables) {
            // Add section header
            $form->header(E::header()->caption($class)->class('subtitle is-4 mt-6'));

            foreach ($variables as $v) {
                $name = $v['name'];
                $comment = $v['comment'];
                $inputName = $class . '[' . $name . ']';

                $label = $comment ? "$name ($comment)" : $name;

                if (gettype($v['value']) == 'boolean') {
                    $control = E::select($inputName, ['false', 'true']);
                } else {
                    $control = E::text($inputName);
                }

                $form->field(E::field($control, E::label($label)));
            }
        }

        $form->field(E::field(E::submit('Test and Save')->class('mt-6')));

        return $form;
    }

    private function flattenConfig(array $config): array
    {
        $data = [];

        foreach ($config as $class => $variables) {
            foreach ($variables as $v) {
                $inputName = $class . '[' . $v['name'] . ']';
                if (gettype($v['value']) == 'boolean') {
                    $data[$inputName] = $v['value'] ? 'true' : 'false';
                } else {
                    $data[$inputName] = (string) $v['value'];
                }
            }
        }

        return $data;
    }

    public function parseConfig(string $code): array
    {
        $config = [];
        $lines = preg_split("/\r?\n/", $code);

        foreach ($lines as $line) {
            if (preg_match('/^\s*MintyPHP\\\([a-z]+)::\$([a-z]+)\s*=(.*);\s*(\/\/(.*))?$/i', $line, $matches)) {
                $class = $matches[1];
                if (!isset($config[$class])) {
                    $config[$class] = [];
                }

                $name = $matches[2];
                $value = trim($matches[3]);

                if (is_numeric($value) && strpos($value, '.') !== false) {
                    $value = (float) $value;
                } else if (is_numeric($value)) {
                    $value = (int) $value;
                } else if (in_array($value, array('true', 'false'))) {
                    $value = $value == 'true';
                } else {
                    $value = trim($value, '\'"');
                }

                if (isset($matches[5])) {
                    $comment = trim($matches[5]);
                } else {
                    $comment = false;
                }

                $config[$class][] = compact('name', 'value', 'comment');
            }
        }

        return $config;
    }

    public function generateCode(array $config): string
    {
        $export = function (array $v): string {
            if (gettype($v['value']) == 'boolean') {
                return $v['value'] ? 'true' : 'false';
            } else {
                return var_export($v['value'], true);
            }
        };

        $code = "<?php\n\n";

        foreach ($config as $class => $variables) {
            $nameChars = 0;
            foreach ($variables as $v) {
                $nameChars = max($nameChars, strlen($v['name']));
            }

            foreach ($variables as $v) {
                $name = sprintf("%-{$nameChars}s", $v['name']);
                $value = sprintf("%s", $export($v));
                $code .= "MintyPHP\\$class::\$$name = $value;";

                if ($v['comment']) {
                    $code .= " // $v[comment]";
                }

                $code .= "\n";
            }

            $code .= "\n";
        }

        return $code;
    }

    private function captureTestConfig(array &$config): array
    {
        ob_start();
        $success = $this->testConfig($config);
        $output = ob_get_clean();

        return [
            'success' => $success,
            'output' => $output ?: ''
        ];
    }

    public function testConfig(array &$config): bool
    {
        $parameters = array();
        foreach ($config as $class => &$variables) {
            foreach ($variables as &$v) {
                $parameters[$class . '_' . $v['name']] = &$v['value'];
            }
        }

        mysqli_report(MYSQLI_REPORT_ERROR);
        $mysqli = new \mysqli($parameters['DB_host'], $parameters['DB_username'], $parameters['DB_password']);

        if ($mysqli->connect_error) {
            echo "ERROR: MySQL connect: ($mysqli->connect_errno) $mysqli->connect_error\n";
            return false;
        }
        echo "INFO: MySQL connected\n";

        $sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$parameters[DB_database]';";
        if (!$result = $mysqli->query($sql)) {
            echo "ERROR: MySQL database check: $mysqli->error\n";
            return false;
        } elseif ($result->num_rows) {
            echo "INFO: MySQL database exists\n";
        } else {
            if ($parameters['DB_username'] != 'root') {
                echo "ERROR: MySQL database not found: $parameters[DB_database]\n";
                return false;
            }

            $sql = "CREATE DATABASE `$parameters[DB_database]` COLLATE 'utf8_bin';";
            if (!$result = $mysqli->query($sql)) {
                echo "ERROR: MySQL database create: $mysqli->error\n";
                return false;
            }
            echo "INFO: MySQL database created\n";

            $host = $parameters['DB_host'] == 'localhost' ? 'localhost' : '%';
            $pass = base64_encode(sha1(rand() . time(true) . $parameters['DB_database'], true));

            $sql = "CREATE USER '$parameters[DB_database]'@'$host' IDENTIFIED BY '$pass';";
            if (!$result = $mysqli->query($sql)) {
                echo "ERROR: MySQL user create: $mysqli->error\n";
                return false;
            }
            echo "INFO: MySQL user created\n";

            $sql = "GRANT ALL PRIVILEGES ON `$parameters[DB_database]`.* TO '$parameters[DB_database]'@'$host';";
            if (!$result = $mysqli->query($sql)) {
                echo "ERROR: MySQL grant user: $mysqli->error\n";
                return false;
            }
            echo "INFO: MySQL user granted\n";

            $parameters['DB_username'] = $parameters['DB_database'];
            $parameters['DB_password'] = $pass;
        }

        $sql = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$parameters[DB_database]' AND TABLE_NAME = 'users';";
        if (!$result = $mysqli->query($sql)) {
            echo "ERROR: MySQL users table check: $mysqli->error\n";
            return false;
        } elseif (!$result->num_rows) {
            $sql = "CREATE TABLE `$parameters[DB_database]`.`users` (";
            $sql .= "`id` int(11) NOT NULL AUTO_INCREMENT,";
            $sql .= "`username` varchar(255) COLLATE utf8_bin NOT NULL,";
            $sql .= "`password` varchar(255) COLLATE utf8_bin NOT NULL,";
            $sql .= "`created` datetime NOT NULL,";
            $sql .= "PRIMARY KEY (`id`),";
            $sql .= "UNIQUE KEY `username` (`username`)";
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;";

            if (!$mysqli->query($sql)) {
                echo "ERROR: MySQL create users table: $mysqli->error\n";
                return false;
            }
            echo "INFO: MySQL users table created\n";
        } else {
            echo "INFO: MySQL users table exists\n";
        }

        if ($mysqli->close()) {
            echo "INFO: MySQL disconnected\n";
        }

        return true;
    }
}
