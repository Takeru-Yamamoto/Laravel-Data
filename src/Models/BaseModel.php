<?php

namespace MyCustom\Models;

use Illuminate\Support\Facades\DB;

/**
 * Modelで使用するtrait
 */
trait BaseModel
{
    /**
     * saveメソッドをtransactionメソッドでwrap
     *
     * @param string $message
     */
    private function safeSave(string $message): void
    {
        $this->transaction($message, function () {
            $this->save();
        });
    }

    /**
     * ログに残すメッセージを作成する
     *
     * @param string $name
     */
    private function createMessage(string $name): string
    {
        $className = className($this);
        $backtrace = debug_backtrace();
        $targetBacktrace = isset($backtrace[1]) ? $backtrace[1] : null;

        if (is_null($targetBacktrace) || !isset($targetBacktrace["file"]) || !isset($targetBacktrace["line"])) return $className . " " . $name . " backtrace: " . jsonEncode($backtrace);

        $service = explode("/", $targetBacktrace["file"]);
        $serviceClassName = str_replace(".php", "", end($service));
        $line = $targetBacktrace["line"];

        return $className . " " . $name . " in " . $serviceClassName . ": " . $line;
    }

    /**
     * transaction を使用した安全な保存
     */
    final public function safeCreate(): void
    {
        $this->safeSave($this->createMessage("CREATE"));
    }

    /**
     * transaction を使用した安全な更新
     */
    final public function safeUpdate(): void
    {
        $this->safeSave($this->createMessage("UPDATE"));
    }

    /**
     * transaction を使用した安全な削除
     */
    final public function safeDelete(): void
    {
        $this->transaction($this->createMessage("DELETE"), function () {
            $this->delete();
        });
    }

    /**
     * is_validカラムが存在する場合 isValid に置き換え保存する
     *
     * @param integer $isValid
     */
    final public function changeIsValid(int $isValid): void
    {
        if (isset($this->is_valid)) {
            $this->is_valid = $isValid;
            $this->safeSave($this->createMessage("CHANGE"));
        }
    }

    /**
     * レコードが有効か無効か
     * is_validカラムが存在しない場合はfalse
     *
     * @param integer $isValid
     */
    final public function isValid(): bool
    {
        return isset($this->is_valid) ? boolval($this->is_valid) : false;
    }

    /**
     * データベースへの変更に失敗した場合、自動的に元の状態に戻す
     */
    final public function transaction(string $description, \Closure $transactional): void
    {
        $exception = null;

        if (config('mycustoms.data.logging_transaction', false)) {
            emphasisLogStart("TRANSACTION");

            DB::beginTransaction();

            try {
                $transactional();

                DB::commit();

                infoLog("SUCCESS TRANSACTION " . $description);
            } catch (\Exception $e) {
                $description = $description ? $description : "";

                $message = "FAILURE TRANSACTION " . $description;
                infoLog($message);

                try {
                    DB::rollback();

                    $message .= " SUCCESS ROLLBACK";
                    infoLog("ROLLBACK: success");
                } catch (\Exception $e2) {
                    $message .= " FAILURE ROLLBACK Caused By " . $e2->getMessage();
                    infoLog("ROLLBACK: success");
                    infoLog("CAUSED: " . $e2->getMessage());
                }

                $exception = new \Exception($message, 0, $e);
            }

            emphasisLogEnd("TRANSACTION");

            if (!is_null($exception)) throw $exception;
        } else {
            DB::beginTransaction();

            try {
                $transactional();
                DB::commit();
            } catch (\Exception $e) {
                try {
                    DB::rollback();
                } catch (\Exception $e2) {
                    throw $e2;
                }
                throw $e;
            }
        }
    }
}
