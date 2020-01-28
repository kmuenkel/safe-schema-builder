<?php

namespace Tests\Feature;

use Schema;
use Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SafeSchemaBuilder\Overrides\Illuminate\Database\Schema\Blueprint;

/**
 * Class DbBuilderTest
 * @package Tests\Feature
 */
class DbBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function canPreventUnnecessarySchemaChanges()
    {
        $tableName = uniqid('testing_');

        $passed = true;
        try {
            Schema::create($tableName, function (Blueprint $table) {
                $table->integer('id', true);
            });

            app('cache')->delete(Schema::getConnection()->getName().'.tables');

            Schema::create($tableName, function (Blueprint $table) {
                $table->integer('id', true);
            });
        } catch (QueryException $error) {
            $passed = false;
        } finally {
            Schema::dropIfExists($tableName);
        }

        self::assertTrue($passed);
    }
}
