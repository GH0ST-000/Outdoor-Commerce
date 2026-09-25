<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Catalog\Contracts\SearchGateway as CatalogSearchGateway;
use App\Domains\Catalog\Search\Contracts\SearchGateway;

trait UsesFakeSearchGateway
{
    protected function fakeSearchGateway(): FakeSearchGateway
    {
        $fake = new FakeSearchGateway;
        $this->app->instance(SearchGateway::class, $fake);
        $this->app->instance(CatalogSearchGateway::class, $fake);

        return $fake;
    }
}
