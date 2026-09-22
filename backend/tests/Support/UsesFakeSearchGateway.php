<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Catalog\Search\Contracts\SearchGateway;

trait UsesFakeSearchGateway
{
    protected function fakeSearchGateway(): FakeSearchGateway
    {
        $fake = new FakeSearchGateway;
        $this->app->instance(SearchGateway::class, $fake);

        return $fake;
    }
}
