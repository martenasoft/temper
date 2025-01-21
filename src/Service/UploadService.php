<?php

namespace App\Service;

use App\Entity\Project;
use phpDocumentor\Reflection\File;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

class UploadService
{

    public const UPLOAD_DIR = 'var/upload';
    private string $uploadDir = '';

    private ?UploadedFile $uploadedFile;
    public function __construct(
        private KernelInterface $kernel,
    ) {
        $this->uploadDir = $this->kernel->getProjectDir() . '/' . self::UPLOAD_DIR;
    }

    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    public function upload(Form $form, array $obj)
    {
        $this->uploadedFile = $form->get('file')->getData();
        if ($this->uploadedFile) {
            $uploadDirectory = $this->uploadDir . DIRECTORY_SEPARATOR . $obj['project']->getSlug();
            $newFilename = uniqid() . '.' . $this->uploadedFile->guessExtension();

            try {
                if (!file_exists($uploadDirectory)) {
                    mkdir($uploadDirectory, 0777, true);
                }
                $this->uploadedFile->move($uploadDirectory, $newFilename);
                $zipFile = $uploadDirectory . DIRECTORY_SEPARATOR . $newFilename;

                $this->unzipFile($zipFile, $uploadDirectory);
                if (file_exists($zipFile)) {
                    unlink($zipFile);
                }


            } catch (FileException $e) {
                // Обрабатываем ошибки
                $this->addFlash('error', 'Ошибка при загрузке файла.');
            }
        }
    }

    public function unzipFile(string $zipFilePath, string $destination): void
    {

        if (!file_exists($zipFilePath)) {
            throw new \Exception("Файл {$zipFilePath} не найден.");
        }

        // Создаем объект ZipArchive
        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath) === true) {
            $zip->extractTo($destination);
            $zip->close();
            if (!file_exists($destination)) {
                throw new \Exception("Не удалось разархивировать файл в {$destination}.");
            }

            echo "Файл успешно разархивирован в {$destination}";
        } else {
            throw new \Exception("Не удалось открыть архив {$zipFilePath}.");
        }
    }

    public function getUploadedFile(): ?UploadedFile
    {
        return $this->uploadedFile;
    }
}