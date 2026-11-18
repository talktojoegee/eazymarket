<?php

namespace App\Enums\BulkUpload;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum BulkUploadOperationEnum: string
{
    use InvokableCases, Names, Values;

    case CREATE = 'create';
    case UPDATE = 'update';
}
