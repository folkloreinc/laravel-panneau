<?php

namespace Panneau\Tests\Feature\Repositories;

/**
 * The search tests on PostgreSQL, when `PANNEAU_TEST_PGSQL_URL` is the URL of a database they can
 * create a table in. CI runs them (the `search` job).
 */
class PgsqlSearchTest extends SearchTest
{
    protected const DRIVER = 'pgsql';

    protected const URL = 'PANNEAU_TEST_PGSQL_URL';
}
