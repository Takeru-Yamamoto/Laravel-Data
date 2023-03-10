<?php

namespace MyCustom\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

use Closure;
use stdClass;
use Carbon\Carbon;

use MyCustom\Repositories\Results\BaseResult;

/**
 * 基底Repositoryクラス
 * 
 * Eloquentをwrapし、データベースからデータを取り出す担当。
 * Repositoryは原則Serviceクラスでのみ操作する。
 * Repositoryはデータを取り出しServiceに渡す際にデフォルトのModel|Collectionだけでなく、オリジナルのDAOであるResultクラスとして渡すことができる。
 * 
 */
abstract class BaseRepository
{
    /**
     * クエリビルダ
     *
     * @var Builder
     */
    private Builder $query;

    /**
     * 関連するモデル
     *
     * @var Model
     */
    private Model $model;

    /**
     * 関連するデータベース名
     *
     * @var string
     */
    private string $tableName;

    function __construct()
    {
        $this->initialize();
    }

    /**
     * 関連するModelを定義する
     *
     * @return Model
     */
    abstract protected function model(): Model;

    /**
     * Modelから必要な情報のみを抽出したResultクラスにconvertする
     *
     * @param object $entity
     */
    abstract public function toResult(object $entity): BaseResult;


    /**
     * 各変数の初期化処理
     * 必要に応じて各Repositoryにてoverrideする
     */
    private function initialize(): void
    {
        $this->model = $this->model();
        $this->query = $this->model::query();

        $this->tableName = $this->model->getTable();
    }

    /**
     * 各変数の初期化処理
     */
    final public function reset(): self
    {
        $this->initialize();
        return $this;
    }

    /**
     * CollectionをResultクラスの配列に変換する
     *
     * @param Collection $collection
     */
    final public function toResultList(Collection $collection): array|null
    {
        if ($collection->isEmpty()) return null;

        return array_map(
            function ($entity) {
                return $this->toResult($entity);
            },
            $collection->all()
        );
    }

    /**
     * Builderを取得
     */
    final public function query(): Builder
    {
        return $this->query;
    }

    /**
     * tableNameを取得
     */
    final public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * 現在のBuilderを用いてResultクラスの配列を取得する
     * column と value がnull出ない場合、取得前にwhereメソッドを実行する
     *
     * @param string|null $column
     * @param mixed $value
     */
    final public function get(?string $column = null, mixed $value = null): array|null
    {
        return $this->toResultList($this->getRaw($column, $value));
    }

    /**
     * 現在のBuilderを用いてCollectionを取得する
     * column と value がnull出ない場合、取得前にwhereメソッドを実行する
     *
     * @param string|null $column
     * @param mixed $value
     */
    final public function getRaw(?string $column = null, mixed $value = null): Collection
    {
        if (!is_null($column) && !is_null($value)) $this->where($column, $value);

        $result = $this->query->get();

        $this->reset();
        return $result;
    }

    /**
     * 現在のBuilderを用いてResultクラスを取得する
     * column と value がnull出ない場合、取得前にwhereメソッドを実行する
     *
     * @param string|null $column
     * @param mixed $value
     */
    final public function find(?string $column = null, mixed $value = null): BaseResult|null
    {
        $result = $this->findRaw($column, $value);

        if (is_null($result)) return $result;

        return $this->toResult($result);
    }

    /**
     * 現在のBuilderを用いてModelクラスを取得する
     * column と value がnull出ない場合、取得前にwhereメソッドを実行する
     *
     * @param string|null $column
     * @param mixed $value
     */
    final public function findRaw(?string $column = null, mixed $value = null): Model|null
    {
        if (!is_null($column) && !is_null($value)) $this->where($column, $value);

        $result = $this->query->first();
        $this->reset();
        return $result;
    }

    /**
     * 現在のBuilderを用いて該当するレコード数を取得する
     * column と value がnull出ない場合、取得前にwhereメソッドを実行する
     *
     * @param string|null $column
     * @param mixed $value
     */
    final public function count(?string $column = null, mixed $value = null): int
    {
        if (!is_null($column) && !is_null($value)) $this->where($column, $value);

        $result = $this->query->count();
        $this->reset();
        return $result;
    }

