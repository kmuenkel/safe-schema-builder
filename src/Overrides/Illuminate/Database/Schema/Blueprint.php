<?php

namespace SafeSchemaBuilder\Overrides\Illuminate\Database\Schema;

use Arr;
use Closure;
use Doctrine\DBAL\Schema;
use InvalidArgumentException;
use Illuminate\Support\Fluent;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;

/**
 * Class Blueprint
 * @package SafeSchemaBuilder\Overrides\Illuminate\Database\Schema
 */
class Blueprint extends BaseBlueprint
{
    /**
     * @var Builder
     */
    protected $schemaBuilder;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $connectionName;

    /**
     * @var Schema\AbstractSchemaManager
     */
    protected $schemaManager;

    /**
     * SafeBlueprint constructor.
     * @param Builder $schema
     * @param string $table
     * @param Closure|null $callback
     * @param string $prefix
     */
    public function __construct(Builder $schema, $table, Closure $callback = null, $prefix = '')
    {
        $this->setSchema($schema);

        parent::__construct($table, $callback, $prefix);
    }

    /**
     * @param string $type
     * @param string $name
     * @param array $parameters
     * @return ColumnDefinition
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $column = $this->getColumnSchema($this->table, $name);
        $default = new ColumnDefinition(array_merge(compact('type', 'name'), $parameters));

//        if ($column) {
//            $type = $column->getType();
//            $name = $column->getName();
//            $parameters = [
//                'autoIncrement' => $column->getAutoincrement(),
//                'comment' => $column->getComment(),
//                'default' => $column->getDefault(),
//                'fixed' => $column->getFixed(),
//                'length' => $column->getLength(),
//                'notNull' => $column->getNotnull(),
//                'precision' => $column->getPrecision(),
//                'scale' => $column->getScale(),
//                'unsigned' => $column->getUnsigned()
//            ];
//            $column = new ColumnDefinition(array_merge(compact('type', 'name'), $parameters));
//        }

        return !$column ? parent::addColumn($type, $name, $parameters) : $default;
    }

    /**
     * {@inheritDoc}
     */
    protected function addCommand($name, array $parameters = [])
    {
        $indexTypes = [
            'primary',
            'unique',
            'index',
            'spatialIndex',
            'foreign'
        ];
        if (($name == 'create' && $this->getTableSchema($this->table))
            || (in_array($name, $indexTypes) && $this->getIndexSchema($this->table, $name, $parameters))
            || ($name == 'foreign' && $this->getForeignKeySchema($this->table, $parameters))
        ) {
            return new Fluent(array_merge(compact('name'), $parameters));
        }

        return parent::addCommand($name, $parameters);
    }

    /**
     * @param string $tableName
     * @return Schema\Table|null
     */
    public function getTableSchema($tableName)
    {
        /** @var Schema\Table[] $tables */
        $tables = get_from_cache("$this->connectionName.tables", function () {
            return $this->schemaManager->listTables();
        }, now()->addHour());

        return collect($tables)->filter(function (Schema\Table $table) use ($tableName) {
            return $table->getName() == $tableName;
        })->first();
    }

    /**
     * @param string $tableName
     * @param string $columnName
     * @return Schema\Column|null
     */
    public function getColumnSchema($tableName, $columnName)
    {
        $table = $this->getTableSchema($tableName);

        try {
            $column = $table ? $table->getColumn($columnName) : null;
        } catch (Schema\SchemaException $e) {
            $column = null;
        }

        return $column;
    }

    /**
     * @param string $tableName
     * @param string $type
     * @param array $properties
     * @return Schema\Index|null
     */
    public function getIndexSchema($tableName, $type, array $properties)
    {
        $indexName = Arr::get($properties, 'index');
        $columnNames = Arr::get($properties, 'columns');
        $table = $this->getTableSchema($tableName);

        try {
            return $table ? $table->getIndex($indexName) : null;
        } catch (Schema\SchemaException $e) {
            $indexes = $table->getIndexes();

            return collect($indexes)->filter(function (Schema\Index $index) use ($type, $columnNames) {
                $found = count(array_intersect($index->getColumns(), $columnNames)) == count($columnNames);

                switch ($type) {
                    case 'primary':
                        $found &= $index->isPrimary();

                        break;
                    case 'unique':
                        $found &= $index->isUnique();

                        break;
                    case 'index':
                        $found &= $index->isSimpleIndex();

                        break;
                    case 'spatialIndex':
                        $found &= $index->hasFlag('spatial');

                        break;
                    default:
                        throw new InvalidArgumentException("Unhandled index type '$type'.");
                }

                return $found;
            })->first();
        }
    }

    /**
     * @param string $tableName
     * @param array $properties
     * @return Schema\ForeignKeyConstraint|null
     */
    public function getForeignKeySchema($tableName, array $properties)
    {
        $indexName = Arr::get($properties, 'index');
        $columnNames = Arr::get($properties, 'columns');
        $table = $this->getTableSchema($tableName);

        try {
            return $table ? $table->getForeignKey($indexName) : null;
        } catch (Schema\SchemaException $e) {
            $foreignKeys = $table->getForeignKeys();

            return collect($foreignKeys)->filter(function (Schema\ForeignKeyConstraint $index) use ($columnNames) {
                $found = count(array_intersect($index->getLocalColumns(), $columnNames)) == count($columnNames);

                return $found;
            })->first();
        }
    }

    /**
     * @param Builder $schemaBuilder
     * @return $this
     */
    public function setSchema(Builder $schemaBuilder)
    {
        $this->schemaBuilder = $schemaBuilder;
        $this->connection = $this->schemaBuilder->getConnection();
        $this->connectionName = $this->connection->getName();
        $this->schemaManager = $this->connection->getDoctrineSchemaManager();

        return $this;
    }

    /**
     * @param Builder $schema
     * @return Closure
     */
    public static function make(Builder $schema)
    {
        return function ($table, Closure $callback = null, $prefix = '') use ($schema) {
            return new static($schema, $table, $callback, $prefix);
        };
    }
}
