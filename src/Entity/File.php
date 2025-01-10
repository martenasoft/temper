<?php

namespace App\Entity;

class File
{
    private string $name;

    private string $file;
    private string $pathname;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): self
    {
        $this->file = $file;
        return $this;
    }

    public function getPathname(): string
    {
        return $this->pathname;
    }

    public function setPathname(string $pathname): File
    {
        $this->pathname = $pathname;
        return $this;
    }
}
