<?php

namespace Tests\Unit\Planner;

use App\Planner\Support\NodePaths;
use PHPUnit\Framework\TestCase;

class NodePathsTest extends TestCase
{
    public function test_a_found_path_points_to_an_existing_file(): void
    {
        $node = NodePaths::binary('node');

        if ($node === null) {
            $this->markTestSkipped('Node is not in one of the known locations on this machine.');
        }

        $this->assertFileExists($node);
    }

    public function test_node_and_npm_come_from_the_same_directory(): void
    {
        // Browsershot asks npm for the global modules directory; an npm from
        // another install then points at the wrong node_modules.
        $node = NodePaths::binary('node');

        if ($node === null) {
            $this->markTestSkipped('Node is not in one of the known locations on this machine.');
        }

        $this->assertSame(dirname($node), dirname((string) NodePaths::binary('npm')));
        $this->assertSame('npm', basename((string) NodePaths::binary('npm')));
    }

    public function test_without_a_home_directory_the_search_falls_back_to_the_fixed_directories(): void
    {
        $old = getenv('HOME');
        putenv('HOME');

        try {
            $node = NodePaths::binary('node');

            $this->assertTrue($node === null || is_file($node));
        } finally {
            $old === false ? putenv('HOME') : putenv('HOME='.$old);
        }
    }
}