    /**
     * 現在のBuilderを用いて該当するレコードが存在するかを取得する
     * column と value がnull出ない場合、取得前にwhereメソッドを実行する
     *
     * @param string|null $column
     * @param mixed $value
     */
    final public function isExist(?string $column = null, mixed $value = null): bool|null
    {
        if (!is_null($column) && !is_null($value)) $this->where($column, $value);

        $result = $this->query->exists();
        $this->reset();
        return $result;
    }

    /**
     * 現在のBuilderを用いて該当するレコードをページネーションで利用しやすい形で取得する
     *
     * @param integer $page
     * @param integer $limit
     */
    final public function paginate(int $page, int $limit): stdClass
    {
        $result = new stdClass();

        $copy = $this->query();
        $result->total = $this->count();

        $this->query = $copy;
        $result->items = $this->forPage($page, $limit)->get();

        return $result;
    }

    /**
     * 以下有用な定義済みメソッド
     */
    final public function valid(): self
    {
        return $this->where("is_valid", 1);
    }
    final public function invalid(): self
    {
        return $this->where("is_valid", 0);
    }
    final public function isValid(int $isValid): self
    {
        if ($isValid === 0) return $this->invalid();
        if ($isValid === 1) return $this->valid();

        return $this->where("is_valid", $isValid);
    }
    final public function findById(int $id): BaseResult|null
    {
        return $this->find("id", $id);
    }
    final public function findRawById(int $id): Model|null
    {
        return $this->findRaw("id", $id);
    }
    final public function findByUserId(int $userId): BaseResult|null
    {
        return $this->find("user_id", $userId);
    }
    final public function findRawByUserId(int $userId): Model|null
    {
        return $this->findRaw("user_id", $userId);
    }
    final public function getByUserId(int $userId): array|null
    {
        return $this->get("user_id", $userId);
    }
    final public function getRawByUserId(int $userId): Collection|null
    {
        return $this->getRaw("user_id", $userId);
    }


    /**
     * Eloquentのwrap
     */
    final public function select(array|string $columns): self
    {
        $this->query = $this->query->select($columns);
        return $this;
    }
    final public function addSelect(array|string $columns): self
    {
        $this->query = $this->query->addSelect($columns);
        return $this;
    }


