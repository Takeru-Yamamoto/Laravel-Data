<?php

namespace MyCustom\Repositories\Results;

/**
 * 基底Resultクラス
 *
 * ResultはDAOであり、Modelから不必要な情報を削ぎ落したもの。
 * メンバ変数の定義と、constructでの代入処理を定義する。
 * 
 */
abstract class BaseResult implements \JsonSerializable
{
    /**
     * json_encode()でJSONにシリアライズするデータを定義する
     */
    public function jsonSerialize(): array
    {
        return config("mycustom.result_nullable", false) ? ['result' => get_object_vars($this)] :  ['result' => $this->removeNullValues(get_object_vars($this))];
    }

    /**
     * privateでないpropertyの中からnullを取り除く
     *
     * @param array $properties
     */
    protected function removeNullValues(array $properties)
    {
        $result = (is_array($properties) ? array() : new \stdClass());

        foreach ($properties as $key => $value) {
            if (is_array($value) || is_object($value)) {
                if (is_object($result)) {
                    $result->$key = $this->removeNullValues($value);
                } else {
                    $result[$key] = $this->removeNullValues($value);
                }
            } elseif (!is_null($value)) {
                if (is_object($result)) {
                    $result->$key = $value;
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}
