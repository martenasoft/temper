<?php

namespace App\Controller;

use App\Entity\Dir;
use App\Form\DirFormType;
use App\Service\DirService;
use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DirController extends AbstractController
{
    #[Route('/dir', name: 'app_dir')]
    public function index(): Response
    {
        return $this->render('dir/index.html.twig', [
            'controller_name' => 'DirController',
        ]);
    }

    #[Route('/dir/edit/{hash}', name: 'app_dir_edit', methods: ['GET', 'POST'])]
    public function edit(string $hash, Request $request, DirService $dirService, ProjectService $projectService): Response
    {
        $item = $projectService->getProjectFs('0001');
        if (!$item[$hash]) {
            throw $this->createNotFoundException();
        }

        if ($item[$hash]['type'] == 'file') {
            return $this->redirectToRoute('app_file_edit', ['hash' => $hash]);
        }

        $file = new Dir();
        $file->setName($item[$hash]['name']);

        $form = $this->createForm(DirFormType::class, $file);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dirService->save($file);
        }

        $items = $projectService->getProjectFs($item[$hash]['pathname'], true);

        return $this->render('dir/edit.html.twig', [
            'form' => $form->createView(),
            'items' => $items,
            'dir' => $item[$hash],
        ]);
    }
}