    final public function where(string $column, mixed $value, string $operator = "="): self
    {
        $this->query = $this->query->where($column, $operator, $value);
        return $this;
    }
    final public function whereLike(string $column, mixed $value): self
    {
        if (!str_contains($value, "%")) return $this->where($column, "%" . $value . "%", "like");

        return $this->where($column, $value, "like");
    }
    final public function whereGreater(string $column, mixed $value): self
    {
        return $this->where($column, $value, ">");
    }
    final public function whereGreaterEqual(string $column, mixed $value): self
    {
        return $this->where($column, $value, ">=");
    }
    final public function whereLess(string $column, mixed $value): self
    {
        return $this->where($column, $value, "<");
    }
    final public function whereLessEqual(string $column, mixed $value): self
    {
        return $this->where($column, $value, "<=");
    }
    final public function whereClosure(Closure $closure): self
    {
        $this->query = $this->query->where($closure);
        return $this;
    }
    final public function whereNot(string $column, mixed $value): self
    {
        $this->query = $this->query->whereNot($column, $value);
        return $this;
    }
    final public function whereIn(string $column, array $values): self
    {
        $this->query = $this->query->whereIn($column, $values);
        return $this;
    }
    final public function whereNotIn(string $column, array $values): self
    {
        $this->query = $this->query->whereNotIn($column, $values);
        return $this;
    }
    final public function whereBetween(string $column, mixed $start, mixed $end): self
    {
        $this->query = $this->query->whereBetween($column, [$start, $end]);
        return $this;
    }
    final public function whereNotBetween(string $column, mixed $start, mixed $end): self
    {
        $this->query = $this->query->whereNotBetween($column, [$start, $end]);
        return $this;
    }
    final public function whereNull(string $column): self
    {
        $this->query = $this->query->whereNull($column);
        return $this;
    }
    final public function whereNotNull(string $column): self
    {
        $this->query = $this->query->whereNotNull($column);
        return $this;
    }
    final public function whereColumn(string $column1, string $column2, string $operator = "="): self
    {
        $this->query = $this->query->whereColumn($column1, $operator, $column2);
        return $this;
    }
    final public function whereColumnGreater(string $column1, string $column2): self
    {
        return $this->whereColumn($column1, $column2, ">");
    }
    final public function whereColumnGreaterEqual(string $column1, string $column2): self
    {
        return $this->whereColumn($column1, $column2, ">=");
    }
    final public function whereColumnLess(string $column1, string $column2): self
    {
        return $this->whereColumn($column1, $column2, "<");
    }
    final public function whereColumnLessEqual(string $column1, string $column2): self
    {
        return $this->whereColumn($column1, $column2, "<=");
    }
    final public function orWhere(string $column, mixed $value, string $operator = "="): self
    {
        $this->query = $this->query->orWhere($column, $operator, $value);
        return $this;
    }
    final public function orWhereLike(string $column, mixed $value): self
    {
        return $this->orWhere($column, $value, "like");
    }
    final public function orWhereGreater(string $column, mixed $value): self
    {
        return $this->orWhere($column, $value, ">");
    }
    final public function orWhereGreaterEqual(string $column, mixed $value): self
    {
        return $this->orWhere($column, $value, ">=");
    }
    final public function orWhereLess(string $column, mixed $value): self
    {
        return $this->orWhere($column, $value, "<");
    }
    final public function orWhereLessEqual(string $column, mixed $value): self
    {
        return $this->orWhere($column, $value, "<=");
    }
    final public function orWhereClosure(Closure $closure): self
    {
        $this->query = $this->query->orWhere($closure);
        return $this;
    }
    final public function orWhereNot(string $column, mixed $value): self
    {
        $this->query = $this->query->whereNot($column, $value);
        return $this;
    }
    final public function orWhereIn(string $column, array $values): self
    {
        $this->query = $this->query->orWhereIn($column, $values);
        return $this;
    }
    final public function orWhereNotIn(string $column, array $values): self
    {
        $this->query = $this->query->orWhereNotIn($column, $values);
        return $this;
    }
    final public function orWhereBetween(string $column, mixed $start, mixed $end): self
    {
        $this->query = $this->query->orWhereBetween($column, [$start, $end]);
        return $this;
    }
    final public function orWhereNotBetween(string $column, mixed $start, mixed $end): self
    {
        $this->query = $this->query->orWhereNotBetween($column, [$start, $end]);
        return $this;
    }
    final public function orWhereNull(string $column): self
    {
        $this->query = $this->query->orWhereNull($column);
        return $this;
    }
    final public function orWhereNotNull(string $column): self
    {
        $this->query = $this->query->orWhereNotNull($column);
        return $this;
    }
    final public function orWhereColumn(string $column1, string $column2, string $operator = "="): self
    {
        $this->query = $this->query->orWhereColumn($column1, $operator, $column2);
        return $this;
    }
    final public function orWhereColumnGreater(string $column1, string $column2): self
    {
        return $this->orWhereColumn($column1, $column2, ">");
    }
    final public function orWhereColumnGreaterEqual(string $column1, string $column2): self
    {
        return $this->orWhereColumn($column1, $column2, ">=");
    }
    final public function orWhereColumnLess(string $column1, string $column2): self
    {
        return $this->orWhereColumn($column1, $column2, "<");
    }
    final public function orWhereColumnLessEqual(string $column1, string $column2): self
    {
        return $this->orWhereColumn($column1, $column2, "<=");
    }
    final public function whereJsonContains(string $jsonColumn, array $values): self
    {
        $this->query = $this->query->whereJsonContains($jsonColumn, $values);
        return $this;
    }
    final public function whereJsonLength(string $jsonColumn, int $length, string $operator = "="): self
    {
        $this->query = $this->query->whereJsonLength($jsonColumn, $operator, $length);
        return $this;
    }
    final public function whereJsonLengthGreater(string $jsonColumn, int $length): self
    {
        return $this->whereJsonLength($jsonColumn, $length, ">");
    }
    final public function whereJsonLengthGreaterEqual(string $jsonColumn, int $length): self
    {
        return $this->whereJsonLength($jsonColumn, $length, ">=");
    }
    final public function whereJsonLengthLess(string $jsonColumn, int $length): self
    {
        return $this->whereJsonLength($jsonColumn, $length, "<");
    }
    final public function whereJsonLengthLessEqual(string $jsonColumn, int $length): self
    {
        return $this->whereJsonLength($jsonColumn, $length, "<=");
    }
    final public function whereDate(string $dateColumn, ?string $date, string $operator = "="): self
    {
        $date = (new Carbon($date))->toDateString();
        $this->query = $this->query->whereDate($dateColumn, $operator, $date);
        return $this;
    }
    final public function whereDateGreater(string $dateColumn, ?string $date): self
    {
        return $this->whereDate($dateColumn, $date, ">");
    }
    final public function whereDateGreaterEqual(string $dateColumn, ?string $date): self
    {
        return $this->whereDate($dateColumn, $date, ">=");
    }
    final public function whereDateLess(string $dateColumn, ?string $date): self
    {
        return $this->whereDate($dateColumn, $date, "<");
    }
    final public function whereDateLessEqual(string $dateColumn, ?string $date): self
    {
        return $this->whereDate($dateColumn, $date, "<=");
    }
    final public function whereYear(string $dateColumn, ?int $year, string $operator = "="): self
    {
        if (is_null($year)) $year = (new Carbon())->year;

        $this->query = $this->query->whereYear($dateColumn, $operator, $year);
        return $this;
    }
    final public function whereYearGreater(string $dateColumn, ?int $year): self
    {
        return $this->whereYear($dateColumn, $year, ">");
    }
    final public function whereYearGreaterEqual(string $dateColumn, ?int $year): self
    {
        return $this->whereYear($dateColumn, $year, ">=");
    }
    final public function whereYearLess(string $dateColumn, ?int $year): self
    {
        return $this->whereYear($dateColumn, $year, "<");
    }
    final public function whereYearLessEqual(string $dateColumn, ?int $year): self
    {
        return $this->whereYear($dateColumn, $year, "<=");
    }
    final public function whereMonth(string $dateColumn, ?int $month, string $operator = "="): self
    {
        if (is_null($month)) $month = (new Carbon())->month;

        $this->query = $this->query->whereMonth($dateColumn, $operator, $month);
        return $this;
    }
    final public function whereMonthGreater(string $dateColumn, ?int $month): self
    {
        return $this->whereMonth($dateColumn, $month, ">");
    }
    final public function whereMonthGreaterEqual(string $dateColumn, ?int $month): self
    {
        return $this->whereMonth($dateColumn, $month, ">=");
    }
    final public function whereMonthLess(string $dateColumn, ?int $month): self
    {
        return $this->whereMonth($dateColumn, $month, "<");
    }
    final public function whereMonthLessEqual(string $dateColumn, ?int $month): self
    {
        return $this->whereMonth($dateColumn, $month, "<=");
    }
    final public function whereDay(string $dateColumn, ?int $day, string $operator = "="): self
    {
        if (is_null($day)) $day = (new Carbon())->day;

        $this->query = $this->query->whereDay($dateColumn, $operator, $day);
        return $this;
    }
    final public function whereDayGreater(string $dateColumn, ?int $day): self
    {
        return $this->whereDay($dateColumn, $day, ">");
    }
    final public function whereDayGreaterEqual(string $dateColumn, ?int $day): self
    {
        return $this->whereDay($dateColumn, $day, ">=");
    }
    final public function whereDayLess(string $dateColumn, ?int $day): self
    {
        return $this->whereDay($dateColumn, $day, "<");
    }
    final public function whereDayLessEqual(string $dateColumn, ?int $day): self
    {
        return $this->whereDay($dateColumn, $day, "<=");
    }
    final public function whereTime(string $dateColumn, ?string $time, string $operator = "="): self
    {
        $time = (new Carbon($time))->toTimeString();
        $this->query = $this->query->whereTime($dateColumn, $operator, $time);
        return $this;
    }
    final public function whereTimeGreater(string $dateColumn, ?string $time): self
    {
        return $this->whereTime($dateColumn, $time, ">");
    }
    final public function whereTimeGreaterEqual(string $dateColumn, ?string $time): self
    {
        return $this->whereTime($dateColumn, $time, ">=");
    }
    final public function whereTimeLess(string $dateColumn, ?string $time): self
    {
        return $this->whereTime($dateColumn, $time, "<");
    }
    final public function whereTimeLessEqual(string $dateColumn, ?string $time): self
    {
        return $this->whereTime($dateColumn, $time, "<=");
    }


