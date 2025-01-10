<?php

namespace App\Service;

use App\Entity\File;

class FileService
{
    public function save(File $file)
    {
        file_put_contents(
            $file->getPathname(),
            $file->getFile()
        );
    }
}