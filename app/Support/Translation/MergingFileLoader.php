<?php

namespace App\Support\Translation;

use Illuminate\Translation\FileLoader;

/**
 * Translation loader that merges a group's homonymous subdirectory into the group.
 *
 * The native Laravel loader reads a group only from the flat file
 * `{path}/{locale}/{group}.php`, so dot notation such as `app.auth.login.title`
 * never reaches `{path}/{locale}/app/auth.php`. This loader additionally walks
 * the `{path}/{locale}/{group}/` directory recursively — each `.php` file
 * becomes a key and each subdirectory a nested level — so a group can be split
 * across many files while dot notation keeps working transparently.
 */
class MergingFileLoader extends FileLoader
{
    /**
     * Load the messages for the given locale, merging the group's subdirectory.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null)
    {
        $lines = parent::load($locale, $group, $namespace);

        // Only the default namespace (non-JSON) groups get subdirectory merging.
        if ($group === '*' || ($namespace !== null && $namespace !== '*')) {
            return $lines;
        }

        foreach ($this->paths as $path) {
            $directory = "{$path}/{$locale}/{$group}";

            if ($this->files->isDirectory($directory)) {
                $lines = array_replace_recursive($lines, $this->loadDirectory($directory));
            }
        }

        return $lines;
    }

    /**
     * Recursively build a translation tree from a directory of PHP files.
     *
     * @return array<string, mixed>
     */
    protected function loadDirectory(string $directory): array
    {
        $output = [];

        foreach ($this->files->files($directory) as $file) {
            if ($file->getExtension() === 'php') {
                $output[$file->getFilenameWithoutExtension()] = $this->files->getRequire($file->getPathname());
            }
        }

        foreach ($this->files->directories($directory) as $subdirectory) {
            $name = basename($subdirectory);

            $output[$name] = array_replace_recursive(
                $output[$name] ?? [],
                $this->loadDirectory($subdirectory),
            );
        }

        return $output;
    }
}
