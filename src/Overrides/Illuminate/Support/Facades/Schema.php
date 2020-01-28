<?php

namespace SafeSchemaBuilder\Overrides\Illuminate\Support\Facades;

use Illuminate\Support\Facades\Schema as BaseSchema;
use SafeSchemaBuilder\Overrides\Illuminate\Database\Schema\Blueprint;

/**
 * Class Schema
 * @package SafeSchemaBuilder\Overrides\Illuminate\Support\Facades
 */
class Schema extends BaseSchema
{
    /**
     * {@inheritDoc}
     */
    public static function connection($name)
    {
        $schemaBuilder = parent::connection($name);
        $schemaBuilder->blueprintResolver(Blueprint::make($schemaBuilder));

        return $schemaBuilder;
    }

    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor()
    {
        $schemaBuilder = parent::getFacadeAccessor();
        $schemaBuilder->blueprintResolver(Blueprint::make($schemaBuilder));

        return $schemaBuilder;
    }
}
