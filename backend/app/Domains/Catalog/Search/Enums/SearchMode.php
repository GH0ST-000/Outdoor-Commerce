<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Enums;

enum SearchMode: string
{
    case Meilisearch = 'meilisearch';
    case MysqlFallback = 'mysql_fallback';
    case BasicMysql = 'basic_mysql';
    case Disabled = 'search_disabled';
}
