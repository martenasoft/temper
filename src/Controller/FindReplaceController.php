<?php

namespace App\Controller;

use App\Service\FindService;
use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FindReplaceController extends AbstractController
{
    public function __construct(
        private ProjectService  $projectService,
        private FindService  $findService
    ) {
    }
    #[Route('/find/replace/{projectUuid}', name: 'app_find_replace', methods: ['GET', 'POST'])]
    public function index(Request $request, string $projectUuid): Response
    {

        $formBuilder = $this->createFormBuilder();
        $formBuilder->add('word');
        $formBuilder->add('replace');

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        $result = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $result = $this->findService->findByWord($this->getUser(), $projectUuid, $data['word'], $data['replace']);
        }

        return $this->render('find_replace/index.html.twig', [
            'form' => $form->createView(),
            'projectUuid' => $projectUuid,
            'result' => $result
        ]);
    }

    #[Route('/find/save/{projectUuid}', name: 'app_find_save', methods: ['POST'])]
    public function save(Request $request, string $projectUuid): Response
    {
        $this->findService->save($request->request->all());
        $this->addFlash('success', 'Values has been replaced.');
        return $this->redirectToRoute('app_find_replace', ['projectUuid' => $projectUuid]);
    }
}
