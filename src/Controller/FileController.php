<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\Project;
use App\Form\FileFormType;
use App\Form\ProjectFormType;
use App\Service\FileService;
use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    #[Route('/file', name: 'app_file')]
    public function index(): Response
    {
        return $this->render('file/index.html.twig', [
            'controller_name' => 'FileController',
        ]);
    }

    #[Route('/file/edit/{hash}', name: 'app_file_edit', methods: ['GET', 'POST'])]
    public function edit(string $hash, Request $request, FileService $fileService, ProjectService $projectService): Response
    {
        $item = $projectService->getProjectFs('0001');
        if (!$item[$hash]) {
            throw $this->createNotFoundException();
        }

        if ($item[$hash]['type'] == 'dir') {
            return $this->redirectToRoute('app_dir_edit', ['hash' => $hash]);
        }

        $file = new File();
        $file->setName($item[$hash]['name']);
        if ($item[$hash]['type'] == 'file') {
            $content = file_get_contents($item[$hash]['pathname']);
            $file->setFile($content);
            $file->setPathname($item[$hash]['pathname']);
        }

        $form = $this->createForm(FileFormType::class, $file);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fileService->save($file);
        }

        return $this->render('file/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
