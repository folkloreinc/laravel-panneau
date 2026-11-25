<?php

namespace Panneau\Tests\Feature\Repositories;

/**
 * The search tests on MySQL, when `PANNEAU_TEST_MYSQL_URL` is the URL of a database they can
 * create a table in. CI runs them (the `search` job).
 */
class MySqlSearchTest extends SearchTest
{
    protected const DRIVER = 'mysql';

    protected const URL = 'PANNEAU_TEST_MYSQL_URL';
}
