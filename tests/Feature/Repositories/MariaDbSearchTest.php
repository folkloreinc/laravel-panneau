<?php

namespace Panneau\Tests\Feature\Repositories;

/**
 * The search tests on MariaDB, when `PANNEAU_TEST_MARIADB_URL` is the URL of a database they can
 * create a table in. CI runs them (the `search` job).
 */
class MariaDbSearchTest extends SearchTest
{
    protected const DRIVER = 'mariadb';

    protected const URL = 'PANNEAU_TEST_MARIADB_URL';
}
