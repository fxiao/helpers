<?php

namespace Encore\Admin\Helpers;

use Encore\Admin\Helpers\Scaffold\ControllerCreator;
use Encore\Admin\Helpers\Scaffold\ModelCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class GenerateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据数据库表生成模型和控制器';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
    	ini_set('memory_limit', '2048M');
        echo date('Y-m-d H:i:s') . "：开始\n";

        $this->generate();

        echo "请到 app/Admin/routes.php 配置相应路由 \n";
        echo date('Y-m-d H:i:s') . '：结束';
    }


    /**
     * 生成
     *
     * @return void
     */
    public function generate()
    {
        $tables = [];

        $type = [
            'int' => 'integer',
            'varchar' => 'string',
            'tinyint' => 'tinyInteger',
            'smallint' => 'smallInteger',
            'bigint' => 'bigInteger',
            'unsignedtinyint' => 'unsignedTinyInteger'
        ];

        $database = Config::get('database.connections.mysql.database');
        Config::Set('database.connections.mysql.database', 'information_schema');
        //DB::reconnect(); 之前没有连接数据库，所以不需要重连
        $flds = DB::table('COLUMNS')->where('TABLE_SCHEMA', $database)
            ->select('TABLE_NAME', 'COLUMN_NAME', 'COLUMN_DEFAULT', 'IS_NULLABLE', 'DATA_TYPE', 'COLUMN_COMMENT')
            ->get();

        foreach ($flds as $fld) {
            if (in_array($fld->COLUMN_NAME, ['id'])) {
                continue;
            }

            if (!array_key_exists($fld->TABLE_NAME, $tables)) {
                $tables[$fld->TABLE_NAME] = [];
            }

            $tables[$fld->TABLE_NAME][] = [
                'name' => $fld->COLUMN_NAME,
                'type' => array_key_exists(strtolower($fld->DATA_TYPE), $type)
                    ? $type[strtolower($fld->DATA_TYPE)]: $fld->DATA_TYPE,
                'key' => null,
                'default' => $fld->COLUMN_DEFAULT == "NULL"? null: $fld->COLUMN_DEFAULT,
                'comment' => $fld->COLUMN_COMMENT
            ];
        }

        foreach ($tables as $table_name => $table) {
            $class_name = Str::studly(Str::singular($table_name));
            $model_name = 'App\\Models\\' . $class_name;
            $controller_name = 'App\\Admin\\Controllers\\' . $class_name . 'Controller';

            /*
            if (file_exists(app_path("Models/$class_name.php"))) {
                continue;
            }
             */

            // 对于 admin_ 开头的表名，admin_ 值为 0, dmin_ 值为 1。
            if (strpos($table_name, 'dmin_') || $table_name == 'migrations') {
                continue;
            }

            if (file_exists(app_path("Admin/Controllers/$class_name" . "Controller.php"))) {
                continue;
            }

            if (file_exists(app_path("Models/$class_name" . ".php"))) {
                continue;
            }

            $timestamps = false;
            $soft_deletes = false;
            foreach ($table as $v => $f) {
                if (Arr::get($f, 'name') == 'created_at') {
                    $timestamps = true;
                    unset($table[$v]);
                }

                if (Arr::get($f, 'name') == 'updated_at') {
                    unset($table[$v]);
                }

                if (Arr::get($f, 'name') == 'deleted_at') {
                    $soft_deletes = true;
                    unset($table[$v]);
                }
            }

            $m = new ModelCreator($table_name, $model_name);
            $m->create(
                $table,
                'id',
                $timestamps,
                $soft_deletes
            );

            echo $m->getPath($model_name) . "\n";

            $c = new ControllerCreator($controller_name);
            $c->buildBluePrint(
                $table,
                $table_name
            )->create($model_name);

            echo $c->getPath($controller_name) . "\n";
        }

    }
}