    final public function orderBy(string $column, string $order): self
    {
        $this->query = $this->query->orderBy($column, $order);
        return $this;
    }
    final public function asc(string $column = "created_at"): self
    {
        return $this->orderBy($column, "asc");
    }
    final public function desc(string $column = "created_at"): self
    {
        return $this->orderBy($column, "desc");
    }


    final public function groupBy(string|array $columns): self
    {
        $this->query = $this->query->groupBy($columns);
        return $this;
    }
    final public function having(string $column, string|int|float|null $value, string $operator = "="): self
    {
        $this->query = $this->query->having($column, $operator, $value);
        return $this;
    }
    final public function havingGreater(string $column, string|int|float|null $value): self
    {
        return $this->having($column, $value, ">");
    }
    final public function havingGreaterEqual(string $column, string|int|float|null $value): self
    {
        return $this->having($column, $value, ">=");
    }
    final public function havingLess(string $column, string|int|float|null $value): self
    {
        return $this->having($column, $value, "<");
    }
    final public function havingLessEqual(string $column, string|int|float|null $value): self
    {
        return $this->having($column, $value, "<=");
    }
    final public function havingBetween(string $column, mixed $start, mixed $end): self
    {
        $this->query = $this->query->havingBetween($column, [$start, $end]);
        return $this;
    }


