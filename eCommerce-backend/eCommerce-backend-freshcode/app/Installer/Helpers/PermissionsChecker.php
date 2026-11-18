<?php

namespace App\Installer\Helpers;

class PermissionsChecker
{
    public function check(array $folders): array
    {
        clearstatcache();
        $checks = [];

        foreach ($folders as $folder => $permission) {
            $checks[$folder] = [is_dir(base_path($folder)) && is_writable(base_path($folder)), 'Writable by PHP'];
        }

        $checks['./ (project root)'] = [is_writable(base_path()), 'Writable by PHP for temporary environment files'];
        $checks['.env'] = [is_file(base_path('.env')) && is_readable(base_path('.env')), 'Readable by PHP'];
        $backup = base_path('.env.backup');
        $checks['.env.backup'] = [
            file_exists($backup) ? is_file($backup) && is_writable($backup) : ! is_link($backup) && is_writable(base_path()),
            'Creatable or writable by PHP',
        ];
        $checks['storage/'] = [is_dir(storage_path()) && is_writable(storage_path()), 'Writable by PHP for installation marker'];
        $marker = storage_path('installed');

        if (file_exists($marker) || is_link($marker)) {
            $checks['storage/installed'] = [is_file($marker) && is_writable($marker), 'Writable by PHP'];
        }

        $results = ['permissions' => [], 'errors' => null];

        foreach ($checks as $folder => [$isSet, $requirement]) {
            $results['permissions'][] = ['folder' => $folder, 'permission' => $requirement, 'isSet' => $isSet];

            if (! $isSet) {
                $results['errors'] = true;
            }
        }

        return $results;
    }

    public function failureMessage(array $results): string
    {
        $failed = array_filter($results['permissions'], fn (array $permission) => ! $permission['isSet']);

        return 'Installation permissions failed: '.implode('; ', array_map(
            fn (array $permission) => $permission['folder'].' — '.$permission['permission'],
            $failed
        )).'. Check ownership and permissions for the PHP worker user, then retry.';
    }
}
