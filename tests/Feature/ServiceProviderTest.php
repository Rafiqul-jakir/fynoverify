<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Feature;

use Rafiquljakir\FynoVerify\FynoVerifyServiceProvider;
use Rafiquljakir\FynoVerify\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

final class ServiceProviderTest extends TestCase
{
    public function test_all_four_commands_are_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach (['fyno:scan', 'fyno:coverage', 'fyno:plan', 'fyno:doctor'] as $command) {
            $this->assertContains($command, $registered);
        }
    }

    public function test_defaults_work_without_publishing_the_config(): void
    {
        $this->assertSame(8, config('fynoVerify.plan.max_scenarios_per_route'));
        $this->assertTrue(config('fynoVerify.coverage.fail_on_empty_scope'));
        $this->assertSame([], config('fynoVerify.coverage.covered_routes'));
    }

    public function test_config_publish_tag_is_registered(): void
    {
        $paths = ServiceProvider::pathsToPublish(FynoVerifyServiceProvider::class, 'fynoVerify-config');

        $this->assertCount(1, $paths);
        $this->assertStringEndsWith('config/fynoVerify.php', (string) array_key_first($paths));
        $this->assertStringEndsWith('fynoVerify.php', (string) array_values($paths)[0]);
    }

    public function test_packaged_config_file_returns_the_documented_defaults(): void
    {
        $config = require __DIR__.'/../../config/fynoVerify.php';

        $this->assertSame(['api/*'], $config['coverage']['include_uri_patterns']);
        $this->assertSame([], $config['coverage']['covered_routes'], 'The default config must not pre-populate fictional mappings.');
        $this->assertSame([], $config['coverage']['ignored_routes']);
    }
}