    final public function limit(int $limit): self
    {
        $this->query = $this->query->limit($limit);
        return $this;
    }
    final public function offset(int $offset): self
    {
        $this->query = $this->query->offset($offset);
        return $this;
    }
    final public function forPage(int $page, int $limit): self
    {
        return $this->limit($limit)->offset(($page - 1) * $limit);
    }


    final public function selectRaw(string $sql, array $bindings = []): self
    {
        $this->query = $this->query->selectRaw($sql, $bindings);
        return $this;
    }
    final public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->query = $this->query->whereRaw($sql, $bindings);
        return $this;
    }
    final public function orWhereRaw(string $sql, array $bindings = []): self
    {
        $this->query = $this->query->orWhereRaw($sql, $bindings);
        return $this;
    }
    final public function havingRaw(string $sql, array $bindings = []): self
    {
        $this->query = $this->query->havingRaw($sql, $bindings);
        return $this;
    }
    final public function orHavingRaw(string $sql, array $bindings = []): self
    {
        $this->query = $this->query->orHavingRaw($sql, $bindings);
        return $this;
    }
    final public function orderByRaw(string $sql, array $bindings = []): self
    {
        $this->query = $this->query->orderByRaw($sql, $bindings);
        return $this;
    }
    final public function groupByRaw(string $sql): self
    {
        $this->query = $this->query->groupByRaw($sql);
        return $this;
    }


    final public function join(string $table, string $tableColumn, string $column, string $operator = "="): self
    {
        $this->query = $this->query->join($table, $this->tableName . "." . $column, $operator, $table . "." . $tableColumn);
        return $this;
    }
    final public function leftJoin(string $table, string $tableColumn, string $column, string $operator = "="): self
    {
        $this->query = $this->query->leftJoin($table, $this->tableName . "." . $column, $operator, $table . "." . $tableColumn);
        return $this;
    }
    final public function rightJoin(string $table, string $tableColumn, string $column, string $operator = "="): self
    {
        $this->query = $this->query->rightJoin($table, $this->tableName . "." . $column, $operator, $table . "." . $tableColumn);
        return $this;
    }
    final public function with(string $method): self
    {
        $this->query = $this->query->with($method);
        return $this;
    }
}
