<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class FileUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Выберите файл для загрузки',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, выберите файл.',
                    ]),
                    new File([
                        'maxSize' => '5M', // Максимальный размер файла
                        'mimeTypes' => [
                            'application/zip',
                        ],
                        'mimeTypesMessage' => 'Допустимы только Zig файлы.',
                    ]),
                ],
            ]);
    }
}
