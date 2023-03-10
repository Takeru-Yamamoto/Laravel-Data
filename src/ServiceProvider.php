<?php

namespace MyCustom\Data;

use Illuminate\Support\ServiceProvider as Provider;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class ServiceProvider extends Provider
{
    /**
     * publications配下をpublishする際に使うルートパス
     *
     * @var string
     */
    private string $publicationsPath = __DIR__ . "/publications";

    public function register(): void
    {
        /* SQL実行時にログに書き込む */
        if (config('mycustoms.data.logging_sql', false)) {
            DB::listen(function ($query): void {
                $sql = $query->sql;

                foreach ($query->bindings as $binding) {
                    $bindingText = match (true) {
                        is_string($binding)                    => "'" . $binding . "'",
                        is_int($binding), is_float($binding)   => strval($binding),
                        is_null($binding)                      => "null",
                        is_bool($binding) && $binding          => "1",
                        is_bool($binding) && !$binding         => "0",
                        $binding instanceof Carbon             => "'" . $binding->toDateTimeString() . "'",
                        $binding instanceof DateTime           => "'" . $binding->format('Y-m-d H:i:s') . "'",

                        default                                => $binding
                    };

                    $sql = preg_replace('/\\?/', $bindingText, $sql, 1);
                }

                infoLog('SQL: "' . $sql . ';", time: ' . $query->time . " ms");
            });
        }
    }


    public function boot(): void
    {
        $this->registerBlueprintMacros();
        $this->publications();
    }


    /**
     * Blueprintにmacroを登録する
     */
    private function registerBlueprintMacros(): void
    {
        Blueprint::macro('isValid', function () {
            assert($this instanceof Blueprint);
            $this->tinyInteger('is_valid')->default(1);
        });
    }


    /**
     * publicationsディレクトリ配下を公開する
     */
    private function publications()
    {
        // 共通タグ
        $this->publishes([
            $this->publicationsPath . "/config" => config_path(),
        ], "mycustom");

        // Presentation Domain のみ
        $this->publishes([
            $this->publicationsPath . "/config" => config_path(),
        ], "mycustom-data");
    }
}
