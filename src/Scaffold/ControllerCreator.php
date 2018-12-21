<?php

namespace Encore\Admin\Helpers\Scaffold;

class ControllerCreator
{
    /**
     * Controller full name.
     *
     * @var string
     */
    protected $name;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    protected $bluePrintGrid = '';
    protected $bluePrintShow = '';
    protected $bluePrintForm = '';
    protected $bluePrintHeader = '';

    /**
     * ControllerCreator constructor.
     *
     * @param string $name
     * @param null   $files
     */
    public function __construct($name, $files = null)
    {
        $this->name = $name;

        $this->files = $files ?: app('files');
    }

    /**
     * Create a controller.
     *
     * @param string $model
     *
     * @throws \Exception
     *
     * @return string
     */
    public function create($model)
    {
        $path = $this->getpath($this->name);

        if ($this->files->exists($path)) {
            throw new \Exception("Controller [$this->name] already exists!");
        }

        $stub = $this->files->get($this->getStub());

        $this->files->put($path, $this->replace($stub, $this->name, $model));

        return $path;
    }

    /**
     * @param string $stub
     * @param string $name
     * @param string $model
     *
     * @return string
     */
    protected function replace($stub, $name, $model)
    {
        $stub = $this->replaceClass($stub, $name);

        return str_replace(
            ['DummyModelNamespace', 'DummyModel', 'Dummyheader', 'DummyStructureGrid', 'DummyStructureShow', 'DummyStructureForm'],
            [$model, class_basename($model), $this->bluePrintHeader, $this->bluePrintGrid, $this->bluePrintShow, $this->bluePrintForm],
            $stub
        );
    }

    /**
     * Get controller namespace from giving name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getNamespace($name)
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     *
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name).'\\', '', $name);

        return str_replace(['DummyClass', 'DummyNamespace'], [$class, $this->getNamespace($name)], $stub);
    }

    /**
     * Get file path from giving controller name.
     *
     * @param $name
     *
     * @return string
     */
    public function getPath($name)
    {
        $segments = explode('\\', $name);

        array_shift($segments);

        return app_path(implode('/', $segments)).'.php';
    }

    /**
     * Get stub file path.
     *
     * @return string
     */
    public function getStub()
    {
        return __DIR__.'/stubs/controller.stub';
    }

    /**
     * Build the table blueprint.
     *
     * @param array      $fields
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function buildBluePrint($fields = [], $header="")
    {
        $fields = array_filter($fields, function ($field) {
            return isset($field['name']) && !empty($field['name']);
        });

        if (empty($fields)) {
            throw new \Exception('Table fields can\'t be empty');
        }

        $types = [
            'string' => 'text', 
            'integer' => 'number', 
            'text' => 'text', 
            'float' => 'text', 
            'double' => 'text', 
            'decimal' => 'text', 
            'boolean' => 'text', 
            'date' => 'date', 
            'time' => 'time',
            'dateTime' => 'datetime', 
            'timestamp' => 'time', 
            'char' => 'text', 
            'mediumText' => 'text', 
            'longText' => 'textarea', 
            'tinyInteger' => 'number', 
            'smallInteger' => 'number',
            'mediumInteger' => 'number', 
            'bigInteger' => 'number', 
            'unsignedTinyInteger' => 'number', 
            'unsignedSmallInteger' => 'number', 
            'unsignedMediumInteger' => 'number',
            'unsignedInteger' => 'number', 
            'unsignedBigInteger' => 'number', 
            'enum' => 'textarea', 
            'json' => 'textarea', 
            'jsonb' => 'textarea', 
            'dateTimeTz' => 'dateTime', 
            'timeTz' => 'time',
            'timestampTz' => 'time', 
            'nullableTimestamps' => 'time', 
            'binary' => 'text', 
            'ipAddress' => 'ip', 
            'macAddress' => 'text'
        ];

        $rows_grid = [];
        $rows_form = []; 

        $i = 0;
        foreach ($fields as $field) {

            $i++;
            $n = "\n";
            if ($i>3) {
                $n = "\n\n";
                $i = 0;
            }

            $default = $field['default'] ? "->default('{$field['default']}')" : "";

            $rows_grid[] = "\$grid->{$field['name']}('{$field['comment']}');$n";
            $rows_show[] = "\$show->{$field['name']}('{$field['comment']}');$n";

            $rows_form[] = "\$form->" .
                array_get($types, $field['type'], 'text') .
                "('{$field['name']}', '{$field['comment']}')$default;$n";
        }


        $this->bluePrintGrid = trim(implode(str_repeat(' ', 12), $rows_grid), "\n");
        $this->bluePrintShow = trim(implode(str_repeat(' ', 12), $rows_show), "\n");
        $this->bluePrintForm = trim(implode(str_repeat(' ', 12), $rows_form), "\n");

        $this->bluePrintHeader = $header;

        return $this;
    }
}
