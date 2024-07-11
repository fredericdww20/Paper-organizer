<?php

namespace App\Service;

use Vich\UploaderBundle\Naming\DirectoryNamerInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use App\Entity\Document;

class DirectoryNamer implements DirectoryNamerInterface
{
    public function directoryName($object, PropertyMapping $mapping): string
    {
        if (!$object instanceof Document) {
            throw new \InvalidArgumentException('The object must be an instance of ' . Document::class);
        }

        return $object->getUploadDir();
    }
}
