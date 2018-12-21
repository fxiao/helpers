<?php

namespace Encore\Admin\Helpers\Controllers;

use Encore\Admin\Facades\Admin;
use Encore\Admin\Helpers\Scaffold\ControllerCreator;
use Encore\Admin\Helpers\Scaffold\MigrationCreator;
use Encore\Admin\Helpers\Scaffold\ModelCreator;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;

class ScaffoldController extends Controller
{
    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('Scaffold');

            $dbTypes = [
                'string', 'integer', 'text', 'float', 'double', 'decimal', 'boolean', 'date', 'time',
                'dateTime', 'timestamp', 'char', 'mediumText', 'longText', 'tinyInteger', 'smallInteger',
                'mediumInteger', 'bigInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger',
                'unsignedInteger', 'unsignedBigInteger', 'enum', 'json', 'jsonb', 'dateTimeTz', 'timeTz',
                'timestampTz', 'nullableTimestamps', 'binary', 'ipAddress', 'macAddress',
            ];

            $action = URL::current();

            $content->row(view('laravel-admin-helpers::scaffold', compact('dbTypes', 'action')));
        });
    }

    public function store(Request $request)
    {
        $paths = [];
        $message = '';

        try {

            $table_name = $request->get('table_name'); 
            $class_name = $this->table_to_model($table_name); 

            $model_name = (env('DEV_HELPERS_MODELS_PATH', 'App\\Models\\') 
                ?: 'App\\Models\\') . $class_name;

            $controller_name = (env('DEV_HELPERS_CONTROLLER_PATH', 'App\\Admin\\Controllers\\')
                ?: 'App\\Admin\\Controllers\\') . $class_name . 'Controller';

            // 1. Create model.
            if (in_array('model', $request->get('create'))) {
                $modelCreator = new ModelCreator($request->get('table_name'), $model_name);

                $paths['model'] = $modelCreator->create(
                    $request->get('primary_key'),
                    $request->get('timestamps') == 'on',
                    $request->get('soft_deletes') == 'on'
                );
            }

            // 2. Create controller.
            if (in_array('controller', $request->get('create'))) {
                $paths['controller'] = (new ControllerCreator($controller_name))
                    ->buildBluePrint(
                        $request->get('fields'),
                        $request->get('header')
                    )->create($model_name);
            }

            // 3. Create migration.
            if (in_array('migration', $request->get('create'))) {
                $migrationName = 'create_'.$request->get('table_name').'_table';

                $paths['migration'] = (new MigrationCreator(app('files')))->buildBluePrint(
                    $request->get('fields'),
                    $request->get('primary_key', 'id'),
                    $request->get('timestamps') == 'on',
                    $request->get('soft_deletes') == 'on'
                )->create($migrationName, database_path('migrations'), $request->get('table_name'));
            }

            // 4. Run migrate.
            if (in_array('migrate', $request->get('create'))) {
                Artisan::call('migrate');
                $message = Artisan::output();
            }
        } catch (\Exception $exception) {

            // Delete generated files if exception thrown.
            app('files')->delete($paths);

            return $this->backWithException($exception);
        }

        return $this->backWithSuccess($paths, $message);
    }

    protected function backWithException(\Exception $exception)
    {
        $error = new MessageBag([
            'title'   => 'Error',
            'message' => $exception->getMessage(),
        ]);

        return back()->withInput()->with(compact('error'));
    }

    protected function backWithSuccess($paths, $message)
    {
        $messages = [];

        foreach ($paths as $name => $path) {
            $messages[] = ucfirst($name).": $path";
        }

        $messages[] = "<br />$message";

        $success = new MessageBag([
            'title'   => 'Success',
            'message' => implode('<br />', $messages),
        ]);

        return back()->with(compact('success'));
    }

    protected function table_to_model(string $table): string {
        $model = '';
        foreach(explode('_', $table) as $mn) {
            $model .= ucwords($mn);
        }

        return $model;
    }
}
