<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Support\RouteIdentifier;
use PHPUnit\Framework\TestCase;

final class RouteIdentifierTest extends TestCase
{
    public function test_named_routes_use_their_name(): void
    {
        $this->assertSame('api.documents.show', RouteIdentifier::for('api.documents.show', ['GET'], null, 'api/documents/{id}'));
    }

    public function test_unnamed_routes_use_a_deterministic_fallback(): void
    {
        $this->assertSame('GET|HEAD api/ping', RouteIdentifier::for(null, ['GET', 'HEAD'], null, 'api/ping'));
        $this->assertSame('GET|HEAD api/ping', RouteIdentifier::for('  ', ['GET', 'HEAD'], null, 'api/ping'));
    }

    public function test_domain_is_part_of_the_fallback(): void
    {
        $this->assertSame('POST api/x @ tenant.example.com', RouteIdentifier::for(null, ['POST'], 'tenant.example.com', 'api/x'));
    }
}
