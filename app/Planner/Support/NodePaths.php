<?php

namespace App\Planner\Support;

/**
 * Where node and npm live for a PHP process.
 *
 * Browsershot starts Chrome through a node script. PHP-FPM does not inherit
 * the PATH of a login shell, so node cannot be found there while the same
 * command works in the terminal: "sh: node: command not found", exit code
 * 127. Setting the path in .env works, but is easily forgotten on a new
 * install or a node upgrade, and the error only surfaces on the first export.
 *
 * So we search ourselves. When that finds nothing this returns null and
 * Browsershot may try the PATH -- on a server node usually sits in /usr/bin.
 */
final class NodePaths
{
    private const FIXED_DIRECTORIES = ['/usr/local/bin', '/opt/homebrew/bin', '/usr/bin'];

    /** The full path to a binary, or null when no suitable directory is found. */
    public static function binary(string $name): ?string
    {
        $directory = self::directory();

        return $directory === null ? null : $directory.'/'.$name;
    }

    /**
     * The first directory that contains both node and npm.
     *
     * Both must come from the same directory: Browsershot asks npm for the
     * global modules directory, and an npm from another install then points
     * at the wrong node_modules.
     */
    private static function directory(): ?string
    {
        foreach ([...self::herdDirectories(), ...self::FIXED_DIRECTORIES] as $directory) {
            if (is_file($directory.'/node') && is_file($directory.'/npm')) {
                return $directory;
            }
        }

        return null;
    }

    /**
     * Herd installs node through nvm, in a directory with the version number
     * in it. Newest version first, so an upgrade is followed automatically.
     *
     * @return array<int, string>
     */
    private static function herdDirectories(): array
    {
        $home = self::homeDirectory();
        if ($home === null) {
            return [];
        }

        $directories = glob($home.'/Library/Application Support/Herd/config/nvm/versions/node/*/bin') ?: [];
        rsort($directories, SORT_NATURAL);

        return $directories;
    }

    /**
     * The home directory of the running process.
     *
     * PHP-FPM does not always get a HOME, and then Herd's nvm directory would
     * be unreachable precisely in the case this class exists for. The
     * password database knows it without that variable.
     */
    private static function homeDirectory(): ?string
    {
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);

        if (blank($home) && function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $home = (posix_getpwuid(posix_geteuid()) ?: [])['dir'] ?? null;
        }

        return is_string($home) && $home !== '' ? $home : null;
    }
}
